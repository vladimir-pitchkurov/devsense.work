---
title: "PHP на сервере: FPM, Swoole, воркеры и event loop | DevSense"
description: "Как PHP выполняется за nginx или Apache: модель PHP-FPM, долгоживущие серверы (Swoole, RoadRunner, FrankenPHP) и асинхронный I/O в духе ReactPHP/AMPHP — компромиссы, рецепты и борьба с утечками памяти."
---

# PHP на сервере: FPM, Swoole, воркеры и event loop

В учебниках часто остаётся картинка «залил `index.php` на хостинг», а в продакшене PHP почти всегда — это **менеджер процессов + веб‑сервер**. Здесь разведены четыре идеи, которые обычно смешивают:

1. **Классический запрос/ответ** — **PHP-FPM** (или `mod_php`): **короткий запрос** на воркер, затем очистка или контролируемое переиспользование.
2. **Долгоживущие приложения** — **Swoole/OpenSwoole**, **RoadRunner**, **FrankenPHP (worker)** — один процесс PHP обслуживает **много** запросов; статические кеши и глобальное состояние становятся реальной проблемой.
3. **Библиотеки с event loop** — **ReactPHP**, **AMPHP**, **Revolt**: кооперативная многозадачность; хорошо для I/O, плохо с блокирующими вызовами.
4. **CLI** — `php` для cron, очередей, миграций — тот же язык, другие ограничения.

Это не «новый PHP», а **разные контракты хостинга**. Выбор зависит от профиля нагрузки, команды и готовности к эксплуатации.

## Содержание

