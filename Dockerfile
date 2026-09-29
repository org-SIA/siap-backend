# Construit l'image à partir du projet Laravel local (siap-api/), et non plus depuis un squelette
# téléchargé à la volée : composer.json/composer.lock existent déjà ici, donc "composer install"
# suffit (plus besoin de "composer create-project").

# ---------- Étape 1 : dépendances PHP (a besoin d'accéder à Packagist) ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer config --global process-timeout 900 \
 && composer config --global github-protocols https \
 && composer install --no-interaction --no-scripts --prefer-dist \
    --no-progress -vvv || \
    (sleep 3 && composer install --no-interaction --no-scripts --prefer-dist --no-progress) || \
    (sleep 5 && composer install --no-interaction --no-scripts --prefer-dist --no-progress)
# ---------- Étape 2 : image d'exécution, légère ----------
FROM php:8.3-fpm-alpine AS app

RUN set -eux; \
    for i in 1 2 3 4 5; do apk update && break || { echo "retry apk update ($i)"; sleep 5; }; done; \
    for i in 1 2 3 4 5; do apk add --no-cache postgresql-libs libzip icu-libs && break || { echo "retry ($i)"; sleep 5; }; done; \
    for i in 1 2 3 4 5; do apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev && break || { echo "retry ($i)"; sleep 5; }; done; \
    docker-php-ext-install pdo_pgsql zip bcmath pcntl intl opcache; \
    apk del .build-deps

WORKDIR /var/www/html

# Code de l'application (le .dockerignore exclut déjà vendor/, .git, tests, etc.)
COPY . .
# Dépendances installées à l'étape précédente, par-dessus.
COPY --from=vendor /app/vendor ./vendor

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
 && rm -f bootstrap/cache/*.php \
 && composer dump-autoload --optimize 2>/dev/null || true \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]
