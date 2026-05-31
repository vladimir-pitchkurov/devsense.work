#!/bin/bash

set -e

echo "🚀 Начало деплоя..."

php artisan down || true

git fetch origin
git reset --hard origin/development

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan optimize:clear
php artisan optimize

php artisan migrate --force

# Импорт статей из Markdown-файлов в базу данных (для админки)
php artisan app:migrate-articles-to-database

# Настройка профиля автора (Vladimir Pichkurov)
php artisan app:setup-author

# Генерация статического файла sitemap.xml
php artisan sitemap:write

# Пинг поисковых систем (Bing, Yandex и др.) через IndexNow API
php artisan seo:ping-indexnow

sudo systemctl reload php8.5-fpm

php artisan up

echo "✅ Деплой успешно завершен!"
