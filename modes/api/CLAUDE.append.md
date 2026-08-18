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

### CORS

`nelmio/cors-bundle`, réglé dans `config/packages/nelmio_cors.yaml`.
`CORS_ALLOW_ORIGIN` (`.env`) est une **expression régulière** : la restreindre
aux domaines réels en production, jamais `^.*$`.

### Base de données

Les entités sont livrées sans migration : après `make db-create`, lancer
`make db-diff` puis `make db-migrate` pour créer le schéma initial.
