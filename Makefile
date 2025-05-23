###############################################################################
# Makefile pour projet Symfony (Docker + Composer + Symfony + JWT)
# Usage :
#   make help               -> liste des commandes
#   make up / down / stop   -> gérer Docker
#   make sf cache:clear     -> exécuter une commande Symfony
#   make crequire symfony/console -> composer require symfony/console
###############################################################################

# Définition des variables
DOCKER_COMPOSE = docker compose
EXEC_PHP       = $(DOCKER_COMPOSE) exec -T php-fpm
EXEC_YARN      = yarn
SYMFONY        = $(EXEC_PHP) bin/console
COMPOSER       = $(EXEC_PHP) composer
VENDOR_BIN     = $(EXEC_PHP) ./vendor/bin/
PHP_CS_FIXER   = $(VENDOR_BIN)php-cs-fixer
NPX            = npx
NPM            = npm

# On récupère tous les arguments après la première cible
Arguments := $(wordlist 2,$(words $(MAKECMDGOALS)),$(MAKECMDGOALS))

# La cible par défaut
.DEFAULT_GOAL := help

###############################################################################
# On déclare toutes les cibles "virtuelles" comme .PHONY
###############################################################################
.PHONY: help build kill up down stop install update crequire dal sf cc regen warmup \
        fix-perms purge fixtures diff dmm dmg db dbm cdb ISO new initfile consume \
        watch dev prod ni lint-css lint-js fix-js phpcs phpcbf lint-twig lint-php \
        fix-php lint stan phpunit jwt-generate-keys jwt-clear-keys

###############################################################################
## Aide
###############################################################################
help: ## Affiche cette aide
	@echo "Commandes disponibles :"
	@grep -E '(^[a-zA-Z_-]+:.*?##.*$$)' $(MAKEFILE_LIST) | \
	 awk 'BEGIN {FS = ":.*?## "}; NF==2 {printf "  \033[32m%-15s\033[0m %s\n", $$1, $$2}'


###############################################################################
## Docker
###############################################################################
build: ## Build des conteneurs
	$(DOCKER_COMPOSE) pull --ignore-pull-failures
	$(DOCKER_COMPOSE) build --pull

up: ## Démarre les conteneurs en arrière-plan
	$(DOCKER_COMPOSE) up -d

down: ## Stoppe et supprime les conteneurs + volumes
	$(DOCKER_COMPOSE) down --volumes --remove-orphans

stop: ## Stoppe les conteneurs
	$(DOCKER_COMPOSE) stop

kill: ## Kill tous les conteneurs + nettoie vendor et cache
	$(DOCKER_COMPOSE) kill
	docker rm $$(docker ps -a -q) || true
	rm -rf vendor var/cache


###############################################################################
## Composer
###############################################################################
install: composer.lock ## Installe les dépendances (composer.lock)
	$(COMPOSER) install --no-interaction --no-progress --prefer-dist

update: composer.json ## Met à jour les dépendances (composer.json)
	$(COMPOSER) update --no-interaction --no-progress --prefer-dist

crequire: composer.json ## Installe un package, ex: "make crequire symfony/console"
	$(COMPOSER) require $(shell echo "$(Arguments)")

dal: composer.json ## Dump-autoload
	$(COMPOSER) dump-autoload


###############################################################################
## Symfony
###############################################################################
sf: vendor ## Exécute une commande Symfony, ex: "make sf cache:clear"
	$(SYMFONY) $(shell echo "$(Arguments)")

cc: vendor ## Clear cache
	$(SYMFONY) cache:clear

regen: vendor ## Regenerate entity
	$(SYMFONY) make:entity --regenerate $(shell echo "$(Arguments)")

warmup: vendor ## Warmup cache
	$(SYMFONY) cache:warmup

