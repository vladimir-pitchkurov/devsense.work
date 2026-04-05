#!/bin/bash

set -e

echo "🚀 Начало деплоя..."

php artisan down || true

git pull origin prod

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan optimize:clear
php artisan optimize

php artisan migrate --force

sudo systemctl reload php8.5-fpm

php artisan up

echo "✅ Деплой успешно завершен!"
