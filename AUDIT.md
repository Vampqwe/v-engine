# AUDIT.md — V-engine: аудит и план исправления

> **Назначение:** самодостаточный контекст для ИИ-агента или нового разработчика. Прочитав только этот файл, можно понять архитектуру, найти проблему по ID, применить фикс и проверить его тестом — без повторного анализа репозитория.
>
> **Дата:** 2026-11-20 · **Объект:** `vampqwe/v-engine` · **Основание:** дамп `vampqwe-v-engine-8a5edab282632443.txt` (ревизия с `RedisNamespace`, `Session.php`, `RATE_LIMIT_DRIVER`)
> **Фокус:** рост трафика · безопасность · скорость добавления модуля и сайта
> **Метод:** статическое чтение исходников. **Ни одна команда валидации не запускалась аудитором.** Все утверждения проверены по коду дампа; непроверяемые помечены `⚠ VERIFY`.

---

## 0. Как читать этот документ

| Правило | Значение |
|---|---|
| ID вида `RL-03` | Стабильный идентификатор. Используй в ветке, коммите, тесте: `fix/RL-03-double-failover-check` |
| Приоритет | Строится по **достижимости сегодня × радиусу поражения × уверенности в доказательстве**, а не по категории |
| Группы | `A` живые дефекты · `B` hardening прода · `C` расширяемость · `D` латентные (есть предусловие) · `E` отложено до измерений · `F` быстрые правки |
| Пути | От корня репозитория. **Номера строк не приводятся намеренно** — ищи по `Класс::метод()` |
| `⚠ VERIFY` | Нельзя подтвердить по дампу. Проверь в реальном файле до изменения |
| Порядок | Фазы в §12 упорядочены по зависимостям. Не начинай `C` раньше `A` |
| Обязательно | Прочитай §15 (инварианты) и §16 (ловушки тестов) **до** первого изменения |

**Формат карточки:** `Файл → Механика → Влияние → Фикс → Приёмка`.

---

## 1. Контекст проекта

```
PHP >= 8.2 · Composer · PHP-DI 7 · vlucas/phpdotenv 5 · PHPUnit 11 · PHP-CS-Fixer (@PER-CS)
MySQL (PDO, ATTR_EMULATE_PREPARES=false) · Redis (ext-redis, опционально)
```

**PSR-4:** `VEngine\`→`src/`, `App\`→`app/`, `Sites\`→`sites/`, `Modules\`→`modules/`, `Tests\`→`tests/`.

**Жизненный цикл запроса:**

```
sites/<Site>/public/index.php
  └─ bootstrap.php            ob_start(); BASE_PATH; dotenv->safeLoad(); new Application; run()
       └─ Application::__construct   валидирует APP_SITE, собирает $paths[], строит DI-контейнер
            └─ Application::run      ErrorHandler->register(); Request; RequestMetrics; Router->dispatch(); Response->send()
                 └─ Router::dispatch routesByMethod[METHOD] → Route::matches (preg_match) → runPipeline
                      └─ middleware[] → Invoker->call(handler) → ResponseInterface
