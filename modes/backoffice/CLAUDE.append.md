
## Mode « backoffice »

Un back-office mono-locataire : thème maison, datatables au-dessus d'API Platform, rafraîchissement
Mercure, moteur de permissions ACL. La coquille et le socle de tableau viennent de deux bundles ;
ce dépôt porte ce qui lui appartient — ses entités, son catalogue de permissions, ses écrans.

### Ce qui vient d'où

| Ce que vous voyez | D'où ça vient | Ce que vous ne devez pas réécrire |
| --- | --- | --- |
| Le layout, la barre latérale, le menu de compte, les toasts | `jul6art/admin-bundle` | `@Admin/layout.html.twig`, ses blocs sont documentés en tête |
| Les pages de connexion / inscription / mot de passe oublié | `jul6art/admin-bundle` | logo et nom viennent d'`admin.branding` |
| L'écran d'apparence, les cinq colonnes `appearance_*` | `jul6art/admin-bundle` | `AppearancePreferencesTrait`, `AppearanceType` |
| Le thème : jetons d'accent, densité, `.panel`, `.btn-*` | `jul6art/admin-bundle` | `assets/styles/*` du bundle, importés par `assets/styles/app.css` |
| Les tableaux : pagination, tri, filtres, actions de masse, temps réel | `jul6art/datatable-bundle` | le contrôleur Stimulus, `AbstractDataTableConfigProvider` |
| Les filtres API Platform (`OrSearchFilter`, tri insensible à la casse) | `jul6art/api-bundle` | |
| Les types de formulaire (e-mail, mot de passe, montant, IBAN…) | `jul6art/ui-bundle` | |
| Le moteur de permissions, `#[CheckPermission]`, le voter | `jul6art/acl-bundle` | |
| Mercure : publication, jetons, canaux | `jul6art/push-bundle` | |
| Repository, voter, contrôleur de base, traits d'entité, purge | `jul6art/core-bundle` | |

**Avant d'écrire une classe, vérifiez qu'un de ces bundles ne la fournit pas déjà.** Réimplémenter
ce qu'ils portent est l'erreur la plus coûteuse de cet écosystème, parce qu'elle ne se voit pas.

### Créer un écran de liste

1. Une classe `App\DataTable\<Entité>DataTableConfigProvider` étendant
   `Jul6Art\DatatableBundle\DataTable\AbstractDataTableConfigProvider`. Utilisez les aides
   (`column()`, `staticFilter()`, `apiFilter()`, `linkAction()`, `bulkDeleteAction()`) : chaque
   libellé passe par le traducteur, et c'est le seul garde-fou contre un en-tête en clé brute.
2. Un `#[ApiResource]` sur l'entité, avec `GetCollection` gardé par un code de permission, plus les
   filtres que les colonnes supposent (`OrderFilter` pour chaque `sortField`, `OrSearchFilter` pour
   la recherche globale).
3. Une action de contrôleur qui passe `columns_config`, `filters_config` et `actions_config`.
4. Un gabarit qui inclut **les deux** partials du bundle, DANS la balise `<table>`.
5. Une route POST par action de ligne, avec son `#[IsGranted]` et son jeton `datatable_action`.

Les cinq, ou la table est cassée d'une manière qui ne lève pas : `sortField` manquant → le tri est
ignoré côté serveur ; `_csrf` oublié → les actions répondent 419 ; `_translations` oublié → la barre
de filtres affiche des clés.

### Les pièges de ce mode, appris ailleurs

- **`admin.base_template` désigne `templates/base.html.twig`.** Sans cette clé, une page
  d'administration étend la base du bundle et s'affiche **sans aucune feuille de style** — rien ne
  le signale.
- **`acl.multi_tenant: false` est vital ici.** Le moteur refuse par défaut toute vérification
  derrière `/api/` dont le locataire n'est pas résolu ; sans locataires, cela refuse tout à tout le
  monde sauf au super-admin, et le symptôme est une datatable vide.
- **L'identifiant Stimulus du tableau est déclaré deux fois** — par le CHEMIN de
  `assets/controllers/core/datatable_controller.js` et par `datatable.stimulus_identifier`. Les deux
  doivent dire `core--datatable`.
- **Ajouter un rendu de badge** = une entrée dans `datatable.status_maps` **et** une dans
  `assets/datatable/renderers.js`. Un nom inconnu du registre affiche la valeur brute.
- **Un code de permission mal orthographié OUVRE l'écran.** Le voter s'abstient sur ce qu'il ne sait
  pas lire, et une décision où tous les voters s'abstiennent vaut « accordé ».
- **`npm` est requis dans ce mode**, et seulement dans celui-là — voir ci-dessous.

### Pourquoi ce mode utilise Webpack Encore

Les autres modes du squelette n'ont pas de Node : Tailwind y passe par un binaire autonome et les
paquets par l'importmap. Celui-ci fait exception, en connaissance de cause.

Le socle de tableau repose sur DataTables 2, son greffon Responsive, jQuery et Select2 — un greffon
jQuery en UMD. C'est la pile exacte dont `datatable-bundle` a été extrait, et la seule éprouvée de
bout en bout aujourd'hui. Reproduire une chaîne éprouvée vaut mieux que préserver une propriété du
squelette au prix d'un socle qu'on n'a pas essayé.

La voie AssetMapper reste ouverte : elle demande de lever une inconnue — Select2 consommé par
`importmap:require` — et, si elle ne se lève pas, de remplacer Select2 par une autocomplétion sans
jQuery. Ce jour-là, `webpack.config.js`, `package.json` et `bundle-assets.js` disparaissent ; le
reste ne bouge pas.
