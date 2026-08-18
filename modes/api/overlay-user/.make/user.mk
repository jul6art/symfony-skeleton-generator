.PHONY: jwt-keypair user-create token

jwt-keypair: ## Génère la paire de clés JWT si elle n'existe pas
	$(CONSOLE) lexik:jwt:generate-keypair --skip-if-exists

user-create: ## Crée un compte — ARGS="moi@exemple.com --admin"
	$(CONSOLE) app:user:create $(ARGS)

token: ## Récupère un JWT — EMAIL=… PASSWORD=…
	@curl -skS -X POST "$(SERVER_URL)/api/login" \
		-H 'Content-Type: application/json' \
		-d "{\"email\":\"$(EMAIL)\",\"password\":\"$(PASSWORD)\"}" \
		| $(PHP) -r 'echo json_encode(json_decode(stream_get_contents(STDIN)), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;'
