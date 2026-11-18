.PHONY: up deploy-stage
.DEFAULT_GOAL := up

up:
	$(MAKE) -C traefik up
	$(MAKE) -C infra up
	$(MAKE) -C kratos up
	$(MAKE) -C api up
	$(MAKE) -C dashboard up

deploy-stage:
	./scripts/deploy-stage.sh
