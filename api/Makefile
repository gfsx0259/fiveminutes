.PHONY: up
.DEFAULT_GOAL := up

USER_ID := $(shell if [ "$$(id -u)" = 0 ]; then echo 1000; else id -u; fi)
GROUP_ID := $(shell if [ "$$(id -g)" = 0 ]; then echo 1000; else id -g; fi)

up:
	test -f .env || cp .env.example .env
	docker network inspect dev-leadmarket-network >/dev/null 2>&1 || docker network create dev-leadmarket-network
	if [ "$$(id -u)" = 0 ]; then chown -R $(USER_ID):$(GROUP_ID) .; fi
	UID=$(USER_ID) GID=$(GROUP_ID) docker compose build api
	UID=$(USER_ID) GID=$(GROUP_ID) docker compose up -d
	UID=$(USER_ID) GID=$(GROUP_ID) docker compose run --rm --no-deps --workdir /app --entrypoint composer api install --no-interaction --ignore-platform-req=ext-bcmath
	UID=$(USER_ID) GID=$(GROUP_ID) docker compose up -d
	docker compose exec -T api php yii migrate:up --no-interaction
	docker compose exec -T api php yii clickhouse:migrate
	docker compose exec -T api php yii dev:seed-chase
