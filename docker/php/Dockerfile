ARG PHP_VERSION=8.5

FROM php:${PHP_VERSION}-cli AS base

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

FROM base AS development

CMD ["php", "--version"]

FROM base AS qa

COPY composer.json composer.lock ./
ARG QA_RUN_ID=default
RUN test -n "${QA_RUN_ID}" \
    && composer install --no-interaction --prefer-dist --no-scripts

COPY . .

RUN composer qa
