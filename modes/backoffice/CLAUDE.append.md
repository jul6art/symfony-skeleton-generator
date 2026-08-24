
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
4. Un gabarit qui inclut `_csrf.html.twig` et `_translations.html.twig`, DANS la balise `<table>`.
5. Une route POST par action de ligne, avec son `#[IsGranted]` et son jeton `datatable_action`.

Les cinq, ou la table est cassée d'une manière qui ne lève pas : `sortField` manquant → le tri est
ignoré côté serveur ; `_csrf` oublié → les actions répondent 419 ; `_translations` oublié → la barre
de filtres affiche des clés.

### Les préférences par compte : colonnes, ordre, vues enregistrées

Un sixième include, celui-là **optionnel**, ouvre le sélecteur de colonnes et les vues
enregistrées pour un tableau :

```twig
{{ include('@Datatable/datatable/_preferences.html.twig', { key: 'user' }) }}
```

La clé nomme un TABLEAU et pas une entité : deux écrans listant la même entité avec des colonnes
différentes sont deux clés. Motif accepté par la route : `[a-z0-9][a-z0-9_.-]{0,63}`. Sans
l'include, la table se rend exactement comme avant — pas de requête, pas de boutons.

Le bundle **interprète** les préférences (il borne, assainit, versionne) et sert `GET` / `PUT` /
`DELETE` sur `/admin/datatable/preferences/{key}` ; il ne stocke rien. Ce mode fournit la moitié
qui manque, et elle vient TOUTE de la brique « comptes » :
`App\Entity\DatatablePreference`, son repository, `App\DataTable\DatatablePreferenceStore`, l'alias
du port dans `config/services.yaml` et la route dans `config/routes/datatable.yaml`.

- **Sans l'alias, il n'y a pas d'endpoint** : `PreferenceControllerPass` retire le contrôleur du
  conteneur. Pas de panneau, pas d'erreur — le même sens de défaillance silencieuse que les deux
  alias ACL.
- **Le compte vient du jeton de sécurité**, jamais du payload : écrire les préférences d'un autre
  n'est pas « interdit », c'est irreprésentable. D'où l'absence de voter et de code de permission.
- **`write()` est un upsert.** L'endpoint est un `PUT` et le navigateur renvoie tout l'état à chaque
  sauvegarde : un `INSERT` aveugle heurte l'UNIQUE `(owner_id, datatable_key)` et sort en 500 au
  SECOND clic.
- **Le contenu est opaque** : colonne `text`, jamais `json`. Un type `json` ferait décoder Doctrine
  à la lecture et ré-encoder à l'écriture, donc les octets rendus ne seraient plus ceux reçus.

**Déclarer large, montrer étroit.** Une colonne que le lecteur peut masquer ne coûte rien à celui
qui n'en veut pas : la question n'est plus « mérite-t-elle la largeur ? » mais « quelqu'un
pourrait-il vouloir la voir ? ». `hidden: true` sur une aide de colonne la met dans le sélecteur et
hors du premier rendu — c'est ce que font `lastName` et `firstName` de la table des comptes. Trois
choses à savoir : le drapeau n'est honoré que si le tableau a l'include ci-dessus ; une colonne
ajoutée depuis la dernière sauvegarde d'un compte reprend ce défaut, donc livrer un lot de colonnes
masquées n'élargit l'écran de personne ; et c'est un défaut d'AFFICHAGE — une colonne masquée reste
sérialisée, donc jamais une raison d'ajouter un champ à un groupe `read`.

**Une colonne se déclare avec son tri et son filtre, ou pas du tout.** Un `sortField` sans l'entrée
correspondante dans l'`OrderFilter` envoie un `order[…]` que le serveur ignore : le clic ne fait
rien, et rien ne le signale. C'est la raison pour laquelle `roles` est un `readOnlyColumn` et
`lastName` un `column`.

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
- **Le catalogue du tableau est le domaine `datatable`**, pas `messages` :
  `translations/datatable.{fr,en}.yaml`, déclaré par `datatable.translation_domain`. Le tree
  `modal:` reste dans `messages` — c'est le vocabulaire des modales de confirmation, partagé par
  tous les `ui--modal`, donc bien au-delà des tableaux.
- **Un code de permission mal orthographié OUVRE l'écran.** Le voter s'abstient sur ce qu'il ne sait
  pas lire, et une décision où tous les voters s'abstiennent vaut « accordé ».
- **`npm` est requis dans ce mode**, et seulement dans celui-là — voir ci-dessous.

### Les pièges du THÈME DE FORMULAIRE (appris à l'écran, 2026-08-23/24)

