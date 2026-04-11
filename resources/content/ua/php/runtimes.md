---
title: "PHP на сервері: FPM, Swoole, воркери та event loop | DevSense"
description: "Як PHP виконується за nginx чи Apache: модель PHP-FPM, довгоживучі сервери (Swoole, RoadRunner, FrankenPHP) та асинхронний I/O у стилі ReactPHP/AMPHP — плюси й мінуси, рецепти та боротьба з витоками пам’яті."
---

# PHP на сервері: FPM, Swoole, воркери та event loop

У багатьох підручниках досі фігурує «закинув `index.php` на хостинг», тоді як у продакшені PHP — це майже завжди **менеджер процесів + вебсервер спереду**. Тут розведено чотири підходи, які часто змішують:

1. **Класичний запит/відповідь** — **PHP-FPM** (або `mod_php`): **короткий життєвий цикл** одного HTTP‑запиту на воркер.
2. **Довгоживучі сервери застосунків** — **Swoole/OpenSwoole**, **RoadRunner**, **FrankenPHP (worker)**: той самий PHP‑воркер обробляє **багато** запитів; спільна пам’ять і статичні кеші стають реальною проблемою.
3. **Бібліотеки з event loop** — **ReactPHP**, **AMPHP**, **Revolt**: кооперативна багатозадачність; добре для I/O, небезпечно з блокуючими викликами.
4. **CLI / cron** — `php` для скриптів, черг, міграцій — не веб‑модель, але той самий мова з іншими обмеженнями.

Жоден із варіантів не є «новим PHP» — це **різні контракти хостингу**. Обирайте за формою трафіку, досвідом команди та готовністю до експлуатації.

## Зміст

