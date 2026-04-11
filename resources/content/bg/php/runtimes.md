---
title: "PHP на сървъра: FPM, Swoole, workers и event loop | DevSense"
description: "Как PHP се изпълнява зад nginx или Apache: моделът PHP-FPM, дългоживеещи сървъри (Swoole, RoadRunner, FrankenPHP) и асинхронен I/O в стил ReactPHP/AMPHP — плюсове, минуси, рецепти и течове на памет."
---

# PHP на сървъра: FPM, Swoole, workers и event loop

В учебниците често остава „качи `index.php`“, а в production PHP почти винаги е **мениджър на процеси + уеб сървър отпред**. Тук са разделени четири идеи, които обикновено се смесват:

1. **Класическа заявка/отговор** — **PHP-FPM** (или `mod_php`): **кратък живот** на един HTTP заявка върху работник.
2. **Дългоживеещи приложения** — **Swoole/OpenSwoole**, **RoadRunner**, **FrankenPHP (worker)**: същият PHP работник обслужва **много** заявки; споделената памет и статичните кешове стават реален проблем.
3. **Библиотеки с event loop** — **ReactPHP**, **AMPHP**, **Revolt**: кооперативна многозадачност; отлично за I/O, опасно при блокиращи извиквания.
4. **CLI / cron** — `php` за скриптове, опашки, миграции — не е уеб модел, но същият език с други ограничения.

Нито един вариант не е „новият PHP“ — това са **различни договори за хостинг**. Изборът зависи от трафика, екипа и толеранса към операционна сложност.

## Съдържание

