.PHONY: user-create permissions-seed jwt-keypair

user-create: ## Crée un compte — make user-create ARGS="moi@exemple.com --admin"
	$(CONSOLE) app:user:create $(ARGS)

permissions-seed: ## Pose les permissions par défaut de chaque rôle (idempotent)
	$(CONSOLE) app:permissions:seed

jwt-keypair: ## (Re)génère la paire de clés JWT
	$(CONSOLE) lexik:jwt:generate-keypair --overwrite --skip-if-exists
