
# AUDIT.md — V-engine: открытые проблемы

> **Дата:** 2026-11-20 · **Основание:** дамп `vampqwe-v-engine-8a5edab282632443.txt`, ревизия с `SECURITY-REVIEW.md`, `config/http.php`, `RedisNamespace`, `HomeSubmitRateLimitMiddleware`.
> **Метод:** статическое чтение исходников. **Команды валидации не запускались.**
> **Содержание:** только дефекты, подтверждаемые чтением кода. Исправленное, отозванное и гипотетическое в документ не включено. Для латентных пунктов явно указано условие проявления.

**Формат:** `ID · Файл::метод → что происходит → следствие → фикс`. Номера строк не приводятся — искать по имени метода.

---

## 1. Сводка

| ID | Файл | Дефект | Проявляется |
|---|---|---|---|
| `RL-06` | `FileRateLimiter::withState/collectExpiredStates` | Полный скан всех состояний под глобальным `LOCK_EX`; каждый запрос ждёт `LOCK_SH` | Всегда (file — драйвер по умолчанию) |
| `RL-07` | `FileRateLimiter::withState` | Исключения уборки抛出 наружу и валят запрос | Всегда |
| `ERR-03` | `ErrorHandler::handleValidationException` | Обращение к сессии внутри обработчика → вложенное исключение → фатал | При отказе хранилища сессий или после отправки заголовков |
| `ERR-01` | `ErrorHandler::handleException` | Нет состояния «ответ отправлен»; `echo` при `headers_sent()` | Фатал после `send()` |
| `CFG-01` | `.env.example`, `Request::isFromTrustedProxy` | Дефолт доверенных прокси не покрывает Docker → все посетители в одном IP | Развёртывание за прокси |
| `SEC-10` | `AdminUserService::DUMMY_PASSWORD_HASH` | Стоимость dummy-хеша не совпадает с `PASSWORD_DEFAULT` на PHP < 8.4 | PHP 8.2/8.3 (заявлены в `composer.json` и CI) |
| `OPS-05` | DI-фабрика `RouterInterface` | Кэш маршрутов читается по `is_file` без инвалидации | prod-деплой |
| `OPS-08` | `Application::buildContainer` | `@mkdir` без проверки записи, затем компиляция контейнера | prod + недоступный `storage/` |
| `ROUTE-01` | `Route::matches/buildUri` | Параметры не декодируются, генерация кодирует | Всегда |
| `ROUTE-02` | `Router::dispatch`, `Request::uri` | Нет HEAD и 405; слеш нормализуется без канонизации | Всегда |
| `EXT-01` | DI-фабрика `RouterInterface` | Все модули грузятся для каждого сайта | Новый сайт |
| `EXT-02` | `config/dependency-rules.php` | Ядро биндит сервисы модуля Admin | Новый модуль |
| `EXT-03` | `modules/Admin/views/layout.php` | CSS модуля физически принадлежит сайту Corporate | Новый сайт |
| `EXT-09` | схема БД | Нет версионированных миграций | Новый модуль с таблицами |
| `OPS-03` | `Logger::writeLog` | Нет ротации, flock на строку, info-поток на отказах | Всегда |
| `OPS-01` | `.htaccess`, `Response`, `layouts/main.php` | Нет `Cache-Control`/ETag для HTML; пустой `.htaccess`; внешние шрифты | Всегда |
| `OPS-06` | `ApiController::health`, `bootstrap.php` | Нет readiness и метрик; bootstrap пишет только message | Всегда |
| `CACHE-01` | `CacheFailoverJournal`, `FailoverCache` | Блокирующий лок + перезапись всего журнала + inline-replay; fallback без catch | Первый потребитель кэша + Redis |
| `VAL-01` | `Validator::validate` | Нет bail; `unique`/`exists` выполняются на невалидном значении | Первое правило `unique`/`exists` |
| `OPS-07` | `DataBaseInterface` | Нет транзакций | Первый модуль с многошаговой записью |
| `RL-08` | `FailoverRateLimiter::hit/clear` | `fallback_hold_seconds` игнорируется в `hit()` | `RATE_LIMIT_DRIVER=redis` |
| `RL-09` | `FileRateLimiter::isFailoverActive` | Read-only проверка создаёт каталоги и берёт локи | `RATE_LIMIT_DRIVER=redis` |
| `MISC-02` | `Application`, `ErrorHandler`, `bootstrap` | Список security-заголовков продублирован трижды | Всегда |
| `MISC-01` | `RedisClient::connection` | Сайт берётся из env, а не из `path.site` | Всегда |
| `SES-07` | `View::flash` + `AdminAuthMiddleware` | Layout переоткрывает сессию после `close()` | Админ-страницы |
| `HTTP-02` | `Request::extractBody` | `Content-Length` проверяется до отсева GET/HEAD | GET с большим `Content-Length` |
| `Q-04` | `EngineStatsService::databaseStats` | Ловит только `DataBaseException` | Сбой логгера внутри `query` |
| `Q-06` | `Router::normalizePath` | Мёртвая ветка `=== ''` | — |
| `TEST-01` | `SessionFlashTest` | Ассерт состояния сессии как предусловие | Порядок тестов |
| `QA-02` | `.php-cs-fixer.dist.php`, `ci.yml` | Нет статического анализа, `strict_types` не enforced, нет `composer audit` | — |

