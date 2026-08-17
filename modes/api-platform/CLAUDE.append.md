## Spécificités du mode api-platform

### Conventions

- L'API est **déclarative** : une ressource s'expose avec les attributs
  `#[ApiResource]` / `#[ApiProperty]` / `#[ApiFilter]`, pas avec des
  contrôleurs. N'écrire un contrôleur que pour un cas vraiment hors modèle
  (et le brancher via une `Operation` avec `controller:`).
- Pour tout ce qui n'est pas du CRUD direct, utiliser un **state provider**
  (`ProviderInterface`) ou un **state processor** (`ProcessorInterface`)
  plutôt que de la logique dans l'entité ou dans un contrôleur.
- Séparer l'exposition du modèle : entités Doctrine dans `src/Entity/`,
  DTO d'API dans `src/ApiResource/` dès que la forme exposée diverge du
  schéma de la base.
- Sérialisation : toujours des groupes explicites
  (`normalizationContext` / `denormalizationContext`), jamais l'entité nue.
- Validation par contraintes Symfony ; API Platform renvoie alors
  automatiquement un 422 au format Problem Details (RFC 7807).
- Les opérations sont `stateless: true` : pas de session, pas d'état serveur.
- La doc OpenAPI est générée : elle ne se maintient pas à la main. Après un
  changement de contrat, `make openapi` et vérifier le diff.
- Sécurité au niveau des opérations (`security:`, `securityPostDenormalize:`)
  et non dans `security.yaml` seul.
- Tests fonctionnels avec `ApiTestCase` (`static::createClient()` +
  `assertMatchesResourceItemJsonSchema()`), pas avec `WebTestCase`.
- `GET /health` est le smoke test : il doit rester sans dépendance à la base.

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
- `/api/users` est réservé à `ROLE_ADMIN` ; une fiche n'est lisible que par son
  propriétaire ou un administrateur (`security: "… or object == user"`).
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

### CORS

`nelmio/cors-bundle` (`CORS_ALLOW_ORIGIN` dans `.env`, une **expression
régulière**) : la restreindre aux domaines réels en production, jamais `^.*$`.
