## Comptes (mode api)

### Comptes

- Le compte est `Jul6Art\AuthBundle\Entity\User` (auth-bundle) : pas d'entité
  `App\Entity\User`. Injecter `UserManagerInterface` / `UserRepositoryInterface`,
  créer les comptes avec `UserFactory::create()`.
- L'entité vendor ne porte aucune règle applicative : contraintes dans
  `config/validator/user.yaml`, groupes de sérialisation dans
  `config/serializer/user.yaml`. Le mot de passe n'est dans aucun groupe, il ne
  sort donc jamais de l'API.
- Rôles : `User::ROLE_USER` et `User::ROLE_ADMIN`, jamais de chaîne littérale —
  et jamais dans un contrôleur : ils se lisent dans `UserVoter`.
- Les accès aux comptes passent par `App\Security\Voter\UserVoter` :
  `USER_LIST`, `USER_VIEW`, `USER_CREATE`, `USER_EDIT`, `USER_DELETE`. Un
  administrateur voit tout, un porteur de jeton ne voit que son propre compte.
  `GET /api/me` cite `USER_VIEW` (`denyAccessUnlessGranted`), et
  `tests/Security/UserVoterTest.php` fige ces cas.

### Authentification (JWT)

- `lexik/jwt-authentication-bundle` : `POST /api/login` avec
  `{"email": "…", "password": "…"}` renvoie `{"token": "…"}`, à présenter
  ensuite dans `Authorization: Bearer <token>`.
- La route `api_login` n'a pas de code : elle est interceptée par la clé
  `json_login` du pare-feu (`config/packages/security.yaml`).
- Trois pare-feux : `login` (échange des identifiants), `api` (jetons, tout est
  `stateless`), `main` (le reste, dont `/health`). `access_control` ne fait
  qu'ouvrir ou fermer grossièrement ; la règle fine s'écrit dans un voter et se
  cite sur l'action.
- Les clés vivent dans `config/jwt/` (non versionnées) :
  `make jwt-keypair` les régénère, la passphrase est dans `.env`.
- Pas d'inscription publique : les comptes se créent en console
  (`make user-create ARGS="moi@exemple.com --admin"`). Ajouter un endpoint
  d'inscription est une décision explicite, pas un défaut.
- `GET /api/me` renvoie le compte porté par le jeton — sonde pratique côté
  client.

### Requêtes de test

`request/test.http` (client HTTP de PhpStorm / VS Code) couvre les routes
livrées : sonde, connexion — elle enregistre le jeton pour les suivantes —,
`/api/me`, les cas 401 et le préflight CORS. **Toute route ajoutée à l'API doit
y être ajoutée aussi**, avec son cas d'échec. L'hôte et l'adresse vivent dans
`request/http-client.env.json`, le mot de passe dans
`request/http-client.private.env.json` (non versionné, créé à la génération).

