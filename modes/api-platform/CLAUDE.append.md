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
- Une classe de `vendor/` ne peut pas porter d'attributs : elle s'expose en
  YAML dans `config/api_platform/` (c'est le cas du `User` du auth-bundle).
  Les trois chemins scannés sont déclarés dans `api_platform.mapping.paths`.
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

### CORS

`nelmio/cors-bundle` (`CORS_ALLOW_ORIGIN` dans `.env`, une **expression
régulière**) : la restreindre aux domaines réels en production, jamais `^.*$`.
