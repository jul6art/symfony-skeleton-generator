.PHONY: assets assets-watch admin

assets: ## Installe les dépendances front et compile (npm requis pour ce mode)
	npm install
	npm run build

assets-watch: ## Recompile à la volée
	npm run watch

admin: ## Ouvre le back-office
	$(SYMFONY) open:local --path=/admin