---

## 2. Блокирующее под нагрузкой

### RL-06 · Уборка состояний сериализует все rate-limited запросы

**Файл:** `src/System/RateLimit/FileRateLimiter.php::withState`, `collectExpiredStates`

**Что происходит.** Каждая операция, включая read-only `isFailoverActive`, проходит через:

```php
$this->ensureDirectory($this->storageDir);
$this->collectGarbageOccasionally();          // 1/1000 → полный скан
$gcLock = fopen($this->storageDir . '/.gc.lock', 'c');
if ($gcLock === false || !flock($gcLock, LOCK_SH)) { ... }
```

`collectExpiredStates()` берёт **блокирующий** `LOCK_EX` на том же `.gc.lock` и внутри него делает `glob('*/*.json')` + `file_get_contents` + `json_decode` **по всему корпусу**, без бюджета и без предфильтра.

**Следствие.** Стоимость одной уборки — O(числа всех сохранённых состояний) (удерживаются сутки после старта окна). На это время все конкурентные rate-limited запросы стоят на `LOCK_SH`. Частота уборки растёт вместе с трафиком, а её цена — вместе с накопленным объёмом. `RATE_LIMIT_DRIVER=file` — значение по умолчанию, то есть путь основной, а не резервный.

**Фикс.**
```php
// 1. Бюджет + дешёвый предфильтр без чтения содержимого
$cutoff = $this->timeDate->timestamp() - 86400;
$scanned = 0;
foreach (glob($this->storageDir . '/*/*.json') ?: [] as $path) {
    if ($scanned++ >= 200) break;
    if (filemtime($path) >= $cutoff) continue;
    // ... дальше чтение и decodeState
}

// 2. Не ждать чужую уборку
if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return 0; }
```
Структурно正确нее — уборка в пределах одного шарда (случайного или по курсору): тогда блокируются только запросы этого шарда.

**Тест.** Занятый `.gc.lock` → `hit()` не ожидает; корпус из N файлов → за один вызов обрабатывается не более бюджета.

---

### RL-07 · Исключения уборки превращаются в 503 и 500

**Файл:** `src/System/RateLimit/FileRateLimiter.php::withState`

**Что происходит.** `collectGarbageOccasionally()` вызывается **до** блока `try`, поэтому её исключения не попадают под `catch (Throwable)` внутри `withState`:

- `collectExpiredStates` бросает `RateLimiterException` при неудачном локе или `unlink` → `RateLimitMiddleware` ловит → для `admin_login` и `home_submit` (`fail_open=false`) → **503 на входе в админку и на публичной форме**;
- `unlink` при гонке или правах emits `E_WARNING` → `ErrorHandler::set_error_handler` превращает его в `PhpErrorException`, который **не** `RateLimiterException` → проходит мимо catch middleware → **500**.

