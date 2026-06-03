#!/bin/bash

# ─────────────────────────────────────────────────────────────────────────────
# ВАЖНО: для работы sudo-команд без пароля добавь в /etc/sudoers на сервере:
#
#   sudo tee /etc/sudoers.d/deploy-nopasswd > /dev/null << 'EOF'
#   Defaults:deploy !requiretty
#   deploy  ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.5-fpm
#   deploy  ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl
#   EOF
#   sudo chmod 440 /etc/sudoers.d/deploy-nopasswd
#
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "🚀 Начало деплоя..."

php artisan down || true

git fetch origin
git reset --hard origin/prod

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan optimize:clear
php artisan optimize

php artisan migrate --force

# Настройка профиля автора (Vladimir Pichkurov)
#php artisan app:setup-author

# Импорт статей из Markdown-файлов в базу данных (для админки)
#php artisan app:migrate-articles-to-database

# Обработка OG-изображений: очистка EXIF/AI-меток и публикация в public/images/og/
#if [ -d "scripts/og-source" ] && ls scripts/og-source/*.png > /dev/null 2>&1; then
#    echo "🖼️  Обработка OG-изображений..."
#    mkdir -p public/images/og
#    php scripts/process-og-images.php
#fi

# Генерация статического файла sitemap.xml
php artisan sitemap:write

# Пинг поисковых систем (Bing, Yandex и др.) через IndexNow API
php artisan seo:ping-indexnow

# ── Перезагрузка PHP-FPM ─────────────────────────────────────────────────────
# Пробуем sudo (работает если настроен sudoers), иначе — сигнал процессу.
if sudo -n systemctl reload php8.5-fpm 2>/dev/null; then
    echo "✅ php8.5-fpm перезагружен через systemctl"
else
    # Fallback: SIGUSR2 перезагружает php-fpm без sudo
    FPM_PID_FILE="/var/run/php/php8.5-fpm.pid"
    if [ -f "$FPM_PID_FILE" ]; then
        kill -USR2 "$(cat "$FPM_PID_FILE")" && echo "✅ php8.5-fpm перезагружен через SIGUSR2"
    else
        echo "⚠️  Не удалось перезагрузить php8.5-fpm — настрой sudoers (см. комментарий выше)"
    fi
fi

# ── Перезапуск очередей (Supervisor) ─────────────────────────────────────────
if sudo -n supervisorctl restart laravel-worker:laravel-worker 2>/dev/null; then
    echo "✅ Supervisor workers перезапущены"
else
    echo "⚠️  Не удалось перезапустить supervisor workers — настрой sudoers (см. комментарий выше)"
fi

php artisan up

echo "✅ Деплой успешно завершен!"
