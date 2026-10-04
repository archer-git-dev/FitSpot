# Fit Spot — инструкции для агента

## О проекте

Реализован модуль 1, блок А: закрытая панель тренера, авторизация, профиль,
недельный график, услуги и правила записи. Публичный виджет, бронирования,
Google Calendar и бот-администратор относятся к следующим блокам.
ТЗ: docs/specs/module-1-block-a.md; запуск: docs/README-block-a.md.

## Стек

- PHP 8.3+, Laravel 13, Composer.
- Vue 3, TypeScript 5.9, Inertia 3, Tailwind CSS 4, Vite 8; Node.js 24.
- Laravel Fortify и серверные сессии; Telegram Login RS256 через firebase/php-jwt.
- PostgreSQL 16 в Docker и `.env.example`; тестовая БД fitspot_test.
- Redis 7 и RabbitMQ 3 в Docker; пакет `laravel-queue-rabbitmq`.
- Nginx, PHP-FPM, Docker Compose, Supervisor для очередей и планировщика.
- PHPUnit 12, Laravel Pint, vue-tsc и Playwright для проверок.

Версии и зависимости уточняй по `composer.json`, `package.json` и lock-файлам.

## Структура

- `app/Modules/Scheduling/` — настройки тренера: Controller → Service → Repository, Form Requests, Policies и события после commit.
- `app/` — общие модели и авторизация; `app/Actions/Fortify/` — действия Fortify.
- `routes/web.php` — веб-маршруты; `routes/console.php` — консольные команды.
- `resources/views/`, `resources/css/`, `resources/js/` — интерфейс.
- `database/migrations/`, `factories/`, `seeders/` — схема БД и тестовые данные.
- `config/` — настройки Laravel; `bootstrap/app.php` — загрузка приложения.
- `tests/Feature/`, `tests/Unit/` — тесты.
- `docker/`, `docker-compose.yml` — окружение контейнеров.

## Ключевые команды

Выполняй команды из корня проекта. Сначала проверь доступность нужных инструментов.

```sh
# Зависимости
composer install
npm ci

# Первый запуск нового локального окружения (если .env ещё нет)
cp .env.example .env
php artisan key:generate
php artisan migrate

# Локальная разработка: сервер и Vite в отдельных терминалах
php artisan serve
npm run dev

# Проверки и сборка
docker compose exec app composer test  # только отдельная PostgreSQL fitspot_test
npm run typecheck
npm run test:browser
vendor/bin/pint app config database routes tests bootstrap/app.php bootstrap/providers.php
npm run build

# Диагностика
php artisan route:list
php artisan config:clear

# Docker
docker compose up -d --build
docker compose ps
docker compose logs --tail=100 app web queue
docker compose exec app php artisan migrate
```

В Docker PHP/Composer-команды выполняй через `docker compose exec app`.
Node.js/npm в текущем PHP-образе не установлены: сборку интерфейса выполняй
в окружении, где они доступны.

## Правила работы

- Общайся с пользователем по-русски; имена классов, методов и переменных — по-английски.
- Перед изменениями изучай связанный код и `git status`; сохраняй чужие изменения.
- Делай минимальные изменения в рамках задачи, соблюдай существующий стиль Laravel.
- Используй Eloquent и миграции для работы со схемой БД, стандартную валидацию Laravel
  для входных данных. Не добавляй абстракции без необходимости.
- Настройки окружения храни в `.env`, обращайся к ним через файлы `config/`.
  Новые переменные описывай в `.env.example` без секретов.
- Не публикуй секреты и содержимое `.env`; не редактируй `vendor/` и `node_modules/`.
- Добавляй зависимости только при необходимости для задачи, сохраняй lock-файлы.
- Для изменений поведения добавляй или обновляй подходящие тесты и запускай
  связанные проверки. После изменений PHP запускай Pint, после изменений интерфейса — сборку.
- Для изменений только документации тесты приложения не требуются.
- Не запускай `migrate:fresh`, удаление Docker volumes или другие операции,
  уничтожающие данные, без явного разрешения пользователя.
- В завершение кратко сообщай, что изменено, что проверено и какие ограничения остались.

## Нюансы текущего окружения

- Docker публикует сайт на `http://localhost:8080`; `artisan serve` обычно на порту 8000.
- Внутри Docker адреса сервисов: `db`, `redis`, `rabbitmq`; с хоста — опубликованные порты.
  Compose переопределяет внутренние адреса сервисов; существующий .env сохраняется.
- `.env.example`: PostgreSQL, Redis для кеша/сессий, RabbitMQ для очереди.
- Supervisor запускает два `queue:work rabbitmq --queue=mail,default` и `schedule:work`.
  Mailpit доступен на http://localhost:8025, письма идут через очередь mail.
- Тесты защищены от запуска на рабочей БД. Создание fitspot_test описано в docs/README-block-a.md.
- Telegram без TELEGRAM_CLIENT_ID недоступен; реальную интеграцию проверять на разрешённом домене.
- `composer setup` устанавливает зависимости, генерирует ключ и выполняет миграции
  с `--force`; используй его только для подходящего нового локального окружения.
- Laravel Boost установлен как dev-зависимость. MCP `laravel-boost` подключён
  в пользовательской конфигурации Codex к этому проекту; запуск: `php artisan boost:mcp`.
- Context7 используй для документации библиотек, Playwright MCP — для проверки
  интерфейса в браузере. Они зарегистрированы в пользовательской конфигурации Codex.

# ExecPlans

When writing complex features or significant refactors, use an ExecPlan (as described in PLANS.md) from design to implementation.
