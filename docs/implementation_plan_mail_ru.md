# Перенос DNS на Cloudflare, входящая почта Resend и тикеты поддержки

Этот план описывает:
1. **Инфраструктура**: Перенос DNS с GoDaddy на Cloudflare и настройка поддомена `mail.devsense.work`.
2. **Исходящая почта**: Настройка системного отправителя по умолчанию на `noreply@mail.devsense.work`.
3. **Входящая почта**: Создание вебхука для парсинга входящих писем через Resend.
4. **Админ-панель**: Реализация листинга тикетов поддержки и системы ответов на них.

---

## Действия пользователя: Перенос DNS на Cloudflare

Чтобы перенести сайт на Cloudflare и сохранить текущую конфигурацию Nginx + Certbot:

1. **Добавление домена в Cloudflare**:
   - Войдите в панель Cloudflare, нажмите **Add a Site** и введите `devsense.work`.
   - Выберите бесплатный тариф (Free). Cloudflare автоматически просканирует существующие DNS-записи домена в GoDaddy.

2. **Проверка и импорт DNS-записей**:
   - Убедитесь, что корневая запись `@` (A) и поддомен `dev` импортированы и указывают на IP-адрес вашего сервера.
   - Для начала установите статус прокси в режим **DNS Only** (серое облако), чтобы продление SSL-сертификатов через Certbot продолжало работать без проблем, либо включите **Proxied** (оранжевое облако), но обязательно переведите режим SSL/TLS в Cloudflare в положение **Full (Strict)**.

3. **Обновление серверов имен (NS) в GoDaddy**:
   - Перейдите в DNS-панель GoDaddy для домена `devsense.work`.
   - Замените текущие NS-сервера на два сервера имен Cloudflare, предоставленные при настройке.
   - *Примечание: Обновление DNS может занять от 2 до 24 часов, но обычно новые NS прописываются в течение 15 минут.*

4. **Проверка SSL в Cloudflare**:
   - В Cloudflare перейдите в раздел **SSL/TLS -> Overview**.
   - Выберите режим **Full (Strict)**. Это гарантирует шифрование трафика со стороны Cloudflare к посетителю и безопасное шифрованное соединение Cloudflare с вашим сервером Nginx с использованием имеющегося сертификата Let's Encrypt от Certbot.

---

## Действия пользователя: Настройка входящей почты в Resend

1. **Добавление `mail.devsense.work` в Resend**:
   - В панели Resend перейдите в **Domains -> Add Domain** и добавьте поддомен `mail.devsense.work`.
   - Пропишите выданные SPF, DKIM и MX записи в панели DNS Cloudflare (Resend предоставит MX записи для входящей маршрутизации, указывающие на `inbound-smtp.us-east-1.amazonaws.com`).
2. **Настройка Webhook**:
   - В панели Resend перейдите в **Webhooks -> Add Webhook**.
   - Укажите URL обработчика: `https://devsense.work/api/webhooks/resend-inbound`.
   - Выберите событие `email.received`.

---

## Планируемые изменения в коде

### 1. Модель данных и миграция базы данных
#### [NEW] [create_support_tickets_table.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/database/migrations/2026_06_03_000000_create_support_tickets_table.php)
- Создание таблицы `support_tickets`:
  - `sender_email` (string)
  - `sender_name` (string, nullable)
  - `subject` (string)
  - `message` (text)
  - `type` (string: complaint / жалоба, suggestion / предложение, general / общее)
  - `status` (string: open / открыт, answered / отвечен)
  - `reply_message` (text, nullable)
  - `replied_at` (timestamp, nullable)

#### [NEW] [SupportTicket.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Models/SupportTicket.php)
- Определение свойств модели, заполняемых полей (`fillable`) и скоупов для открытых/отвеченных тикетов.

### 2. Обработчик входящего вебхука
#### [NEW] [ResendInboundController.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Http/Controllers/Api/Webhook/ResendInboundController.php)
- Парсинг заголовка `data.from` для извлечения имени и email отправителя.
- Автоматическая категоризация темы и тела письма на типы (`complaint`, `suggestion`, `general`).
- Запись обращения в таблицу `support_tickets`.

#### [MODIFY] [api.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/routes/api.php)
- Регистрация роута `/webhooks/resend-inbound` в обход CSRF и авторизации.

### 3. Исходящее письмо с ответом поддержки
#### [NEW] [SupportTicketReplyMail.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Mail/SupportTicketReplyMail.php)
- Класс Mailable, принимающий объект тикета и форматирующий ответ администратора.

#### [NEW] [reply.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/emails/support-ticket-reply.blade.php)
- Стильный адаптивный шаблон письма (Markdown/HTML) для отправки ответов пользователям.

### 4. Управление обращениями в админ-панели
#### [NEW] [AdminTicketsController.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Http/Controllers/Admin/AdminTicketsController.php)
- Метод `index()`: листинг обращений с вкладками (Все, Открытые, Отвеченные).
- Метод `show(SupportTicket $ticket)`: просмотр обращения и форма ответа.
- Метод `reply(Request $request, SupportTicket $ticket)`: отправка письма с ответом через Resend и смена статуса на `answered`.

#### [NEW] [index.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/admin/tickets/index.blade.php)
- Отображение списка тикетов с цветными бейджами статуса и типа.

#### [NEW] [show.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/admin/tickets/show.blade.php)
- Просмотр деталей тикета и форма отправки ответа.

#### [MODIFY] [web.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/routes/web.php)
- Регистрация маршрутов тикетов внутри локализованной группы админки.
- Добавление ссылки на раздел «Поддержка» в боковое меню админ-панели.

#### [MODIFY] [.env.example](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/.env.example)
- Обновление дефолтного значения `MAIL_FROM_ADDRESS` на `noreply@mail.devsense.work`.

## План проверки (Верификация)

### Автоматические тесты
- Создание теста `Tests\Feature\SupportTicketsTest.php` для проверки:
  1. Корректности парсинга и сохранения тикета при входящем POST-запросе вебхука.
  2. Доступности вебхука без авторизации.
  3. Успешного отображения страниц листинга и просмотра тикета в админке.
  4. Обновления статуса тикета и отправки исходящего письма при ответе администратора.
- Запуск тестов: `wsl ./vendor/bin/sail test`.