**Следствие.** Сборка мусора — фоновая задача — способна отказать пользовательскому запросу.

**Фикс.** Обернуть вызов уборки в `try/catch (Throwable)` с логированием и продолжением; `unlink` — через `@` + проверку результата. Уборка не должна влиять на исход `hit()`.

**Тест.** Уборка бросает исключение → `hit()` возвращает корректный `RateLimitResult`.

---

## 3. Обработка запроса и ошибок

### ERR-03 · Вложенное исключение внутри обработчика ошибок

**Файл:** `src/System/Error/ErrorHandler.php::handleValidationException`

**Что происходит.** Обработчик `ValidationException` обращается к сессии:

```php
$this->session->flash('_errors', $errors);
```

`Session::flash` → `ensureStarted` → `start()`. При `SESSION_DRIVER=redis` и недоступном Redis `start()` бросает `HttpException(503)`; при уже отправленных заголовках — `SessionException`. Исключение возникает **внутри** `set_exception_handler`.

**Следствие.** Штатный ответ (422 или 503) не формируется: PHP завершает обработку фаталом. Валидационная ошибка превращается в 500.

**Фикс.** Ветка валидации не должна зависеть от недоступного хранилища: обернуть работу с сессией в `try/catch`, при отказе отдавать 422/503 напрямую без flash. Плюс флаг состояния ответа (см. `ERR-01`).

**Тест.** `SessionInterface::flash` бросает `HttpException(503)` → ответ 503, а не фатал.

---

### ERR-01 · Нет состояния жизненного цикла ответа

**Файл:** `src/System/Error/ErrorHandler.php::register/handleException`

**Что происходит.** Shutdown-хенджер вызывает `handleException` без знания того, был ли ответ уже отправлен. `handleException` чистит буферы и делает `echo` даже при `headers_sent() === true`.

**Следствие.** Фатал в shutdown-функции после успешного `Response::send()` дописывает тело ошибки к уже отправленной странице со статусом 200. В debug-режиме — со стеком.

**Фикс.** Флаг «ответ отправлен» + флаг «ошибка уже обработана»; при `headers_sent()` — только запись в лог, без вывода.

**Тест.** После `send()` смоделировать фатал → тело ответа не изменяется.

---

### ROUTE-01 · Параметры маршрута не декодируются

**Файл:** `src/System/Router/Route.php::matches`, `buildUri`

**Что происходит.** `matches()` кладёт в `$params` сырые значения из `preg_match` по процент-кодированному пути; `buildUri()` кодирует через `rawurlencode`.

**Следствие.** `/hello/John%20Doe` даёт параметр `John%20Doe`. Генерация и распознавание URL не симметричны.

**Фикс.** `rawurldecode` значений параметров **после** сопоставления (чтобы `%2F` не влиял на выбор маршрута).

**Тест.** Закодированный параметр распознаётся декодированным; `buildUri` → `matches` — круговая симметрия.

---

### ROUTE-02 · Нет HEAD и 405, слеш не канонизируется

**Файлы:** `src/System/Router/Router.php::dispatch`, `src/System/Http/Request.php::__construct`

**Что происходит.** `dispatch` ищет только в `routesByMethod[$method]`; при отсутствии — `RouteNotFoundException`. `Request` делает `rtrim($uri, '/')`, поэтому `/about` и `/about/` попадают в один маршрут.

**Следствие.** HEAD к существующему GET-маршруту даёт 404 (ломает часть мониторингов и линк-чекеров). Неверный метод даёт 404 вместо 405. оба варианта слеша отдают один контент без канонического адреса.

**Фикс.** HEAD → GET с пустым телом; 405 при совпадении пути с другим методом; 301 на канонический вариант слеша.

---

## 4. Конфигурация и развёртывание

### CFG-01 · Дефолт доверенных прокси схлопывает посетителей в один IP

