.PHONY: assets assets-watch admin

# ⚠️ Le préchauffage AVANT le build, et ce n'est pas un confort : `assets/translator.js` importe
# `var/translations/index.js`, que le préchauffage écrit et que `/var/` gitignore. Sur une machine
# neuve, en CI et au déploiement, sans cette ligne le build échoue sur un import introuvable.
#
# ⚠️ Et le préchauffage du traducteur doit passer avant celui d'ux-translator, qui dépose les
# catalogues DÉJÀ CHARGÉS : sur un traducteur froid il écrit `export const messages = {};` — un
# fichier valide, un catalogue vide, aucune erreur nulle part. `cache:warmup` fait les deux dans
# le bon ordre.
assets: ## Installe les dépendances front et compile (npm requis pour ce mode)
	npm install
	$(PHP) -d memory_limit=-1 bin/console cache:warmup
	npm run build

assets-watch: ## Recompile à la volée
	npm run watch

admin: ## Ouvre le back-office
	$(SYMFONY) open:local --path=/admin