* [PHP-FPM + nginx (або Apache як проксі)](#php-fpm)
* [Apache `mod_php` (вбудований)](#mod-php)
* [CLI PHP (cron, воркери, Artisan)](#cli-php)
* [Swoole / OpenSwoole / Laravel Octane](#swoole)
* [RoadRunner](#roadrunner)
* [FrankenPHP](#frankenphp)
* [Event loop: ReactPHP, AMPHP, Revolt](#event-loop)
* [Порівняння: коли що брати](#comparison)
* [Витоки пам’яті: спільний чеклист](#memory-leaks)

---

<a id="php-fpm"></a>
## PHP-FPM + nginx (або Apache як reverse proxy)

### Що відбувається

1. nginx завершує TLS і віддає статику.
2. Для `*.php` запит передається в **PHP-FPM** через FastCGI (Unix‑сокет або TCP).
3. FPM **видає воркер** з пулу. Він виконує bootstrap (`public/index.php` у Laravel), відправляє відповідь і повертається в пул (або завершується після N запитів — див. `pm.max_requests`).

На кожен запит не варто розраховувати, що глобальні змінні «переживуть» наступний HTTP‑запит (навіть якщо Opcache тримає байткод теплим).

### Плюси

* **Перевірена зв’язка** з Laravel, Symfony, WordPress тощо.
* **Зрозуміла модель**: запит увійшов — відповідь вийшла; пам’ять звільняється при перезапуску воркера.
* **Легке горизонтальне масштабування**: більше FPM‑воркерів і серверів за балансувальником.
* Менше сюрпризів із типовими Composer‑пакетами.

### Мінуси

* **Вартість bootstrap** на кожен запит (зменшується Opcache, preload у налаштованих середовищах).
* **Паралелізм = кількість воркерів**; під навантаженням черга росте в backlog FPM — потрібен тюнінг `pm.*`.
* Не найкращий варіант для **мільйонів довгих WebSocket** на одній машині без додаткового шару.

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

Пул `/etc/php/8.5/fpm/pool.d/www.conf` (підлаштуйте під RAM):

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

### Пам’ять (FPM)

* **`pm.max_requests`** — перезапуск воркера після N запитів; недорога страховка від дрібних витоків у розширеннях.
* Якщо RSS воркерів росте без обмежень — профілюйте **код** і розширення; FPM лише приховує симптоми.

---

<a id="mod-php"></a>
## Apache `mod_php` (вбудований)

Apache запускає PHP **у своїх процесах/потоках** (`mod_php`). Здебільшого це теж **межі запиту**, але архітектура процесів відрізняється від FPM.

### Плюси

* Просто на **односерверних** сетапах; спадщина shared hosting.

### Мінуси

* **Життєвий цикл PHP прив’язаний до воркерів Apache** — тюнінг і ізоляція відрізняються від nginx + FPM.
* У сучасних Laravel‑деплоях рідше, ніж **FPM + nginx**.

**Коли брати:** легасі або свідомий вибір команди; інакше зазвичай **FPM + nginx**.

---

<a id="cli-php"></a>
## CLI PHP (cron, черги, Artisan)

`php artisan ...`, `php bin/console ...`, воркери черг, планувальник — **без HTTP‑фасаду**.

### Плюси

* Ідеально для **пакетної обробки**, **черг**, **переіндексації**, **імпортів**.

### Мінуси

* Не замінює веб‑SAPI; інші таймаути, немає семантики nginx‑буферів «на запит».

### Рецепт

```bash
cd /var/www/app
php artisan schedule:work   # зазвичай dev; у проді часто cron -> artisan schedule:run
php artisan queue:work redis --sleep=1 --tries=3
```

**Пам’ять:** довгоживучі воркери черг поводяться як **малі сервери** — застосуйте [чеклист для довгоживучих процесів](#memory-leaks).

---

<a id="swoole"></a>
## Swoole / OpenSwoole / Laravel Octane

**Swoole** (і форк **OpenSwoole**) — **довгоживучий** сервер: воркери живуть довго, обробляють багато запитів і можуть використовувати **корутини** для конкурентного I/O.

**Laravel Octane** може керувати Swoole/RoadRunner/FrankenPHP — та сама ідея: **завантажити фреймворк один раз**, обслужити багато запитів.

### Плюси

* **Висока пропускна здатність** для I/O‑bound коду, який «співпрацює» з моделлю.
* **WebSocket**, таймери, асинхронні примітиви (за наявності корутинно‑сумісних API).

### Мінуси

* **Глобальний стан переживає запити** — `static`, синглтони, кеші можуть «перетікати» між користувачами.
* **Не всі пакети Composer безпечні** (прихований блокуючий I/O, глобалі, припущення щодо `$_SESSION`).
* Складніші **деплой** і **налагодження** — після релізу потрібен **reload** воркерів.

### Рецепт (ілюстративний HTTP‑сервер)

> У проді зазвичай Octane або інтеграція у фреймворк; тут показано *форму* Swoole:

```bash
pecl install swoole   # або пакет php8.5-swoole у дистрибутиві
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
php artisan octane:install   # оберіть swoole/roadrunner/frankenphp
php artisan octane:start
```

### Пам’ять / витоки

* Налаштуйте **перезапуск воркерів** (див. актуальні опції Octane/Swoole для вашої версії).
* Не зберігайте **дані конкретного користувача** в статичних полях.
* Після деплою — **graceful restart** (systemd, `octane:reload` тощо).

---

<a id="roadrunner"></a>
## RoadRunner

**RoadRunner** — **Go**‑бінарник, який тримає **PHP‑воркери** живими; зв’язок через **goridge** (часто разом із Octane або пакетами Spiral).

### Плюси

* Сильна історія **нагляду за воркерами**; Go‑шар обслуговує HTTP, gRPC, черги тощо.
* Чітке розділення **воркерів застосунку** і крайніх протоколів.

### Мінуси

* Додатковий компонент у релізі (RR + конфіг).
* Ті самі обмеження **персистентного стану**, що й у Swoole.

### Рецепт

Завантажте бінарник із [релізів RoadRunner](https://github.com/roadrunner-server/roadrunner/releases), додайте `.rr.yaml` і запускайте:

```bash
./rr serve -c .rr.yaml
```

Команда воркера задається в конфігу (часто через Octane або згенерований worker).

---

<a id="frankenphp"></a>
## FrankenPHP

**FrankenPHP** — сервер застосунків на **Caddy** з **worker mode** (довгий PHP на багато запитів) і сучасними шляхами деплою HTTP/3.

### Плюси

* **Один бінарник** разом із Caddy; цікаво для edge і worker‑режиму.

### Мінуси

* Новіша екосистема — перевірте сумісність розширень і матрицю Laravel/Octane для вашої версії.

Див. офіційні гайди FrankenPHP та варіант установки через Octane.

---

<a id="event-loop"></a>
## Event loop: ReactPHP, AMPHP, Revolt

Бібліотеки на кшталт **ReactPHP** або **AMPHP** (на **Revolt** / `amphp/amp`) реалізують **однопотоковий event loop** і **неблокуючий I/O**, якщо ви викликаєте їхні API.

### Плюси

* Чудово для **I/O‑bound** агентів: багато сокетів, HTTP‑клієнти, DNS, таймери.
* Зручно для **власних протоколів**, проксі, інтеграційних «мостів».

### Мінуси

* **Будь-який блокуючий виклик** (`PDO::query` до віддаленої БД типовим драйвером, `sleep()`, `file_get_contents('http://...')`) **зупиняє цикл** для всіх.
* Потрібні **async‑клієнти** (`amphp/http-client`, адаптери ReactPHP) або винесення блокуючої роботи в **пул потоків/дочірні процеси** (складніше).

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
    $response = $future->await();
    echo $response->getStatus(), "\n";
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
## Порівняння: коли що брати

| Середовище | Добре підходить | Зазвичай ні |
|------------|-----------------|-------------|
| **PHP-FPM** | Типові сайти та API на Laravel/Symfony | Мільйони дешевих duplex‑з’єднань на одній ноді без додаткового шару |
| **Swoole / Octane** | Високий QPS, WebSocket, корутинний I/O | Команда не готова до персистентного стану; багато блокуючих бібліотек |
| **RoadRunner** | Нагляд за воркерами + кілька протоколів на краю | Немає ресурсу на ще один бінарник у деплої |
| **FrankenPHP** | Caddy‑центричні деплої, експерименти з worker | Потрібен максимально консервативний стек |
| **ReactPHP / AMPHP** | Власні мережеві сервіси, async‑клей | Класичний CRUD із блокуючим стеком фреймворка |

---

<a id="memory-leaks"></a>
## Витоки пам’яті: спільний чеклист (особливо для довгоживучого PHP)

1. **Статичні властивості й синглтони** — лише **конфігурація**, ніколи **дані запиту**.
2. **Глобальні кеші без меж** — LRU з обмеженням або Redis/Memcached замість необмежених масивів у PHP.
3. **Замикання**, що утримують великі графи об’єктів.
4. **Таймери / підписки** — скасовуйте періодичні таймери; знімайте слухачі при завершенні.
5. **Результати з БД** — читайте порціями; не накопичуйте величезні масиви в пам’яті.
6. **Opcache** не лікує витоки — **перезапускайте воркери** (`pm.max_requests`, reload Octane), щоб зменшити дрейф на рівні розширень.

**Спостереження:**

```bash
# FPM: стежте за RSS воркерів під навантажувальним тестом
ps aux | grep php-fpm
```

**У коді:**

```php
<?php
echo memory_get_usage(true), " bytes\n";
```

### Додатково

* [Конфігурація PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php)
* [Laravel Octane](https://laravel.com/docs/octane)
* [RoadRunner](https://roadrunner.dev/)
* [FrankenPHP](https://frankenphp.dev/)
* [AMPHP](https://amphp.org/)
* [ReactPHP](https://reactphp.org/)
