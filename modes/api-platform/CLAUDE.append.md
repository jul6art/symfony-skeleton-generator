## Spécificités du mode api-platform

### Conventions

- **Règle n°1, version API Platform : la décision d'accès est portée par
  l'opération** (`security:`, `securityPostDenormalize:`), pas par
  `security.yaml` seul et pas par un contrôleur — **une opération sans
  `security:` est un bug**. L'expression nomme un attribut de voter
  (`is_granted('USER_VIEW', object)`) et jamais un rôle ni une comparaison
  écrite sur place : la règle vit dans le voter, où elle est testable.
- `security:` est évalué après la lecture : `object` est la ressource concernée,
  c'est donc le sujet à passer au voter. Une décision qui dépend du corps de la
  requête va dans `securityPostDenormalize:`.
- `api_platform.defaults.security` (`config/packages/api_platform.yaml`) ferme
  par défaut : une opération qui oublie sa clé répond 403 au lieu d'être
  ouverte. C'est un filet, pas une dispense — et c'est le seul garde-fou de ce
  côté, les routes d'API Platform étant servies par un contrôleur du bundle que
  `RouteAccessDecisionTest` ne voit pas passer.
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
- Tests fonctionnels avec `ApiTestCase` (`static::createClient()` +
  `assertMatchesResourceItemJsonSchema()`), pas avec `WebTestCase`.
- `GET /health` est le smoke test : il doit rester sans dépendance à la base.

### CORS

`nelmio/cors-bundle` (`CORS_ALLOW_ORIGIN` dans `.env`, une **expression
régulière**) : la restreindre aux domaines réels en production, jamais `^.*$`.
