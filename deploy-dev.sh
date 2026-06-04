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

# Настройка профиля автора (Vladimir Pichkurov)
php artisan app:setup-author

# Импорт статей из Markdown-файлов в базу данных (для админки)
php artisan app:migrate-articles-to-database

# Генерация статического файла sitemap.xml
php artisan sitemap:write

# Пинг поисковых систем (Bing, Yandex и др.) через IndexNow API
php artisan seo:ping-indexnow

# ── Перезагрузка PHP-FPM ─────────────────────────────────────────────────────
if sudo -n systemctl reload php8.5-fpm 2>/dev/null; then
    echo "✅ php8.5-fpm перезагружен через systemctl"
else
    FPM_PID_FILE="/var/run/php/php8.5-fpm.pid"
    if [ -f "$FPM_PID_FILE" ]; then
        kill -USR2 "$(cat "$FPM_PID_FILE")" && echo "✅ php8.5-fpm перезагружен через SIGUSR2"
    else
        echo "⚠️  Не удалось перезагрузить php8.5-fpm — настройте sudoers"
    fi
fi

# ── Перезапуск очередей (Supervisor) ─────────────────────────────────────────
if sudo -n supervisorctl restart laravel-worker:* 2>/dev/null; then
    echo "✅ Supervisor workers перезапущены"
else
    echo "⚠️  Не удалось перезапустить supervisor workers — настройте sudoers"
fi

php artisan up

echo "✅ Деплой успешно завершен!"