**Файлы:** `.env.example` (`TRUSTED_PROXIES=127.0.0.1,::1`), `src/System/Http/Request.php::isFromTrustedProxy`

**Что происходит.** Заголовки `X-Forwarded-For` / `X-Real-IP` учитываются только если `REMOTE_ADDR` входит в доверенный список. В типичной связке Nginx/балансировщик в Docker адрес прокси — из приватной подсети (`172.x`, `10.x`), а не loopback.

**Следствие.** Все посетители получают IP прокси. Глобальный бакет `60/60s` исчерпывается на весь сайт (`/api/*` → 429 для всех). Политики `admin_login` (`5/300s`) и `home_submit` (`10/60s`) с `fail_open=false` означают, что неудачные попытки одного человека блокируют вход и форму для всех.

**Фикс.** В `.env.example` показать реальный сценарий (`TRUSTED_PROXIES=<подсеть прокси>`) и явно предупредить о последствиях пустого/loopback-значения за прокси. Дополнительно: диагностическое предупреждение в лог, если `REMOTE_ADDR` приватный и не доверен.

---

### SEC-10 · Стоимость dummy-хеша не совпадает с реальными на PHP < 8.4

**Файл:** `modules/Admin/Services/AdminUserService.php::DUMMY_PASSWORD_HASH`, `authenticate`

**Что происходит.** Dummy зафиксирован как `$2y$12$…`. Реальные хеши создаются `Security::hashPassword` через `PASSWORD_DEFAULT`. Дефолтная стоимость bcrypt повышена до 12 только начиная с PHP 8.4; на PHP 8.2/8.3 (заявлены в `composer.json: >=8.2` и в матрице CI) `PASSWORD_DEFAULT` даёт cost 10.

**Следствие.** На PHP 8.2 проверка для несуществующего email дороже проверки для существующего. Временной каналenumeration не закрыт, а инвертирован. `AdminUserServiceTest` проверяет только `algoName === 'bcrypt'` и разницу стоимости не поймает.

**Фикс.** Получать dummy из того же алгоритма и стоимости, что и реальные хеши (ленивая инициализация через `hashPassword` с кешированием в статике), либо явно закрепить алгоритм и стоимость в `Security`.

**Тест.** `password_get_info(dummy)['options'] === password_get_info($security->hashPassword('x'))['options']`.

---

### OPS-05 · Кэш маршрутов не инвалидируется

**Файл:** `config/dependency-rules.php`, фабрика `RouterInterface`

**Что происходит.**
```php
if ($isProd && is_file($cacheFile)) { $compiled = require $cacheFile; ... return $router; }
```
Ни mtime, ни сигнатуры. additionally `exportCompiled()` возвращает `null` при любом Closure-хендлере, и причина нигде не логируется.

**Следствие.** Добавленный модуль или маршрут в prod не появляется, пока файл не удалён вручную. Так как `APP_ENV` теперь по умолчанию `prod`, это касается и развёртывания без `.env`. Тихая потеря кэша маршрутов не диагностируется.

**Фикс.** Версия в имени файла (`routes.<hash>.compiled.php` от mtime роут-файлов и списка модулей); лог причины отказа экспорта; `composer cache:clear` в deploy-хуке.

---

### OPS-08 · Компиляция контейнера без проверки доступности записи

**Файл:** `src/Kernel/Application.php::buildContainer`

**Что происходит.**
```php
if (!is_dir($diCacheDir)) { @mkdir($diCacheDir, 0o755, true); }
$builder->enableCompilation($diCacheDir);
```
Результат `mkdir` игнорируется, доступность записи не проверяется.

**Следствие.** При недоступном для записи `storage/` и prod-режиме (в том числе по умолчанию, без `.env`) инициализация контейнера падает на bootstrap. Наружу уходит обезличенная 500-страница, диагностика — только `log/Bootstrap.log`.

**Фикс.** Явная проверка `is_dir && is_writable` с внятным сообщением; либо отказ от компиляции с CRITICAL в лог вместо падения.

---

