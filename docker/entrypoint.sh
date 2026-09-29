#!/bin/sh
# Attend la base de données, prépare la configuration, puis lance la commande demandée
# (php-fpm pour l'API, "php artisan queue:work" pour le worker, etc.)
set -e

if [ -n "$DB_HOST" ]; then
  echo "⏳ Attente de la base de données ($DB_HOST:${DB_PORT:-5432})…"
  until php -r "new PDO('pgsql:host=${DB_HOST};port=${DB_PORT:-5432};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
    sleep 1
  done
  echo "✔ Base de données disponible."
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  php artisan key:generate --force
fi

# Réservé au conteneur "app" : migrations (+ données de démo si activées) au démarrage.
if [ "$RUN_MIGRATIONS" = "true" ]; then
  if [ "$SIAP_SEED_DEMO" = "true" ]; then
    php artisan migrate --seed --force
  else
    php artisan migrate --force
  fi
fi

php artisan storage:link 2>/dev/null || true

exec "$@"
