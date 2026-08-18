## Comptes (mode web)

### Comptes et sécurité

- Le compte est `Jul6Art\AuthBundle\Entity\User` (auth-bundle) : pas d'entité
  `App\Entity\User`. Les contrôleurs passent par `UserManagerInterface` et
  `UserRepositoryInterface`, et créent les comptes avec `UserFactory::create()`.
- Contraintes de validation dans `config/validator/user.yaml` (le mot de passe
  n'y est pas validé : il est haché *après* la validation du formulaire, ses
  règles de robustesse vivent dans `App\Form\PlainPasswordType`).
- `App\Entity\ResetPasswordRequest` montre le patron attendu pour une entité
  maison : `IdTrait` du core-bundle, et son dépôt hérite d'`AbstractRepository`.
- Les mouvements de comptes passent par `App\Event\UserEvent` (héritant
  d'`AbstractEvent`) et `App\EventListener\UserEventListener` (héritant
  d'`AbstractEventListener`) : y brancher toute nouvelle réaction plutôt que
  d'alourdir les contrôleurs.
- Parcours livrés : inscription (`/register`), connexion (`/login`), mot de passe
  oublié (`/reset-password`, `symfonycasts/reset-password-bundle`), changement de
  mot de passe (`/profile/password`), administration des comptes (`/admin/users`).
- Le pare-feu utilise `form_login` (pas d'authenticator maison) avec CSRF,
  `remember_me` et `login_throttling`. Toute nouvelle règle d'accès va dans
  `config/packages/security.yaml` **et** dans un `#[IsGranted]` sur le contrôleur.
- La déconnexion est en **POST + CSRF** (`enable_csrf`, route `app_logout`
  limitée à POST) : le lien GET historique se déclenchait sur un préchargement
  ou une image tierce. Toujours utiliser `logout_path()` dans un formulaire.
- L'inscription publique est pilotée par `APP_REGISTRATION_ENABLED` (`.env`) :
  à 0, `/register` répond 404 et les liens « Créer un compte » disparaissent
  (variable Twig globale `registration_enabled`, paramètre
  `app.registration_enabled`). Le projet peut être généré fermé d'entrée avec
  `./bin/new-project <projet> --web --no-registration`.
- Le premier administrateur se crée en console :
  `make user-create ARGS="moi@exemple.com --admin"`.
- Les e-mails partent par Messenger (`SendEmailMessage` routé sur `async`) : sans
  `make worker`, le lien de réinitialisation reste dans la file et n'arrive
  jamais. En test, l'assertion à utiliser est `assertQueuedEmailCount()`.
- Règles de mot de passe centralisées dans `App\Form\PlainPasswordType` : les
  modifier là, pas dans chaque formulaire.
- Le parcours « mot de passe oublié » ne doit jamais révéler si une adresse
  existe : toujours la même réponse, quelle que soit l'issue.

