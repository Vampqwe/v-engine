# AUDIT.md — V-engine: открытые пункты

> **Дата:** 2026-11-20 · **Основание:** текущая ревизия проекта после исправлений `RL-10`, `OPS-05` и `OPS-08`.
> **Метод:** статическая сверка исходников; валидация выполнена командами, указанными в §10.
> **Содержание:** открытые и частично смягчённые пункты. Закрытые изменения и их проверки приведены в §2. Для латентных пунктов указано предусловие активации. Перечень тестов зафиксирован в §7 — при расхождении с фактом считать документ устаревшим.

**Формат карточки:** `Файл::метод → что происходит → следствие → фикс`. Номера строк не приводятся — искать по имени метода.

---

## 1. Как читать

| Правило | Значение |
|---|---|
| ID | Стабильный идентификатор для ветки/коммита/теста: `fix/RL-10-gc-starvation` |
| Группа A | Живые пункты: достижимы в текущем коде при штатной конфигурации |
| Группа D | Латентные: код-дефект есть, но **не проявляется**, пока не выполнено предусловие |
| Группа F | Мелкие подтверждённые defects и рассинхроны |
| Приоритет | По достижимости × радиусу поражения, не по категории |
| ⚠ VERIFY | Нельзя подтвердить по дампу; проверить до изменения |

---

## 2. Что проверено независимо

Проверки выполнены по исходникам текущей ревизии и результатам запуска тестов.

**2.1. Перечень тестов и отчёт синхронизированы.** В текущей ревизии — **70** методов `test*`, включая один пропускаемый интеграционный тест. Полный `composer test` завершился с **70 tests, 167 assertions, 1 skipped**. Перечень — §7.

**2.2. Предфильтр по mtime в GC логически корректен.** Файл со свежим `getMTime()` не может быть кандидатом на удаление: либо `window_started_at` обновлён при сбросе окна, либо `fallback_until` в будущем. Ложных пропусков предфильтр не даёт.

**2.3. Паритет стоимости dummy-хеша закрыт и защищён тестом.** `DUMMY_PASSWORD_HASH_COST_10` / `_COST_12` выбираются по `PHP_VERSION_ID >= 80400`; граница совпадает с повышением дефолтной стоимости bcrypt в PHP 8.4. `AdminUserServiceTest` сверяет **и алгоритм, и `options['cost']`** с `password_hash(PASSWORD_DEFAULT)`. Если `PASSWORD_DEFAULT` сменится на argon2, ключ `cost` исчезнет и тест упадёт — регрессия не пройдёт незамеченной. Матрица CI (8.2 / 8.5) покрывает обе ветки.

**2.4. `@unserialize` корректно взаимодействует с обработчиком ошибок.** В PHP 8 оператор `@` маскирует `E_WARNING`; проверка `error_reporting() & $severity` в `set_error_handler` возвращает `false`, исключение не бросается. `FileCache::get` и `RedisCache::get` действительно дают cache-miss, а не 500.

**2.5. Wildcard доверенных прокси мёртв структурно.** Помимо запрета в `config/app.php`, ветка `'*'` удалена из `isFromTrustedProxy`: `inet_pton('*')` вернёт `false`, CIDR-ветка тоже не сработает. Даже при обходе валидации конфига wildcard не оживёт.

**2.6. Самокоррекция: умножение циклов сессии в текущих шаблонах не происходит.** В прошлом разборе я утверждал «4 открытия / 3 закрытия на админ-страницу». Повторная трассировка `modules/Admin/views/layout.php`: `csrfField()` (→ `session->get`, открывает сессию и оставляет открытой) вызывается в `<header>` **до** `flash('error')` и `flash('success')` в `<main>`. Поэтому оба `getFlash` видят `wasActive === true` и не закрывают сессию. На дашборде — 2 открытия / 1 закрытие, как и раньше; на публичной главной — 1 открытие и **раннее** закрытие вместо удержания до конца запроса, то есть улучшение. Умножение остаётся структурным риском с предусловием, а не живым дефектом. (`SES-08`, группа D)

**2.7. Ошибка фоновой GC не должна срывать запрос.** Вероятностный `collectGarbageOccasionally()` обёрнут в `try/catch (Throwable)` с `logGarbageCollectionFailure`; файловые операции используют защиту от обычных ошибок файловой системы. Отдельная CLI-команда `composer rate-limit:gc` выполняет GC с ожиданием эксклюзивной блокировки. Команда и обработка ошибок проверены lint/стилем; нагрузочный тест и проверка конкурирующего процесса не запускались.

