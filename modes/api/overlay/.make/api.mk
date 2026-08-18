SERVER_URL ?= https://127.0.0.1:8000

.PHONY: routes health

routes: ## Liste les routes de l'API
	$(CONSOLE) debug:router

health: ## Appelle GET /health (SERVER_URL=… pour changer d'hôte)
	@curl -skS "$(SERVER_URL)/health" \
		| $(PHP) -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;'

