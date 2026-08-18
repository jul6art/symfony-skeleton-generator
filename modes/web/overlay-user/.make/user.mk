.PHONY: user-create

user-create: ## Crée un compte — ARGS="moi@exemple.com --admin"
	$(CONSOLE) app:user:create $(ARGS)