## 5. Границы модулей и сайтов

### EXT-01 · Каждый сайт получает маршруты всех модулей
`config/dependency-rules.php`: `glob($modulesDir . '/*/routes.php')` без фильтра. Новый сайт автоматически открывает `/admin/login`, `/admin/`, `/admin/logout`.
**Фикс:** `sites/<Site>/config/modules.php` со списком разрешённых модулей; фабрика грузит только их.

### EXT-02 · Ядро знает о конкретном модуле
`config/dependency-rules.php` содержит `use Modules\Admin\Services\…` и биндинги `AdminUserService`, `EngineStatsService` (последний — с параметрами путей). Каждый новый модуль требует правки конфига ядра, что противоречит заявленным границам слоёв.
**Фикс:** определения модуля в `modules/<M>/config/dependency-rules.php`, сборка через манифест.

### EXT-03 · Ассеты модуля принадлежат сайту
`modules/Admin/views/layout.php` подключает `/assets/css/admin.css`, файл лежит только в `sites/Corporate/public/assets/css/`. На новом сайте админка渲染 без стилей.
**Фикс:** `modules/Admin/public/assets/…` + публикация в `sites/<Site>/public/assets/vendor/<M>/`.

### EXT-09 · Нет версионированных миграций
Схема `admin_users` создаётся CLI-скриптом `bin/create-admin.php` (подтверждено владельцем). Общего механизма миграций нет.
**Следствие:** первый модуль со своей таблицей не имеет штатного пути развёртывания; версия схемы нигде не фиксируется.
**Фикс:** `modules/<M>/database/migrations/*.sql` + `bin/migrate.php`; CLI-скрипт перестаёт быть источником схемы.

---

## 6. Эксплуатация

### OPS-03 · Логгер
**Файл:** `src/System/Logger/Logger.php::writeLog`, `src/System/File/File.php::write`

- Ротации нет: `log/<Module>.log` растёт неограниченно.
- Каждая строка — `file_put_contents(FILE_APPEND | LOCK_EX)`: общий файл на модуль сериализует записи всех воркеров.
- Уровней и фильтрации нет: `info()` пишет всегда. `AdminAuthMiddleware::denyAccess`, `AuthMiddleware` (отказ токена) и `RateLimitMiddleware` (429) пишут по info-строке на каждый отказ — под атакой это поток записи в один файл.
- Нет идентификатора запроса для сопоставления записей.

**Фикс:** ротация по размеру/дню, фильтрация по уровню, request-id в контексте, ограничение частоты повторяющихся отказов.

### OPS-01 · HTTP-кэширование и статика
**Файлы:** `sites/Corporate/public/.htaccess`, `src/System/Http/Response.php`, `sites/Corporate/views/layouts/main.php`

- `.htaccess` содержит только rewrite: нет `Options -Indexes`, `DirectoryIndex`, expires и сжатия.
- Ни один ответ не несёт `Cache-Control` или `ETag`; повторный запрос всегда доходит до PHP.
- Шрифты грузятся с `fonts.googleapis.com` / `fonts.gstatic.com` (два preconnect + блокирующий запрос к третьей стороне).

**Разделение ответственности:** сжатие и expires для `/assets/*` — веб-сервер; `Cache-Control` и ETag для генерируемого HTML — приложение (веб-сервер не знает, авторизован ли запрос). Предусловие со стороны приложения выполнено: анонимный ответ больше не отдаёт `Set-Cookie`.

### OPS-06 · Наблюдаемость
`ApiController::health` возвращает статический `ok` и не проверяет зависимости — как readiness непригоден. Метрики `RequestMetrics` доступны только через заголовок `Server-Timing` и только вне prod; внешнего эндпоинта нет. `bootstrap.php` пишет в лог лишь message/file/line без стека — падение инициализации в prod разбирается плохо.

---

## 7. Латентные (с условием проявления)

### CACHE-01 · Журнал failover-кэша
**Условие:** появляется потребитель `CacheInterface` **и** `CACHE_DRIVER=redis`. В дампе потребителей кэша вне тестов нет.

