#!/bin/sh
set -e
cd /var/www/html
# Migrasi otomatis hanya untuk container web (ROLE=web)
if [ "${ROLE:-web}" = "web" ]; then
  php artisan migrate --force
  php artisan config:cache && php artisan route:cache && php artisan view:cache
fi
exec "$@"