```

| Подсистема | Реализация | Особенность |
|---|---|---|
| Мультисайт | `APP_SITE` → `sites/<Site>/`; один инстанс = один сайт | Файловый кэш изолирован: `storage/cache/sites/<Site>/` |
| Модули | `glob(modules/*/routes.php)` в DI-фабрике роутера | Грузятся **для всех сайтов безусловно** (`EXT-01`) |
| Кэш | `CACHE_DRIVER=file\|redis`; при redis — `FailoverCache(RedisCache, FileCache, Journal)` | **Нет ни одного потребителя `CacheInterface` вне тестов** |
| Rate limit | `RATE_LIMIT_DRIVER=file\|redis`; при redis — `FailoverRateLimiter(Redis, File, hold=300)` | Потребители: `/api/*` группа, `POST /admin/login` |
| Redis namespace | `RedisNamespace::forSite($prefix, $site)` | Применяется в `RedisClient` и в `Session::configureRedisHandler` (+`sess:`) |
| Роутер | Индекс по HTTP-методу + линейный `preg_match` внутри метода | prod-кэш `routes.compiled.php`, экспорт невозможен при Closure-хендлере |
| Сессии | `Session`, драйвер `file\|redis`, lazy `getFlash()` | Redis-сессии — авторитетное состояние, failover отсутствует намеренно |
| Шаблоны | `View::renderFile` — `extract(EXTR_SKIP)` + `require` в closure, `$this` = View | `@Module/path` → `modules/<M>/views/` |
| Метрики | `RequestMetrics` → заголовок `Server-Timing` | Уходит всем клиентам (`SEC-06`) |

---

## 2. Ограничения дампа

> **Главный урок предыдущей ревизии:** отсутствие файла в дампе **не означает** его отсутствие в репозитории. Дамп.lossy: `README.md` помечен `[Binary file]`, `AUDIT.md` пришёл пустым.

| Не видно в дампе | Статус |
|---|---|
| `README.md` | Помечен `[Binary file]`. Конвенции взяты из `.github/copilot-instructions.md` |
| `bin/create-admin.php`, `bin/cache-clear.php` | **Подтверждены владельцем как существующие.** `create-admin.php` создаёт таблицу `admin_users` |
| `composer.lock`, `.gitignore` | **Подтверждены владельцем как существующие** |
| Схема БД вне `bin/create-admin.php` | Общей системы миграций нет — это реальный пробел (`EXT-09`), но не блокер сборки |

**Правило для агента:** прежде чем заявить «файл отсутствует», запроси подтверждение. Ошибка `QA-01` в прошлой ревизии произошла именно так.

---

## 3. Что уже исправлено (верифицировано по коду и тестам)

| ID | Фикс | Подтверждение в коде | Тест |
|---|---|---|---|
| `RL-01` | GET/HEAD → прямой `429`; HEAD с пустым телом; POST оставлен на PRG-редирект | `RateLimitMiddleware::buildTooManyRequestsResponse` | `RateLimitMiddlewareTest::testHtmlGetRateLimitReturns429InsteadOfRedirectingToTheSameUri` |
| `SES-01` | `getFlash()` не стартует сессию без входящего cookie | `Session::hasSessionToRead()` | `SessionFlashTest::testReadingFlashWithoutAnIncomingSessionDoesNotStartOne` |
| `RL-02` | `RATE_LIMIT_DRIVER` конфигурируется и валидируется; `file`-ветка не конструирует Redis | `config/rate-limit.php`, `match` в DI | `RateLimitConfigTest` (4 кейса) |
| `RL-02a` | `clear()` чистит **оба** бэкенда | `FailoverRateLimiter::clear` | `FailoverRateLimiterTest::testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive` |
| `CACHE-02` | Site-namespace в Redis, включая сессии (`vengine:Corporate:sess:`) | `RedisNamespace`, `RedisClient::connection`, `Session::configureRedisHandler` | `RedisNamespaceTest` |
| `CACHE-03` | Атомарная запись `tempnam+rename`; `@unserialize` + проверка ключа `data` | `FileCache::set/get` | `FileCacheTest::testWritesToTemporaryFileBeforeReplacingTheCacheEntry` |

**Два неочевидных момента, сделанных правильно:**

1. `@unserialize` корректно взаимодействует с `ErrorHandler::set_error_handler`: в PHP 8 оператор `@` маскирует `E_WARNING`, проверка `error_reporting() & $severity` возвращает `false`, исключение не бросается. Фикс действительно даёт cache-miss, а не 500.
2. `use_strict_mode=1` + per-site префикс Redis означают, что общий `VENGINE_SESSID` между сайтами на одном домене не даёт кросс-сайтового чтения сессии — чужой ID отвергается. Это лучше, чем переименование cookie.

**Чек-лист `SES-02`, подтверждённый чтением `Session.php`:** `use_strict_mode=1` ✅, `use_only_cookies=1` ✅, `gc_maxlifetime = lifetime` ✅, cookie-параметры применяются **до** `session_start()` ✅, `remove()` после `close()` работает (повторный `ensureStarted()` при живом ob-буфере) ✅. Остаток — `SES-03…SES-07`.

---

## 4. Отозвано — не воскрешать

| ID | Что было предложено | Почему отозвано |
|---|---|---|
| `QA-01` | «Проект не собирается с нуля» | Артефакт дампа. `bin/*`, `composer.lock`, CI существуют. Остаётся только отсутствие миграций (`EXT-09`) |
| `SES-02-failover` | File-failover для Redis-сессий | **Кэш неавторитетен, сессия авторитетна.** Dual-write позволяет восстановить уничтоженную сессию из второго бэкенда. HA решать Sentinel/replication, не в приложении |
| `SEC-01-example` | Менять `APP_ENV=dev` в `.env.example` | `dev` в примере — нормальное локальное значение. Дефект в коде, не в примере (`SEC-01`) |
| `OPS-02-pconnect` | Внедрять `pconnect` / `PDO::ATTR_PERSISTENT` | При FPM число соединений ограничено пулом; выигрыш не оправдывает риски состояния. Сначала измерить (`E`) |
| `OPS-04-wording` | «Роутер линейно сканирует все маршруты» | Индекс по методу есть. Линейный поиск — только внутри метода, при ~9 маршрутах незначим |

---

## 5. Реестр открытых находок

### A — живые дефекты (достижимы сегодня)

| ID | Файл::метод | Симптом |
|---|---|---|
| `SEC-03` | `Request::ip/isFromTrustedProxy` | Читает `$_SERVER`; дефолт прокси ломает Docker; `'*'` = спуфинг |
| `ERR-02` | `ErrorRenderer::renderProduction` | 404 отдаёт тело «500»; API получает HTML |
| `STATS-01` | `EngineStatsService::redisStatus` | Дашборд показывает Redis «не используется», хотя rate limiter на нём |
| `CFG-01` | `.env.example`, `config/rate-limit.php` | `CACHE_DRIVER=file` + `RATE_LIMIT_DRIVER=redis` — пример не самосогласован |
| `RL-03` | `FailoverRateLimiter::hit` | Второй `isFailoverActive()` → двойной подсчёт при флаппинге + лишний файловый лок |
| `RL-04` | `FileRateLimiter::withState` | Файл на каждую пару (bucket, IP), нет GC, `chmod` на каждый вызов, плоский каталог |
| `SES-03` | `Session::start` | `gc_probability`/`gc_divisor` не заданы → просроченные сессии могут не удаляться |
| `SES-04` | `Session::start/configureRedisHandler` | Отказ Redis-сессий → 500, а не 503 |
| `SES-05` | `Session::start` | `cookie lifetime = 7200` = абсолютный таймаут, не idle; не задокументировано |
| `EXT-01` | DI-фабрика `RouterInterface` | Каждый новый сайт автоматически открывает `/admin/login` |
| `EXT-03` | `modules/Admin/views/layout.php` | Admin грузит `/assets/css/admin.css` из сайта Corporate |
| `OPS-05` | DI-фабрика роутера, `Application::buildContainer` | Кэш маршрутов/DI не инвалидируется при деплое |

### B — hardening прода

| ID | Файл::метод | Симптом |
|---|---|---|
| `SEC-01` | 7 точек чтения `APP_ENV` | 6 дефолтов `'dev'`, 1 `'prod'`; при отсутствии настройки — debug + небезопасная cookie |
| `SEC-02` | `Application::run` | Нет security-заголовков и нет глобального middleware-конвейера |
| `SEC-04` | `AdminUserService::authenticate`, `AuthController::login` | Timing-enumeration email; email в логах; нет per-account трения |
| `SEC-05` | `sites/Corporate/routes/web.php`, `HomeController::submit` | `POST /` без лимита, валидации и ограничения длины |
| `SEC-06` | `Application::run` | `Server-Timing` раскрывает тайминги всем |
| `SEC-07` | `Security::csrfToken`, `AuthController::login/logout` | CSRF-токен не ротируется при смене привилегий |
| `SEC-08` | `RedisClient::connection/resetConnection` | `auth()` не проверяется; `resetConnection()` без `close()`; нет TLS |
| `ERR-01` | `ErrorHandler::register/handleException` | Нет состояния жизненного цикла ответа; гипотеза двойного рендера не доказана |
| `OPS-03` | `Logger::writeLog`, `Application::run` | flock на строку, dev-лог каждого запроса, нет ротации и уровней |
| `OPS-01` | `.htaccess`, `layouts/main.php`, `Response` | Нет `Cache-Control`/ETag; gzip/expires не заданы; внешние Google Fonts |
| `OPS-06` | `RequestMetrics`, `ApiController::health` | Нет `/metrics`, readiness, request-id |

### C — расширяемость

| ID | Файл | Симптом |
|---|---|---|
| `EXT-02` | `config/dependency-rules.php` | Ядро хардкодит `Modules\Admin\Services\*` |
| `EXT-04` | `View::resolvePath` | Сайт не может переопределить шаблон модуля |
| `EXT-05` | `Application`, DI-фабрика роутера | Нет манифеста модуля (config/DI/migrations/assets) |
| `EXT-06` | `bin/` | Нет скаффолдеров сайта и модуля |
| `EXT-07` | `AdminAuthMiddleware` | Нет ролей/прав — только «админ или не админ» |
| `EXT-08` | шаблоны, `Validator` | Строки захардкожены по-русски, нет i18n |
| `EXT-09` | `bin/create-admin.php` | Схема `admin_users` живёт в CLI-скрипте; нет версионированных миграций |
| `QA-02` | `.php-cs-fixer.dist.php`, `ci.yml` | Нет PHPStan, `strict_types` не enforced, нет `composer audit` |

### D — латентные (есть предусловие активации)

| ID | Предусловие | Симптом |
|---|---|---|
| `CACHE-01` | Первый потребитель кэша + Redis в prod | Глобальный блокирующий flock журнала + O(N) перезапись + inline-replay |
| `CACHE-04` | Первый потребитель `FileCache` | Нет GC файлов кэша |
| `CACHE-05` | Первый потребитель `FileCache` | Гонка `exists()→read()` → `FileException` → 500; fallback в `FailoverCache` не обёрнут |
| `CACHE-06` | Каталог кэша не writable | `tempnam()` молча уходит в системный temp → `EXDEV` при `rename` |
| `CACHE-07` | Первый потребитель `RedisCache` | `get()` без `@unserialize` и без проверки `data` — асимметрия с `FileCache` |
| `HTTP-01` | Первый `PUT/PATCH/DELETE` маршрут | Тело читается в память без лимита (`post_max_size` на не-POST не действует) |
| `VAL-01` | Первое правило `unique`/`exists` | Нет bail → запрос к БД даже при невалидном значении |
| `OPS-07` | Первый модуль с многошаговой записью | Нет транзакций в `DataBaseInterface`; stateful-синглтоны блокируют long-running |
| `RL-05` | Rate limit на POST-only маршруте | 302 на URI без GET-пары → 404 |

### E — отложено до измерений

| ID | Что мерить | Порог возврата |
|---|---|---|
| `OPS-02` | Выделить `redis.connect` / `db.connect` в `RequestMetrics` | Доля connect > 10 % p95 при удалённом бэкенде |
| `OPS-04` | Доля времени матчинга маршрутов | > 50–100 GET-маршрутов **и** измеримая доля в p95 |

### F — быстрые правки

| ID | Файл::метод | Проблема |
|---|---|---|
| `Q-01` | `View::error` | Возвращает **неэкранированную** строку (`old()` — экранирует) |
| `Q-02` | `Response::json` | Нет `JSON_INVALID_UTF8_SUBSTITUTE` → 500 на битом UTF-8 |
| `Q-03` | `AdminAuthMiddleware::process` | `ctype_digit((string)$id)` пропускает `true`/`1.0` |
| `Q-04` | `EngineStatsService::databaseStats` | Ловит только `DataBaseException` |
| `Q-05` | `TimeDate` | Нет явной таймзоны → логи и дашборд расходятся |
| `Q-06` | `Router::normalizePath` | Мёртвая ветка `=== ''` |
| `Q-07` | `RequestMetrics::serverTiming` | `desc="1 calls"` — **тест фиксирует строку**, см. §16 |
| `Q-08` | `Response::withHeader` | Нет валидации имени/значения заголовка |
| `Q-09` | `FileCache::set` | Не отсекает `ttl <= 0` (в отличие от `RedisCache`) |
| `Q-10` | `CacheFailoverJournal::recordSetWithFallback` | `time()` вместо внедрённого `TimeDateInterface` |
| `Q-11` | `View::resolvePath` | Однопроходный фильтр `..`; добавить `realpath()`-containment |
| `Q-12` | `RedisNamespace::forSite` | Валидирует только непустоту; charset проверяется лишь в `Application` |
| `MISC-01` | `RedisClient::connection` | Берёт `APP_SITE` из env, а не из DI-параметра `path.site` |
| `SES-06` | `Session::start/destroy` | `sid_length`/`sid_bits` не заданы (дефолт 32/4 = 128 бит); `destroy()` шлёт cookie без `samesite` |
| `SES-07` | `View::flash` + `AdminAuthMiddleware` | Layout переоткрывает сессию после `close()`, обнуляя выигрыш раннего закрытия |
| `TEST-01` | `SessionFlashTest` | Ассертит `session_status() === PHP_SESSION_NONE` как предусловие — хрупко при порядке тестов |

---

## 6. Карточки группы A

### SEC-03 · `Request::ip()` — суперглобал и доверенные прокси

**Файл:** `src/System/Http/Request.php::ip/isFromTrustedProxy`

**Механика.**
```php
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
```
Читается суперглобал вместо внедрённых `$serverParams` — прямое нарушение конвенции проекта («Use `RequestInterface` … rather than reading raw superglobals ad hoc») и причина, по которой `RequestTest` с injected `serverParams` не покрывает `ip()`.

Дефолт `TRUSTED_PROXIES=127.0.0.1,::1`: в Docker/Nginx прокси обычно `172.x` → `X-Forwarded-For` игнорируется → все посетители схлопываются в один IP → глобальный бакет 60/мин исчерпывается на весь сайт. Обратный случай `TRUSTED_PROXIES=*` даёт спуфинг IP и обход брутфорс-лимита админки. Сравнение IPv6 строками: `::1` ≠ `0:0:0:0:0:0:0:1`.

**Влияние.** Единственный оставшийся пункт, который в паре с уже починенным `RL-01` даёт отказ `/api/*` при умеренной нагрузке.

**Фикс.**
```php
// В конструкторе:
$this->remoteAddr = (string) ($serverParams['REMOTE_ADDR'] ?? '127.0.0.1');
// В ip(): использовать $this->remoteAddr
// В isFromTrustedProxy(): нормализация через inet_pton, убрать ветку '*'
```
В `.env.example` описать Docker-сценарий и явно запретить `*`.

**Приёмка.** Тест: `X-Forwarded-For` учитывается при injected `REMOTE_ADDR` доверенного прокси; `*` не принимается; две записи одного IPv6 сравниваются равными.

---

### ERR-02 · 404 отдаёт тело «500», API получает HTML

**Файлы:** `src/System/Error/ErrorRenderer.php::renderProduction`, `ErrorHandler::handleException`

**Механика.** `renderProduction()` возвращает heredoc с `<h1>500</h1>` и «Внутренняя ошибка сервера» **независимо** от `$statusCode`. `RouteNotFoundException` даёт статус 404 с телом 500. `ErrorHandler` всегда ставит `Content-Type: text/html`, поэтому `/api/*` получает HTML.

**Влияние.** Детерминированный дефект на каждом 404: неверное тело, soft-404 для SEO, нечитаемые ошибки для API-клиентов и мониторинга.

**Фикс.** Добавить `$status` в `ErrorRendererInterface::render(Throwable, bool, int $status)`; отдельный рендер для 404/403/429/500; content-negotiation в `ErrorHandler` (JSON для `/api/*` и `Accept: json`).

**Приёмка.** Тест: 404 → тело содержит «404», `Content-Type: text/html`; `/api/missing` → JSON со `status: 404`.

---

### STATS-01 · дашборд не учитывает rate-limit драйвер

**Файл:** `modules/Admin/Services/EngineStatsService.php::collect/redisStatus`

**Механика.** `redisStatus(string $cacheDriver, string $sessionDriver)` возвращает `'not_used'`, если оба не `redis`. Про `RATE_LIMIT_DRIVER` метод не знает. При дефолтном `.env.example` (`file` / `file` / `redis`) дашборд показывает «Redis — Не выбран текущими драйверами», хотя **каждый** rate-limited запрос идёт в Redis.

**Влияние.** Ложная картина инфраструктуры именно в той конфигурации, где Redis является скрытой зависимостью. Дефект появился вместе с фиксом `RL-02`.

**Фикс.**
```php
$rateLimitDriver = (string) $this->config->get('rate-limit', 'driver', 'redis');
$redisStatus = $this->redisStatus($cacheDriver, $sessionDriver, $rateLimitDriver);
```
+ в карточке «Сессии»/«Redis» показывать, какие драйверы реально используют Redis.

**Приёмка.** Тест: `cache=file, session=file, rate-limit=redis` → `redis_status !== 'not_used'`.

---

### CFG-01 · пример конфигурации не самосогласован

**Файлы:** `.env.example`, `config/rate-limit.php`

**Механика.** `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, но `RATE_LIMIT_DRIVER=redis`; дефолт в коде тоже `redis`. Комментарий рядом гласит «Redis необязателен». Свежая установка без ext-redis по-прежнему платит за Redis на каждом rate-limited запросе — теперь это можно выключить, но пример об этом не говорит.

**Фикс.** В `.env.example` поставить `RATE_LIMIT_DRIVER=file` вровень с `CACHE_DRIVER=file`; рядом комментарием — когда переключать на `redis`. Дефолт в коде оставить как есть (осознанный выбор), но задокументировать.

**Приёмка.** Смоук: копия `.env.example` без ext-redis → ни одной попытки подключения к Redis на `/api/health`.

---

### RL-03 · двойная проверка failover

**Файл:** `src/System/RateLimit/FailoverRateLimiter.php::hit`

**Механика.** После успешного `primary->hit()` снова вызывается `isFailoverActive()`. Если параллельный воркер успел активировать fallback, запрос считается **дважды** (Redis + файл) → преждевременный 429. На здоровом Redis это ещё и второй open+flock+read на каждый запрос.

**Фикс.** Удалить вторую проверку.

**Приёмка.** Проверено по обновлённым тестам: удаление не ломает ни `testUsesFileCountersOnRedisFailureAndKeepsThemUntilTheWindowExpires`, ни `testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive`. Добавить кейс с двумя идентификаторами при флаппинге.

---

### RL-04 · файловые счётчики растут без GC

**Файл:** `src/System/RateLimit/FileRateLimiter.php::withState`

**Механика.** `<sha256>.json` на каждую пару (bucket, IP), удаления нет. `chmod($path, 0o600)` вызывается на **каждом** обращении, включая read-only `isFailoverActive`. Плоский каталог.

**Влияние.** Живой рост: при увеличении трафика — сотни тысяч мелких файлов, иноды, деградация ext4/overlayfs, лишний syscall на запрос.

**Фикс.** Шардирование по первым 2 символам хеша; `chmod` только при создании; вероятностный GC (1/1000) + cron-чистка просроченных окон.

**Приёмка.** Тест: файл с истёкшим `window_started_at` и `fallback_until` удаляется GC; `chmod` не вызывается при read-only пути. **Обновить `tearDown` в `FailoverRateLimiterTest`** (см. §16).

---

### SES-03 · GC файловых сессий не задан

**Файл:** `src/System/Session/Session.php::start`

**Механика.** Выставляется только `gc_maxlifetime`. `session.gc_probability` / `gc_divisor` не инициализируются, а на Debian/Ubuntu в сборках PHP вероятность по умолчанию 0 (GC вынесен на системный cron).

**Влияние.** Просроченные файлы сессий могут не удаляться никогда. После `SES-01` объём резко меньше, но `/admin/login` сессию создаёт (нужен CSRF), а её сканируют боты.

**Фикс.** Явно `ini_set('session.gc_probability', '1')` + `gc_divisor` (например 1000), либо задокументировать внешнюю уборку и добавить её в deploy/cron.

**Приёмка.** Тест на ini-значения после `start()`; документированный cron-скрипт.

---

### SES-04 · отказ Redis-сессий даёт 500 вместо 503

**Файл:** `src/System/Session/Session.php::start/configureRedisHandler`

**Механика.** При недоступном Redis обработчик сессий либо emits warning (→ `ErrorHandler` → `ErrorException`), либо `session_start()` вернёт `false` (→ `SessionException extends BaseException`). Оба пути → HTTP 500.

**Влияние.** `copilot-instructions.md` требует: «Explicitly define failure behavior for database, Redis, cache, and external-service operations». Для авторитетного состояния 500 — неопределённое поведение.

**Фикс.** Обернуть отказ инициализации сессии в `SessionException`, маппить её в **503** для auth и state-changing маршрутов; анонимные read-only страницы отдавать без сессии. **Не** добавлять файловый failover (см. §4).

**Приёмка.** Тест: драйвер `redis`, обработчик недоступен → 503 на `/admin/login`, анонимная главная отдаётся.

---

### SES-05 · семантика lifetime не описана

**Файл:** `src/System/Session/Session.php::start`

**Механика.** `session_set_cookie_params(['lifetime' => 7200])`. Cookie живёт 2 часа **от выдачи**; PHP не переотправляет её на каждом запросе. Это фактический absolute timeout, а не idle.

**Влияние.** Активный пользователь разлогинится ровно через 2 часа. Как решение — защитимо, но сейчас выглядит как побочный эффект.

**Фикс.** Либо задокументировать как задуманный absolute timeout, либо `lifetime => 0` + опора на `gc_maxlifetime` для idle-семантики. Решение зафиксировать в `config/session.php` комментарием.

**Приёмка.** Тест на выбранный режим + запись в README.

---

### EXT-01 · каждый сайт получает все модули

**Файл:** `config/dependency-rules.php`, фабрика `RouterInterface`

**Механика.** `glob($modulesDir . '/*/routes.php')` без фильтра. Новый сайт автоматически открывает `/admin/login`, `/admin/`, `/admin/logout`.

**Фикс.** `sites/<Site>/config/modules.php` → allowlist; фабрика грузит только разрешённые. Дефолт для Corporate — `['Admin']`, для нового сайта — `[]`.

**Приёмка.** Тест: сайт без `Admin` в allowlist → `/admin/login` = 404.

---

### EXT-03 · ассеты модуля живут в сайте

**Файлы:** `modules/Admin/views/layout.php`, `sites/Corporate/public/assets/css/admin.css`

**Механика.** Layout модуля подключает `/assets/css/admin.css`, который физически лежит в Corporate. Новый сайт → админка без стилей.

**Фикс.** `modules/Admin/public/assets/css/admin.css` + `bin/assets:publish.php` (copy/symlink в `sites/<Site>/public/assets/vendor/<Module>/`); layout ссылается на вендорный путь.

**Приёмка.** Тест/смоук: новый сайт после `assets:publish` отдаёт CSS модуля.

---

### OPS-05 · кэш маршрутов и DI не инвалидируется

**Файлы:** фабрика `RouterInterface` (`is_file($cacheFile)`), `Application::buildContainer`

**Механика.** В prod скомпилированные маршруты читаются, пока файл существует. Добавили модуль — он не появится, пока файл не удалён вручную. ⚠ VERIFY: поведение PHP-DI 7 по staleness компиляции; в любом случае чистить при деплое.

**Фикс.** Ключ версии в имени файла (`routes.<hash>.compiled.php`, hash от mtime роут-файлов + списка модулей); то же для DI; `composer cache:clear` в deploy-хуке. Дополнительно: логировать причину, когда `exportCompiled()` вернул `null` (Closure-хендлер) — сейчас prod тихо теряет кэш.

**Приёмка.** Тест: изменение роут-файла меняет имя кэша и маршрут появляется без ручной очистки.

---

## 7. Карточки группы B (сжато)

| ID | Фикс | Приёмка |
|---|---|---|
| `SEC-01` | Один резолвер окружения вместо 7 точек (`config/app.php`, `config/session.php`, `Application`, DI-фабрика роутера, `bootstrap.php`, `EngineStatsService` — все `'dev'`; `ErrorHandler` — `'prod'`). Рендер **fail-closed в prod**; отсутствие `APP_ENV` → CRITICAL в лог + флаг в readiness; `APP_DEBUG` отдельным явным флагом. **Особый риск:** `config/session.php` при отсутствии `APP_ENV` даёт `secure=false` | Тест: без `APP_ENV` страница ошибки без путей/стека **и** cookie с `Secure` |
| `SEC-02` | `app/Middleware/SecurityHeadersMiddleware.php` + `app.global_middleware` в `Application::run`. Заголовки: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, `Permissions-Policy`; HSTS только при HTTPS | Тест: каждый ответ содержит набор |
| `SEC-04` | Всегда `password_verify($password, $dummyHash)` при отсутствии пользователя — **dummy того же алгоритма и стоимости**, иначе канал сохранится. В логе — хеш email вместо email. Per-IP жёсткий лимит + per-нормализованный-email (`mb_strtolower`+`trim`) **эскалация трения** (captcha/задержка), **не блокировка** | Тест: `authenticate` вызывает `verifyPassword` и для неизвестного email; лог не содержит email |
| `SEC-05` | Глобальный мягкий лимит через `app.global_middleware`; отдельный бакет для POST/PUT/PATCH/DELETE; `validateOrFail` + `max:1000` в `HomeController::submit` | Тест: `POST /` сверх лимита → 429 |
| `SEC-06` | `Server-Timing` только при debug или для авторизованного админа | Тест: prod-ответ без заголовка |
| `SEC-07` | Ротация CSRF вместе с `regenerate(true)` в `login`/`logout` (`SecurityInterface::rotateCsrfToken()`). Задокументировать: форма входа во второй вкладке станет невалидной | Тесты: токен до ≠ после логина; после logout ≠ до; старый токен отклоняется; `regenerate(true)` сохранён |
| `SEC-08` | Проверять результат `auth()`; `resetConnection()` вызывает `close()`; TLS-опции для Redis/MySQL. **Условно:** если когда-либо pconnect — сначала чинить `select($database)` (вызывается только при `> 0`) | Тест: неверный пароль → явное исключение; reset закрывает соединение |
| `ERR-01` | Разделить: (а) **доказано** — shutdown-хенджер не знает, отправлен ли ответ; фатал после `send()` дописывает тело к 200 при `headers_sent()`. (б) **гипотеза** — двойной рендер при uncaught exception. Добавить флаг состояния ответа; при `headers_sent()` — только лог | Тест (subprocess) на (б): `proc_open(PHP_BINARY, ['-r', $code])`, зарегистрировать handler, бросить исключение, `assertSame(1, substr_count($stdout, '<!DOCTYPE html>'))`. Прогнать на матрице 8.2/8.5 |
| `OPS-03` | Ротация по дню/размеру; в prod только `error`; sampling; request-id в каждой строке; redaction PII | Тест: prod-запрос не пишет info-строку |
| `OPS-01` | **Веб-сервер:** статика `/assets/*` — `expires 1y immutable`, gzip/brotli. **Приложение:** только `Cache-Control` (nginx не знает, авторизован ли запрос) и ETag для генерируемого HTML. Self-host шрифтов (приватность + LCP, не P0). **Предусловие выполнено:** `SES-01` больше не шлёт `Set-Cookie` анониму, значит CDN/прокси теперь может кэшировать | Анонимная главная отдаёт 304 при повторном `If-None-Match` |
| `OPS-06` | `/metrics` (Prometheus, закрыт токеном), readiness с проверкой БД/Redis, `X-Request-Id` в ответе и логах | Тест: readiness отражает отказ бэкенда |

---

## 8. Карточки группы D (латентные)

> **Предусловие для всех `CACHE-*`:** в дампе **нет ни одного потребителя `CacheInterface` вне тестов**. Ни один контроллер или сервис не пишет в кэш. Чинить до первого потребителя, не после.

### CACHE-01 · журнал failover сериализует пул воркеров

**Файлы:** `CacheFailoverJournal::withLock/update/readEntries/writeEntries/replay`, `FailoverCache::recoverPendingWrites`

При отказе Redis каждый `get/set/delete` идёт через **блокирующий** `LOCK_EX` на одном файле; внутри — `unserialize` всего журнала и перезапись всего файла. `replay(10)` выполняется внутри пользовательского запроса (до 20 сетевых операций под локом). При 10 000 отложенных ключей — O(N²) на дренаж и полная сериализация FPM-воркеров **во время аварии**.

**Фикс:**
```php
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    return null;   // вызывающий трактует как «replay не выполнен», отдаём fallback
}
```
+ replay в CLI (`bin/cache-replay.php`, cron, пакет 500–1000, бюджет времени); в HTTP-запросе только чтение из fallback;
+ шардирование журнала или append-only + компакция;
+ `Q-10`: внедрить `TimeDateInterface`.

**Приёмка.** При занятом локе `get()` возвращает fallback без ожидания (< 50 мс). `FailoverCacheTest` остаётся зелёным (§16).

### CACHE-05 · гонка чтения файлового кэша

Запись стала атомарной, чтение — нет:
```php
if (!$this->file->exists($path)) return $default;
$payload = @unserialize($this->file->read($path), ...);
```
`File::read()` бросает `FileException`, если файл исчез между `exists()` и `read()` (конкурентный `delete()`, в т.ч. при истечении TTL). Исключение не ловится ни в `FileCache::get`, ни вокруг `$this->fallback->*` в `FailoverCache` → **500**.

```php
try {
    $raw = $this->file->read($path);
} catch (FileException) {
    return $default;   // конкурентное удаление — это miss, не ошибка
}
```
+ обернуть fallback-вызовы в `FailoverCache` в try/catch.

### CACHE-06 · `tempnam()` молча уходит в системный temp

Если каталог существует, но не writable, `tempnam($directory, …)` вернёт путь в `sys_get_temp_dir()`, а `rename()` через границу ФС даст `EXDEV`. Проверять `dirname($temporaryPath) === $directory`.

### Прочие латентные

| ID | Фикс |
|---|---|
| `CACHE-04` | GC файлов кэша + шардирование каталога |
| `CACHE-07` | Привести `RedisCache::get` к тому же виду, что `FileCache::get` (`@unserialize` + `array_key_exists('data')`) |
| `HTTP-01` | Лимит по `Content-Length` (дефолт 1 МБ) → `HttpException(413)`; потоковое чтение для не-POST; не читать `php://input` для GET/HEAD. **Уточнение:** для POST тело уже ограничено `post_max_size`; вектор — именно PUT/PATCH/DELETE |
| `VAL-01` | Bail на первой ошибке поля; как минимум пропускать `unique`/`exists`, если поле уже невалидно |
| `OPS-07` | `DataBaseInterface::beginTransaction/commit/rollBack/transaction(callable)`; retry на разрыв; `reset()` у stateful-сервисов (`Validator`, `View`, `RequestMetrics`) — разблокирует RoadRunner/FrankenPHP |
| `RL-05` | При 429 на POST проверять наличие GET-пары маршрута; иначе отдавать 429 напрямую |

---

## 9. Целевая модель расширяемости (группа C)

**Контракт модуля** (`modules/<Module>/module.php`):

```php
<?php

declare(strict_types=1);

return [
    'name'        => 'Admin',
    'version'     => '1.0.0',
    'definitions' => __DIR__ . '/config/dependency-rules.php',
    'config'      => __DIR__ . '/config',
    'routes'      => __DIR__ . '/routes.php',
    'migrations'  => __DIR__ . '/database/migrations',
    'assets'      => __DIR__ . '/public',
    'middleware'  => [],
];
```

| ID | Изменение |
|---|---|
| `EXT-02` | Перенести биндинги `AdminUserService` / `EngineStatsService` в `modules/Admin/config/dependency-rules.php`. Ядро перестаёт знать о модулях |
| `EXT-04` | `View::resolvePath`: цепочка `sites/<Site>/views/vendor/<Module>/<path>` → `modules/<Module>/views/<path>` |
| `EXT-05` | `Application` собирает манифесты (`glob(modules/*/module.php)`), фильтрует по allowlist (`EXT-01`), мержит definitions/config, регистрирует маршруты |
| `EXT-06` | `bin/create-site.php <Name>`, `bin/create-module.php <Name>` — генерируют структуру из инструкции на главной |
| `EXT-07` | Колонка `role` в `admin_users` + `AdminRoleMiddleware`/`Gate`; инфраструктурные блоки дашборда — только для `role=owner` |
| `EXT-08` | `TranslatorInterface` + `lang/ru.php`, `lang/en.php` |
| `EXT-09` | `modules/<M>/database/migrations/*.sql` + `bin/migrate.php`; `create-admin.php` перестаёт быть источником схемы |
| `QA-02` | PHPStan level 8 в CI; `declare_strict_types` (risky allowed); `composer audit` |

---

## 10. Эталон прод-конфигурации

```dotenv
APP_ENV=prod
APP_DEBUG=false
APP_SITE=Corporate
CACHE_DRIVER=redis
CACHE_REDIS_FALLBACK=true
RATE_LIMIT_DRIVER=redis
SESSION_DRIVER=redis
SESSION_SECURE=true
TRUSTED_PROXIES=172.18.0.0/16    # реальная подсеть прокси, НЕ "*" и НЕ 127.0.0.1 в Docker
REDIS_PREFIX=vengine:            # site добавляется автоматически (CACHE-02)
REDIS_CONNECT_TIMEOUT=0.3
REDIS_TIMEOUT=0.2
DB_CONNECT_TIMEOUT=2
```

**Deploy-хук (обязателен после `OPS-05`):**
```bash
composer install --no-dev --optimize-autoloader
php bin/migrate.php            # после EXT-09
php bin/assets:publish.php     # после EXT-03
php bin/cache-clear.php --all-sites
# opcache_reset через FPM reload
```

---

## 11. Отложено до измерений (группа E)

**`OPS-02`.** Сейчас `RequestMetrics` пишет `redis`/`db`, но время коннекта растворено в первой операции. Выделить `redis.connect` / `db.connect` отдельными компонентами — решение по цифрам.

**Условный баг, если pconnect будет внедряться:** `RedisClient::connection()` вызывает `select($database)` только при `$database > 0`. С pconnect соединение, оставшееся на DB 3, будет отдано запросу, ожидающему DB 0. Плюс `resetConnection()` не закрывает сокет — пул получит потенциально битое соединение. Чинить **до** включения pconnect.

**`OPS-04`.** Fast-path `array` для статических путей, затем chunked-regex. Отдельно — функциональные пробелы, не оптимизация: 405 при совпадении пути с другим методом, HEAD→GET (важно для мониторинга и линк-чекеров), 301-канонизация слеша, `rawurldecode` параметров **после** матчинга.

---

## 12. План по фазам

```
Фаза 1 (A, живое)        SEC-03 → ERR-02 → STATS-01 → CFG-01
                         RL-03 · RL-04 · SES-03 · SES-04 · SES-05
                         EXT-01 · EXT-03 · OPS-05
        │                └─ STATS-01 и CFG-01 — один PR (одна тема: драйверы)
        ▼
Фаза 2 (B, hardening)    SEC-01 → ERR-01 → SEC-02 → SEC-06
                         SEC-04 · SEC-05 · SEC-07 · SEC-08 · OPS-03 · OPS-01 · OPS-06
        │                └─ SEC-05 требует SEC-02 (глобальный конвейер)
        │                └─ OPS-01 требует SES-01 (выполнено)
        ▼
Фаза 3 (D, до потребителей)  CACHE-05 · CACHE-06 · CACHE-07 · CACHE-04 · CACHE-01
                             HTTP-01 · VAL-01 · OPS-07 · RL-05
        │                    └─ выполнять ДО первого модуля, пишущего в кэш
        ▼
Фаза 4 (C, расширяемость)  EXT-05 → EXT-02 · EXT-04 · EXT-06 · EXT-09 · EXT-07 · EXT-08 · QA-02
        │                    └─ EXT-05 требует EXT-01
        ▼
Фаза 5                     F-правки одним PR (Q-01…Q-12, MISC-01, SES-06/07, TEST-01)
        ▼
Фаза 6 (E)                 измерения → решение по OPS-02 / OPS-04
```

| Фаза | Definition of Done |
|---|---|
| 1 | Дашборд показывает реальные зависимости; 404 отдаёт 404; новый сайт без Admin не открывает `/admin/login`; деплой не требует ручной очистки кэша |
| 2 | Ни один prod-ответ не содержит путей/стека; все ответы несут security-заголовки; логи ротируются; анонимная главная отдаёт 304 |
| 3 | Нагрузочный прогон `http_smoke.py --workers 16 --duration 60`: 0 failures; при выключенном Redis p95 не деградирует > 2×; повреждённый/удалённый файл кэша = miss, не 500 |
| 4 | Новый модуль и сайт создаются одной командой, без правок `src/` и `config/` |
| 5 | `composer cs:check` + `composer test` зелёные; PHPStan в CI |
| 6 | Решение по pconnect/роутеру принято по цифрам, а не по вкусу |

---

## 13. Матрица «фикс → тест»

| ID | Тест |
|---|---|
| `SEC-03` | `tests/Unit/RequestTest.php` — дополнить кейсами прокси и IPv6 |
| `ERR-02` | `tests/Unit/ErrorRendererTest.php` (новый) |
| `ERR-01` | `tests/Unit/ErrorHandlerTest.php` (новый, subprocess) |
| `STATS-01` | `tests/Unit/EngineStatsServiceTest.php` — кейс `rate-limit=redis` |
| `CFG-01` | `tests/Unit/RateLimitConfigTest.php` — согласованность примера |
| `RL-03` | `tests/Unit/FailoverRateLimiterTest.php` — два идентификатора при флаппинге |
| `RL-04` | `tests/Unit/FileRateLimiterGcTest.php` (новый) |
| `SES-03/04/05` | `tests/Unit/SessionRuntimeTest.php` (новый) |
| `SEC-01` | `tests/Unit/EnvironmentResolverTest.php` (новый) |
| `SEC-02` | `tests/Unit/SecurityHeadersMiddlewareTest.php` (новый) |
| `SEC-04` | `tests/Unit/AdminUserServiceTest.php` (новый) |
| `SEC-07` | `tests/Unit/CsrfRotationTest.php` (новый) |
| `EXT-01` | `tests/Unit/ApplicationSiteTest.php` — allowlist-кейс |
| `OPS-05` | `tests/Unit/RouteCacheInvalidationTest.php` (новый) |
| `CACHE-05/06` | `tests/Unit/FileCacheTest.php` — конкурентное удаление, не-writable каталог |

---

## 14. Команды валидации

```bash
php -l <каждый изменённый PHP-файл>
vendor/bin/phpunit tests/Unit/<TestClass>.php
composer cs:check
composer test
```

Интеграционные (при поднятых MySQL/Redis):
```bash
CI_INTEGRATION=1 DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root \
REDIS_HOST=127.0.0.1 CACHE_DRIVER=redis composer test
```

Нагрузочная проверка:
```bash
php -S 127.0.0.1:8000 -t sites/Corporate/public &
python3 tests/load/http_smoke.py --url http://127.0.0.1:8000/ --workers 16 --duration 60
```

> **Правило отчётности (`copilot-instructions.md`):** сообщай только те команды, которые реально запустил, и их фактический результат. Не заявляй «прошло», если не запускал. **Аудитор команд не запускал.**

---

## 15. Инварианты — что нельзя ломать

Эти решения **уже правильные**. Не «рефактори» их:

- `Session::regenerate(true)` при входе и выходе — защита от session fixation.
- `Session::hasSessionToRead()` — lazy-чтение flash без cookie (`SES-01`). Не убирать.
- `use_strict_mode=1` + per-site Redis-префикс — защита от кросс-сайтового чтения сессии.
- **Отсутствие failover у Redis-сессий** — намеренно. Сессия авторитетна, dual-write недопустим.
- `hash_equals` в `Security::verifyCsrfToken` и `AuthMiddleware`.
- Все SQL — через PDO-плейсхолдеры; `Validator::sanitizeIdentifier` (`^[a-zA-Z0-9_]+$`) защищает интерполяцию имён таблиц.
- `View::escape()` (`ENT_QUOTES | ENT_SUBSTITUTE`), `View::old()` экранирует, шаблоны экранируют flash.
- `Validator::validateOrFail` возвращает **только** описанные в правилах поля — защита от mass assignment.
- `AdminAuthMiddleware` читает сессию и сразу `close()` — образец (но см. `SES-07`).
- `FileCache::set` — атомарная запись `tempnam+rename`; `get` — `@unserialize` + проверка `data`.
- `RedisRateLimiter` — атомарный Lua-скрипт `INCR`+`EXPIRE`+`TTL`.
- `Router::$routesByMethod` — индекс по методу; `Route::compilePattern` экранирует литералы через `preg_quote`.
- `FileRateLimiter` создаёт каталог `0700`, файл `0600`.
- `tests/load/http_smoke.py` ограничен по duration/workers, требует `--allow-remote` для нелокальных URL.
- Границы `src/` ↔ `app/` ↔ `sites/` ↔ `modules/`; интерфейсы + биндинги; `declare(strict_types=1)`.

---

## 16. Ловушки: тесты, которые сломаются от «очевидных» улучшений

| Изменение | Что сломается | Как обойти |
|---|---|---|
| Шардирование кэша в 2 уровня (`data/ab/file.cache`) | `FailoverCacheTest::tearDown` чистит только один уровень вложенности | Обновить tearDown на рекурсивный обход **в том же PR** |
| Шардирование rate-limit файлов | `FailoverRateLimiterTest::tearDown` делает `unlink` по плоскому glob | То же — рекурсивный обход |
| Создание каталога/файла журнала «заранее» | `FailoverCacheTest` ассертит `assertFileDoesNotExist(pending.dat)` и `assertDirectoryDoesNotExist(data)` на здоровом пути | Не создавать артефакты на healthy-path |
| Исправление грамматики `desc="1 calls"` (`Q-07`) | `RequestMetricsTest` явно ассертит `desc="1 calls"` | Менять код и тест одновременно |
| Дефолт `APP_ENV=prod` (`SEC-01`) | `ApplicationSiteTest`, `EngineStatsServiceTest`, `SessionConfigTest` мокают/подменяют env | Явно устанавливать env в `setUp`/`tearDown` новых тестов |
| Любой тест, стартующий сессию раньше `SessionFlashTest` | `SessionFlashTest` ассертит `session_status() === PHP_SESSION_NONE` как предусловие (`TEST-01`) | Заменить на skip/guard или изолировать процесс |
| Смена hold с per-IP на глобальный | `FailoverRateLimiterTest` проверяет per-identifier поведение | Тест использует один identifier — проходит; добавить кейс с двумя IP |
| Удаление второго `isFailoverActive` (`RL-03`) | **Ничего не ломает** — проверено по обновлённым тестам | — |

---

## 17. Что уже хорошо

Failover-архитектура кэша и rate-limit с журналом — сильная идея; проблема только в **месте исполнения** (request-path вместо фонового воркера) и гранулярности лока. Индекс маршрутов по методу, атомарная запись журнала и кэша через `tmp+rename`, ограниченный размер пакета replay, bounded load-скрипт, чёткие границы слоёв, site-namespace в Redis и lazy-сессии — база, на которой стоит строить, а не заменять.

---

## 18. Открытые вопросы к владельцу

1. **Redis в prod:** используется, локальный или удалённый, shared между сайтами? → определяет вес `CACHE-*`, `RL-03`, `SEC-08`.
2. **Топология:** адрес прокси относительно PHP (Docker/Nginx/балансировщик)? → определяет, живёт ли `SEC-03` в вашем деплое.
3. **Планируется ли потребитель кэша** в ближайших модулях? → определяет, чинить `CACHE-*` сейчас или зафиксировать как предусловие.
4. **Целевой профиль нагрузки:** RPS пик, доля анонимного трафика, число сайтов на инстанс?
5. **Long-running runtime** (RoadRunner/FrankenPHP) в планах? → приоритет `OPS-07`.
6. **Роли и i18n** нужны в ближайших 2–3 модулях? → приоритет `EXT-07`/`EXT-08`.
7. **Семантика сессии:** idle или absolute timeout? → решение по `SES-05`.

---

**Рекомендуемый первый PR:** `STATS-01` + `CFG-01` одной темой («драйверы Redis: конфигурация и наблюдаемость») — дёшево, детерминированно, закрывает ложную картину инфраструктуры. Затем `SEC-03` и `ERR-02` — единственные оставшиеся пункты с отказом сайта и неверными ответами на каждом 404.# AUDIT.md — V-engine: аудит и план исправления

> **Назначение:** самодостаточный контекст для ИИ-агента или нового разработчика. Прочитав только этот файл, можно понять архитектуру, найти проблему по ID, применить фикс и проверить его тестом — без повторного анализа репозитория.
>
> **Дата:** 2026-11-20 · **Объект:** `vampqwe/v-engine` · **Основание:** дамп `vampqwe-v-engine-8a5edab282632443.txt` (ревизия с `RedisNamespace`, `Session.php`, `RATE_LIMIT_DRIVER`)
> **Фокус:** рост трафика · безопасность · скорость добавления модуля и сайта
> **Метод:** статическое чтение исходников. **Ни одна команда валидации не запускалась аудитором.** Все утверждения проверены по коду дампа; непроверяемые помечены `⚠ VERIFY`.

---

## 0. Как читать этот документ

| Правило | Значение |
|---|---|
| ID вида `RL-03` | Стабильный идентификатор. Используй в ветке, коммите, тесте: `fix/RL-03-double-failover-check` |
| Приоритет | Строится по **достижимости сегодня × радиусу поражения × уверенности в доказательстве**, а не по категории |
| Группы | `A` живые дефекты · `B` hardening прода · `C` расширяемость · `D` латентные (есть предусловие) · `E` отложено до измерений · `F` быстрые правки |
| Пути | От корня репозитория. **Номера строк не приводятся намеренно** — ищи по `Класс::метод()` |
| `⚠ VERIFY` | Нельзя подтвердить по дампу. Проверь в реальном файле до изменения |
| Порядок | Фазы в §12 упорядочены по зависимостям. Не начинай `C` раньше `A` |
| Обязательно | Прочитай §15 (инварианты) и §16 (ловушки тестов) **до** первого изменения |

**Формат карточки:** `Файл → Механика → Влияние → Фикс → Приёмка`.

---

## 1. Контекст проекта

```
PHP >= 8.2 · Composer · PHP-DI 7 · vlucas/phpdotenv 5 · PHPUnit 11 · PHP-CS-Fixer (@PER-CS)
MySQL (PDO, ATTR_EMULATE_PREPARES=false) · Redis (ext-redis, опционально)
```

**PSR-4:** `VEngine\`→`src/`, `App\`→`app/`, `Sites\`→`sites/`, `Modules\`→`modules/`, `Tests\`→`tests/`.

**Жизненный цикл запроса:**

```
sites/<Site>/public/index.php
  └─ bootstrap.php            ob_start(); BASE_PATH; dotenv->safeLoad(); new Application; run()
       └─ Application::__construct   валидирует APP_SITE, собирает $paths[], строит DI-контейнер
            └─ Application::run      ErrorHandler->register(); Request; RequestMetrics; Router->dispatch(); Response->send()
                 └─ Router::dispatch routesByMethod[METHOD] → Route::matches (preg_match) → runPipeline
                      └─ middleware[] → Invoker->call(handler) → ResponseInterface
```

| Подсистема | Реализация | Особенность |
|---|---|---|
| Мультисайт | `APP_SITE` → `sites/<Site>/`; один инстанс = один сайт | Файловый кэш изолирован: `storage/cache/sites/<Site>/` |
| Модули | `glob(modules/*/routes.php)` в DI-фабрике роутера | Грузятся **для всех сайтов безусловно** (`EXT-01`) |
| Кэш | `CACHE_DRIVER=file\|redis`; при redis — `FailoverCache(RedisCache, FileCache, Journal)` | **Нет ни одного потребителя `CacheInterface` вне тестов** |
| Rate limit | `RATE_LIMIT_DRIVER=file\|redis`; при redis — `FailoverRateLimiter(Redis, File, hold=300)` | Потребители: `/api/*` группа, `POST /admin/login` |
| Redis namespace | `RedisNamespace::forSite($prefix, $site)` | Применяется в `RedisClient` и в `Session::configureRedisHandler` (+`sess:`) |
| Роутер | Индекс по HTTP-методу + линейный `preg_match` внутри метода | prod-кэш `routes.compiled.php`, экспорт невозможен при Closure-хендлере |
| Сессии | `Session`, драйвер `file\|redis`, lazy `getFlash()` | Redis-сессии — авторитетное состояние, failover отсутствует намеренно |
| Шаблоны | `View::renderFile` — `extract(EXTR_SKIP)` + `require` в closure, `$this` = View | `@Module/path` → `modules/<M>/views/` |
| Метрики | `RequestMetrics` → заголовок `Server-Timing` | Уходит всем клиентам (`SEC-06`) |

---

## 2. Ограничения дампа

> **Главный урок предыдущей ревизии:** отсутствие файла в дампе **не означает** его отсутствие в репозитории. Дамп.lossy: `README.md` помечен `[Binary file]`, `AUDIT.md` пришёл пустым.

| Не видно в дампе | Статус |
|---|---|
| `README.md` | Помечен `[Binary file]`. Конвенции взяты из `.github/copilot-instructions.md` |
| `bin/create-admin.php`, `bin/cache-clear.php` | **Подтверждены владельцем как существующие.** `create-admin.php` создаёт таблицу `admin_users` |
| `composer.lock`, `.gitignore` | **Подтверждены владельцем как существующие** |
| Схема БД вне `bin/create-admin.php` | Общей системы миграций нет — это реальный пробел (`EXT-09`), но не блокер сборки |

**Правило для агента:** прежде чем заявить «файл отсутствует», запроси подтверждение. Ошибка `QA-01` в прошлой ревизии произошла именно так.

---

## 3. Что уже исправлено (верифицировано по коду и тестам)

| ID | Фикс | Подтверждение в коде | Тест |
|---|---|---|---|
| `RL-01` | GET/HEAD → прямой `429`; HEAD с пустым телом; POST оставлен на PRG-редирект | `RateLimitMiddleware::buildTooManyRequestsResponse` | `RateLimitMiddlewareTest::testHtmlGetRateLimitReturns429InsteadOfRedirectingToTheSameUri` |
| `SES-01` | `getFlash()` не стартует сессию без входящего cookie | `Session::hasSessionToRead()` | `SessionFlashTest::testReadingFlashWithoutAnIncomingSessionDoesNotStartOne` |
| `RL-02` | `RATE_LIMIT_DRIVER` конфигурируется и валидируется; `file`-ветка не конструирует Redis | `config/rate-limit.php`, `match` в DI | `RateLimitConfigTest` (4 кейса) |
| `RL-02a` | `clear()` чистит **оба** бэкенда | `FailoverRateLimiter::clear` | `FailoverRateLimiterTest::testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive` |
| `CACHE-02` | Site-namespace в Redis, включая сессии (`vengine:Corporate:sess:`) | `RedisNamespace`, `RedisClient::connection`, `Session::configureRedisHandler` | `RedisNamespaceTest` |
| `CACHE-03` | Атомарная запись `tempnam+rename`; `@unserialize` + проверка ключа `data` | `FileCache::set/get` | `FileCacheTest::testWritesToTemporaryFileBeforeReplacingTheCacheEntry` |

**Два неочевидных момента, сделанных правильно:**

1. `@unserialize` корректно взаимодействует с `ErrorHandler::set_error_handler`: в PHP 8 оператор `@` маскирует `E_WARNING`, проверка `error_reporting() & $severity` возвращает `false`, исключение не бросается. Фикс действительно даёт cache-miss, а не 500.
2. `use_strict_mode=1` + per-site префикс Redis означают, что общий `VENGINE_SESSID` между сайтами на одном домене не даёт кросс-сайтового чтения сессии — чужой ID отвергается. Это лучше, чем переименование cookie.

**Чек-лист `SES-02`, подтверждённый чтением `Session.php`:** `use_strict_mode=1` ✅, `use_only_cookies=1` ✅, `gc_maxlifetime = lifetime` ✅, cookie-параметры применяются **до** `session_start()` ✅, `remove()` после `close()` работает (повторный `ensureStarted()` при живом ob-буфере) ✅. Остаток — `SES-03…SES-07`.

---

## 4. Отозвано — не воскрешать

| ID | Что было предложено | Почему отозвано |
|---|---|---|
| `QA-01` | «Проект не собирается с нуля» | Артефакт дампа. `bin/*`, `composer.lock`, CI существуют. Остаётся только отсутствие миграций (`EXT-09`) |
| `SES-02-failover` | File-failover для Redis-сессий | **Кэш неавторитетен, сессия авторитетна.** Dual-write позволяет восстановить уничтоженную сессию из второго бэкенда. HA решать Sentinel/replication, не в приложении |
| `SEC-01-example` | Менять `APP_ENV=dev` в `.env.example` | `dev` в примере — нормальное локальное значение. Дефект в коде, не в примере (`SEC-01`) |
| `OPS-02-pconnect` | Внедрять `pconnect` / `PDO::ATTR_PERSISTENT` | При FPM число соединений ограничено пулом; выигрыш не оправдывает риски состояния. Сначала измерить (`E`) |
| `OPS-04-wording` | «Роутер линейно сканирует все маршруты» | Индекс по методу есть. Линейный поиск — только внутри метода, при ~9 маршрутах незначим |

---

## 5. Реестр открытых находок

### A — живые дефекты (достижимы сегодня)

| ID | Файл::метод | Симптом |
|---|---|---|
| `SEC-03` | `Request::ip/isFromTrustedProxy` | Читает `$_SERVER`; дефолт прокси ломает Docker; `'*'` = спуфинг |
| `ERR-02` | `ErrorRenderer::renderProduction` | 404 отдаёт тело «500»; API получает HTML |
| `STATS-01` | `EngineStatsService::redisStatus` | Дашборд показывает Redis «не используется», хотя rate limiter на нём |
| `CFG-01` | `.env.example`, `config/rate-limit.php` | `CACHE_DRIVER=file` + `RATE_LIMIT_DRIVER=redis` — пример не самосогласован |
| `RL-03` | `FailoverRateLimiter::hit` | Второй `isFailoverActive()` → двойной подсчёт при флаппинге + лишний файловый лок |
| `RL-04` | `FileRateLimiter::withState` | Файл на каждую пару (bucket, IP), нет GC, `chmod` на каждый вызов, плоский каталог |
| `SES-03` | `Session::start` | `gc_probability`/`gc_divisor` не заданы → просроченные сессии могут не удаляться |
| `SES-04` | `Session::start/configureRedisHandler` | Отказ Redis-сессий → 500, а не 503 |
| `SES-05` | `Session::start` | `cookie lifetime = 7200` = абсолютный таймаут, не idle; не задокументировано |
| `EXT-01` | DI-фабрика `RouterInterface` | Каждый новый сайт автоматически открывает `/admin/login` |
| `EXT-03` | `modules/Admin/views/layout.php` | Admin грузит `/assets/css/admin.css` из сайта Corporate |
| `OPS-05` | DI-фабрика роутера, `Application::buildContainer` | Кэш маршрутов/DI не инвалидируется при деплое |

### B — hardening прода

| ID | Файл::метод | Симптом |
|---|---|---|
| `SEC-01` | 7 точек чтения `APP_ENV` | 6 дефолтов `'dev'`, 1 `'prod'`; при отсутствии настройки — debug + небезопасная cookie |
| `SEC-02` | `Application::run` | Нет security-заголовков и нет глобального middleware-конвейера |
| `SEC-04` | `AdminUserService::authenticate`, `AuthController::login` | Timing-enumeration email; email в логах; нет per-account трения |
| `SEC-05` | `sites/Corporate/routes/web.php`, `HomeController::submit` | `POST /` без лимита, валидации и ограничения длины |
| `SEC-06` | `Application::run` | `Server-Timing` раскрывает тайминги всем |
| `SEC-07` | `Security::csrfToken`, `AuthController::login/logout` | CSRF-токен не ротируется при смене привилегий |
| `SEC-08` | `RedisClient::connection/resetConnection` | `auth()` не проверяется; `resetConnection()` без `close()`; нет TLS |
| `ERR-01` | `ErrorHandler::register/handleException` | Нет состояния жизненного цикла ответа; гипотеза двойного рендера не доказана |
| `OPS-03` | `Logger::writeLog`, `Application::run` | flock на строку, dev-лог каждого запроса, нет ротации и уровней |
| `OPS-01` | `.htaccess`, `layouts/main.php`, `Response` | Нет `Cache-Control`/ETag; gzip/expires не заданы; внешние Google Fonts |
| `OPS-06` | `RequestMetrics`, `ApiController::health` | Нет `/metrics`, readiness, request-id |

### C — расширяемость

| ID | Файл | Симптом |
|---|---|---|
| `EXT-02` | `config/dependency-rules.php` | Ядро хардкодит `Modules\Admin\Services\*` |
| `EXT-04` | `View::resolvePath` | Сайт не может переопределить шаблон модуля |
| `EXT-05` | `Application`, DI-фабрика роутера | Нет манифеста модуля (config/DI/migrations/assets) |
| `EXT-06` | `bin/` | Нет скаффолдеров сайта и модуля |
| `EXT-07` | `AdminAuthMiddleware` | Нет ролей/прав — только «админ или не админ» |
| `EXT-08` | шаблоны, `Validator` | Строки захардкожены по-русски, нет i18n |
| `EXT-09` | `bin/create-admin.php` | Схема `admin_users` живёт в CLI-скрипте; нет версионированных миграций |
| `QA-02` | `.php-cs-fixer.dist.php`, `ci.yml` | Нет PHPStan, `strict_types` не enforced, нет `composer audit` |

### D — латентные (есть предусловие активации)

| ID | Предусловие | Симптом |
|---|---|---|
| `CACHE-01` | Первый потребитель кэша + Redis в prod | Глобальный блокирующий flock журнала + O(N) перезапись + inline-replay |
| `CACHE-04` | Первый потребитель `FileCache` | Нет GC файлов кэша |
| `CACHE-05` | Первый потребитель `FileCache` | Гонка `exists()→read()` → `FileException` → 500; fallback в `FailoverCache` не обёрнут |
| `CACHE-06` | Каталог кэша не writable | `tempnam()` молча уходит в системный temp → `EXDEV` при `rename` |
| `CACHE-07` | Первый потребитель `RedisCache` | `get()` без `@unserialize` и без проверки `data` — асимметрия с `FileCache` |
| `HTTP-01` | Первый `PUT/PATCH/DELETE` маршрут | Тело читается в память без лимита (`post_max_size` на не-POST не действует) |
| `VAL-01` | Первое правило `unique`/`exists` | Нет bail → запрос к БД даже при невалидном значении |
| `OPS-07` | Первый модуль с многошаговой записью | Нет транзакций в `DataBaseInterface`; stateful-синглтоны блокируют long-running |
| `RL-05` | Rate limit на POST-only маршруте | 302 на URI без GET-пары → 404 |

### E — отложено до измерений

| ID | Что мерить | Порог возврата |
|---|---|---|
| `OPS-02` | Выделить `redis.connect` / `db.connect` в `RequestMetrics` | Доля connect > 10 % p95 при удалённом бэкенде |
| `OPS-04` | Доля времени матчинга маршрутов | > 50–100 GET-маршрутов **и** измеримая доля в p95 |

### F — быстрые правки

| ID | Файл::метод | Проблема |
|---|---|---|
| `Q-01` | `View::error` | Возвращает **неэкранированную** строку (`old()` — экранирует) |
| `Q-02` | `Response::json` | Нет `JSON_INVALID_UTF8_SUBSTITUTE` → 500 на битом UTF-8 |
| `Q-03` | `AdminAuthMiddleware::process` | `ctype_digit((string)$id)` пропускает `true`/`1.0` |
| `Q-04` | `EngineStatsService::databaseStats` | Ловит только `DataBaseException` |
| `Q-05` | `TimeDate` | Нет явной таймзоны → логи и дашборд расходятся |
| `Q-06` | `Router::normalizePath` | Мёртвая ветка `=== ''` |
| `Q-07` | `RequestMetrics::serverTiming` | `desc="1 calls"` — **тест фиксирует строку**, см. §16 |
| `Q-08` | `Response::withHeader` | Нет валидации имени/значения заголовка |
| `Q-09` | `FileCache::set` | Не отсекает `ttl <= 0` (в отличие от `RedisCache`) |
| `Q-10` | `CacheFailoverJournal::recordSetWithFallback` | `time()` вместо внедрённого `TimeDateInterface` |
| `Q-11` | `View::resolvePath` | Однопроходный фильтр `..`; добавить `realpath()`-containment |
| `Q-12` | `RedisNamespace::forSite` | Валидирует только непустоту; charset проверяется лишь в `Application` |
| `MISC-01` | `RedisClient::connection` | Берёт `APP_SITE` из env, а не из DI-параметра `path.site` |
| `SES-06` | `Session::start/destroy` | `sid_length`/`sid_bits` не заданы (дефолт 32/4 = 128 бит); `destroy()` шлёт cookie без `samesite` |
| `SES-07` | `View::flash` + `AdminAuthMiddleware` | Layout переоткрывает сессию после `close()`, обнуляя выигрыш раннего закрытия |
| `TEST-01` | `SessionFlashTest` | Ассертит `session_status() === PHP_SESSION_NONE` как предусловие — хрупко при порядке тестов |

---

## 6. Карточки группы A

### SEC-03 · `Request::ip()` — суперглобал и доверенные прокси

**Файл:** `src/System/Http/Request.php::ip/isFromTrustedProxy`

**Механика.**
```php
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
```
Читается суперглобал вместо внедрённых `$serverParams` — прямое нарушение конвенции проекта («Use `RequestInterface` … rather than reading raw superglobals ad hoc») и причина, по которой `RequestTest` с injected `serverParams` не покрывает `ip()`.

Дефолт `TRUSTED_PROXIES=127.0.0.1,::1`: в Docker/Nginx прокси обычно `172.x` → `X-Forwarded-For` игнорируется → все посетители схлопываются в один IP → глобальный бакет 60/мин исчерпывается на весь сайт. Обратный случай `TRUSTED_PROXIES=*` даёт спуфинг IP и обход брутфорс-лимита админки. Сравнение IPv6 строками: `::1` ≠ `0:0:0:0:0:0:0:1`.

**Влияние.** Единственный оставшийся пункт, который в паре с уже починенным `RL-01` даёт отказ `/api/*` при умеренной нагрузке.

**Фикс.**
```php
// В конструкторе:
$this->remoteAddr = (string) ($serverParams['REMOTE_ADDR'] ?? '127.0.0.1');
// В ip(): использовать $this->remoteAddr
// В isFromTrustedProxy(): нормализация через inet_pton, убрать ветку '*'
```
В `.env.example` описать Docker-сценарий и явно запретить `*`.

**Приёмка.** Тест: `X-Forwarded-For` учитывается при injected `REMOTE_ADDR` доверенного прокси; `*` не принимается; две записи одного IPv6 сравниваются равными.

---

### ERR-02 · 404 отдаёт тело «500», API получает HTML

**Файлы:** `src/System/Error/ErrorRenderer.php::renderProduction`, `ErrorHandler::handleException`

**Механика.** `renderProduction()` возвращает heredoc с `<h1>500</h1>` и «Внутренняя ошибка сервера» **независимо** от `$statusCode`. `RouteNotFoundException` даёт статус 404 с телом 500. `ErrorHandler` всегда ставит `Content-Type: text/html`, поэтому `/api/*` получает HTML.

**Влияние.** Детерминированный дефект на каждом 404: неверное тело, soft-404 для SEO, нечитаемые ошибки для API-клиентов и мониторинга.

**Фикс.** Добавить `$status` в `ErrorRendererInterface::render(Throwable, bool, int $status)`; отдельный рендер для 404/403/429/500; content-negotiation в `ErrorHandler` (JSON для `/api/*` и `Accept: json`).

**Приёмка.** Тест: 404 → тело содержит «404», `Content-Type: text/html`; `/api/missing` → JSON со `status: 404`.

---

### STATS-01 · дашборд не учитывает rate-limit драйвер

**Файл:** `modules/Admin/Services/EngineStatsService.php::collect/redisStatus`

**Механика.** `redisStatus(string $cacheDriver, string $sessionDriver)` возвращает `'not_used'`, если оба не `redis`. Про `RATE_LIMIT_DRIVER` метод не знает. При дефолтном `.env.example` (`file` / `file` / `redis`) дашборд показывает «Redis — Не выбран текущими драйверами», хотя **каждый** rate-limited запрос идёт в Redis.

**Влияние.** Ложная картина инфраструктуры именно в той конфигурации, где Redis является скрытой зависимостью. Дефект появился вместе с фиксом `RL-02`.

**Фикс.**
```php
$rateLimitDriver = (string) $this->config->get('rate-limit', 'driver', 'redis');
$redisStatus = $this->redisStatus($cacheDriver, $sessionDriver, $rateLimitDriver);
```
+ в карточке «Сессии»/«Redis» показывать, какие драйверы реально используют Redis.

**Приёмка.** Тест: `cache=file, session=file, rate-limit=redis` → `redis_status !== 'not_used'`.

---

### CFG-01 · пример конфигурации не самосогласован

**Файлы:** `.env.example`, `config/rate-limit.php`

**Механика.** `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, но `RATE_LIMIT_DRIVER=redis`; дефолт в коде тоже `redis`. Комментарий рядом гласит «Redis необязателен». Свежая установка без ext-redis по-прежнему платит за Redis на каждом rate-limited запросе — теперь это можно выключить, но пример об этом не говорит.

**Фикс.** В `.env.example` поставить `RATE_LIMIT_DRIVER=file` вровень с `CACHE_DRIVER=file`; рядом комментарием — когда переключать на `redis`. Дефолт в коде оставить как есть (осознанный выбор), но задокументировать.

**Приёмка.** Смоук: копия `.env.example` без ext-redis → ни одной попытки подключения к Redis на `/api/health`.

---

### RL-03 · двойная проверка failover

**Файл:** `src/System/RateLimit/FailoverRateLimiter.php::hit`

**Механика.** После успешного `primary->hit()` снова вызывается `isFailoverActive()`. Если параллельный воркер успел активировать fallback, запрос считается **дважды** (Redis + файл) → преждевременный 429. На здоровом Redis это ещё и второй open+flock+read на каждый запрос.

**Фикс.** Удалить вторую проверку.

**Приёмка.** Проверено по обновлённым тестам: удаление не ломает ни `testUsesFileCountersOnRedisFailureAndKeepsThemUntilTheWindowExpires`, ни `testClearResetsPrimaryAndFallbackCountersWhileFallbackIsActive`. Добавить кейс с двумя идентификаторами при флаппинге.

---

### RL-04 · файловые счётчики растут без GC

**Файл:** `src/System/RateLimit/FileRateLimiter.php::withState`

**Механика.** `<sha256>.json` на каждую пару (bucket, IP), удаления нет. `chmod($path, 0o600)` вызывается на **каждом** обращении, включая read-only `isFailoverActive`. Плоский каталог.

**Влияние.** Живой рост: при увеличении трафика — сотни тысяч мелких файлов, иноды, деградация ext4/overlayfs, лишний syscall на запрос.

**Фикс.** Шардирование по первым 2 символам хеша; `chmod` только при создании; вероятностный GC (1/1000) + cron-чистка просроченных окон.

**Приёмка.** Тест: файл с истёкшим `window_started_at` и `fallback_until` удаляется GC; `chmod` не вызывается при read-only пути. **Обновить `tearDown` в `FailoverRateLimiterTest`** (см. §16).

---

### SES-03 · GC файловых сессий не задан

**Файл:** `src/System/Session/Session.php::start`

**Механика.** Выставляется только `gc_maxlifetime`. `session.gc_probability` / `gc_divisor` не инициализируются, а на Debian/Ubuntu в сборках PHP вероятность по умолчанию 0 (GC вынесен на системный cron).

**Влияние.** Просроченные файлы сессий могут не удаляться никогда. После `SES-01` объём резко меньше, но `/admin/login` сессию создаёт (нужен CSRF), а её сканируют боты.

**Фикс.** Явно `ini_set('session.gc_probability', '1')` + `gc_divisor` (например 1000), либо задокументировать внешнюю уборку и добавить её в deploy/cron.

**Приёмка.** Тест на ini-значения после `start()`; документированный cron-скрипт.

---

### SES-04 · отказ Redis-сессий даёт 500 вместо 503

**Файл:** `src/System/Session/Session.php::start/configureRedisHandler`

**Механика.** При недоступном Redis обработчик сессий либо emits warning (→ `ErrorHandler` → `ErrorException`), либо `session_start()` вернёт `false` (→ `SessionException extends BaseException`). Оба пути → HTTP 500.

**Влияние.** `copilot-instructions.md` требует: «Explicitly define failure behavior for database, Redis, cache, and external-service operations». Для авторитетного состояния 500 — неопределённое поведение.

**Фикс.** Обернуть отказ инициализации сессии в `SessionException`, маппить её в **503** для auth и state-changing маршрутов; анонимные read-only страницы отдавать без сессии. **Не** добавлять файловый failover (см. §4).

**Приёмка.** Тест: драйвер `redis`, обработчик недоступен → 503 на `/admin/login`, анонимная главная отдаётся.

---

### SES-05 · семантика lifetime не описана

**Файл:** `src/System/Session/Session.php::start`

**Механика.** `session_set_cookie_params(['lifetime' => 7200])`. Cookie живёт 2 часа **от выдачи**; PHP не переотправляет её на каждом запросе. Это фактический absolute timeout, а не idle.

**Влияние.** Активный пользователь разлогинится ровно через 2 часа. Как решение — защитимо, но сейчас выглядит как побочный эффект.

**Фикс.** Либо задокументировать как задуманный absolute timeout, либо `lifetime => 0` + опора на `gc_maxlifetime` для idle-семантики. Решение зафиксировать в `config/session.php` комментарием.

**Приёмка.** Тест на выбранный режим + запись в README.

---

### EXT-01 · каждый сайт получает все модули

**Файл:** `config/dependency-rules.php`, фабрика `RouterInterface`

**Механика.** `glob($modulesDir . '/*/routes.php')` без фильтра. Новый сайт автоматически открывает `/admin/login`, `/admin/`, `/admin/logout`.

**Фикс.** `sites/<Site>/config/modules.php` → allowlist; фабрика грузит только разрешённые. Дефолт для Corporate — `['Admin']`, для нового сайта — `[]`.

**Приёмка.** Тест: сайт без `Admin` в allowlist → `/admin/login` = 404.

---

### EXT-03 · ассеты модуля живут в сайте

**Файлы:** `modules/Admin/views/layout.php`, `sites/Corporate/public/assets/css/admin.css`

**Механика.** Layout модуля подключает `/assets/css/admin.css`, который физически лежит в Corporate. Новый сайт → админка без стилей.

**Фикс.** `modules/Admin/public/assets/css/admin.css` + `bin/assets:publish.php` (copy/symlink в `sites/<Site>/public/assets/vendor/<Module>/`); layout ссылается на вендорный путь.

**Приёмка.** Тест/смоук: новый сайт после `assets:publish` отдаёт CSS модуля.

---

### OPS-05 · кэш маршрутов и DI не инвалидируется

**Файлы:** фабрика `RouterInterface` (`is_file($cacheFile)`), `Application::buildContainer`

**Механика.** В prod скомпилированные маршруты читаются, пока файл существует. Добавили модуль — он не появится, пока файл не удалён вручную. ⚠ VERIFY: поведение PHP-DI 7 по staleness компиляции; в любом случае чистить при деплое.

**Фикс.** Ключ версии в имени файла (`routes.<hash>.compiled.php`, hash от mtime роут-файлов + списка модулей); то же для DI; `composer cache:clear` в deploy-хуке. Дополнительно: логировать причину, когда `exportCompiled()` вернул `null` (Closure-хендлер) — сейчас prod тихо теряет кэш.

**Приёмка.** Тест: изменение роут-файла меняет имя кэша и маршрут появляется без ручной очистки.

---

## 7. Карточки группы B (сжато)

| ID | Фикс | Приёмка |
|---|---|---|
| `SEC-01` | Один резолвер окружения вместо 7 точек (`config/app.php`, `config/session.php`, `Application`, DI-фабрика роутера, `bootstrap.php`, `EngineStatsService` — все `'dev'`; `ErrorHandler` — `'prod'`). Рендер **fail-closed в prod**; отсутствие `APP_ENV` → CRITICAL в лог + флаг в readiness; `APP_DEBUG` отдельным явным флагом. **Особый риск:** `config/session.php` при отсутствии `APP_ENV` даёт `secure=false` | Тест: без `APP_ENV` страница ошибки без путей/стека **и** cookie с `Secure` |
| `SEC-02` | `app/Middleware/SecurityHeadersMiddleware.php` + `app.global_middleware` в `Application::run`. Заголовки: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY`, `Permissions-Policy`; HSTS только при HTTPS | Тест: каждый ответ содержит набор |
| `SEC-04` | Всегда `password_verify($password, $dummyHash)` при отсутствии пользователя — **dummy того же алгоритма и стоимости**, иначе канал сохранится. В логе — хеш email вместо email. Per-IP жёсткий лимит + per-нормализованный-email (`mb_strtolower`+`trim`) **эскалация трения** (captcha/задержка), **не блокировка** | Тест: `authenticate` вызывает `verifyPassword` и для неизвестного email; лог не содержит email |
| `SEC-05` | Глобальный мягкий лимит через `app.global_middleware`; отдельный бакет для POST/PUT/PATCH/DELETE; `validateOrFail` + `max:1000` в `HomeController::submit` | Тест: `POST /` сверх лимита → 429 |
| `SEC-06` | `Server-Timing` только при debug или для авторизованного админа | Тест: prod-ответ без заголовка |
| `SEC-07` | Ротация CSRF вместе с `regenerate(true)` в `login`/`logout` (`SecurityInterface::rotateCsrfToken()`). Задокументировать: форма входа во второй вкладке станет невалидной | Тесты: токен до ≠ после логина; после logout ≠ до; старый токен отклоняется; `regenerate(true)` сохранён |
| `SEC-08` | Проверять результат `auth()`; `resetConnection()` вызывает `close()`; TLS-опции для Redis/MySQL. **Условно:** если когда-либо pconnect — сначала чинить `select($database)` (вызывается только при `> 0`) | Тест: неверный пароль → явное исключение; reset закрывает соединение |
| `ERR-01` | Разделить: (а) **доказано** — shutdown-хенджер не знает, отправлен ли ответ; фатал после `send()` дописывает тело к 200 при `headers_sent()`. (б) **гипотеза** — двойной рендер при uncaught exception. Добавить флаг состояния ответа; при `headers_sent()` — только лог | Тест (subprocess) на (б): `proc_open(PHP_BINARY, ['-r', $code])`, зарегистрировать handler, бросить исключение, `assertSame(1, substr_count($stdout, '<!DOCTYPE html>'))`. Прогнать на матрице 8.2/8.5 |
| `OPS-03` | Ротация по дню/размеру; в prod только `error`; sampling; request-id в каждой строке; redaction PII | Тест: prod-запрос не пишет info-строку |
| `OPS-01` | **Веб-сервер:** статика `/assets/*` — `expires 1y immutable`, gzip/brotli. **Приложение:** только `Cache-Control` (nginx не знает, авторизован ли запрос) и ETag для генерируемого HTML. Self-host шрифтов (приватность + LCP, не P0). **Предусловие выполнено:** `SES-01` больше не шлёт `Set-Cookie` анониму, значит CDN/прокси теперь может кэшировать | Анонимная главная отдаёт 304 при повторном `If-None-Match` |
| `OPS-06` | `/metrics` (Prometheus, закрыт токеном), readiness с проверкой БД/Redis, `X-Request-Id` в ответе и логах | Тест: readiness отражает отказ бэкенда |

---

## 8. Карточки группы D (латентные)

> **Предусловие для всех `CACHE-*`:** в дампе **нет ни одного потребителя `CacheInterface` вне тестов**. Ни один контроллер или сервис не пишет в кэш. Чинить до первого потребителя, не после.

### CACHE-01 · журнал failover сериализует пул воркеров

**Файлы:** `CacheFailoverJournal::withLock/update/readEntries/writeEntries/replay`, `FailoverCache::recoverPendingWrites`

При отказе Redis каждый `get/set/delete` идёт через **блокирующий** `LOCK_EX` на одном файле; внутри — `unserialize` всего журнала и перезапись всего файла. `replay(10)` выполняется внутри пользовательского запроса (до 20 сетевых операций под локом). При 10 000 отложенных ключей — O(N²) на дренаж и полная сериализация FPM-воркеров **во время аварии**.

**Фикс:**
```php
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    return null;   // вызывающий трактует как «replay не выполнен», отдаём fallback
}
```
+ replay в CLI (`bin/cache-replay.php`, cron, пакет 500–1000, бюджет времени); в HTTP-запросе только чтение из fallback;
+ шардирование журнала или append-only + компакция;
+ `Q-10`: внедрить `TimeDateInterface`.

**Приёмка.** При занятом локе `get()` возвращает fallback без ожидания (< 50 мс). `FailoverCacheTest` остаётся зелёным (§16).

### CACHE-05 · гонка чтения файлового кэша

Запись стала атомарной, чтение — нет:
```php
if (!$this->file->exists($path)) return $default;
$payload = @unserialize($this->file->read($path), ...);
```
`File::read()` бросает `FileException`, если файл исчез между `exists()` и `read()` (конкурентный `delete()`, в т.ч. при истечении TTL). Исключение не ловится ни в `FileCache::get`, ни вокруг `$this->fallback->*` в `FailoverCache` → **500**.

```php
try {
    $raw = $this->file->read($path);
} catch (FileException) {
    return $default;   // конкурентное удаление — это miss, не ошибка
}
```
+ обернуть fallback-вызовы в `FailoverCache` в try/catch.

### CACHE-06 · `tempnam()` молча уходит в системный temp

Если каталог существует, но не writable, `tempnam($directory, …)` вернёт путь в `sys_get_temp_dir()`, а `rename()` через границу ФС даст `EXDEV`. Проверять `dirname($temporaryPath) === $directory`.

### Прочие латентные

| ID | Фикс |
|---|---|
| `CACHE-04` | GC файлов кэша + шардирование каталога |
| `CACHE-07` | Привести `RedisCache::get` к тому же виду, что `FileCache::get` (`@unserialize` + `array_key_exists('data')`) |
| `HTTP-01` | Лимит по `Content-Length` (дефолт 1 МБ) → `HttpException(413)`; потоковое чтение для не-POST; не читать `php://input` для GET/HEAD. **Уточнение:** для POST тело уже ограничено `post_max_size`; вектор — именно PUT/PATCH/DELETE |
| `VAL-01` | Bail на первой ошибке поля; как минимум пропускать `unique`/`exists`, если поле уже невалидно |
| `OPS-07` | `DataBaseInterface::beginTransaction/commit/rollBack/transaction(callable)`; retry на разрыв; `reset()` у stateful-сервисов (`Validator`, `View`, `RequestMetrics`) — разблокирует RoadRunner/FrankenPHP |
| `RL-05` | При 429 на POST проверять наличие GET-пары маршрута; иначе отдавать 429 напрямую |

---

## 9. Целевая модель расширяемости (группа C)

**Контракт модуля** (`modules/<Module>/module.php`):

```php
<?php

declare(strict_types=1);

return [
    'name'        => 'Admin',
    'version'     => '1.0.0',
    'definitions' => __DIR__ . '/config/dependency-rules.php',
    'config'      => __DIR__ . '/config',
    'routes'      => __DIR__ . '/routes.php',
    'migrations'  => __DIR__ . '/database/migrations',
    'assets'      => __DIR__ . '/public',
    'middleware'  => [],
];
```

| ID | Изменение |
|---|---|
| `EXT-02` | Перенести биндинги `AdminUserService` / `EngineStatsService` в `modules/Admin/config/dependency-rules.php`. Ядро перестаёт знать о модулях |
| `EXT-04` | `View::resolvePath`: цепочка `sites/<Site>/views/vendor/<Module>/<path>` → `modules/<Module>/views/<path>` |
| `EXT-05` | `Application` собирает манифесты (`glob(modules/*/module.php)`), фильтрует по allowlist (`EXT-01`), мержит definitions/config, регистрирует маршруты |
| `EXT-06` | `bin/create-site.php <Name>`, `bin/create-module.php <Name>` — генерируют структуру из инструкции на главной |
| `EXT-07` | Колонка `role` в `admin_users` + `AdminRoleMiddleware`/`Gate`; инфраструктурные блоки дашборда — только для `role=owner` |
| `EXT-08` | `TranslatorInterface` + `lang/ru.php`, `lang/en.php` |
| `EXT-09` | `modules/<M>/database/migrations/*.sql` + `bin/migrate.php`; `create-admin.php` перестаёт быть источником схемы |
| `QA-02` | PHPStan level 8 в CI; `declare_strict_types` (risky allowed); `composer audit` |

---

## 10. Эталон прод-конфигурации

```dotenv
APP_ENV=prod
APP_DEBUG=false
APP_SITE=Corporate
CACHE_DRIVER=redis
CACHE_REDIS_FALLBACK=true
RATE_LIMIT_DRIVER=redis
SESSION_DRIVER=redis
SESSION_SECURE=true
TRUSTED_PROXIES=172.18.0.0/16    # реальная подсеть прокси, НЕ "*" и НЕ 127.0.0.1 в Docker
REDIS_PREFIX=vengine:            # site добавляется автоматически (CACHE-02)
REDIS_CONNECT_TIMEOUT=0.3
REDIS_TIMEOUT=0.2
DB_CONNECT_TIMEOUT=2
```

**Deploy-хук (обязателен после `OPS-05`):**
```bash
composer install --no-dev --optimize-autoloader
php bin/migrate.php            # после EXT-09
php bin/assets:publish.php     # после EXT-03
php bin/cache-clear.php --all-sites
# opcache_reset через FPM reload
```

---

## 11. Отложено до измерений (группа E)

**`OPS-02`.** Сейчас `RequestMetrics` пишет `redis`/`db`, но время коннекта растворено в первой операции. Выделить `redis.connect` / `db.connect` отдельными компонентами — решение по цифрам.

**Условный баг, если pconnect будет внедряться:** `RedisClient::connection()` вызывает `select($database)` только при `$database > 0`. С pconnect соединение, оставшееся на DB 3, будет отдано запросу, ожидающему DB 0. Плюс `resetConnection()` не закрывает сокет — пул получит потенциально битое соединение. Чинить **до** включения pconnect.

**`OPS-04`.** Fast-path `array` для статических путей, затем chunked-regex. Отдельно — функциональные пробелы, не оптимизация: 405 при совпадении пути с другим методом, HEAD→GET (важно для мониторинга и линк-чекеров), 301-канонизация слеша, `rawurldecode` параметров **после** матчинга.

---

## 12. План по фазам

```
Фаза 1 (A, живое)        SEC-03 → ERR-02 → STATS-01 → CFG-01
                         RL-03 · RL-04 · SES-03 · SES-04 · SES-05
                         EXT-01 · EXT-03 · OPS-05
        │                └─ STATS-01 и CFG-01 — один PR (одна тема: драйверы)
        ▼
Фаза 2 (B, hardening)    SEC-01 → ERR-01 → SEC-02 → SEC-06
                         SEC-04 · SEC-05 · SEC-07 · SEC-08 · OPS-03 · OPS-01 · OPS-06
        │                └─ SEC-05 требует SEC-02 (глобальный конвейер)
        │                └─ OPS-01 требует SES-01 (выполнено)
        ▼
Фаза 3 (D, до потребителей)  CACHE-05 · CACHE-06 · CACHE-07 · CACHE-04 · CACHE-01
                             HTTP-01 · VAL-01 · OPS-07 · RL-05
        │                    └─ выполнять ДО первого модуля, пишущего в кэш
        ▼
Фаза 4 (C, расширяемость)  EXT-05 → EXT-02 · EXT-04 · EXT-06 · EXT-09 · EXT-07 · EXT-08 · QA-02
        │                    └─ EXT-05 требует EXT-01
        ▼
Фаза 5                     F-правки одним PR (Q-01…Q-12, MISC-01, SES-06/07, TEST-01)
        ▼
Фаза 6 (E)                 измерения → решение по OPS-02 / OPS-04
```

| Фаза | Definition of Done |
|---|---|
| 1 | Дашборд показывает реальные зависимости; 404 отдаёт 404; новый сайт без Admin не открывает `/admin/login`; деплой не требует ручной очистки кэша |
| 2 | Ни один prod-ответ не содержит путей/стека; все ответы несут security-заголовки; логи ротируются; анонимная главная отдаёт 304 |
| 3 | Нагрузочный прогон `http_smoke.py --workers 16 --duration 60`: 0 failures; при выключенном Redis p95 не деградирует > 2×; повреждённый/удалённый файл кэша = miss, не 500 |
| 4 | Новый модуль и сайт создаются одной командой, без правок `src/` и `config/` |
| 5 | `composer cs:check` + `composer test` зелёные; PHPStan в CI |
| 6 | Решение по pconnect/роутеру принято по цифрам, а не по вкусу |

---

## 13. Матрица «фикс → тест»

| ID | Тест |
|---|---|
| `SEC-03` | `tests/Unit/RequestTest.php` — дополнить кейсами прокси и IPv6 |
| `ERR-02` | `tests/Unit/ErrorRendererTest.php` (новый) |
| `ERR-01` | `tests/Unit/ErrorHandlerTest.php` (новый, subprocess) |
| `STATS-01` | `tests/Unit/EngineStatsServiceTest.php` — кейс `rate-limit=redis` |
| `CFG-01` | `tests/Unit/RateLimitConfigTest.php` — согласованность примера |
| `RL-03` | `tests/Unit/FailoverRateLimiterTest.php` — два идентификатора при флаппинге |
| `RL-04` | `tests/Unit/FileRateLimiterGcTest.php` (новый) |
| `SES-03/04/05` | `tests/Unit/SessionRuntimeTest.php` (новый) |
| `SEC-01` | `tests/Unit/EnvironmentResolverTest.php` (новый) |
| `SEC-02` | `tests/Unit/SecurityHeadersMiddlewareTest.php` (новый) |
| `SEC-04` | `tests/Unit/AdminUserServiceTest.php` (новый) |
| `SEC-07` | `tests/Unit/CsrfRotationTest.php` (новый) |
| `EXT-01` | `tests/Unit/ApplicationSiteTest.php` — allowlist-кейс |
| `OPS-05` | `tests/Unit/RouteCacheInvalidationTest.php` (новый) |
| `CACHE-05/06` | `tests/Unit/FileCacheTest.php` — конкурентное удаление, не-writable каталог |

---

## 14. Команды валидации

```bash
php -l <каждый изменённый PHP-файл>
vendor/bin/phpunit tests/Unit/<TestClass>.php
composer cs:check
composer test
```

Интеграционные (при поднятых MySQL/Redis):
```bash
CI_INTEGRATION=1 DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root \
REDIS_HOST=127.0.0.1 CACHE_DRIVER=redis composer test
```

Нагрузочная проверка:
```bash
php -S 127.0.0.1:8000 -t sites/Corporate/public &
python3 tests/load/http_smoke.py --url http://127.0.0.1:8000/ --workers 16 --duration 60
```

> **Правило отчётности (`copilot-instructions.md`):** сообщай только те команды, которые реально запустил, и их фактический результат. Не заявляй «прошло», если не запускал. **Аудитор команд не запускал.**

---

## 15. Инварианты — что нельзя ломать

Эти решения **уже правильные**. Не «рефактори» их:

- `Session::regenerate(true)` при входе и выходе — защита от session fixation.
- `Session::hasSessionToRead()` — lazy-чтение flash без cookie (`SES-01`). Не убирать.
- `use_strict_mode=1` + per-site Redis-префикс — защита от кросс-сайтового чтения сессии.
- **Отсутствие failover у Redis-сессий** — намеренно. Сессия авторитетна, dual-write недопустим.
- `hash_equals` в `Security::verifyCsrfToken` и `AuthMiddleware`.
- Все SQL — через PDO-плейсхолдеры; `Validator::sanitizeIdentifier` (`^[a-zA-Z0-9_]+$`) защищает интерполяцию имён таблиц.
- `View::escape()` (`ENT_QUOTES | ENT_SUBSTITUTE`), `View::old()` экранирует, шаблоны экранируют flash.
- `Validator::validateOrFail` возвращает **только** описанные в правилах поля — защита от mass assignment.
- `AdminAuthMiddleware` читает сессию и сразу `close()` — образец (но см. `SES-07`).
- `FileCache::set` — атомарная запись `tempnam+rename`; `get` — `@unserialize` + проверка `data`.
- `RedisRateLimiter` — атомарный Lua-скрипт `INCR`+`EXPIRE`+`TTL`.
- `Router::$routesByMethod` — индекс по методу; `Route::compilePattern` экранирует литералы через `preg_quote`.
- `FileRateLimiter` создаёт каталог `0700`, файл `0600`.
- `tests/load/http_smoke.py` ограничен по duration/workers, требует `--allow-remote` для нелокальных URL.
- Границы `src/` ↔ `app/` ↔ `sites/` ↔ `modules/`; интерфейсы + биндинги; `declare(strict_types=1)`.

---

## 16. Ловушки: тесты, которые сломаются от «очевидных» улучшений

| Изменение | Что сломается | Как обойти |
|---|---|---|
| Шардирование кэша в 2 уровня (`data/ab/file.cache`) | `FailoverCacheTest::tearDown` чистит только один уровень вложенности | Обновить tearDown на рекурсивный обход **в том же PR** |
| Шардирование rate-limit файлов | `FailoverRateLimiterTest::tearDown` делает `unlink` по плоскому glob | То же — рекурсивный обход |
| Создание каталога/файла журнала «заранее» | `FailoverCacheTest` ассертит `assertFileDoesNotExist(pending.dat)` и `assertDirectoryDoesNotExist(data)` на здоровом пути | Не создавать артефакты на healthy-path |
| Исправление грамматики `desc="1 calls"` (`Q-07`) | `RequestMetricsTest` явно ассертит `desc="1 calls"` | Менять код и тест одновременно |
| Дефолт `APP_ENV=prod` (`SEC-01`) | `ApplicationSiteTest`, `EngineStatsServiceTest`, `SessionConfigTest` мокают/подменяют env | Явно устанавливать env в `setUp`/`tearDown` новых тестов |
| Любой тест, стартующий сессию раньше `SessionFlashTest` | `SessionFlashTest` ассертит `session_status() === PHP_SESSION_NONE` как предусловие (`TEST-01`) | Заменить на skip/guard или изолировать процесс |
| Смена hold с per-IP на глобальный | `FailoverRateLimiterTest` проверяет per-identifier поведение | Тест использует один identifier — проходит; добавить кейс с двумя IP |
| Удаление второго `isFailoverActive` (`RL-03`) | **Ничего не ломает** — проверено по обновлённым тестам | — |

---

## 17. Что уже хорошо

Failover-архитектура кэша и rate-limit с журналом — сильная идея; проблема только в **месте исполнения** (request-path вместо фонового воркера) и гранулярности лока. Индекс маршрутов по методу, атомарная запись журнала и кэша через `tmp+rename`, ограниченный размер пакета replay, bounded load-скрипт, чёткие границы слоёв, site-namespace в Redis и lazy-сессии — база, на которой стоит строить, а не заменять.

---

## 18. Открытые вопросы к владельцу

1. **Redis в prod:** используется, локальный или удалённый, shared между сайтами? → определяет вес `CACHE-*`, `RL-03`, `SEC-08`.
2. **Топология:** адрес прокси относительно PHP (Docker/Nginx/балансировщик)? → определяет, живёт ли `SEC-03` в вашем деплое.
3. **Планируется ли потребитель кэша** в ближайших модулях? → определяет, чинить `CACHE-*` сейчас или зафиксировать как предусловие.
4. **Целевой профиль нагрузки:** RPS пик, доля анонимного трафика, число сайтов на инстанс?
5. **Long-running runtime** (RoadRunner/FrankenPHP) в планах? → приоритет `OPS-07`.
6. **Роли и i18n** нужны в ближайших 2–3 модулях? → приоритет `EXT-07`/`EXT-08`.
7. **Семантика сессии:** idle или absolute timeout? → решение по `SES-05`.

---

**Рекомендуемый первый PR:** `STATS-01` + `CFG-01` одной темой («драйверы Redis: конфигурация и наблюдаемость») — дёшево, детерминированно, закрывает ложную картину инфраструктуры. Затем `SEC-03` и `ERR-02` — единственные оставшиеся пункты с отказом сайта и неверными ответами на каждом 404.