fix-perms: ## Fix permissions (ex: 777 sur var/*)
	chmod -R 777 var/*

purge: ## Purge cache et logs
	rm -rf var/cache/* var/log/*

fixtures: vendor ## Charge les fixtures
	$(SYMFONY) doctrine:fixtures:load --no-interaction --append

diff: vendor ## Crée une migration à partir du diff
	$(SYMFONY) doctrine:migrations:diff --no-interaction

dmm: vendor ## Migrations + fixtures
	$(SYMFONY) doctrine:migrations:migrate --no-interaction
	$(SYMFONY) doctrine:fixtures:load --no-interaction --append

dmg: vendor ## Génère un fichier de migration
	$(SYMFONY) doctrine:migrations:generate --no-interaction

db: vendor ## Drop + Create la DB
	$(SYMFONY) doctrine:database:drop --if-exists --force -n
	$(SYMFONY) doctrine:database:create

dbm: db dmm cc ## Drop DB + Migrate + Clear cache

cdb: vendor ## Custom DB cleaner (ex: supprime users de test)
	$(SYMFONY) app:db:cleaner

###############################################################################
## Project / Hooks
###############################################################################
new: kill build up initfile update jwt-generate-keys ## Première installation du projet

initfile: .env ## Copie des hooks Git, etc.
	cp commit-msg.dist .git/hooks/commit-msg
	cp pre-commit.dist .git/hooks/pre-commit
	chmod +x .git/hooks/commit-msg .git/hooks/pre-commit


###############################################################################
## JWT
###############################################################################
jwt-generate-keys: ## Génère les clés JWT
	mkdir -p config/jwt
	openssl genpkey -algorithm RSA -out config/jwt/private.pem -pkeyopt rsa_keygen_bits:4096
	openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
	chmod 644 config/jwt/public.pem
	chmod 600 config/jwt/private.pem

jwt-clear-keys: ## Supprime les clés JWT
	rm -f config/jwt/private.pem config/jwt/public.pem

###############################################################################
## Front / Assets
###############################################################################
watch: ## Webpack watch
	rm -rf public/build
	$(EXEC_YARN) watch

dev: ## Build de dev
	rm -rf public/build
	$(EXEC_YARN) dev

prod: ## Build de prod
	rm -rf public/build
	$(EXEC_YARN) prod

ni: ## Yarn install forcé
	rm -rf node_modules package-lock.json
	$(EXEC_YARN) install --force

###############################################################################
## RabbitMQ / Messenger
###############################################################################

mq-setup: vendor ## Crée les files d'attente Messenger dans RabbitMQ
	$(SYMFONY) messenger:setup-transports

mq-restart: ## Redémarre le conteneur RabbitMQ
	$(DOCKER_COMPOSE) restart rabbitmq

mq-logs: ## Affiche les logs de RabbitMQ
	$(DOCKER_COMPOSE) logs -f rabbitmq

consume: vendor ## Consomme les messages async
	$(SYMFONY) messenger:consume async -vv

consume-failed: vendor ## Consomme les messages échoués
	$(SYMFONY) messenger:consume failed -vv

mq-retry-failed: vendor ## Réessaye tous les messages échoués
	$(SYMFONY) messenger:failed:retry

mq-clear-failed: vendor ## Supprime tous les messages échoués
	$(SYMFONY) messenger:failed:remove

mq-show-failed: vendor ## Affiche les messages échoués
	$(SYMFONY) messenger:failed:show

###############################################################################
## Quality / Lint / Tests
###############################################################################
lint-css: .stylelint.json ## Lint CSS
	$(NPX) stylelint --config ./.stylelint.json "**/*.css" --allow-empty-input

lint-js: .eslintrc.json ## Lint JS
	$(NPX) eslint assets/js

fix-js: .eslintrc.json ## Fix JS
	$(NPX) eslint assets/js --fix

phpcs: vendor .phpcs.xml ## PHP_CodeSniffer
	$(VENDOR_BIN)phpcs -v --standard=.phpcs.xml src --ignore=Migrations/*

phpcbf: vendor .phpcs.xml ## PHP_CodeSniffer (auto-fix)
	$(VENDOR_BIN)phpcbf -v --standard=.phpcs.xml src

lint-twig: ## Lint Twig
	$(SYMFONY) lint:twig

lint-php: ## Lint PHP (dry-run avec php-cs-fixer)
	$(PHP_CS_FIXER) fix --dry-run

fix-php: ## Fix PHP (avec php-cs-fixer)
	$(PHP_CS_FIXER) fix

lint: lint-php lint-twig lint-css lint-js ## Lint global

stan: .phpstan.neon ## PHPStan
	$(VENDOR_BIN)phpstan analyse -c .phpstan.neon --memory-limit 1G

phpunit: vendor ## Tests PHPUnit
	$(VENDOR_BIN)phpunit

###############################################################################
## Secrets
###############################################################################
secrets-list: vendor ## Affiche la liste des secrets avec reveal
	$(SYMFONY) secrets:list --reveal

###############################################################################
## Règle générique pour éviter l'erreur "No rule to make target ..."
###############################################################################
%:
	@: