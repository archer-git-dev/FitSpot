# Реализовать панель тренера FitSpot


Этот ExecPlan ведётся по корневому PLANS.md. Он является живым документом; Progress, Surprises & Discoveries, Decision Log и Outcomes & Retrospective обновляются во время работы.

## Purpose / Big Picture


Тренер регистрируется, подтверждает email и настраивает профиль, недельные рабочие интервалы, услуги и правила будущей записи. Блок А не создаёт бронирования, публичный виджет, календарные интеграции или платежи. Один пользователь владеет ровно одним пространством; чужие данные недоступны.

## Progress


- [x] (2026-10-04) Изучены AGENTS.md, PLANS.md и текущий Laravel-каркас.
- [x] (2026-10-04) Часть 1: зависимости, Vue/Inertia, PostgreSQL, RabbitMQ, Mailpit и миграции.
- [x] (2026-10-04) Часть 2: Fortify, подтверждение email, восстановление, Telegram и привязка.
- [x] (2026-10-04) Часть 3: профиль, фото, slug, часовой пояс и настройки доступа.
- [x] (2026-10-04) Часть 4: атомарное сохранение недели и общие правила записи.
- [x] (2026-10-04) Часть 5: услуги и обзор готовности.
- [x] (2026-10-04) Часть 6: PostgreSQL-тесты, браузер, очередь, нагрузка и инструкция.

- [ ] Внешняя приёмка: реальный Telegram Login на разрешённом домене и production SMTP после настройки окружения.

## Context and Orientation


На старте проект был каркасом Laravel 13 на PHP 8.3 без авторизации и Vue. Теперь routes/web.php содержит авторизацию и защищённые /app маршруты, ресурсы Vue находятся в resources/js/Pages, настройки расписания — в app/Modules/Scheduling. Docker содержит app/web/db/redis/rabbitmq/queue/mailpit; config/queue.php подключает RabbitMQ. На хосте PHP не имеет pdo_pgsql или GD; проверки выполняются PHP из Docker. В контейнере проект находится в /var/www, на хосте — /home/oem/projects/fit-spot. Node.js 24 доступен в /home/oem/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/bin; системный Node 18 несовместим с Vite 8.

## Plan of Work


Авторизация остаётся общей в app/Actions/Fortify, app/Services/Auth и app/Http/Controllers/Auth. Модуль настроек находится в app/Modules/Scheduling с Models, Repositories, Services, Http/Requests, Http/Controllers, Policies и Events. Inertia передаёт серверные свойства Vue-страницам без отдельного REST API. Добавить HandleInertiaRequests и FortifyServiceProvider. PostgreSQL хранит workspaces, telegram_identities, working_intervals, training_services, booking_rules; unique(owner_id), unique(slug), unique(telegram subject) и внешние ключи обеспечивают целостность. Интервалы хранят минуты 0..1440, деньги — копейки. Все изменения выполняются в транзакциях, события используют ShouldDispatchAfterCommit.

Часть 1 добавляет firebase/php-jwt, Fortify и Inertia; Vue, TypeScript и плагин Vite. Настроить Docker environment без перезаписи .env, Mailpit, очередь mail и тестовую PostgreSQL БД. Проверить сборку и применимость миграций.

Часть 2 использует Fortify для email и queued notifications. Telegram Login JS передаёт подписанный id_token; RS256 проверяется через кешируемый JWKS, iss, aud, exp, iat и одноразовый nonce. Новая identity создаётся только с новым подтверждаемым email, совпадение email не объединяет аккаунты. Pending signup действует 10 минут. Контролировать повторное использование nonce Redis add/lock. До email verification доступны только подтверждение/исправление email/выход. Смена подтверждённого email требует пароль либо свежую Telegram-проверку и письмо новому адресу; старый адрес сохраняется до принятия ссылки.

Часть 3 реализует профиль с валидацией slug, IANA timezone, телефона и GD-перекодированием JPEG/PNG/WebP до 1024. Фото читаются через авторизованный маршрут владельца, а не общий public storage. После успешной замены удалять старое фото; при ошибке новое удалять, прежнее сохранять.

Часть 4 принимает всю неделю (дни 1..7, максимум 8 интервалов). Проверяет начало < конец, отсутствие пересечений и 24:00 только в конце, сливает соседние интервалы. Repository заменяет неделю внутри транзакции с блокировкой пространства. Правила: buffers 0/10, lead 120, horizon 30, step 15, cancellation/reschedule 720 минут; диапазоны и сетка соответствуют ТЗ. Для согласованности lead < horizon*1440; применение календарного горизонта/DST к слотам остаётся блоку Б.

Часть 5 добавляет personal/split, in_person/online; capacity 1/2, duration 15..240 кратно 5, price 0..100000000 копеек. Только отключение, не удаление. Обзор готов, когда профиль, рабочий интервал и активная услуга существуют. Предупреждать о непомещающихся услугах без запрета сохранения.

Часть 6 добавляет изолированную тестовую БД fitspot_test; тесты не запускаются на рабочей БД. Демо-данные создаются отдельной идемпотентной командой только local/testing. Нагрузочный скрипт проверяет p95 при 20 клиентах, результаты включить ниже.

## Concrete Steps


Из корня проекта установить composer require laravel/fortify inertiajs/inertia-laravel firebase/php-jwt, npm install vue @inertiajs/vue3 и инструменты TypeScript/Vue/Playwright. После изменений выполнять vendor/bin/pint app config database routes tests bootstrap/app.php bootstrap/providers.php, npm run typecheck, npm run build. PHP-команды выполнять docker compose exec app php artisan ... . Для тестов использовать phpunit.postgres.xml с DB_DATABASE=fitspot_test и CACHE_STORE=array, QUEUE_CONNECTION=sync, SESSION_DRIVER=array. Поднять Mailpit командой docker compose up -d. Применять обычные additive migrations; migrate:fresh не использовать на пользовательских данных.

## Validation and Acceptance


php artisan test --configuration=phpunit.postgres.xml должен пройти. Проверить регистрацию и письмо, подтверждение, восстановление, идентичность Telegram и запрет чужих объектов, пересечение/атомарность недели, точную цену, статус услуги и rollback событий. Браузером пройти регистрацию, профиль, график, услуги, сохранение, перезагрузку, выход/вход на 360px. Проверить queue mail через RabbitMQ -> Mailpit. Telegram реальный smoke требует Client ID и разрешённый HTTPS origin; без них проверка честно остаётся внешним пунктом.

## Idempotence and Recovery


Зависимости и обычные миграции можно повторять. Не менять существующий .env и не удалять volumes. Перед production migration резервная копия обязательна; rollback откатывает только новую схему и может удалить новые данные, поэтому предпочтителен исправляющий migration. Тесты работают только на отдельной БД. Неподтверждённые/ошибочные Telegram-токены не создают пользователя. Сбой очереди письма не удаляет аккаунт; пользователю доступна повторная отправка после восстановления очереди.

## Interfaces and Dependencies


WorkspaceRepository получает пространство по текущему User; ScheduleRepository заменяет интервалы, TrainingServiceRepository читает и сохраняет только принадлежащие пространству услуги. Services вызывают repositories, controllers используют FormRequests и policies. TelegramTokenVerifier::verify(string token, string nonce): array возвращает проверенные sub/name claims. WorkspaceCreated, TrainerProfileUpdated, WeeklyScheduleUpdated, BookingRulesUpdated, ServiceCreated, ServiceUpdated, ServiceStatusChanged содержат event_id, workspace_id, object_id, occurred_at и отправляются после commit.

## Surprises & Discoveries


Хост PHP без PostgreSQL/GD; Docker уже запущен, поэтому используем его для проверок. Старый Node 18 заменяется доступным Node 24 только для команд сборки.

## Decision Log


2026-10-04: реализуем одним модульным монолитом; Telegram-проверка — единственное исключение из очередей для внешних вызовов. Email обязателен и подтверждается до входа в панель. Выбрано хранение фото вне public для изоляции тренеров. Правила буферов являются данными блока А; слот-алгоритм не реализуется.

## Artifacts and Notes


Результаты локальной проверки 2026-10-04:

    PHPUnit на PostgreSQL: 50 tests, 209 assertions, OK.
    Playwright Chromium: 1 passed (полный путь, 360 px, email через RabbitMQ/Mailpit).
    vue-tsc, Vite production build, Pint: успешно.
    composer audit: 0 vulnerabilities.
    Нагрузка: 20 клиентов, 200 запросов, 0 ошибок.
    read: p95 247 мс, максимум 274 мс.
    save: p95 237 мс, максимум 252 мс.
    save_with_redirect: p95 436 мс, максимум 451 мс.

Измерение выполнено scripts/benchmark-panel.mjs против http://localhost:8080 в локальном Docker: Nginx, PHP 8.3.35/FPM, PostgreSQL 16, Redis 7; без внешнего Telegram и фото. save измеряет ответ PUT 303; save_with_redirect дополнительно включает последующее чтение страницы. Браузерная проверка подтверждает, что реальные queued notifications проходят RabbitMQ и Mailpit. Полноценное руководство повторения находится в docs/README-block-a.md; оно не требует сброса рабочей БД.

## Outcomes & Retrospective


Блок А реализован: тренер проходит обязательное подтверждение email, сохраняет профиль, недельные интервалы, правила и услуги; другой тренер не может обращаться к его данным. Локальные PHP, браузерные и нагрузочные проверки выполнены. Код проверки подписей Telegram и привязки проверен контролируемыми RSA-токенами; реальный Telegram-вход и production SMTP остаются внешней приёмкой, поскольку Client ID, разрешённый домен и SMTP-провайдер отсутствуют. Публичная запись не реализована в соответствии с границами блока.

Изменение 2026-10-04: создан план перед реализацией, чтобы сохранить согласованные требования и порядок проверки.


Изменение 2026-10-04 (завершение): отмечены выполненные части и добавлены результаты проверок. Обнаружено, что окружение контейнера переопределяет PHPUnit env: тестовая конфигурация использует force=true и защиту имени БД до миграций. TypeScript ограничен веткой 5.9 для совместимости с vue-tsc. GD собран с JPEG/WebP. Inertia DevTools выключены, чтобы не записывать частные данные; это также сократило задержку ответов. Для изменяющих запросов используются явные 303-редиректы. Зависимость CommonMark обновлена для устранения обнаруженной composer audit уязвимости.
