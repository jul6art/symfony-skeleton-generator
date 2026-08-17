## Spécificités du mode api

- Pas de Twig, pas d'assets : toute réponse est du JSON.
- Contrôleurs invocables (`__invoke`) sous `src/Controller/`, une action par
  classe, préfixe de route `/api` sauf pour `/health`.
- Entrée : DTO + `#[MapRequestPayload]` / `#[MapQueryString]` avec contraintes
  de validation. Jamais `$request->request->get()` dans la logique métier.
- Sortie : `JsonResponse` via le Serializer avec des groupes de sérialisation
  explicites, jamais l'entité brute.
- Erreurs : codes HTTP corrects (422 pour la validation, 404, 409) et corps
  d'erreur homogène.
- CORS géré par `nelmio/cors-bundle` (`CORS_ALLOW_ORIGIN` dans `.env`).
- `GET /health` est le smoke test : il doit rester sans dépendance à la base.
