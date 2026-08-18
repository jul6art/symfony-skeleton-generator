## Spécificités du mode web

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
- Turbo est actif : un formulaire invalide doit être renvoyé en **422**
  (`Response::HTTP_UNPROCESSABLE_ENTITY`), sinon Turbo ignore la réponse.
- Toute action sensible passe par le contrôleur Stimulus `confirm`
  (`assets/controllers/confirm_controller.js`) : le formulaire porte
  `data-controller="confirm" data-action="submit->confirm#request"` et inclut
  `partials/_confirm_dialog.html.twig` (titre, message, libellés, icônes en
  paramètres). Jamais de `confirm()` natif : il bloque le thread et jure avec
  le reste de l'interface. Déjà en place sur la suppression d'un compte et sur
  la déconnexion.

### Base de données

Les entités sont livrées sans migration : après `make db-create`, lancer
`make db-diff` puis `make db-migrate` pour créer le schéma initial.
