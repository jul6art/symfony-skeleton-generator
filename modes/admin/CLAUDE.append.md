## Spécificités du mode admin

### Autorisation

La règle n°1 vaut aussi pour les routes qu'EasyAdmin génère, qui n'ont pas
d'`#[Route]` à décorer. Elles se déclarent dans le CRUD :

- `configureActions()` — `->setPermission(Action::EDIT, UserVoter::EDIT)` : une
  action, un attribut. EasyAdmin vérifie l'attribut **à l'entrée de la page**
  (403 sinon) et masque le bouton correspondant. Le sujet transmis au voter est
  l'instance de l'entité pour les actions de ligne (`DETAIL`, `EDIT`, `DELETE`),
  et la classe pour les actions globales (`INDEX`, `NEW`) : un voter doit donc
  accepter les deux.
- `configureCrud()` — `->setEntityPermission(UserVoter::VIEW)` : la fiche
  elle-même, vérifiée sur l'instance.
- `configureMenuItems()` — `->setPermission(UserVoter::LIST)` sur l'entrée de
  menu qui ouvre le CRUD : le menu ne montre que ce qui est autorisé.

Le tableau de bord, lui, est une porte sans sujet : sa décision est
`AdminVoter::ACCESS`, prise dans `index()`. Le front public suit la règle
générale — `/` est publique et le déclare.

### Front public

Le site public (accueil, connexion, mot de passe oublié, profil) utilise le même
front que le mode web ; le back-office est rendu par EasyAdmin.

- **Aucune ressource distante** : ni CDN, ni `<script src="https://…">`. Tailwind
  et Font Awesome sont servis depuis le projet.
- **Tailwind** via `symfonycasts/tailwind-bundle` (binaire autonome, pas de Node) :
  `make tailwind` compile, `make tailwind-watch` en développement. La version du
  binaire est fixée dans `config/packages/symfonycasts_tailwind.yaml`.
- Feuille d'entrée : `assets/styles/app.css` (`@import "tailwindcss"` + directives
  `@source`). Les classes utilitaires composées (`.btn`, `.card`, `.nav-link`,
  `.badge`) y sont définies dans `@layer components`.
- **Font Awesome Free** est téléchargé dans `assets/fontawesome/` par
  `make fontawesome` (appelé par `make install`). Le dossier n'est pas versionné.
  Icônes utilisées en `<i class="fa-solid fa-…"></i>`.
- Le reste du front passe par **AssetMapper** : pas de build Node, dépendances JS
  ajoutées avec `symfony console importmap:require <paquet>`, `importmap.php`
  versionné.
- Les formulaires sont rendus par le thème maison `templates/form/theme.html.twig`
  (déclaré dans `config/packages/twig.yaml`) : ne pas remettre de classes Tailwind
  sur chaque `form_row`.
- **Pas de Turbo** : `symfony/ux-turbo`, installé par le pack `--webapp`, est
  retiré à la génération (`packages-remove.txt`). Il imposait ses règles à tout
  le projet — 422 obligatoire sur formulaire invalide, redirections, cache
  d'instantanés qui repeint une barre de navigation périmée — pour un gain nul
  sur des pages rendues côté serveur. Un projet qui en a besoin le réinstalle
  avec `symfony composer require symfony/ux-turbo`, et applique alors ces
  règles. Stimulus, lui, reste : c'est lui qui porte les modales.
- Toute action sensible passe par le contrôleur Stimulus `confirm`
  (`assets/controllers/confirm_controller.js`) : le formulaire porte
  `data-controller="confirm" data-action="submit->confirm#request"` et inclut
  `partials/_confirm_dialog.html.twig` (titre, message, libellés, icônes en
  paramètres). Jamais de `confirm()` natif : il bloque le thread et jure avec
  le reste de l'interface. Déjà en place sur la suppression d'un compte et sur
  la déconnexion.

### Thème du back-office

EasyAdmin est habillé par `assets/styles/admin.scss`, compilé par
`symfonycasts/sass-bundle` (binaire Dart Sass local, aucun Node) : `make sass`,
`make sass-watch` en développement, et `make start` / `make test` le compilent
au besoin. Le point d'entrée `assets/admin.js` l'importe, et
`DashboardController::configureAssets()` déclare cette entrée.

Deux règles à respecter quand on retouche le thème :

- **Passer par les jetons.** EasyAdmin 5 expose tout son design en propriétés
  CSS (`--ea-primary`, `--ea-radius`, `--gray-*`, `--sidebar-*`, `--table-*`,
  `--form-*`) : les redéfinir suffit presque toujours et survit aux montées de
  version, contrairement aux sélecteurs internes. Les jetons maison
  (`--app-panel-*`, `--app-thead-bg`) dessinent les panneaux, qu'EasyAdmin 5 ne
  fournit plus — les classes `.content-panel` / `.form-panel` d'EasyAdmin 4
  n'existent plus, ne pas les réutiliser.
- **Ne pas mettre `!important`.** EasyAdmin range ses règles dans des couches
  (`@layer ea…`) et cette feuille n'est dans aucune : elle l'emporte déjà, quelle
  que soit la spécificité.

Le schéma sombre est géré par EasyAdmin (classe `.ea-dark-scheme` sur `<body>`,
suivant la préférence système) : toute couleur ajoutée doit avoir sa contrepartie
dans le bloc `.ea-dark-scheme` de la feuille, sinon elle ne survit pas au
basculement. Les cartes du tableau de bord utilisent `.app-card`.

### Traductions JavaScript

Toute chaîne qu'un contrôleur Stimulus affiche est une chaîne traduite, et elle vit dans le
catalogue `translations/javascript.{fr,en}.yaml` — **le seul domaine que le navigateur reçoit**
(`symfony/ux-translator`, configuré par `core.js_translations` de `jul6art/core-bundle`).

```js
import { trans } from '../translator';

this.element.textContent = trans('confirm.delete.title');
```

- ⚠️ **`javascript` est un domaine de TRANSPORT, pas de sujet.** Les autres domaines répondent à
  « de quoi parle ce libellé » ; celui-ci répond à « qui le lit ». Un libellé lu à la fois par un
  gabarit et par du JavaScript y est DÉPLACÉ, jamais recopié — deux exemplaires divergent au
  premier changement. Et le déplacement se termine par la reprise de **tous** ses appelants, y
  compris côté serveur : le garde ne regarde que le domaine `javascript`.
- ⚠️ **Le domaine est fixé dans `assets/translator.js`, une fois.** `domains: javascript` restreint
  ce qui est DÉPOSÉ ; il ne change pas le domaine par défaut de `trans()`, qui reste `messages`.
  Un appel non qualifié ne trouve rien et rend la clé brute, sans rien lever.
- ⚠️ **`var/translations/index.js` est écrit par le préchauffage du cache** et gitignoré. Sous
  AssetMapper il n'y a rien d'autre à câbler : le paquet déclare ses chemins tout seul.
- `tests/Translation/JsTranslationTest.php` tient les deux bouts — une clé lue et absente, une clé
  présente que plus rien ne lit, et le retour d'un `data-…-translations-value`.

### Base de données

Les entités sont livrées sans migration : après `make db-create`, lancer
`make db-diff` puis `make db-migrate` pour créer le schéma initial.
