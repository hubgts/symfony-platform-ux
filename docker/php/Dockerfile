# Image commune à l'API (API Platform 5) et au front (Symfony UX) : FrankenPHP = PHP + Caddy dans un seul binaire.
FROM dunglas/frankenphp:1-php8.5

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip postgresql-client \
    && rm -rf /var/lib/apt/lists/*

RUN install-php-extensions pdo_pgsql intl zip opcache apcu @composer

COPY php.ini $PHP_INI_DIR/conf.d/zz-app.ini
COPY Caddyfile /etc/frankenphp/Caddyfile

# Le conteneur tourne avec l'UID/GID de l'hôte : les fichiers créés dans les volumes restent à toi.
RUN groupadd -g ${GID} app 2>/dev/null || true \
    && useradd -u ${UID} -g ${GID} -m -s /bin/bash app 2>/dev/null || true \
    && chown -R ${UID}:${GID} /data /config

ENV COMPOSER_HOME=/tmp/composer \
    SERVER_NAME=:8080

USER ${UID}:${GID}
WORKDIR /app
EXPOSE 8080
