# Google Authentication (Login & Registration)

Описание реализации возможности входа и регистрации пользователей через учетную запись Google с использованием пакета **Laravel Socialite**.

## User Review Required

> [!IMPORTANT]
> **Конфигурация Google Client ID & Client Secret**:
> Для работы интеграции на сервере и локально необходимо прописать в файле `.env` переменные:
> * `GOOGLE_CLIENT_ID`
> * `GOOGLE_CLIENT_SECRET`
> * `GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"`
>
> На стороне Google Developer Console необходимо зарегистрировать Callback URL: `http://localhost:8282/auth/google/callback` (для локальной разработки) и `https://devsense.work/auth/google/callback` (для продакшна).

> [!NOTE]
> **Автоматическая синхронизация гостевого квиза**:
> Если гость прошел квиз, сохранил прогресс в сессии, а затем залогинился/зарегистрировался через Google, его результаты и ачивки автоматически привяжутся к созданному пользователю (как и в стандартном флоу авторизации).

## Open Questions

*Вопросов нет. План полностью соответствует существующей архитектуре аутентификации и локализации проекта.*

## Proposed Changes

### 1. Зависимости (Composer)
* Установка официального пакета `laravel/socialite` для интеграции с OAuth.

### 2. База данных
#### [NEW] [2026_06_08_203810_add_google_id_to_users_table.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/database/migrations/2026_06_08_203810_add_google_id_to_users_table.php)
* Добавление столбца `google_id` (string, nullable, unique) в таблицу `users`.
* Изменение столбца `password` на `nullable()`, так как у пользователей Google пароль изначально отсутствует.

### 3. Конфигурация
#### [MODIFY] [services.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/config/services.php)
* Добавление конфигурации для драйвера `google`:
  ```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
  ],
  ```

#### [MODIFY] [.env.example](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/.env.example)
* Добавление заготовок для переменных окружения Google OAuth.

### 4. Модели
#### [MODIFY] [User.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Models/User.php)
* Добавление `google_id` в список `Fillable` полей модели.

### 5. Контроллеры и маршрутизация
#### [NEW] [GoogleAuthController.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/app/Http/Controllers/Auth/GoogleAuthController.php)
* Создание контроллера с методами:
    - `redirectToGoogle`: сохранение текущей локали в сессию (`auth_locale`) и редирект на Google.
    - `handleGoogleCallback`: обработка коллбэка, поиск/создание пользователя, привязка `google_id` к существующим email, автоматический логин, синхронизация гостевого квиза и редирект в админку с восстановлением локали.

#### [MODIFY] [web.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/routes/web.php)
* Добавление нелокализованных маршрутов:
    - `Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle'])->name('auth.google');`
    - `Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');`

### 6. Локализация (Переводы)
#### [MODIFY] `lang/*/ui.php` (все 8 локалей: en, ru, ua, bg, de, fr, es, it)
* Добавление ключей `'or' => 'или'` (или соответствующий перевод) и `'google_btn' => 'Продолжить через Google'` для отображения кнопки.

### 7. Шаблоны и стили
#### [MODIFY] [login.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/auth/login.blade.php) & [register.blade.php](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/views/auth/register.blade.php)
* Добавление разделителя "или" и кнопки "Продолжить через Google" под формами.

#### [MODIFY] [_auth.scss](file:///wsl$/Ubuntu/home/ubuntu/Development/laravel-playground/resources/sass/blocks/_auth.scss)
* Стилизация разделителя `.auth-divider` и кнопки `.auth-google-btn` (включая иконку Google, отступы, hover-эффекты в темной/светлой теме).

---

## Verification Plan

### Automated Tests
* Создание нового функционального теста `tests/Feature/GoogleAuthTest.php` для проверки:
    - Маршрута редиректа на Google.
    - Успешного коллбэка для нового пользователя (регистрация).
    - Успешного коллбэка для существующего пользователя по email (привязка Google ID).
    - Успешного входа для пользователя с существующим `google_id`.
    - Синхронизации гостевого квиза после входа через Google.
* Запуск всех тестов проекта: `sail php artisan test`.

### Manual Verification
* Эмуляция/проверка редиректа на Google OAuth.
