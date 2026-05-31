#!/bin/bash

set -e

echo "🚀 Начало деплоя..."

php artisan down || true

git pull origin prod

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan optimize:clear
php artisan optimize

php artisan migrate --force

# Импорт статей из Markdown-файлов в базу данных (для админки)
php artisan app:migrate-articles-to-database

# Настройка профиля автора (Vladimir Pichkurov)
php artisan app:setup-author

# Обработка OG-изображений: очистка EXIF/AI-меток и публикация в public/images/og/
if [ -d "scripts/og-source" ] && ls scripts/og-source/*.png > /dev/null 2>&1; then
    echo "🖼️  Обработка OG-изображений..."
    mkdir -p public/images/og
    php scripts/process-og-images.php
fi

# Генерация статического файла sitemap.xml
php artisan sitemap:write

# Пинг поисковых систем (Bing, Yandex и др.) через IndexNow API
php artisan seo:ping-indexnow

sudo systemctl reload php8.5-fpm

php artisan up

echo "✅ Деплой успешно завершен!"
