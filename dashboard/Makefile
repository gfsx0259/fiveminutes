.PHONY: up
.DEFAULT_GOAL := up

up:
	docker network inspect dev-leadmarket-network >/dev/null 2>&1 || docker network create dev-leadmarket-network
	docker compose up -d
