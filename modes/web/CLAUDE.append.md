## Spécificités du mode web

### Autorisation

La règle n°1 s'applique telle quelle aux pages Twig : la décision est portée par
l'action, jamais par le gabarit. `/` est publique et le déclare
(`#[IsGranted(AuthenticatedVoter::PUBLIC_ACCESS)]`) ; toute page ajoutée déclare
la sienne de la même façon.

Un gabarit ne recopie pas la règle : il demande l'attribut
(`{% if is_granted('USER_DELETE', user) %}`). Un lien affiché que le voter refuse
est un bug d'interface ; une condition sur `'ROLE_ADMIN' in user.roles` pour
décider d'un accès en est un autre — ce test ne sert qu'à *afficher* le rôle.

### Front

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