**Файлы:** `CacheFailoverJournal::withLock/update/readEntries/writeEntries/replay`, `FailoverCache::get/set/delete`

- `withLock` берёт блокирующий `LOCK_EX`; внутри — `unserialize` всего журнала и перезапись всего файла. При отказе Redis через этот путь идёт каждая операция → сериализация воркеров именно во время аварии, O(N) на операцию.
- `replay(10)` выполняется внутри пользовательского запроса.
- `recordSetWithFallback` использует `time()` вместо внедрённого `TimeDateInterface` — журнал нетестируем.
- Вызовы `$this->fallback->*` в `FailoverCache` не обёрнуты: `FileCache::get` пробрасывает `FileException`, если файл существует, но не читается → 500 вместо деградации.

**Фикс:** `LOCK_EX | LOCK_NB` с отказом от ожидания; replay вынести в CLI/cron; журнал шардировать или сделать append-only; внедрить clock; обернуть fallback-вызовы.

### VAL-01 · Нет bail в валидаторе
**Условие:** первое правило `unique` или `exists` (сейчас используются только `required|email|string|min`).
`Validator::validate` выполняет все правила поля подряд, поэтому `checkUnique`/`checkExists` обращаются к БД даже когда значение уже признано невалидным.
**Фикс:** останавливать обработку поля на первой ошибке.

### OPS-07 · Нет транзакций
**Условие:** первый модуль с многошаговой записью.
`DataBaseInterface` exposes только `connection()` и `query()`; для транзакции модуль вынужден лезть в `PDO` напрямую, обходя абстракцию.
**Фикс:** `beginTransaction/commit/rollBack/transaction(callable)` в интерфейсе и реализации.

### RL-08 · `fallback_hold_seconds` не управляет путём `hit`
**Условие:** `RATE_LIMIT_DRIVER=redis`.
`hit()` передаёт в `activateFallback` значение `$decaySeconds`, а `clear()` — `$this->fallbackHoldSeconds`. Для бакета `global` (`decay_seconds=60`) удержание составляет 60 с, а не заявленные конфигом 300.
**Фикс:** одна семантика удержания для обоих путей.

### RL-09 · Read-only проверка создаёт каталоги и берёт локи
**Условие:** `RATE_LIMIT_DRIVER=redis`.
`isFailoverActive` вызывает `withState(..., persist: false, create: false)`, но `ensureDirectory`, бросок кубика уборки и `flock(.gc.lock, LOCK_SH)` выполняются **до** проверки `!$create && !is_file($path)`. На здоровом Redis каждый запрос платит за это дважды (проверка до и запись состояния).
**Фикс:** если файл состояния отсутствует — сразу `false`, без mkdir и локов.

---

## 8. Мелкие подтверждённые дефекты

| ID | Файл::метод | Дефект | Фикс |
|---|---|---|---|
| `MISC-02` | `Application::run`, `ErrorHandler::sendSecurityHeaders`, `bootstrap.php` | Один и тот же набор из четырёх заголовков задан в трёх местах | Вынести в один helper |
| `MISC-01` | `RedisClient::connection`, `Session::configureRedisHandler` | Имя сайта читается из `APP_SITE`, хотя `Application` уже отдаёт `path.site` | Внедрять `path.site` |
| `SES-07` | `View::flash` + `AdminAuthMiddleware::process` | После `close()` layout вызывает `flash()`; `hasSessionToRead()` истинно (`session_id() !== ''`) → сессия открывается снова и держится до конца запроса | Читать flash только при активной сессии либо закрывать после чтения |
| `HTTP-02` | `Request::extractBody` | Проверка `Content-Length` стоит до отсева GET/HEAD → ложный 413 на GET с этим заголовком | Перенести проверку после отсева методов |
| `Q-04` | `EngineStatsService::databaseStats` | `catch (DataBaseException)` не покрывает `PhpErrorException`/`FileException` из логгера внутри `DataBase::query` → дашборд 500 | Ловить `Throwable` в сборе статистики |
| `Q-06` | `Router::normalizePath` | `'/' . trim(...)` никогда не равен `''`; ветка мертва | Удалить |
| `TEST-01` | `SessionFlashTest` | `assertSame(PHP_SESSION_NONE, session_status())` как предусловие: любой более ранний тест с реальной сессией даст ложное падение | Skip/guard или изоляция процесса |
| `QA-02` | `.php-cs-fixer.dist.php`, `.github/workflows/ci.yml` | `setRiskyAllowed(false)` + только `@PER-CS` → `declare(strict_types=1)` не проверяется; в CI нет статического анализа и `composer audit` | PHPStan в CI; risky-правило `declare_strict_types`; `composer audit` |