**2.8. Закрытые в текущей ревизии пункты.**
- `OPS-05`: production-кэш маршрутов принимается только при совпадении SHA-256 fingerprint списка и содержимого файлов маршрутов сайта и модулей. Изменение/добавление/удаление такого файла приводит к повторной регистрации маршрутов. Ошибка записи кэша логируется. Проверки: `RouteCacheFingerprintTest`, `RouteCacheInvalidationTest`.
- `OPS-08`: создание каталога DI-кэша проверяется, далее проверяется доступность для записи; невозможность подготовки каталога даёт явный `RuntimeException`. Проверка: `ApplicationSiteTest::testReportsWhenProductionDiCacheDirectoryCannotBeCreated`.
- `DOC-01`: перечень тестов и числовой результат `SECURITY-REVIEW.md` обновлены.
- `Q-14`: порядок импортов, который выглядит неалфавитным, не нарушает активные правила PHP-CS-Fixer; `composer cs:check` проходит. Отдельная правка не нужна.

---

## 3. Сводная таблица открытых пунктов

### A — живые

| ID | Файл | Симптом |
|---|---|---|
| `RL-10` | `FileRateLimiter::withState/collectExpiredStates` | Уборка в запросе всё ещё best-effort и может пропускаться при активных `LOCK_SH`; CLI-команда смягчает это только при настроенном периодическом запуске |
| `EXT-01` | DI-фабрика `RouterInterface` | Все модули грузятся для каждого сайта |
| `EXT-02` | `config/dependency-rules.php` | Ядро биндит сервисы модуля Admin |
| `EXT-03` | `modules/Admin/views/layout.php` | CSS модуля принадлежит сайту Corporate |
| `EXT-09` | схема БД | Нет версионированных миграций |
| `OPS-03` | `Logger::writeLog` | Нет ротации, flock на строку, info-поток на отказах, нет request-id |
| `OPS-01` | `.htaccess`, `Response`, `layouts/main.php` | Нет `Cache-Control`/ETag для HTML; пустой `.htaccess`; внешние шрифты |
| `OPS-06` | `ApiController::health`, `bootstrap.php` | Нет readiness и метрик; bootstrap пишет только message |
| `ROUTE-01` | `Route::matches/buildUri` | Параметры не декодируются, генерация кодирует |
| `ROUTE-02` | `Router::dispatch`, `Request::__construct` | Нет HEAD и 405; слеш нормализуется без канонизации |
| `HTTP-02` | `Request::extractBody` | `Content-Length` проверяется до отсева GET/HEAD |
| `SEC-11` | `RedisClient::connection`, `DataBase::connection` | Нет TLS-опций для Redis и MySQL |
| `MISC-01` | `RedisClient::connection`, `Session::configureRedisHandler` | Имя сайта из env, а не из `path.site` |
| `MISC-02` | `Application`, `ErrorHandler`, `bootstrap` | Набор security-заголовков задан трижды |
| `QA-02` | `.php-cs-fixer.dist.php`, `ci.yml` | Нет статического анализа, `strict_types` не enforced, нет `composer audit` |

### D — латентные (с предусловием)

| ID | Предусловие | Симптом |
|---|---|---|
| `CACHE-01` | Потребитель `CacheInterface` **и** `CACHE_DRIVER=redis` | Блокирующий лок журнала, перезапись целиком, inline-replay, `time()` вместо clock |
| `CACHE-04` | Потребитель `FileCache` | Нет GC файлов кэша |
| `CACHE-08` | Потребитель кэша + redis-failover | Вызовы `fallback` в `FailoverCache` не обёрнуты; `FileException` уходит как 500 |
| `OPS-07` | Модуль с многошаговой записью / long-running runtime | Нет транзакций в `DataBaseInterface`; stateful-синглтоны без `reset()` |
| `SES-08` | Шаблон читает ≥2 flash-ключей без предшествующего обращения к сессии | Каждый `getFlash` открывает и закрывает сессию отдельно |
| `RL-05` | Rate limit на POST-only маршруте | 302 на URI без GET-пары → 404 |
| `RL-11` | Измерение показало значимую долю `fopen+flock` в p95 | Второй файловый лок на каждой записи состояния |

### F — мелкие

| ID | Файл::метод | Симптом |
|---|---|---|
| `Q-04` | `EngineStatsService::databaseStats` | `catch (DataBaseException)` не покрывает сбой логгера внутри `query` |
| `Q-06` | `Router::normalizePath` | Мёртвая ветка `=== ''` |
| `Q-07` | `RequestMetrics::serverTiming` | `desc="1 calls"`; строку фиксирует тест |
| `Q-13` | `.env.example` | `APP_DEBUG` упомянут в комментарии, ключа нет |
| `VAL-01` | `Validator::validate` | После ошибки продолжает правила; `unique` применяется в CLI `create-admin`, поэтому лишний запрос БД уже достижим, но влияние ограничено CLI |

---

## 4. Карточки группы A

### RL-10 · Уборка голодает под нагрузкой

**Файл:** `src/System/RateLimit/FileRateLimiter.php::withState`, `collectExpiredStates`

**Что происходит.** Каждая запись состояния берёт `flock($gcLock, LOCK_SH)` **блокирующе**. Вероятностная уборка в запросе берёт `LOCK_EX | LOCK_NB` и при отказе возвращает `0`. Эксклюзивный лок не выдаётся, пока удерживается хотя бы один разделяемый; запросная GC остаётся best-effort.

Периодическая команда `composer rate-limit:gc` теперь вызывает ту же очистку с блокирующим `LOCK_EX`: при запуске она ждёт освобождения локов запросов. Однако бюджет `GARBAGE_COLLECTION_SCAN_LIMIT = 200` по-прежнему считает просмотренные файлы, а не удалённые. Шард со свежими файлами может расходовать бюджет без удаления; один запуск не гарантирует удаление всех просроченных состояний.

**Следствие.** Без настроенного периодического запуска файловые состояния могут накапливаться. Дефолтный драйвер — `file`. Эффект под реальной нагрузкой не измерен.

**Текущее смягчение.** Добавлены `bin/rate-limit-gc.php` и Composer-команда `composer rate-limit:gc`. Настройте её периодический запуск от пользователя приложения. Запуск ждёт освобождения общей блокировки; не запускайте частые параллельные задания.

**Остаток.** Рассмотреть раздельные бюджеты просмотра/удаления и проверку поведения при занятых локах. `testGarbageCollectionProcessesAtMostTheConfiguredScanBudget` проверяет лимит сканирования (200 записей из 250), но не блокирующее ожидание CLI-процесса.

---

### OPS-05 · Кэш маршрутов не инвалидируется

**Статус: исправлено в текущей ревизии.**

Production-кэш теперь включает fingerprint (SHA-256) всех файлов маршрутов сайта и модулей. Файлы и их содержимое проверяются при создании Router; несовпадение заставляет зарегистрировать маршруты заново и атомарно заменить кэш. Ошибка записи логируется. Регрессионное покрытие: `RouteCacheFingerprintTest` и `RouteCacheInvalidationTest`.

**Остаток:** если экспорт маршрутов невозможен из-за Closure-handler, кэш не записывается; отдельная диагностика причины отказа экспорта не добавлена.

---

### OPS-08 · Компиляция контейнера без проверки доступности записи

**Статус: исправлено в текущей ревизии.**

Перед включением DI-компиляции приложение проверяет создание каталога и `is_writable`; ошибки приводят к явному исключению с путём каталога. Регрессионное покрытие: `ApplicationSiteTest::testReportsWhenProductionDiCacheDirectoryCannotBeCreated`.

---

### EXT-01 · Каждый сайт получает маршруты всех модулей

`glob($modulesDir . '/*/routes.php')` без фильтра. Новый сайт автоматически открывает `/admin/login`, `/admin/`, `/admin/logout`.

**Фикс.** `sites/<Site>/config/modules.php` со списком разрешённых модулей; фабрика грузит только их. Дефолт для Corporate — `['Admin']`, для нового сайта — `[]`.

**Приёмка.** Сайт без Admin в allowlist → `/admin/login` = 404.

---

### EXT-02 · Ядро знает о конкретном модуле

`config/dependency-rules.php` содержит `use Modules\Admin\Services\…` и биндинги `AdminUserService`, `EngineStatsService` (последний — с параметрами путей). Каждый новый модуль требует правки конфига ядра, что противоречит заявленным границам слоёв.

**Фикс.** Определения модуля в `modules/<M>/config/dependency-rules.php`, сборка через манифест `modules/<M>/module.php`.

---

### EXT-03 · Ассеты модуля принадлежат сайту

`modules/Admin/views/layout.php` подключает `/assets/css/admin.css`; файл лежит только в `sites/Corporate/public/assets/css/`. На новом сайте админка рендерится без стилей.

**Фикс.** `modules/Admin/public/assets/…` + публикация в `sites/<Site>/public/assets/vendor/<M>/`.

---

### EXT-09 · Нет версионированных миграций

Схема `admin_users` создаётся CLI-скриптом `bin/create-admin.php` (подтверждено владельцем). Общего механизма миграций нет; версия схемы нигде не фиксируется. Первый модуль со своей таблицей не имеет штатного пути развёртывания.

**Фикс.** `modules/<M>/database/migrations/*.sql` + `bin/migrate.php`; CLI-скрипт перестаёт быть источником схемы.

---

### OPS-03 · Логгер

**Файлы:** `src/System/Logger/Logger.php::writeLog`, `src/System/File/File.php::write`

- Ротации нет: `log/<Module>.log` растёт неограниченно.
- Каждая строка — `file_put_contents(FILE_APPEND | LOCK_EX)`: общий файл на модуль сериализует записи всех воркеров.
- Уровней и фильтрации нет. `AdminAuthMiddleware::denyAccess`, `AuthMiddleware` (отказ токена), `RateLimitMiddleware` (429) пишут по info-строке на каждый отказ — под атакой это поток записи в один файл.
- Нет идентификатора запроса для сопоставления записей.

**Фикс.** Ротация по размеру/дню, фильтрация по уровню, request-id в контексте, ограничение частоты повторяющихся отказов.

---

### OPS-01 · HTTP-кэширование и статика

**Файлы:** `sites/Corporate/public/.htaccess`, `src/System/Http/Response.php`, `sites/Corporate/views/layouts/main.php`

- `.htaccess` содержит только rewrite: нет `Options -Indexes`, `DirectoryIndex`, expires, сжатия.
- Ни один ответ не несёт `Cache-Control` или `ETag`; повторный запрос всегда доходит до PHP.
- Шрифты грузятся с `fonts.googleapis.com` / `fonts.gstatic.com` (два preconnect + блокирующий запрос к третьей стороне).

**Разделение ответственности:** сжатие и expires для `/assets/*` — веб-сервер; `Cache-Control` и ETag для генерируемого HTML — приложение. Предусловие со стороны приложения выполнено: анонимный ответ больше не отдаёт `Set-Cookie`.

---

### OPS-06 · Наблюдаемость

`ApiController::health` возвращает статический `ok`, зависимости не проверяются — как readiness непригоден. Метрики `RequestMetrics` доступны только через `Server-Timing` и только вне prod; внешнего эндпоинта нет. `bootstrap.php` пишет в лог лишь message/file/line без стека.

**Фикс.** Readiness с проверкой БД/Redis, `/metrics` под токеном, `X-Request-Id` в ответе и логах, стек в bootstrap-лог.

---

### ROUTE-01 · Параметры маршрута не декодируются

`Route::matches` кладёт в `$params` сырые значения из `preg_match` по процент-кодированному пути; `buildUri` кодирует через `rawurlencode`. `/hello/John%20Doe` даёт параметр `John%20Doe`. Генерация и распознавание не симметричны.

**Фикс.** `rawurldecode` значений **после** сопоставления, чтобы `%2F` не влиял на выбор маршрута.

**Приёмка.** `buildUri` → `matches` — круговая симметрия.

---

### ROUTE-02 · Нет HEAD и 405, слеш не канонизируется

`dispatch` ищет только в `routesByMethod[$method]`, иначе `RouteNotFoundException`. `Request` делает `rtrim($uri, '/')`, поэтому `/about` и `/about/` попадают в один маршрут.

**Следствие.** HEAD к существующему GET-маршруту даёт 404 (ломает часть мониторингов и линк-чекеров); неверный метод даёт 404 вместо 405; оба варианта слеша отдают один контент без канонического адреса.

**Фикс.** HEAD → GET с пустым телом; 405 при совпадении пути с другим методом; 301 на канонический вариант слеша.

---

### HTTP-02 · `Content-Length` проверяется до отсева GET/HEAD

**Файл:** `src/System/Http/Request.php::extractBody`

Блок валидации `Content-Length` стоит выше раннего возврата для GET/HEAD. GET с большим `Content-Length` (встречается у части прокси и клиентов) получает 413, хотя тело не читается.

**Фикс.** Перенести проверку после отсева методов.

**Приёмка.** GET с `CONTENT_LENGTH` больше лимита и пустым телом → 200, не 413.

---

### SEC-11 · Нет TLS для Redis и MySQL

`RedisClient::connection` и `DataBase::connection` не принимают SSL-опций. При удалённых бэкендах учётные данные и трафик идут открытым текстом. Актуальность зависит от топологии.

**Фикс.** Проброс TLS-параметров из конфига (`redis.ssl`, `database.ssl`), по умолчанию выключено; описать в `.env.example`.

---

### MISC-01 · Источник истины для имени сайта

`RedisClient::connection` и `Session::configureRedisHandler` читают `APP_SITE` через `config->env('APP_SITE', 'Corporate')`, хотя `Application` уже валидирует имя и отдаёт `path.site`. Дублирование: нормализация имени в ядре разойдётся с Redis-namespace.

**Фикс.** Внедрять `path.site` в оба места.

---

### MISC-02 · Security-заголовки заданы трижды

Один и тот же набор из четырёх заголовков в `Application::run`, `ErrorHandler::sendSecurityHeaders`, `bootstrap.php`. guaranteed дрейф при добавлении пятого.

**Фикс.** Один helper / value object, используемый всеми тремя точками.

---

### DOC-01 · `SECURITY-REVIEW.md` устарел

**Статус: исправлено в текущей ревизии.**

Ранее отчёт указывал «58 tests, 144 assertions, 1 skipped» и не описывал ряд последующих исправлений. Отчёт и перечень тестов обновлены до фактических 70 тестов и 167 assertions.

**Проверка.** `composer test`: 70 tests, 167 assertions, 1 skipped.

---

### QA-02 · Нет статического анализа и принуждения конвенций

`.php-cs-fixer.dist.php`: `setRiskyAllowed(false)` + только `@PER-CS` → `declare(strict_types=1)` не проверяется, хотя требуется конвенцией. В `ci.yml` — только `cs:check` и `test`: нет PHPStan/Psalm, нет `composer audit`.

**Фикс.** PHPStan level 8 в CI; risky-правило `declare_strict_types`; `composer audit` отдельным шагом.

---

## 5. Карточки группы D (латентные)

### CACHE-01 · Журнал failover-кэша
**Предусловие:** появляется потребитель `CacheInterface` **и** `CACHE_DRIVER=redis`. В дампе потребителей кэша вне тестов нет.

**Файлы:** `CacheFailoverJournal::withLock/update/readEntries/writeEntries/replay`, `FailoverCache::recoverPendingWrites`

- `withLock` берёт блокирующий `LOCK_EX`; внутри — `unserialize` всего журнала и перезапись всего файла. При отказе Redis через этот путь идёт каждая операция: сериализация воркеров во время аварии, O(N) на операцию.
- `replay(10)` выполняется внутри пользовательского запроса.
- `recordSetWithFallback` использует `time()` вместо внедрённого `TimeDateInterface` — журнал нетестируем.

**Фикс.** `LOCK_EX | LOCK_NB` с отказом от ожидания; replay в CLI/cron; шардирование журнала или append-only; внедрить clock.

### CACHE-04 · Нет GC файлов кэша
**Предусловие:** первый потребитель `FileCache`. `<md5>.cache` создаются и не удаляются, каталог плоский.
**Фикс.** Шардирование + вероятностный GC по образцу `FileRateLimiter`, с уроками `RL-10` (CLI-уборка, бюджет по удалённым).

### CACHE-08 · Fallback в `FailoverCache` не обёрнут
**Предусловие:** потребитель кэша + `CACHE_REDIS_FALLBACK=true`.
`FileCache::get` ловит `FileException` и возвращает default, **только если файл исчез**; если файл существует, но не читается (права, I/O), исключение пробрасывается. `FailoverCache::get/set/delete` ловят вокруг fallback только `CacheException` → наружу уходит 500 вместо деградации.
**Фикс.** Обернуть вызовы `$this->fallback->*` в `try/catch (Throwable)` → `default` / `false` + лог.

### OPS-07 · Нет транзакций; stateful-синглтоны
**Предусловие:** модуль с многошаговой записью / переход на long-running runtime.
`DataBaseInterface` exposes только `connection()` и `query()` — для транзакции модуль лезет в `PDO` напрямую, обходя абстракцию. `Validator::$errors`, `View::$cachedOldInput/$cachedErrors`, `RequestMetrics::$components` — состояние в синглтонах без `reset()`.
**Фикс.** `beginTransaction/commit/rollBack/transaction(callable)` в интерфейсе и реализации; `reset()` у stateful-сервисов.

### SES-08 · Отдельный цикл сессии на каждый flash-ключ
**Предусловие:** шаблон читает ≥2 flash-ключей, и до этого не было обращения к сессии (`get/set/has/csrfField`).
`getFlash` закрывает сессию, если она не была активна (`$wasActive`). `View` кеширует только `_old_input` и `_errors`; `flash('error')` и `flash('success')` — отдельные вызовы. **Текущие шаблоны предусловие не выполняют** (см. §2.6), поэтому это структурный риск, а не живой дефект.
**Фикс.** Читать flash-мешок один раз за запрос: `SessionInterface::pullFlash(array $keys)` либо кеш мешка в `View`.

### RL-05 · 429 на POST-only маршруте
**Предусловие:** rate limit подключён к маршруту без GET-пары.
Для не-GET/HEAD не-JSON ответа `buildTooManyRequestsResponse` делает 302 на `$request->uri()`; если GET-версии нет — 404. Сейчас все rate-limited маршруты имеют GET-пару.
**Фикс.** При отсутствии GET-пары отдавать 429 напрямую.

### RL-11 · Второй файловый лок на горячем пути
**Предусловие:** измерение показало значимую долю `fopen+flock` в p95.
Запись состояния = 2 `fopen` + 2 `flock` (`.gc.lock` + файл состояния). Разделяемый лок защищает от unlink открытого файла, но mtime-предфильтр в 24 часа делает пересечение практически невозможным: кандидатом может быть только файл, untouched сутки. Цена ошибки — один потерянный инкремент у вернувшегося через сутки посетителя.
**Не убирать вслепую:** для `admin_login` с `fail_open=false` это ослабление лимита. Сначала измерить, потом решать.

---

## 6. Мелкие подтверждённые

| ID | Фикс |
|---|---|
| `Q-04` | `EngineStatsService::databaseStats`: ловить `Throwable` — `PhpErrorException`/`FileException` из логгера внутри `DataBase::query` сейчас дают 500 дашборда |
| `Q-06` | `Router::normalizePath`: удалить ветку `=== ''` — `'/' . trim(...)` никогда не пуст |
| `Q-07` | `RequestMetrics::serverTiming`: грамматика `desc="%d calls"`. **Менять код и `RequestMetricsTest` одновременно** — тест ассертит `desc="1 calls"` |
| `Q-13` | `.env.example`: добавить явный ключ `APP_DEBUG=true`. Сейчас он только в комментарии, а дефолт в `config/app.php` — `($appEnv === 'dev')`, то есть комментарий расходится с фактическим поведением |
---

## 7. Зафиксированный перечень тестов

**Всего: 70 методов `test*` в 26 файлах Unit + 1 Integration.** Из них 1 skipped (`CI_INTEGRATION !== '1'`). Полный запуск: 70 tests, 167 assertions, 1 skipped.

| Файл | Методов | Имена |
|---|---|---|
| `AdminUserServiceTest` | 1 | `testUnknownEmailStillVerifiesPasswordAgainstDummyHash` |
| `AppConfigTest` | 3 | `testDebugIsDisabledInProductionEvenWhenExplicitlyRequested`, `testDebugCanBeEnabledInDevelopment`, `testTrustedProxyListDefaultsToEmpty` |
| `ApplicationSiteTest` | 4 | `testSelectsTheDefaultSiteAndExposesSitePaths`, `testRejectsPathTraversalInSiteName`, `testRejectsUnknownSite`, `testReportsWhenProductionDiCacheDirectoryCannotBeCreated` |
| `AuthMiddlewareTest` | 3 | `testRejectsAnArbitraryAuthorizationHeader`, `testAcceptsMatchingBearerTokenWithoutPassingItDownstream`, `testFailsClosedWhenApiTokenIsNotConfigured` |
| `CacheConfigTest` | 3 | `testCacheDriverDefaultsToFile`, `testRejectsUnsupportedCacheDriver`, `testRedisFailoverIsEnabledByDefaultForRedisDriver` |
| `CreateAdminCommandTest` | 1 | `testRejectsPasswordArgument` |
| `EngineStatsServiceTest` | 2 | `testCollectsSiteStructureAndReportsUnavailableDatabase`, `testReportsRedisAsUsedByRateLimiter` |
| `ErrorHandlerTest` | 2 | `testExceptionAfterResponseWasSentDoesNotWriteASecondBody`, `testValidationExceptionAfterResponseWasSentDoesNotWriteAnotherResponse` |
| `ErrorRendererTest` | 1 | `testProductionPageUsesTheActualHttpStatus` |
| `FailoverCacheTest` | 4 | `testOfflineWritesAreServedFromFilesAndReplayedToRedis`, `testOfflineDeletesAreReplayedWithoutResurrectingTheKey`, `testHealthyRedisWritesDoNotWriteToTheFileFallbackOrJournal`, `testRecoveryIsProcessedInBoundedBatches` |
| `FailoverRateLimiterTest` | 8 | `testUsesFileCountersOnRedisFailureAndKeepsThemUntilTheWindowExpires`, `testFileLimiterKeepsSeparateCountersForDifferentIdentifiers`, `testReadOnlyFailoverCheckDoesNotCreateStorageForMissingState`, `testGarbageCollectionProcessesAtMostTheConfiguredScanBudget`, `testGarbageCollectorRemovesExpiredShardedState`, `testRedisFallbackUsesConfiguredHoldDurationInsteadOfBucketWindow`, `testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive`, `testDoesNotCountSuccessfulPrimaryHitAgainWhenFailoverActivatesConcurrently` |
| `FileCacheTest` | 3 | `testWritesToTemporaryFileBeforeReplacingTheCacheEntry`, `testConcurrentDeletionDuringReadIsTreatedAsCacheMiss`, `testNonPositiveTtlDeletesInsteadOfWriting` |
| `InfrastructureConfigTest` | 1 | `testDatabaseAndRedisSettingsAreReadFromEnvironment` |
| `RateLimitConfigTest` | 4 | `testRateLimiterDriverDefaultsToFile`, `testSelectsFileRateLimiterDriver`, `testFileDriverResolvesWithoutConstructingRedisRateLimiter`, `testRejectsUnsupportedRateLimiterDriver` |
| `RateLimitMiddlewareTest` | 3 | `testGeneralRateLimitFailsOpenWhenRedisIsUnavailable`, `testAdminLoginFailsClosedWhenRedisIsUnavailable`, `testHtmlGetRateLimitReturns429InsteadOfRedirectingToTheSameUri` |
| `RedisNamespaceTest` | 2 | `testPrefixesRedisKeysWithTheSelectedSite`, `testRejectsAnEmptySiteName` |
| `RequestMetricsTest` | 2 | `testServerTimingReportsApplicationAndAggregatedDependencies`, `testStartingAnotherRequestClearsPreviousMeasurements` |
| `RouteCacheFingerprintTest` | 1 | `testFingerprintChangesWhenRouteContentsOrFileListChanges` |
| `RouteCacheInvalidationTest` | 1 | `testChangingRouteFileInvalidatesTheCompiledProductionCache` |
| `RequestTest` | 6 | `testParsesJsonUsingPhpContentTypeServerVariable`, `testParsesUrlEncodedPutBody`, `testRejectsMalformedJsonWithBadRequest`, `testUsesInjectedRemoteAddressAndTrustedForwardedAddress`, `testMatchesEquivalentIpv6TrustedProxyAddresses`, `testRejectsRequestBodyLargerThanConfiguredLimit` |
| `ResponseTest` | 2 | `testJsonSubstitutesInvalidUtf8`, `testRejectsLineBreaksInHeaderValues` |
| `RouterDispatchTest` | 2 | `testDispatchesRoutesFromTheMatchingHttpMethodIndex`, `testCompiledRoutesAreAddedToTheMethodIndex` |
| `RouteTest` | 3 | `testLiteralPunctuationIsMatchedExactly`, `testParameterConstraintsRemainActive`, `testParameterConstraintCanContainRegexDelimiter` |
| `SecurityTest` | 1 | `testCsrfTokenRotationInvalidatesThePreviousToken` |
| `SessionConfigTest` | 4 | `testSecureCookieDefaultsToEnabledInProduction`, `testSecureCookieCanBeExplicitlyDisabled`, `testRejectsInvalidSecureCookieValue`, `testRejectsUnsupportedSessionDriver` |
| `SessionFlashTest` | 2 | `testReadingFlashWithoutAnIncomingSessionDoesNotStartOne`, `testReadingFlashFromAClosedSessionClosesTheResumedSession` |
| `InfrastructureIntegrationTest` | 1 (skipped) | `testMySqlAndRedisBackendsAreReachable` |

**Правило:** при добавлении или удалении теста обновлять эту таблицу и итог. Расхождение таблицы с фактом означает, что документ устарел — как это произошло с `SECURITY-REVIEW.md` (`DOC-01`).

---

## 8. Матрица «пункт → регрессионный тест»

| ID | Тест |
|---|---|
| `RL-10` | `tests/Unit/FailoverRateLimiterTest.php` — лимит сканирования и удаление просроченного состояния; ожидание CLI при занятом lock отдельно не тестируется |
| `OPS-05` | `tests/Unit/RouteCacheFingerprintTest.php`, `tests/Unit/RouteCacheInvalidationTest.php` |
| `OPS-08` | `tests/Unit/ApplicationSiteTest.php::testReportsWhenProductionDiCacheDirectoryCannotBeCreated` |
| `EXT-01` | `tests/Unit/ApplicationSiteTest.php` — allowlist-кейс |
| `EXT-02/05` | `tests/Unit/ModuleManifestTest.php` (новый) |
| `EXT-03` | `tests/Unit/ModuleAssetsTest.php` (новый) |
| `EXT-09` | `tests/Unit/MigrateCommandTest.php` (новый) |
| `ROUTE-01` | `tests/Unit/RouteTest.php` — encoded-параметр и круговая симметрия |
| `ROUTE-02` | `tests/Unit/RouterDispatchTest.php` — HEAD, 405, канонизация слеша |
| `HTTP-02` | `tests/Unit/RequestTest.php` — GET с большим `Content-Length` |
| `MISC-01` | `tests/Unit/RedisNamespaceTest.php` — namespace из `path.site` |
| `MISC-02` | `tests/Unit/SecurityHeadersTest.php` (новый): один источник для трёх точек |
| `CACHE-08` | `tests/Unit/FailoverCacheTest.php` — падающий fallback → default |
| `VAL-01` | Добавить тест ранней остановки правил валидатора; такого теста пока нет |
| `SES-08` | `tests/Unit/SessionFlashTest.php` — два ключа, один цикл |
| `Q-04` | `tests/Unit/EngineStatsServiceTest.php` — сбой логгера внутри `query` |
| `Q-07` | `tests/Unit/RequestMetricsTest.php` — править вместе с кодом |

---

## 9. Порядок работ

```
1. RL-10                   настроить периодическую CLI-уборку; оценить поведение под нагрузкой
2. EXT-01 + EXT-03         границы сайта: allowlist модулей, публикация ассетов
3. ROUTE-01 + ROUTE-02     декодирование параметров, HEAD/405, канонизация слеша
4. HTTP-02 + MISC-01/02    мелкие живые правки одним PR
5. OPS-03 + OPS-06         ротация логов, request-id, readiness
6. EXT-02 + EXT-09         манифест модуля и миграции
7. OPS-01 + SEC-11 + QA-02 кэш-заголовки, TLS-опции, статический анализ
8. Группа D                по мере выполнения предусловий
9. Группа F                одним PR
```

**Обоснование.** Инвалидация кэша маршрутов и подготовка DI-каталога уже обработаны. RL-10 смягчён CLI-командой, но остаётся best-effort без периодического запуска и не проверен нагрузкой. Остальные пункты приоритизируются по реальной топологии и требованиям продукта.

---

## 10. Валидация

```bash
php -l <каждый изменённый PHP-файл>
vendor/bin/phpunit tests/Unit/<TestClass>.php
composer cs:check
composer test
```

**Выполнено для текущих исправлений:** `composer test` — 70 tests, 167 assertions, 1 skipped; `composer cs:check` — пройден; `php -l` для затронутых PHP-файлов — пройден; `composer validate --no-check-publish` — valid; `git -c core.whitespace=cr-at-eol diff --check` — без ошибок (аудит хранится с CRLF). Команды запускались на PHP 8.5.1; PHP-CS-Fixer выдал предупреждение, что проект поддерживает PHP 8.2. Нагрузочный тест RL-10 и интеграционные проверки MySQL/Redis для этой валидации не запускались.

Интеграционные (при поднятых MySQL/Redis):
```bash
CI_INTEGRATION=1 DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root \
REDIS_HOST=127.0.0.1 CACHE_DRIVER=redis composer test
```

Нагрузочная проверка RL-10 (после запуска приложения через подходящий многопроцессный сервер):
```bash
python3 tests/load/http_smoke.py --url http://127.0.0.1/ --workers 16 --duration 60
```

### Ловушки существующих тестов

| Изменение | Что сломается |
|---|---|
| Бюджет GC по удалённым вместо просмотренных (`RL-10`) | `testGarbageCollectionProcessesAtMostTheConfiguredScanBudget` ожидает ровно 200 удалённых из 250 — сохранить контракт или править тест осознанно |
| Рекурсивная уборка каталогов | `FailoverRateLimiterTest::tearDown` и `FailoverCacheTest::tearDown` обходят фиксированную глубину вложенности |
| Создание артефактов журнала/каталога на здоровом пути | `FailoverCacheTest` ассертит `assertFileDoesNotExist(pending.dat)` и `assertDirectoryDoesNotExist(data)`; `testReadOnlyFailoverCheckDoesNotCreateStorageForMissingState` — `assertDirectoryDoesNotExist` |
|Grammатика `Server-Timing` (`Q-07`) | `RequestMetricsTest` ассертит `desc="1 calls"` |
| Дефолты окружения | `AppConfigTest`, `ApplicationSiteTest`, `EngineStatsServiceTest`, `SessionConfigTest`, `RateLimitConfigTest` подменяют `$_ENV`/`$_SERVER` — новые тесты обязаны делать то же в `setUp`/`tearDown` |
| Порядок тестов с реальными сессиями | `SessionFlashTest` восстанавливает `session_id`/`session_name`/`session_save_path`; новые сессионные тесты должны делать так же |

### Не менять при фиксах

`Session::regenerate(true)` на входе и выходе · `hasSessionToRead()` и `$wasActive` в `getFlash` · `use_strict_mode=1` + per-site Redis-префикс · **отсутствие failover у Redis-сессий** (сессия авторитетна, dual-write недопустим) · `hash_equals` в CSRF и Bearer · PDO-плейсхолдеры и `sanitizeIdentifier` · `validateOrFail` возвращает только описанные поля · `markResponseSent` и `$responseSent` · атомарная запись кэша и журнала · атомарный Lua-скрипт rate limiter · `FileRateLimiter` — каталог `0700`, файл `0600` · ограничения `http_smoke.py`.

> **Отчётность (`.github/copilot-instructions.md`):** указывать только реально запущенные команды и фактический результат; результаты этой ревизии приведены выше.

---

## 11. Требует проверки в целевом окружении

| Условие | Что проверить |
|---|---|
| Удалённые MySQL/Redis | Нужен ли TLS с учётом сетевой топологии, сертификатов и конфигурации сервера |
| Несколько сайтов | Должны ли сайты иметь независимые наборы модулей; сейчас маршруты всех модулей загружаются выбранным сайтом |
| Production под нагрузкой | Настроить периодический `composer rate-limit:gc`, затем проверить рост файлов и задержки; этот нагрузочный сценарий не запускался |