* [PHP-FPM + nginx (или Apache като прокси)](#php-fpm)
* [Apache `mod_php` (вграден)](#mod-php)
* [CLI PHP (cron, workers, Artisan)](#cli-php)
* [Swoole / OpenSwoole / Laravel Octane](#swoole)
* [RoadRunner](#roadrunner)
* [FrankenPHP](#frankenphp)
* [Event loop: ReactPHP, AMPHP, Revolt](#event-loop)
* [Сравнение: кога какво](#comparison)
* [Течове на памет: общ чеклист](#memory-leaks)

---

<a id="php-fpm"></a>
## PHP-FPM + nginx (или Apache като reverse proxy)

### Какво се случва

1. nginx прекъсва TLS и обслужва статика.
2. За `*.php` заявката отива към **PHP-FPM** през FastCGI (Unix сокет или TCP).
3. FPM **взима работник** от пула. Той изпълнява bootstrap (`public/index.php` в Laravel), връща отговора и се връща в пула (или приключва след N заявки — вижте `pm.max_requests`).

Не разчитайте глобалните променливи да „преживеят“ следващата HTTP заявка (дори Opcache да държи байткода топъл).

### Плюсове

* **Изпитана комбинация** с Laravel, Symfony, WordPress и др.
* **Ясен модел**: заявка влезе — отговор излезе; паметта се освобождава при рециклиране на работника.
* **Лесно хоризонтално мащабиране**: повече FPM работници и сървъри зад балансьор.
* По-малко изненади с типични Composer пакети.

### Минуси

* **Цена за bootstrap** на всяка заявка (намалява се с Opcache, preload при настройка).
* **Паралелизмът = броят работници**; под натоварване опашката расте в backlog на FPM — настройвайте `pm.*`.
* Не е идеално за **милиони дълги WebSocket** върху една машина без допълнителен слой.

### Рецепт (Ubuntu)

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

Пул `/etc/php/8.5/fpm/pool.d/www.conf` (настройте според RAM):

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

### Памет (FPM)

* **`pm.max_requests`** — рестартира работник след N заявки; евтина защита срещу малки течове в разширения.
* Ако RSS на работниците расте безкрайно — профилирайте **кода** и разширенията; FPM само маскира симптоми.

---

<a id="mod-php"></a>
## Apache `mod_php` (вграден)

Apache изпълнява PHP **в своите процеси/нишки** (`mod_php`). Отново е предимно **обхват на заявка**, но архитектурата се различава от FPM.

### Плюсове

* Просто при **едносървърни** конфигурации; наследство от споделен хостинг.

### Минуси

* **Жизненият цикъл на PHP е вързан за работниците на Apache** — настройката се различава от nginx + FPM.
* По-рядко в съвременни Laravel деплойменти.

**Кога:** легаси или съзнателен избор; иначе обикновено **FPM + nginx**.

---

<a id="cli-php"></a>
## CLI PHP (cron, опашки, Artisan)

`php artisan ...`, `php bin/console ...`, консуматори на опашки, планировчик — **без HTTP вход**.

### Плюсове

* Идеално за **пакетна обработка**, **опашки**, **преиндексация**, **импорти**.

### Минуси

* Не замества уеб SAPI; различни таймаути, няма nginx буфери „на заявка“.

### Рецепт

```bash
cd /var/www/app
php artisan schedule:work   # често dev; в prod обикновено cron -> artisan schedule:run
php artisan queue:work redis --sleep=1 --tries=3
```

**Памет:** дълго живеещите консуматори на опашки са като **мини сървъри** — приложете [чеклиста](#memory-leaks).

---

<a id="swoole"></a>
## Swoole / OpenSwoole / Laravel Octane

**Swoole** (и общностният **OpenSwoole**) — **дългоживеещ** сървър: работниците живеят дълго, обработват много заявки и могат да ползват **корутини** за конкурентен I/O.

**Laravel Octane** може да управлява Swoole/RoadRunner/FrankenPHP — същата идея: **заредете фреймуърка веднъж**, обслужете много заявки.

### Плюсове

* **Висок throughput** за I/O-bound код, който „сътрудничи“ на модела.
* **WebSocket**, таймери, асинхронни примитиви (с корутинно-съвместими API).

### Минуси

* **Глобалното състояние преживява заявки** — `static`, синглтони, кешове могат да „текат“ между потребители.
* **Не всички Composer пакети са безопасни** (скрит блокиращ I/O, глобали, предположения за `$_SESSION`).
* По-труден **деплой** и **дебъг** — след релиз трябва **reload** на работниците.

### Рецепт (илюстративен HTTP сървър)

> В production обикновено Octane или интеграция във фреймуърка; тук е *формата* на Swoole:

```bash
pecl install swoole   # или пакет php8.5-swoole в дистрибуцията
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
php artisan octane:install   # изберете swoole/roadrunner/frankenphp
php artisan octane:start
```

### Памет / течове

* Настройте **рециклиране на работници** (вижте текущите опции на Octane/Swoole за вашата версия).
* Не съхранявайте **данни за конкретен потребител** в статични полета.
* След деплой — **graceful restart** (systemd, `octane:reload` и т.н.).

---

<a id="roadrunner"></a>
## RoadRunner

**RoadRunner** е **Go** бинарник, който държи **PHP работниците** живи; комуникацията е през **goridge** (често с Octane или пакети на Spiral).

### Плюсове

* Силна история за **надзор на работници**; Go слоят обслужва HTTP, gRPC, опашки и др.
* Ясно разделение между **работници на приложението** и крайните протоколи.

### Минуси

* Още един компонент в релиза (RR + конфиг).
* Същите предупреждения за **персистентно състояние** като при Swoole.

### Рецепт

Изтеглете бинарник от [релизите на RoadRunner](https://github.com/roadrunner-server/roadrunner/releases), добавете `.rr.yaml` и стартирайте:

```bash
./rr serve -c .rr.yaml
```

Командата на работника е в конфигурацията (често чрез Octane или генериран worker).

---

<a id="frankenphp"></a>
## FrankenPHP

**FrankenPHP** е сървър на приложения върху **Caddy** с **worker mode** (дълъг PHP за много заявки) и съвременни пътища за HTTP/3.

### Плюсове

* **Един бинарник** с Caddy; интересно за edge и worker режим.

### Минуси

* По-нова екосистема — проверете съвместимостта на разширенията и матрицата Laravel/Octane за вашата версия.

Вижте официалните ръководства на FrankenPHP и инсталацията през Octane.

---

<a id="event-loop"></a>
## Event loop: ReactPHP, AMPHP, Revolt

Библиотеки като **ReactPHP** или **AMPHP** (върху **Revolt** / `amphp/amp`) реализират **еднонишков event loop** и **неблокиращ I/O**, когато използвате техните API.

### Плюсове

* Отлично за **I/O-bound** агенти: много сокети, HTTP клиенти, DNS, таймери.
* Удобно за **персонализирани протоколи**, проксита, интеграционен „лепило“.

### Минуси

* **Всяко блокиращо извикване** (`PDO::query` към отдалечена БД с типичен драйвер, `sleep()`, `file_get_contents('http://...')`) **спира цикъла** за всички.
* Нужни са **async клиенти** или изнасяне на блокиращата работа в **пул от нишки/дъщерни процеси** (по-сложно).

### Рецепт (набросък AMPHP)

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

### Рецепт (набросък ReactPHP)

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
## Сравнение: кога какво

| Среда | Подходящо за | Обикновено не |
|--------|----------------|----------------|
| **PHP-FPM** | Типични Laravel/Symfony сайтове и API | Милиони евтини duplex връзки на един възел без допълнителен слой |
| **Swoole / Octane** | Висок QPS, WebSocket, корутинен I/O | Екипът не е готов за персистентно състояние; много блокиращи библиотеки |
| **RoadRunner** | Надзор на работници + много протоколи на ръба | Нямате ресурс за още един бинарник в деплоя |
| **FrankenPHP** | Caddy-центрични деплойменти, worker експерименти | Нужен е максимално консервативен стек |
| **ReactPHP / AMPHP** | Персонализирани мрежови услуги, async лепило | Класически CRUD с блокиращ стек на фреймуърка |

---

<a id="memory-leaks"></a>
## Течове на памет: общ чеклист (особено при дългоживеещ PHP)

1. **Статични свойства и синглтони** — само **конфигурация**, никога **данни от заявка**.
2. **Глобални кешове без граница** — LRU с таван или Redis/Memcached вместо неограничени PHP масиви.
3. **Затваряния**, държащи големи графи от обекти.
4. **Таймери / слушатели** — отменяйте периодични таймери; махайте слушатели при teardown.
5. **Резултати от БД** — четете на части; не трупайте огромни масиви в памет.
6. **Opcache** не лекува течове — **рециклирайте работници** (`pm.max_requests`, Octane reload), за да ограничите дрейф на ниво разширение.

**Наблюдение:**

```bash
# FPM: следете RSS на работниците под натоварване
ps aux | grep php-fpm
```

**В кода:**

```php
<?php
echo memory_get_usage(true), " bytes\n";
```

### Вижте също

* [API gateway, gRPC и RabbitMQ](../microservices/api-gateway#comparison) — периметър и междусервизен трафик отвъд само PHP-FPM.

### Още материали

* [Конфигурация на PHP-FPM](https://www.php.net/manual/en/install.fpm.configuration.php)
* [Laravel Octane](https://laravel.com/docs/octane)
* [RoadRunner](https://roadrunner.dev/)
* [FrankenPHP](https://frankenphp.dev/)
* [AMPHP](https://amphp.org/)
* [ReactPHP](https://reactphp.org/)
