.PHONY: up
.DEFAULT_GOAL := up

up:
	docker network inspect dev-leadmarket-network >/dev/null 2>&1 || docker network create dev-leadmarket-network
	docker compose up -d
	docker compose exec -T postgres sh -c 'until pg_isready -U rider -d app; do sleep 1; done'
