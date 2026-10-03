# PHP with what the providers need (dom and libxml for the statement files,
# intl for the SEPA character set) and Composer: the harness installs this
# package and every omnibank/* provider at start.
FROM php:8.4-cli-alpine
RUN apk add --no-cache git unzip icu-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install -j"$(nproc)" bcmath intl zip \
    && apk del $PHPIZE_DEPS
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_CACHE_DIR=/harness/.cache
WORKDIR /harness
ENTRYPOINT ["/omnibank/core/docker/entrypoint.sh"]
CMD ["gateways"]