---

## 9. Порядок исправления

```
1. RL-06 + RL-07          один PR: уборка не должна ни блокировать, ни ронять запрос
2. ERR-03 + ERR-01        один PR: состояние ответа и независимость от хранилища сессий
3. SEC-10 + CFG-01        один PR: стоимость хеша и документация прокси
4. OPS-05 + OPS-08        один PR: инвалидизация кэша и проверка записи при компиляции
5. EXT-01 + EXT-03        один PR: границы сайта (allowlist модулей, публикация ассетов)
6. ROUTE-01 + ROUTE-02    один PR: декодирование параметров, HEAD/405, канонизация слеша
7. OPS-03 + OPS-06        ротация логов, request-id, readiness
8. EXT-02 + EXT-09        манифест модуля и миграции
9. CACHE-01, VAL-01, OPS-07, RL-08, RL-09   до появления соответствующего потребителя
10. Мелкие (§8)           одним PR
```

**Обоснование порядка:** пункты 1–2 — единственные, где дефект возникает из уже внесённых изменений и способен отказать запросу при штатной нагрузке. Пункты 3–4 определяют корректность развёртывания. Остальное — границы, наблюдаемость и латентные дефекты с явным предусловием.

---

## 10. Валидация

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

Нагрузочная проверка после пункта 1:
```bash
php -S 127.0.0.1:8000 -t sites/Corporate/public &
python3 tests/load/http_smoke.py --url http://127.0.0.1:8000/ --workers 16 --duration 60
```

**Ловушки существующих тестов:**

| Изменение | Что сломается |
|---|---|
| Бюджет/шардирование уборки (`RL-06`) | `FailoverRateLimiterTest::testGarbageCollectorRemovesExpiredShardedState` вызывает `collectExpiredStates` через reflection и ожидает удаления ровно одного файла — сохранить контракт |
| Рекурсивная уборка каталогов | `FailoverRateLimiterTest::tearDown` и `FailoverCacheTest::tearDown` обходят фиксированную глубину вложенности |
| Создание артефактов журнала на здоровом пути | `FailoverCacheTest` ассертит `assertFileDoesNotExist(pending.dat)` и `assertDirectoryDoesNotExist(data)` |
| Дефолты окружения | `ApplicationSiteTest`, `EngineStatsServiceTest`, `SessionConfigTest`, `RateLimitConfigTest` подменяют `$_ENV` — новые тесты обязаны делать то же в `setUp`/`tearDown` |

> **Отчётность (`copilot-instructions.md`):** указывать только реально запущенные команды и их фактический результат. Аудитор команд не запускал.

---

## 11. Не проверяется по дампу

Два файла помечены `[Binary file]`; утверждений по ним в документе нет.

| Файл | Что проверить |
|---|---|
| `config/app.php` | Наличие ключа `debug`. `ErrorHandler` читает `config->get('app', 'debug', false)`, тогда как `Application` и `bootstrap.php` опираются на `APP_ENV` + `APP_DEBUG`. Если ключа нет — подробная страница ошибок не работает и в dev; если он не содержит условия `APP_ENV === 'dev'` — возможен debug в prod |
| `sites/Corporate/App/Controllers/HomeController.php` | Заявленная в `SECURITY-REVIEW.md` валидация `max:1000` в `submit()` |
