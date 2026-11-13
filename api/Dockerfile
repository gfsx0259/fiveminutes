FROM composer/composer:2-bin AS composer

FROM konstantinpopov/frankenphp:1.13.2-php8.4.27 AS base

#
# Development
#

FROM base AS dev
ARG USER_ID=10001
ARG GROUP_ID=10001
ARG USER_NAME=appuser
ARG GROUP_NAME=appuser

RUN install-php-extensions \
    xdebug

RUN <<EOF
cat > /usr/local/etc/php/conf.d/zzz-xdebug.ini <<'XDEBUG'
xdebug.mode=debug
xdebug.client_host=host.docker.internal
xdebug.start_with_request=yes
XDEBUG
EOF

COPY --from=composer /composer /usr/bin/composer

# Based on https://frankenphp.dev/docs/docker/#running-as-a-non-root-user
RUN \
	groupadd --gid ${GROUP_ID} ${GROUP_NAME}; \
    useradd --gid ${GROUP_ID} --uid ${USER_ID} -m -d /home/${USER_NAME} ${USER_NAME}; \
	# Add additional capability to bind to port 80 and 443
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp; \
	# Give write access to /data/caddy and /config/caddy
	chown -R ${USER_NAME}:${GROUP_NAME} /data/caddy && chown -R ${USER_NAME}:${GROUP_NAME} /config/caddy
USER ${USER_NAME}

#
# Production
#

FROM base AS prod-builder
COPY --from=composer /composer /usr/bin/composer
COPY . /app
RUN --mount=type=cache,target=/tmp/cache \
    composer install --no-dev --no-progress --no-interaction --classmap-authoritative && \
    rm composer.lock composer.json

FROM base AS prod
ENV RIDER_ENV=prod
COPY --from=prod-builder --chown=www-data:www-data /app /app
USER www-data
