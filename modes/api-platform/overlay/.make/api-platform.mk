SERVER_URL ?= https://127.0.0.1:8000

.PHONY: routes assets docs openapi health

routes: ## Liste les routes de l'API
	$(CONSOLE) debug:router

# Swagger UI, polices et feuille de style d'API Platform vivent dans public/bundles/.
install: assets

assets: ## (Ré)installe les assets des bundles dans public/
	$(CONSOLE) assets:install public

docs: ## Ouvre la doc interactive (Swagger UI) dans le navigateur
	@symfony open:local --path=/api/docs || true

openapi: ## Exporte la spec OpenAPI dans var/openapi.json
	$(CONSOLE) api:openapi:export --output=var/openapi.json

health: ## Appelle GET /health (SERVER_URL=… pour changer d'hôte)
	@curl -skS "$(SERVER_URL)/health" \
		| $(PHP) -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;'


# Avec api-platform/graphql installé :
# graphql-schema: ## Exporte le schéma GraphQL
# 	$(CONSOLE) api:graphql:export --output=var/schema.graphql