- **La classe d'un champ passe par les OPTIONS de `form_widget`, jamais par `form_widget_simple`.**
  Les `Custom*Type` d'`ui-bundle` rendent tous par `input_group_addon_widget`, qui écrit son PROPRE
  `<input>` et ne délègue pas. Une classe posée dans `form_widget_simple` n'atteint donc pas le
  champ : l'`<input>` sort sans attribut `class`, donc sans bordure ni fond — blanc sur blanc,
  invisible, HTML parfaitement valide, rien en console. C'est `templates/form/theme.html.twig` qui
  s'en charge, dans son bloc `form_control`.
- **Le conteneur compound du formulaire racine porte `form-grid`.** `form_widget(form)` rend un
  `<div id="…">` intermédiaire ; un `space-y-*` posé sur le `<form>` ne voit que ce div et le
  bouton, jamais les lignes. Sans ça, tous les libellés sont collés au champ précédent.
- **Un jeton de composant (`@layer components`) perd contre TOUTE utilitaire Tailwind posée à côté
  de lui.** `class="toggle-switch block w-fit"` a réduit seize interrupteurs à **0 px de large** —
  invisibles, non cliquables, gabarit valide. On centre par le PARENT (`text-center` sur la
  cellule), on n'écrase jamais le composant.
- **La largeur d'un écran se pose DANS le contenu, jamais par `{% block container_class %}`** : le
  conteneur du layout porte `mx-auto`, donc restreindre le bloc recentre toute la page.
- **Un rendu de datatable est CURRIFIÉ** : `(contexte) => (valeur) => html`. L'appeler à plat rend
  une fonction là où DataTables attend une chaîne, et l'exception avorte le `drawCallback` ENTIER —
  la colonne ET la ligne de filtres disparaissent, pour une parenthèse mal placée.
- **`BulkActionRunner::run()` NE FLUSHE PAS.** Il ouvre une transaction, applique l'action et
  commite ; le flush appartient à l'appelant. Sans lui, une route de masse répond 302, pose son
  flash de succès et n'écrit rien.
- **`SendEmailMessage` est routé en `sync` en dev et en test** (`config/packages/messenger.yaml`).
  Le routage `async` est juste en production, où un worker tourne ; en local la file se remplit et
  rien n'arrive jamais — et le jour où on la draine, elle livre des messages sérialisés des heures
  plus tôt, avec des jetons périmés.
- **Changer la langue demande un écouteur à la priorité 7 ET une rediffusion manuelle**
  (`src/EventListener/LocaleListener.php`) : le pare-feu remplit `TokenStorage` à 8,
  `LocaleAwareListener` diffuse la locale à 15 sans repasser. Poser `$request->setLocale()` seul
  écrit la colonne, écrit la session, et laisse l'écran dans la langue précédente.
- **La langue voyage dans le CORPS de la requête**, pas dans l'URL : c'est le contrat du
  contrôleur `ui--locale-switcher` d'`admin-bundle`. Une route `/locale/{locale}` répond 204 et ne
  change rien.

### Les fixtures font partie du mode

`doctrine:fixtures:load` **PURGE**. Tout ce sans quoi l'application ne démarre pas doit donc vivre
dans les fixtures et pas seulement dans une commande de seed : `UserFixtures` recrée le premier
administrateur, `RolePermissionFixtures` repose ce que `app:permissions:seed` avait posé. Sans la
seconde, un `make fixtures` laisse la base sans aucune permission et tous les écrans gardés
répondent 403.

**Un fichier de fixtures par classe d'entité**, jamais deux entités dans un même fichier.
`AppFixtures` reste vide : c'est un vestige du squelette, pas un fourre-tout.

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

### Le profileur de performance (développement)

`core-bundle` compte les requêtes SQL de chaque requête HTTP et les enregistre ;
`admin-bundle` en affiche le tableau de bord sur `/admin/performance`, et le menu de compte y
mène. Les trois pièces ne vivent qu'en développement : le drapeau
(`config/packages/performance.yaml`), les routes (`config/routes/performance.yaml`) et la clé
`admin.routes.performance` sont toutes sous `when@dev`.

C'est l'outil qui répond à la seule question qui décide de la vitesse d'une application Symfony :
**combien de requêtes cette route fait-elle, et ce nombre grandit-il avec les données ?** Le
panneau de la barre de debug donne la réponse page par page ; le tableau de bord la donne pour
toutes les routes visitées, triées par coût.

⚠️ Le préfixe de nom `admin_performance_` est un contrat entre les deux bundles : le core l'exclut
de sa collecte (`ignored_route_prefix`), sans quoi le tableau de bord mesurerait sa propre page.
