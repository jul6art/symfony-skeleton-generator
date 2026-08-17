.PHONY: assets importmap-update

assets: ## Compile les assets (AssetMapper)
	$(CONSOLE) asset-map:compile

importmap-update: ## Met à jour les dépendances JS de l'importmap
	$(CONSOLE) importmap:update
