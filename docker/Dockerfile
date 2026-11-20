# Local development image: FrankenPHP with Magento's PHP extensions. The
# repository is mounted at /app by compose.yaml. Spectivis Cloud does not use
# this file; it builds the repository with its own Magento preset.
FROM dunglas/frankenphp:php8.4-trixie

RUN install-php-extensions bcmath gd intl pdo_mysql soap sockets sodium xsl zip redis opcache apcu \
    && apt-get update && apt-get install -y --no-install-recommends default-mysql-client git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/zz-magento.ini
COPY Caddyfile /etc/frankenphp/Caddyfile

ENV COMPOSER_ALLOW_SUPERUSER=1 SERVER_NAME=:80
WORKDIR /app
