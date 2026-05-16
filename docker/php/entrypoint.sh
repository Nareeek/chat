#!/bin/sh
set -e

if [ ! -f vendor/autoload.php ]; then
    composer update --no-interaction --prefer-dist
fi

php artisan storage:link >/dev/null 2>&1 || true

exec php-fpm
