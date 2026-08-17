## Spécificités du mode api-platform

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
- CORS géré par `nelmio/cors-bundle` (`CORS_ALLOW_ORIGIN` dans `.env`).
- `GET /health` est le smoke test : il doit rester sans dépendance à la base.
