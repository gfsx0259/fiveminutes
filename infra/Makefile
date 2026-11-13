.PHONY: up
.DEFAULT_GOAL := up

up:
	docker network inspect dev-leadmarket-network >/dev/null 2>&1 || docker network create dev-leadmarket-network
	docker compose up -d --wait
	i=0; until docker compose exec -T clickhouse clickhouse-client --user rider --password rider --query "SELECT 1"; do i=$$((i+1)); [ $$i -lt 60 ] || exit 1; sleep 2; done
