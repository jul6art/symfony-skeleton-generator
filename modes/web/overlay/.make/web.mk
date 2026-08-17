FONTAWESOME_VERSION ?= 7.3.1
FA_DIR  := assets/fontawesome
FA_BASE := https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@$(FONTAWESOME_VERSION)

.PHONY: assets tailwind tailwind-watch fontawesome importmap-update worker user-create

# `make install` récupère aussi les polices : elles ne sont pas versionnées.
install: fontawesome

# Les templates référencent la feuille compilée par Tailwind : sans elle,
# AssetMapper ne sait pas résoudre `@import "tailwindcss"` et toute page casse.
start: tailwind
test: tailwind

assets: tailwind ## Compile les assets (Tailwind puis AssetMapper)
	$(CONSOLE) asset-map:compile

tailwind: ## Compile la feuille Tailwind (télécharge le binaire au besoin)
	$(CONSOLE) tailwind:build --minify

tailwind-watch: ## Recompile Tailwind à chaque modification
	$(CONSOLE) tailwind:build --watch

fontawesome: ## (Ré)installe Font Awesome Free en local dans assets/fontawesome/
	@mkdir -p "$(FA_DIR)/css" "$(FA_DIR)/webfonts"
	@curl -fsSL "$(FA_BASE)/css/all.min.css" -o "$(FA_DIR)/css/all.min.css"
	@# Les polices réellement référencées par la feuille, et elles seules.
	@grep -o 'url(\.\./webfonts/[^)?#]*' "$(FA_DIR)/css/all.min.css" \
		| sed 's|url(\.\./webfonts/||' | sort -u \
		| while read -r font; do \
			curl -fsSL "$(FA_BASE)/webfonts/$$font" -o "$(FA_DIR)/webfonts/$$font" || exit 1; \
		done
	@echo "Font Awesome $(FONTAWESOME_VERSION) installé dans $(FA_DIR)/"

importmap-update: ## Met à jour les dépendances JS de l'importmap
	$(CONSOLE) importmap:update

worker: ## Consomme la file async (les e-mails y passent : mot de passe oublié…)
	$(CONSOLE) messenger:consume async -vv

user-create: ## Crée un compte — ARGS="moi@exemple.com --admin"
	$(CONSOLE) app:user:create $(ARGS)
