---
title: "Laravel Sail: WSL2, права, портове, пресборка, OPcache и Vite — диагностика | DevSense"
description: "Често срещани проблеми с Laravel Sail: синхронизация на файлове в WSL2, UID/GID и storage, конфликти на FORWARD_* портове, остарели Docker слоеве, OPcache и Xdebug при разработка, npm/Vite на хоста срещу контейнер, безопасно нулиране на томове."
---

# Sail: диагностика и производителност

Тук са събрани **чести триения с Sail**, които не са „бъгове на Laravel“: файлова система, мрежата на Compose, кеш на образи и PHP разширения в контейнери. Четете заедно с [Бази данни и услуги](sail-databases#networking), [Опашки](sail-queues#queue-work) и [Среди и деплой](sail-env-deploy#env-files).

**Навигация:** [Всички инструменти](../) · [Sail](sail#what-sail-is) · [БД](sail-databases#networking) · [Опашки](sail-queues#connections) · [Env](sail-env-deploy#forward-ports)

## Съдържание

* [WSL2, Docker Desktop и синхронизация на файлове](#wsl-filesync)
* [Права, `storage` и `vendor`](#permissions)
* [Портът е зает / `FORWARD_*`](#ports)
* [„На хоста работи, в Sail — не“](#host-vs-container)
* [Остарял код: пресборка и слоеве](#rebuild)
* [OPcache и автозареждане в dev](#opcache)
* [Xdebug забавя](#xdebug)
* [Vite, npm, Node извън и в Sail](#frontend)
* [Логове и бърза диагностика](#logs)
* [Кога да нулирате томове (загуба на данни)](#volumes)

---

<a id="wsl-filesync"></a>
## WSL2, Docker Desktop и синхронизация на файлове

На **Windows + WSL2** монтирането от `/mnt/c/...` в Linux контейнери често е **бавно** и чупи watcher-и (Vite, polling на файлове). Дръжте проекта **във файловата система на WSL** (напр. `~/projects/...`) и пускайте Sail оттам.

Ако редактор на Windows пипа файлове през границата към WSL, понякога се появяват **права или времеви печати** — изберете една страна (всички редакции в WSL или ясно правило за Windows) и го спазвайте.

---

<a id="permissions"></a>
## Права, `storage` и `vendor`

Laravel изисква **`storage/`** и **`bootstrap/cache/`** да са записваеми за PHP потребителя в контейнера **`laravel.test`**. На Linux хост **`WWWUSER`** / **`WWWGROUP`** в `.env` (вижте документацията на Sail) намаляват файлове, собственост на root, след `sail artisan`.

Bind mount-ът отразява промените и в двете посоки — ако логовете не се пишат, оправете права **вътре в контейнера**, не само на копието на хоста.

```bash
sail exec laravel.test ls -la storage bootstrap/cache
```

---

<a id="ports"></a>
## Портът е зает / `FORWARD_*`

Sail публикува MySQL, Redis, HTTP на приложението и др. към **localhost**. Втори локален MySQL или втори Sail проект често води до **`address already in use`**.

Задайте различни стойности в `.env` за всеки проект:

```dotenv
FORWARD_DB_PORT=3307
APP_PORT=8081
FORWARD_REDIS_PORT=6380
```

След това `sail down && sail up -d`. **Вътрешните** портове в мрежата на Compose (3306, 6379) остават същите; променя се само **картирането към хоста**. Повече контекст: [Среди и деплой](sail-env-deploy#forward-ports).

---

<a id="host-vs-container"></a>
## „На хоста работи, в Sail — не“

**`php` / `composer` на macOS или Windows** ползват PHP на **хоста**; **`sail artisan`** ползва PHP и разширенията в **контейнера**. Липсващи **`pdo_pgsql`**, **`redis`**, **`mongodb`** в образа са честа причина — коригирайте публикувания **Dockerfile** и направете **`sail build --no-cache`**.

В `.env` хостовете към базата трябва да са **имена на услуги** от Compose (`pgsql`, `redis`), не `127.0.0.1`, защото приложението работи **вътре** в `laravel.test`. Вижте [Бази данни и услуги](sail-databases#networking).

---

<a id="rebuild"></a>
## Остарял код: пресборка и слоеве

След промени в **Dockerfile** (`pecl install`, `apt`, смяна на базов образ) обикновеният `sail up` може да ползва **кеширани слоеве**. Полезно:

```bash
sail build --no-cache
sail up -d
```

След нов **`composer.lock`** пуснете `sail composer install`, за да съответства **`vendor/`** на версията PHP в контейнера.

Дълго живеещите **queue workers** държат bootstrap в памет — след промени в код изпълнете `sail artisan queue:restart` ([Опашки](sail-queues#restart)).

---

<a id="opcache"></a>
## OPcache и автозареждане в dev

В продукционни образи **OPcache** често е с агресивни настройки. Ако промените в код не личат, проверете **`opcache.revalidate_freq`** / **`validate_timestamps`** в **`php.ini`** в контейнера (Sail понякога публикува override-и). За локална итерация валидирането по timestamp намалява ефекта „виждам стар код“.

Оптимизираният autoload на Composer (`--optimize-autoloader`) в dev е по избор; ако след PSR-4 промени липсват класове, пуснете `sail composer dump-autoload`.

---

<a id="xdebug"></a>
## Xdebug забавя

Xdebug **винаги** добавя режийни разходи. Изключвайте го, когато не дебъгвате активно (Sail поддържа превключване според версията и env). По-добре кратки сесии с breakpoints, отколкото Xdebug цял ден.

---

<a id="frontend"></a>
## Vite, npm, Node извън и в Sail

Екипите често разделят така:

* **`npm run dev` на хоста**, когато Vite слуша порт, който отваряте в браузъра.
* Или **`sail npm` / Node услуга**, когато всичко трябва да е в Compose.

Смесването е окей, ако **`APP_URL`**, **HMR host** и **прокси портовете** съвпадат. Несъответствията обикновено показват **провал на websocket/HMR** или **404 на `@vite`** — подравнете портовете според [Среди и деплой](sail-env-deploy#app-url).

---

<a id="logs"></a>
## Логове и бърза диагностика

```bash
sail logs -f laravel.test
docker compose ps
sail exec laravel.test php -v
sail exec laravel.test php -m
```

За връзка към БД от приложението: например `sail exec laravel.test php artisan migrate:status` или кратък тест през `tinker`. За Redis: `sail exec redis redis-cli ping`.

---

<a id="volumes"></a>
## Кога да нулирате томове (загуба на данни)

**`sail down -v`** премахва **именуваните томове** — **локалните данни** за MySQL/Redis за този проект изчезват. Ползвайте при подозрение за **повреден том** или след големи ъпгрейди на образи, не като ежедневен навик.

За **един том**: `docker volume ls` и `docker volume rm <име>`, за да не пипнете други проекти. Детайли: [Бази данни и услуги](sail-databases#volumes).

---

## Вижте също

* [Sail — пълен гайд](sail#what-sail-is)  
* [Бази данни и услуги](sail-databases#networking)  
* [Опашки](sail-queues#queue-work)  
* [Среди и деплой](sail-env-deploy#env-files)  

[← Всички инструменти](../)