* [PHP-FPM + nginx (или Apache как прокси)](#php-fpm)
* [Apache `mod_php`](#mod-php)
* [CLI PHP](#cli-php)
* [Swoole / OpenSwoole / Laravel Octane](#swoole)
* [RoadRunner](#roadrunner)
* [FrankenPHP](#frankenphp)
* [Event loop: ReactPHP, AMPHP, Revolt](#event-loop)
* [Сравнение: когда что уместно](#comparison)
* [Утечки памяти: общий чеклист](#memory-leaks)

---

<a id="php-fpm"></a>
## PHP-FPM + nginx (или Apache как reverse proxy)

### Как это устроено

1. nginx завершает TLS и отдаёт статику.
2. Для `*.php` запрос уходит в **PHP-FPM** по FastCGI (сокет или TCP).
3. FPM выдаёт **воркер из пула**. Он выполняет bootstrap (`public/index.php` в Laravel), отвечает и возвращается в пул (или завершается после N запросов — см. `pm.max_requests`).

На **один запрос** не стоит рассчитывать, что глобальные переменные «переживут» следующий HTTP‑запрос (даже если Opcache держит байткод тёплым).

### Плюсы

* **Проверенная связка** с Laravel, Symfony, WordPress.
* **Простая модель**: запрос пришёл — ответ ушёл; память освобождается при перезапуске воркера.
* **Горизонтальное масштабирование**: больше воркеров и серверов за балансировщиком.
* Меньше сюрпризов с типичными Composer‑пакетами.

### Минусы

* **Стоимость bootstrap на запрос** (смягчается Opcache, preload при тюнинге).
* **Параллелизм = число воркеров**; при перегрузе очередь растёт в FPM — важно настроить `pm.*`.
* Не лучший вариант для **миллионов долгих WebSocket** на одной машине без дополнительного слоя.

### Рецепт (стиль Ubuntu)

```bash
sudo apt update
sudo apt install php8.5-fpm
sudo systemctl enable --now php8.5-fpm
```

Фрагмент nginx:

```nginx
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

Пул `/etc/php/8.5/fpm/pool.d/www.conf` (подстройте под RAM):

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

```bash
sudo systemctl reload php8.5-fpm
```

### Память (FPM)

* **`pm.max_requests`** — периодический перезапуск воркера; дешёвая страховка от редких утечек в расширениях.
* Если RSS воркеров ползёт вверх — профилируйте **код** и расширения; FPM только смягчает симптомы.

---

<a id="mod-php"></a>
## Apache `mod_php`

PHP встроен в процессы/потоки Apache. По сути тоже **запрос/ответ**, но другая модель изоляции и тюнинга.

### Плюсы

* Просто на **одном сервере**, наследие shared hosting.

### Минусы

* Жизненный цикл PHP связан с воркерами Apache; меньше гибкости, чем у **nginx + FPM** в типичных Laravel‑деплоях.

**Когда уместно:** легаси или осознанный выбор команды; для новых Laravel‑проектов чаще выбирают **FPM + nginx**.

---

<a id="cli-php"></a>
## CLI PHP (cron, очереди, Artisan)

`php artisan ...`, консюмеры очередей, планировщик — **без HTTP**.

### Плюсы

* Идеально для **пакетов**, **импорта**, **переиндексации**.

### Минусы

* Не заменяет веб‑SAPI; другие таймауты и окружение.

### Рецепт

```bash
cd /var/www/app
php artisan queue:work redis --sleep=1 --tries=3
```

Долгоживущие воркеры очередей = **мини‑серверы**: применяйте [чеклист для долгоживущих процессов](#memory-leaks).

---

<a id="swoole"></a>
## Swoole / OpenSwoole / Laravel Octane

**Swoole** и **OpenSwoole** — встроенный **долгоживущий** сервер: воркеры живут долго, обрабатывают много запросов, могут использовать **корутины** для конкурентного I/O.

**Laravel Octane** может использовать Swoole/RoadRunner/FrankenPHP: **один раз загрузили фреймворк**, много запросов.

### Плюсы

* Высокий **throughput** для I/O‑bound при «согласующемся» коде.
* **WebSocket**, таймеры, асинхронные примитивы (при корутинно‑совместимых API).

### Минусы

* **Глобальное состояние переживает запросы** — `static`, синглтоны, кеши в памяти.
* Не все пакеты **безопасны** (скрытый блокирующий I/O, предположения про сессии).
* Сложнее **деплой** и **отладка** — после выката нужен **reload** воркеров.

### Рецепт (минимальный пример Swoole)

```bash
pecl install swoole   # либо пакет php8.5-swoole в дистрибутиве
```

```php
<?php
$http = new Swoole\Http\Server('127.0.0.1', 9501);
$http->on('request', function ($request, $response) {
    $response->header('Content-Type', 'text/plain; charset=utf-8');
    $response->end('ok');
});
$http->start();
```

### Octane (Laravel)

```bash
composer require laravel/octane
php artisan octane:install
php artisan octane:start
```

### Память

* Настройте **перезапуск воркеров** (см. актуальные опции Octane/Swoole для вашей версии).
* Не кладите **данные конкретного пользователя** в статические поля.
* После деплоя — **graceful reload** (systemd, `octane:reload` и т.д.).

---

<a id="roadrunner"></a>
## RoadRunner

**RoadRunner** — бинарник на **Go**, держит **PHP‑воркеры** живыми; связь через goridge (часто вместе с Octane или пакетами Spiral).

### Плюсы

* Сильная история **надзора за воркерами**; Go‑слой обслуживает HTTP, gRPC, очереди.

### Минусы

* Ещё один компонент в релизе (RR + конфиг).
* Те же риски **персистентного состояния**, что у Swoole.

### Рецепт

Скачайте бинарник с [релизов RoadRunner](https://github.com/roadrunner-server/roadrunner/releases), положите `.rr.yaml` и запускайте:

```bash
./rr serve -c .rr.yaml
```

Команда воркера задаётся в конфиге (часто через Octane или сгенерированный worker).

---

<a id="frankenphp"></a>
## FrankenPHP

**FrankenPHP** — сервер приложений на базе **Caddy** с режимом **worker** (долгий PHP на многие запросы) и современным HTTP‑стеком.

### Плюсы

* Удобный **single binary** сценарий с Caddy; интересен для edge и worker‑режима.

### Минусы

* Экосистема новее — проверяйте матрицу совместимости Laravel/Octane и расширений.

Следуйте официальным гайдам FrankenPHP и варианту установки через Octane.

---

<a id="event-loop"></a>
## Event loop: ReactPHP, AMPHP, Revolt

**ReactPHP** и **AMPHP** (на **Revolt** / `amphp/amp`) дают **однопоточный event loop** и неблокирующий I/O **если вы вызываете их API**.

### Плюсы

* Отлично для **I/O‑bound** задач: много сокетов, HTTP‑клиенты, DNS, таймеры.
* Удобно для **кастомных протоколов** и интеграционных «мостов».

### Минусы

* Любой **блокирующий** вызов (`sleep()`, синхронный PDO к удалённой БД, `file_get_contents('http://...')`) **стопорит весь цикл**.
* Нужны **async‑клиенты** или вынос блокирующей работы в **пул процессов/потоков**.

### Рецепт (набросок AMPHP)

```bash
composer require amphp/http-client revolt/event-loop
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Amp\async;

$client = HttpClientBuilder::buildDefault();
$futures = [];
foreach (['https://example.com', 'https://php.net'] as $url) {
    $futures[] = async(fn () => $client->request(new Request($url)));
}
foreach ($futures as $future) {
    echo $future->await()->getStatus(), "\n";
}
```

### Рецепт (набросок ReactPHP)

```bash
composer require react/event-loop react/http
```

```php
<?php
require __DIR__ . '/vendor/autoload.php';
$loop = React\EventLoop\Loop::get();
$loop->addPeriodicTimer(1.0, fn () => print "tick\n");
$loop->run();
```

---

<a id="comparison"></a>
## Сравнение: когда что уместно

| Среда | Хорошо подходит | Обычно не стоит |
|--------|-----------------|-----------------|
| **PHP-FPM** | Типичные Laravel/Symfony сайты и API | Огромное число дешёвых duplex‑соединений на одной ноде без доп. слоя |
| **Swoole / Octane** | Высокий QPS, WebSocket, корутинный I/O | Команда не готова к персистентному состоянию; много блокирующих библиотек |
| **RoadRunner** | Строгий надзор за воркерами, несколько протоколов | Нельзя тащить ещё один бинарник в деплой |
| **FrankenPHP** | Caddy‑центричный деплой, worker‑режим | Нужен максимально консервативный стек |
| **ReactPHP / AMPHP** | Свои сетевые сервисы, async‑клей | Классический CRUD с блокирующим стеком фреймворка |

---

<a id="memory-leaks"></a>
## Утечки памяти: общий чеклист

1. **Статика и синглтоны** — только конфигурация, не данные запроса.
2. **Кеши без лимита** — лучше Redis/Memcached или LRU в памяти с потолком.
3. **Замыкания**, удерживающие большие графы объектов.
4. **Таймеры и подписки** — отменяйте при завершении работы.
5. **Большие выборки из БД** — читайте порциями.
6. **Opcache** не лечит утечки — используйте **перезапуск воркеров** (`pm.max_requests`, reload Octane).

**Наблюдение:**

```bash
ps aux | grep php-fpm
```

**В коде:**

```php
<?php
echo memory_get_usage(true), " bytes\n";
```

### Смотрите также

* [API gateway, gRPC и RabbitMQ](../microservices/api-gateway#comparison) — периметр и межсервисный трафик поверх одной только модели PHP-FPM.

### Материалы

* [Конфигурация PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php)
* [Laravel Octane](https://laravel.com/docs/octane)
* [RoadRunner](https://roadrunner.dev/)
* [FrankenPHP](https://frankenphp.dev/)
* [AMPHP](https://amphp.org/)
* [ReactPHP](https://reactphp.org/)
