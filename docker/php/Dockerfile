ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli AS base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

FROM base AS development

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN groupadd --gid "${GROUP_ID}" env-sync \
    && useradd --uid "${USER_ID}" --gid "${GROUP_ID}" --create-home env-sync \
    && mkdir -p /app/vendor /tmp/composer-cache \
    && chown -R env-sync:env-sync /app /tmp/composer-cache

USER env-sync

CMD ["php", "--version"]

FROM base AS qa

COPY composer.json composer.lock ./
ARG QA_RUN_ID=default
RUN test -n "${QA_RUN_ID}" \
    && composer install --no-interaction --prefer-dist --no-scripts

COPY . .

RUN composer qa
