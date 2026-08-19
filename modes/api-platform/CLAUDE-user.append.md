## Comptes (mode api-platform)

### Comptes

- Le compte est `Jul6Art\AuthBundle\Entity\User` (auth-bundle) : pas d'entité
  `App\Entity\User`. Injecter `UserManagerInterface` / `UserRepositoryInterface`,
  créer les comptes avec `UserFactory::create()`.
- L'entité vendor ne porte aucune règle applicative : contraintes dans
  `config/validator/user.yaml`, groupes de sérialisation dans
  `config/serializer/user.yaml`. Le mot de passe n'est dans aucun groupe, il ne
  sort donc jamais de l'API.
- Rôles : `User::ROLE_USER` et `User::ROLE_ADMIN`, jamais de chaîne littérale —
  et jamais dans une expression `security:` : ils se lisent dans `UserVoter`.
- Les accès aux comptes passent par `App\Security\Voter\UserVoter` :
  `USER_LIST`, `USER_VIEW`, `USER_CREATE`, `USER_EDIT`, `USER_DELETE`. Un
  administrateur voit tout, un porteur de jeton ne voit que son propre compte.
  `tests/Security/UserVoterTest.php` fige ces cas.

### Authentification (JWT)

- `lexik/jwt-authentication-bundle` : `POST /api/login` avec
  `{"email": "…", "password": "…"}` renvoie `{"token": "…"}`, à présenter
  ensuite dans `Authorization: Bearer <token>`. Cette route n'a pas de code :
  elle est interceptée par la clé `json_login` du pare-feu.
- Trois pare-feux (`config/packages/security.yaml`) : `login` (échange des
  identifiants), `api` (jetons, tout est `stateless`), `main` (le reste, dont
  `/health`). `access_control` n'ouvre que ce qui doit l'être — la sécurité
  fine se déclare **sur les opérations** (`security:` d'`#[ApiResource]`).
- `POST /api/login` est documenté par l'intégration d'API Platform du bundle
  lexik (`lexik_jwt_authentication.api_platform`), et
  `api_platform.swagger.http_auth` ajoute le bouton « Authorize » (Bearer) de
  Swagger UI. Le `check_path` y est écrit **en chemin** (`/api/login`) et non
  en nom de route, sinon c'est le nom brut qui s'affiche dans la doc. Une
  opération hors modèle non documentée n'existe pas pour les clients.
- Swagger UI a besoin des assets du bundle dans `public/bundles/` : ils sont
  installés à la génération et par `make install`. Sans eux, `/api` s'affiche
  sans aucune mise en forme — c'est le premier réflexe si la doc « a perdu son
  design » (`make assets`).
- `GET /api/me` est une opération `Get` alimentée par `App\State\MeProvider` :
  le compte porté par le jeton, sans identifiant dans l'URL.
- Les trois opérations exposées citent leur attribut de voter dans
  `config/api_platform/resources.yaml` : `GetCollection` → `USER_LIST` (réservé
  à l'administration), `Get` et `me` → `is_granted('USER_VIEW', object)`, donc le
  propriétaire de la fiche ou un administrateur. Plus de comparaison
  `object == user` écrite dans l'expression : la règle est dans le voter.
- Le mot de passe n'appartient à aucun groupe de sérialisation : il ne peut pas
  sortir. Pas d'inscription publique : les comptes se créent en console
  (`make user-create ARGS="moi@exemple.com --admin"`).
- Les clés vivent dans `config/jwt/` (non versionnées) : `make jwt-keypair` les
  régénère, la passphrase est dans `.env`.
- La documentation `/api/docs` est ouverte : la fermer dans `access_control` si
  l'API n'est pas publique.

### Requêtes de test

`request/test.http` (client HTTP de PhpStorm / VS Code) couvre les routes
livrées : sonde, spec OpenAPI, connexion — elle enregistre le jeton pour les
suivantes —, `/api/me`, la collection paginée, une fiche, les cas 401 et le
préflight CORS. **Toute opération ajoutée doit y être ajoutée aussi**, avec son
cas d'échec. L'hôte et l'adresse vivent dans `request/http-client.env.json`, le
mot de passe dans `request/http-client.private.env.json` (non versionné, créé à
la génération).

