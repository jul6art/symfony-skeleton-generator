## Spécificités du mode api

### Conventions

- Pas de Twig, pas d'assets : toute réponse est du JSON.
- Contrôleurs invocables (`__invoke`) sous `src/Controller/`, une action par
  classe, préfixe de route `/api` sauf pour `/health`.
- Entrée : DTO + `#[MapRequestPayload]` / `#[MapQueryString]` avec contraintes
  de validation. Jamais `$request->request->get()` dans la logique métier.
- Sortie : `JsonResponse` via le Serializer avec des groupes de sérialisation
  explicites (`user:read`…), jamais l'entité brute.
- Erreurs : codes HTTP corrects (422 pour la validation, 404, 409) et corps
  d'erreur homogène.
- `GET /health` est le smoke test : il doit rester sans dépendance à la base.

### Comptes

- Le compte est `Jul6Art\AuthBundle\Entity\User` (auth-bundle) : pas d'entité
  `App\Entity\User`. Injecter `UserManagerInterface` / `UserRepositoryInterface`,
  créer les comptes avec `UserFactory::create()`.
- L'entité vendor ne porte aucune règle applicative : contraintes dans
  `config/validator/user.yaml`, groupes de sérialisation dans
  `config/serializer/user.yaml`. Le mot de passe n'est dans aucun groupe, il ne
  sort donc jamais de l'API.
- Rôles : `User::ROLE_USER` et `User::ROLE_ADMIN`, jamais de chaîne littérale.

### Authentification (JWT)

- `lexik/jwt-authentication-bundle` : `POST /api/login` avec
  `{"email": "…", "password": "…"}` renvoie `{"token": "…"}`, à présenter
  ensuite dans `Authorization: Bearer <token>`.
- La route `api_login` n'a pas de code : elle est interceptée par la clé
  `json_login` du pare-feu (`config/packages/security.yaml`).
- Trois pare-feux : `login` (échange des identifiants), `api` (jetons, tout est
  `stateless`), `main` (le reste, dont `/health`). Toute nouvelle règle d'accès
  va dans `access_control` **et** dans un `#[IsGranted]` sur le contrôleur.
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

### CORS

`nelmio/cors-bundle`, réglé dans `config/packages/nelmio_cors.yaml`.
`CORS_ALLOW_ORIGIN` (`.env`) est une **expression régulière** : la restreindre
aux domaines réels en production, jamais `^.*$`.

### Base de données

Les entités sont livrées sans migration : après `make db-create`, lancer
`make db-diff` puis `make db-migrate` pour créer le schéma initial.
