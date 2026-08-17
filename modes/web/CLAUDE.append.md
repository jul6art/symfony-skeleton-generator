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

### Comptes et sécurité

- Entité `App\Entity\User` (e-mail + rôles + mot de passe haché). `ROLE_USER` est
  implicite : `getRoles()` l'ajoute, `setRoles()` le retire du stockage.
- Parcours livrés : inscription (`/register`), connexion (`/login`), mot de passe
  oublié (`/reset-password`, `symfonycasts/reset-password-bundle`), changement de
  mot de passe (`/profile/password`), administration des comptes (`/admin/users`).
- Le pare-feu utilise `form_login` (pas d'authenticator maison) avec CSRF,
  `remember_me` et `login_throttling`. Toute nouvelle règle d'accès va dans
  `config/packages/security.yaml` **et** dans un `#[IsGranted]` sur le contrôleur.
- La déconnexion est en **POST + CSRF** (`enable_csrf`, route `app_logout`
  limitée à POST) : le lien GET historique se déclenchait sur un préchargement
  ou une image tierce. Toujours utiliser `logout_path()` dans un formulaire.
- Le premier administrateur se crée en console :
  `make user-create ARGS="moi@exemple.com --admin"`.
- Les e-mails partent par Messenger (`SendEmailMessage` routé sur `async`) : sans
  `make worker`, le lien de réinitialisation reste dans la file et n'arrive
  jamais. En test, l'assertion à utiliser est `assertQueuedEmailCount()`.
- Règles de mot de passe centralisées dans `App\Form\PlainPasswordType` : les
  modifier là, pas dans chaque formulaire.
- Le parcours « mot de passe oublié » ne doit jamais révéler si une adresse
  existe : toujours la même réponse, quelle que soit l'issue.

### Base de données

Les entités sont livrées sans migration : après `make db-create`, lancer
`make db-diff` puis `make db-migrate` pour créer le schéma initial.
