# Развёртывание API

Инструкция без адресов и паролей — они лежат вне репозитория.

## Что понадобится на сервере

PHP 8.3 + FPM, nginx, MySQL, Composer, git. Node не нужен: это чистый JSON API,
фронт собирается отдельно.

## Первая установка

```bash
# 1. код
cd /var/www
git clone <repo> qr-menu && cd qr-menu
git config --global --add safe.directory /var/www/qr-menu
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader

# 2. база — своя, отдельным пользователем, не root
mysql -e "CREATE DATABASE IF NOT EXISTS qr_menu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
          CREATE USER IF NOT EXISTS 'qr_user'@'localhost' IDENTIFIED BY '<пароль>';
          GRANT ALL PRIVILEGES ON qr_menu.* TO 'qr_user'@'localhost';
          FLUSH PRIVILEGES;"

# 3. окружение
cp .env.example .env
php artisan key:generate
# заполнить .env — см. таблицу ниже
chmod 640 .env

# 4. схема
php artisan migrate --force
# Сидеры НЕ запускать: они ничего не создают, но привычка опасная.

# 5. nginx
cp deploy/nginx.conf /etc/nginx/sites-available/qr-menu
ln -s /etc/nginx/sites-available/qr-menu /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx

# 6. права и кеши
php artisan config:cache && php artisan route:cache
chown -R www-data:www-data /var/www/qr-menu
chmod -R 775 storage bootstrap/cache
systemctl reload php8.3-fpm
```

## Переменные окружения

| Переменная | Значение | Зачем |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Иначе стектрейс с путями и запросами уедет клиенту |
| `APP_URL` | адрес API | |
| `APP_TIMEZONE` | `Asia/Almaty` | Часы сервера могут идти по другой зоне |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | своя база | |
| `SESSION_DRIVER` | `database` | Схема наша, таблица `sessions` в миграциях есть |
| `CACHE_STORE` | `database` | Позже — Redis |
| `FRONTEND_URL` | origin фронта | Попадает в `allowed_origins` CORS |
| `SANCTUM_STATEFUL_DOMAINS` | хост фронта | Без него Sanctum не откроет сессию |
| `SESSION_DOMAIN` | `.<домен>` | Общий домен второго уровня для фронта и API |
| `SESSION_SECURE_COOKIE` | `true` на https | |

## ⚠️ Авторизация требует общего домена и HTTPS

Sanctum в SPA-режиме работает на **куках**. Пока фронт и API живут на разных
доменах — а тем более если API отдаётся по `http` — кабинет работать не будет,
и это не чинится настройками:

- браузер блокирует запросы с `https`-страницы на `http` (mixed content);
- кука на чужой домен требует `SameSite=None; Secure`, а её режет Safari
  и отключённые сторонние куки в Chrome.

**Публичное меню при этом работает** — оно без авторизации, и фронт запрашивает
его на своей стороне, а не из браузера гостя.

Рабочая схема:

```
<домен>       → фронт
api.<домен>   → этот API
SESSION_DOMAIN=.<домен>
```

Сертификат — Certbot, после того как домен смотрит на сервер.

## Обновление

```bash
cd /var/www/qr-menu && ./deploy.sh
```

Скрипт делает `git reset --hard origin/main` — правки, сделанные руками прямо
на сервере, будут потеряны. Это намеренно: сервер должен повторять репозиторий.

## Проверка после деплоя

```bash
# API отвечает
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8082/up

# публичное меню (404 без заведений — это нормально)
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8082/api/public/menu/test

# закрытый эндпоинт отвечает 401, а не 500
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8082/api/user

# соседние проекты живы
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8081/
```

## Грабли

- **Composer под root** требует `export COMPOSER_ALLOW_SUPERUSER=1`.
- **git под root** ругается на каталог `www-data`:
  `git config --global --add safe.directory /var/www/qr-menu`.
- **После деплоя нужен `systemctl reload php8.3-fpm`** — иначе opcache продолжит
  отдавать старый байткод. В `deploy.sh` это есть.
- **`view:cache` не нужен** — Blade-шаблонов в проекте нет.
- **Нет расширения PHP → `composer install` падает** и оставляет старый `vendor`.
  Проверять `php -m` перед добавлением пакетов.
