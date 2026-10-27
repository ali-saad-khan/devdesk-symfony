SHELL := /bin/bash
export UID := $(shell id -u)
export GID := $(shell id -g)
RUN := docker compose run --rm app

.PHONY: up down sh console test stan cs

up:        ## start app, database and mail catcher
	docker compose up -d
down:
	docker compose down
sh:
	$(RUN) bash
console:   ## make console c="about"
	$(RUN) php bin/console $(c)
test:
	$(RUN) php bin/phpunit
stan:
	$(RUN) vendor/bin/phpstan analyse
cs:
	$(RUN) vendor/bin/php-cs-fixer fix
