# Fit Spot — инструкции для агента

## О проекте

Проект находится на стадии базового каркаса Laravel. Пока есть стандартная
главная страница и модель пользователя; прикладные модули ещё не реализованы.
Не предполагай наличие функций, которых нет в коде.

## Стек

- PHP 8.3+, Laravel 13, Composer.
- Blade, JavaScript, Tailwind CSS 4, Vite 8; зависимости интерфейса через npm.
- PostgreSQL 16 в Docker; SQLite в стандартном `.env.example`.
- Redis 7 и RabbitMQ 3 в Docker; пакет `laravel-queue-rabbitmq`.
- Nginx, PHP-FPM, Docker Compose, Supervisor для очередей и планировщика.
- PHPUnit 12 для тестов, Laravel Pint для форматирования PHP.

Версии и зависимости уточняй по `composer.json`, `package.json` и lock-файлам.

## Структура

- `app/` — модели, контроллеры и код приложения.
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
npm ci                         # если есть package-lock.json; иначе npm install

# Первый запуск нового локального окружения (если .env ещё нет)
cp .env.example .env
php artisan key:generate
php artisan migrate

# Локальная разработка: сервер и Vite в отдельных терминалах
php artisan serve
npm run dev

# Проверки и сборка
composer test
php artisan test --filter=ExampleTest
vendor/bin/pint --dirty
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
  Настройки `.env.example` пока не адаптированы к Docker.
- По умолчанию `.env.example` использует SQLite, а также БД для очередей, кеша и сессий.
  Наличие PostgreSQL и Redis в Compose само по себе не переключает Laravel на них.
- Supervisor запускает два обработчика `queue:work rabbitmq` и `schedule:work`.
  Подключение `rabbitmq` в `config/queue.php` пока отсутствует: перед использованием
  очереди нужно завершить её настройку.
- `composer setup` устанавливает зависимости, генерирует ключ и выполняет миграции
  с `--force`; используй его только для подходящего нового локального окружения.
- Laravel Boost установлен как dev-зависимость. MCP `laravel-boost` подключён
  в пользовательской конфигурации Codex к этому проекту; запуск: `php artisan boost:mcp`.
- Context7 используй для документации библиотек, Playwright MCP — для проверки
  интерфейса в браузере. Они зарегистрированы в пользовательской конфигурации Codex.

# ExecPlans

When writing complex features or significant refactors, use an ExecPlan (as described in PLANS.md) from design to implementation.
