.PHONY: help dc-build dc-up dc-down dc-restart dc-destroy dc-init dc-logs dc-ps dc-sh-api dc-sh-front \
	api-install api-db api-import api-import-refresh front-install front-cache-clear test cc

# Les conteneurs tournent avec ton UID/GID : les fichiers créés dans api/ et front/ restent les tiens.
export UID := $(shell id -u)
export GID := $(shell id -g)

DC       = docker compose
API      = $(DC) exec api
FRONT    = $(DC) exec front
CONSOLE  = php bin/console

help: ## Affiche cette aide
	@grep -E '(^[a-zA-Z0-9_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-22s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## --- Docker -----------------------------------------------------
dc-build: ## Construit l'image PHP (FrankenPHP 8.5)
	$(DC) build --pull

dc-up: ## Démarre les conteneurs (database, api, front)
	$(DC) up -d --remove-orphans
	@echo ""
	@echo "  Front Symfony UX : http://localhost:8181"
	@echo "  API Platform     : http://localhost:8180/api"
	@echo "  Docs Scalar      : http://localhost:8180/api/docs?ui=scalar"

dc-down: ## Arrête les conteneurs
	$(DC) down --remove-orphans

dc-restart: dc-down dc-up ## Redémarre les conteneurs

dc-destroy: ## Arrête les conteneurs et supprime la base (volume)
	$(DC) down -v --remove-orphans

dc-init: dc-build ## Installation complète : image, dépendances, base, données, assets (relançable)
	@# En mode worker, FrankenPHP démarre Symfony dès le lancement : vendor/ doit exister avant `up`.
	@# Les dépendances sont donc installées dans des conteneurs éphémères.
	$(DC) run --rm --no-deps api composer install --no-interaction --prefer-dist
	$(DC) run --rm --no-deps front composer install --no-interaction --prefer-dist
	$(DC) up -d --remove-orphans --wait database
	$(DC) up -d --remove-orphans
	$(MAKE) api-db api-import
	$(FRONT) $(CONSOLE) importmap:install
	@$(MAKE) --no-print-directory dc-up

dc-logs: ## Suit les logs des conteneurs
	$(DC) logs -f --tail=100

dc-ps: ## Liste les conteneurs
	$(DC) ps

dc-sh-api: ## Shell dans le conteneur de l'API
	$(API) bash

dc-sh-front: ## Shell dans le conteneur du front
	$(FRONT) bash

## --- API (API Platform 5) ---------------------------------------
api-install: ## composer install côté API
	$(API) composer install --no-interaction --prefer-dist

api-db: ## Crée la base et joue les migrations
	$(API) $(CONSOLE) doctrine:database:create --if-not-exists --no-interaction
	$(API) $(CONSOLE) doctrine:migrations:migrate --no-interaction --allow-no-migration

api-import: ## Charge le Pokédex depuis l'instantané api/data/pokedex.json
	$(API) $(CONSOLE) app:pokedex:import
	@$(MAKE) --no-print-directory front-cache-clear

api-import-refresh: ## Regénère l'instantané depuis PokeAPI puis l'importe (LIMIT=1025 par défaut)
	$(API) $(CONSOLE) app:pokedex:import --no-debug --refresh $(or $(LIMIT),1025)
	@$(MAKE) --no-print-directory front-cache-clear

## --- Front (Symfony UX) -----------------------------------------
front-install: ## composer install + vendors JS (AssetMapper) côté front
	$(FRONT) composer install --no-interaction --prefer-dist
	$(FRONT) $(CONSOLE) importmap:install

front-cache-clear: ## Vide les caches de données du front (réponses HTTP de l'API, Pokédex complet)
	-$(FRONT) $(CONSOLE) cache:pool:clear cache.app cache.http_client --quiet

## --- Qualité ----------------------------------------------------
test: ## Lance les tests fonctionnels de l'API (base _test recréée)
	$(API) $(CONSOLE) doctrine:database:drop --force --if-exists --env=test
	$(API) $(CONSOLE) doctrine:database:create --env=test
	$(API) $(CONSOLE) doctrine:migrations:migrate --no-interaction --env=test
	$(API) $(CONSOLE) app:pokedex:import --env=test
	$(API) php bin/phpunit

cc: ## Vide le cache des deux applications
	$(API) $(CONSOLE) cache:clear
	$(FRONT) $(CONSOLE) cache:clear
