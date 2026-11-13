.PHONY: up
.DEFAULT_GOAL := up

# Standalone proxy for a machine of its own. Locally the same ports stay with meta/compose.yaml.

up:
	docker network inspect dev-leadmarket-network >/dev/null 2>&1 || docker network create dev-leadmarket-network
	test -f cert/_wildcard.rider.io.pem
	test -f cert/_wildcard.rider.io-key.pem
	test -f cert/_wildcard.chase.io.pem
	test -f cert/_wildcard.chase.io-key.pem
	test -f cert/_wildcard.marshall.io.pem
	test -f cert/_wildcard.marshall.io-key.pem
	docker compose up -d
