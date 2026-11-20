Назначение файла: самодостаточный контекст для ИИ-агента или нового разработчика. Прочитав только этот документ, можно понять архитектуру, найти проблему по ID, применить фикс и проверить его тестом — без повторного анализа всего репозитория.
Дата аудита: 2026-11-20 · Объект: vampqwe/v-engine (дамп vampqwe-v-engine-8a5edab282632443.txt) · Фокус: рост трафика, безопасность, скорость добавления модуля/сайта.
Статус: ни один фикс не применён. Все утверждения проверены по исходникам дампа; спорные помечены ⚠ VERIFY.
0. Как читать этот документ (инструкция для агента)
Правило
Значение
ID вида RL-01
Стабильный идентификатор находки. Используй его в имени ветки, коммите и тесте: fix/RL-01-429-redirect-loop
Severity
P0 — ломается под нагрузкой / не собирается; P1 — безопасность прод-контура; P2 — масштабирование и эксплуатация; P3 — расширяемость; P4 — косметика
Пути
Относительно корня репозитория. Номера строк не приводятся намеренно — ищи по Класс::метод()
⚠ VERIFY
Утверждение нельзя подтвердить по дампу. Проверь в реальном файле перед изменением
Порядок
Фазы в §9 упорядочены по зависимостям. Не начинай P3 раньше P0
Инварианты
§12 — что нельзя ломать. §13 — тесты, которые сломаются от «правильных» на вид изменений
Формат карточки: Файл → Симптом → Механика → Влияние → Фикс → Приёмка.
1. Контекст проекта (факты, не требующие перечитывания кода)
PHP >= 8.2 · Composer · PHP-DI 7 · vlucas/phpdotenv 5 · PHPUnit 11 · PHP-CS-Fixer (@PER-CS)
MySQL (PDO, ATTR_EMULATE_PREPARES=false) · Redis (ext-redis, опционально)
PSR-4: VEngine\→src/, App\→app/, Sites\→sites/, Modules\→modules/, Tests\→tests/.
Жизненный цикл запроса:
sites/<Site>/public/index.php
  └─ bootstrap.php            ob_start(); BASE_PATH; dotenv->safeLoad(); new Application; run()
       └─ Application::__construct   валидирует APP_SITE, собирает $paths[], строит DI-контейнер
            └─ Application::run      ErrorHandler->register(); Request; RequestMetrics; Router->dispatch(); Response->send()
                 └─ Router::dispatch routesByMethod[METHOD] → Route::matches (preg_match) → runPipeline
                      └─ middleware[] → Invoker->call(handler) → ResponseInterface
Ключевые механизмы:
Подсистема
Реализация
Особенность
Мультисайт
APP_SITE → sites/<Site>/; один инстанс = один сайт
Кэш изолирован: storage/cache/sites/<Site>/
Модули
glob(modules/*/routes.php) в DI-фабрике роутера
Грузятся для всех сайтов безусловно
Кэш
CACHE_DRIVER=file|redis; при redis — FailoverCache(RedisCache, FileCache, Journal)
Журнал отложенных записей, inline-replay по 10
Rate limit
FailoverRateLimiter(RedisRateLimiter, FileRateLimiter, hold=300)
Драйвер не конфигурируется, всегда Redis-primary
Роутер
Индекс по HTTP-методу + линейный preg_match; prod-кэш routes.compiled.php
Экспорт невозможен при Closure-хендлере
Сессии
SessionInterface, драйвер file|redis
Тело Session.php в дампе недоступно
Шаблоны
View::renderFile — extract(EXTR_SKIP) + require в closure, $this = View
@Module/path → modules/<M>/views/
Метрики
RequestMetrics → заголовок Server-Timing
Уходит всем клиентам
2. Ограничения аудита: чего нет в дампе
Отсутствует
Пометка в дампе
Следствие
src/System/Session/Session.php
[Binary file]
Аудит сессий невозможен → чек-лист SES-02
README.md
[Binary file]
Конвенции взяты из .github/copilot-instructions.md
bin/create-admin.php
нет в дереве
composer admin:create и CreateAdminCommandTest падают
bin/cache-clear.php
нет в дереве
composer cache:clear падает; нет deploy-хука
Миграции / схема admin_users
нет
БД не поднимается с нуля
composer.lock, .gitignore
нет
⚠ VERIFY: сборка невоспроизводима; риск коммита .env
Первое действие агента: запросить/восстановить эти файлы. Без bin/* и миграций CI красный независимо от остальных фиксов (QA-01).
3. Реестр находок
P0 — блокеры

ID
Компонент
Файл::метод
Симптом одной строкой
RL-01
RateLimit
app/Middleware/RateLimitMiddleware::buildTooManyRequestsResponse
302 на тот же URI → цикл, амплификация ×20
SES-01
Session/View
View::flash + sites/Corporate/views/layouts/main.php
Сессия и cookie на каждый анонимный запрос
CACHE-01
Cache
CacheFailoverJournal::withLock/update/replay
Глобальный блокирующий flock + O(N) перезапись на каждую операцию
RL-02
RateLimit
config/dependency-rules.php + FailoverRateLimiter::hit
Redis-primary вопреки CACHE_DRIVER=file; 2–4 файловых лока на запрос
CACHE-02
Cache/Redis
RedisCache::formatKey, RedisClient::connection
Ключи кэша не изолированы по сайту → кросс-сайтовые данные
CACHE-03
Cache/File
FileCache::get/set, File::write
Torn-read → warning → ErrorException → HTTP 500 вместо miss
QA-01
Repo
composer.json, tests/Unit/CreateAdminCommandTest
Проект не собирается и не тестируется с нуля
P1 — безопасность прода
ID
Файл::метод
Симптом
SEC-01
.env.example, config/app.php, bootstrap.php, Application
APP_ENV=dev по умолчанию → стек и исходники наружу
ERR-01
ErrorHandler::register/handleException
Нет guard повторного рендера; вывод после отправки ответа
ERR-02
ErrorHandler::handleException, ErrorRenderer::renderProduction
404 отдаёт тело «500»; API получает HTML
SEC-02
Application::run
Нет security-заголовков и нет глобального middleware-конвейера
SEC-03
Request::ip/isFromTrustedProxy
Читает $_SERVER; дефолт прокси ломает Docker; * = спуфинг
SEC-04
AdminUserService::authenticate, AuthController::login
Timing-enumeration email; email в логах; нет per-account лимита
SEC-05
sites/Corporate/routes/web.php, HomeController::submit
POST / без лимита, валидации и ограничения длины
SES-02
config/session.php, Session.php
Чек-лист hardening; redis-сессии без failover; общая cookie
EXT-01
DI-фабрика RouterInterface
Каждый новый сайт автоматически открывает /admin/login
SEC-06
Application::run
Server-Timing раскрывает внутренние тайминги всем
HTTP-01
Request::extractBody
Нет лимита тела → OOM; multipart на PUT/PATCH/DELETE не работает
SEC-07
Security::csrfToken, AuthController::login/logout
CSRF-токен не ротируется при смене привилегий
P2 — масштабирование и эксплуатация
ID
Файл
Симптом
OPS-01
.htaccess, layouts/main.php, Response
Нет ETag/304/Cache-Control/gzip/expires; внешние Google Fonts
OPS-02
RedisClient::connection, DataBase::connection
Новое соединение на каждый запрос (connect+auth+select)
OPS-03
Logger::writeLog, Application::run
flock на строку, dev-лог каждого запроса, нет ротации и уровней
CACHE-04
FileCache::getPath, FileRateLimiter::withState
Плоский каталог, chmod на каждый вызов, нет GC
OPS-04
Router::dispatch, Route::matches/buildUri
Линейный regex; нет 405/HEAD; параметры не urldecode-ятся
OPS-05
DI-фабрика роутера, Application::buildContainer
Кэш маршрутов/DI не инвалидируется при деплое
OPS-06
RequestMetrics, ApiController::health
Нет /metrics, readiness, request-id
OPS-07
DataBaseInterface, Validator, View
Нет транзакций; stateful-синглтоны блокируют RoadRunner
SEC-08
RedisClient::connection
Нет TLS; auth() не проверяется; resetConnection без close()
VAL-01
Validator::validate
Нет bail → unique-запрос к БД даже при невалидном значении
P3 — расширяемость
ID
Файл
Симптом
EXT-02
config/dependency-rules.php
Ядро хардкодит Modules\Admin\Services\*
EXT-03
modules/Admin/views/layout.php
Admin грузит /assets/css/admin.css из сайта Corporate
EXT-04
View::resolvePath
Сайт не может переопределить шаблон модуля
EXT-05
Application, DI-фабрика роутера
Нет манифеста модуля (config/DI/migrations/assets)
EXT-06
bin/
Нет скаффолдеров сайта и модуля
EXT-07
AdminAuthMiddleware
Нет ролей/прав — только «админ или не админ»
EXT-08
шаблоны, Validator
Строки захардкожены по-русски, нет i18n
QA-02
.php-cs-fixer.dist.php, ci.yml
Нет PHPStan, strict_types не enforced, нет composer audit
P4 — быстрые правки
ID
Файл::метод
Проблема
Q-01
View::error
Возвращает неэкранированную строку (old() — экранирует)
Q-02
Response::json
Нет JSON_INVALID_UTF8_SUBSTITUTE → 500 на битом UTF-8
Q-03
AdminAuthMiddleware::process
ctype_digit((string)$id) пропускает true/1.0
Q-04
EngineStatsService::databaseStats
Ловит только DataBaseException
Q-05
TimeDate
Нет явной таймзоны → логи и дашборд расходятся
Q-06
Router::normalizePath
Мёртвая ветка === ''
Q-07
RequestMetrics::serverTiming
desc="1 calls" — тест фиксирует эту строку, см. §13
Q-08
Response::withHeader
Нет валидации имени/значения заголовка
Q-09
FileCache::set
Не отсекает ttl <= 0 (в отличие от RedisCache)
Q-10
CacheFailoverJournal::recordSetWithFallback
time() вместо внедрённого TimeDateInterface
Q-11
View::resolvePath
Однопроходный фильтр ..; добавить realpath()-containment
4. Карточки P0
RL-01 · Цикл редиректов при 429
Файл: app/Middleware/RateLimitMiddleware.php::buildTooManyRequestsResponse
Механика. Для не-JSON запроса метод делает session->flash() и redirect($request->uri(), 302) — на тот же URI. Браузер, запрашивающий /api/health с Accept: text/html, попадает в цикл: каждый переход снова инкрементит счётчик, пишет сессию и строку лога, пока браузер не остановится (~20 итераций).
Влияние. Один запрос бота = ~20 запросов к PHP + 20 сессионных файлов. DoS-усилитель. Retry-After на 302 бессмысленен. Для POST /admin/login цикл не возникает (редирект на GET-форму) — не путай сценарии.
Фикс.

$isApi = str_starts_with($request->uri(), '/api/');

if ($this->isJsonRequest($request) || $isApi) {
    return $this->jsonError("Слишком много запросов. Повторите через {$retryAfter} сек.", 429)
        ->withHeader('Retry-After', (string) $retryAfter)
        ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
        ->withHeader('X-RateLimit-Remaining', '0');
}

// Никаких редиректов и никакой сессии для анонимного HTML-трафика
return Response::html($this->renderTooManyRequests($retryAfter), 429)
    ->withHeader('Retry-After', (string) $retryAfter)
    ->withHeader('X-RateLimit-Limit', (string) $maxAttempts)
    ->withHeader('X-RateLimit-Remaining', '0');
	Опционально сохранить flash только для HTML-форм, у которых есть отдельная GET-страница (проверять $request->method() !== 'GET').
Приёмка. Новый тест: GET /api/health с Accept: text/html при превышении → статус 429, нет заголовка Location, SessionInterface::flash не вызывался (mock expects($this->never())).
SES-01 · Сессия на каждый анонимный запрос
Файлы: src/System/View/View.php::flash, sites/Corporate/views/layouts/main.php, modules/Admin/views/layout.php, src/System/Session/Session.php ⚠ VERIFY
Механика. Layout вызывает $this->flash('notice') → Session::getFlash(). Судя по AdminAuthMiddleware (get() затем close()), get() внутри стартует сессию. Значит каждый рендер главной = session_start() + файл + Set-Cookie + write-lock до конца запроса.
Влияние. Краулеры без cookie создают по файлу на запрос → исчерпание инод в session.save_path; конкурентные запросы одного посетителя сериализуются файловым локом. Прямой потолок RPS.
Фикс (по возрастанию усилий).
Session: не стартовать сессию, если нет cookie и нет данных на запись (hasCookie(): bool, startReadOnly()).
View::flash/old/error: читать только если сессия уже активна; после чтения без записи — close() (паттерн уже есть в AdminAuthMiddleware).
Убрать создание сессии в 429 (RL-01) и в CSRF-ответах для API.
Приёмка. Тест: рендер layout без cookie → SessionInterface::start() не вызван, Set-Cookie отсутствует. Интеграционно: 200 запросов без cookie → 0 новых файлов сессий.
CACHE-01 · Журнал failover сериализует весь пул воркеров
Файлы: src/System/Cache/CacheFailoverJournal.php::withLock/update/readEntries/writeEntries/replay, FailoverCache::recoverPendingWrites
Механика. При отказе Redis каждый get/set/delete идёт через withLock() с блокирующим LOCK_EX на одном файле; внутри — unserialize всего журнала и перезапись всего файла. replay(10) выполняется внутри пользовательского запроса: до 20 сетевых операций под тем же локом.
Влияние. При 10 000 отложенных ключей — мегабайты чтения/записи на каждую операцию, O(N²) на дренаж, полная сериализация FPM-воркеров именно во время аварии. Self-DoS.
Фикс.

// 1. Неблокирующий лок: занят — отдаём fallback, не ждём
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    return null;               // вызывающий код трактует как «replay не выполнен»
}

Вынести replay в CLI: bin/cache-replay.php (cron/systemd-timer), пакет 500–1000, бюджет времени. В HTTP-запросе — только чтение из fallback.
Шардировать журнал (pending/<aa>.dat) или append-only + компакция вместо полной перезаписи.
Q-10: внедрить TimeDateInterface вместо time().
Приёмка. Тест: при занятом локе FailoverCache::get возвращает значение из fallback без ожидания (замер < 50 мс). Тест: bin/cache-replay.php дренирует N записей и удаляет журнал.EXISTING FailoverCacheTest должен остаться зелёным — см. §13.
RL-02 · Rate limiter игнорирует драйвер + лишние файловые локи
Файлы: config/dependency-rules.php (замыкание RateLimiterInterface), src/System/RateLimit/FailoverRateLimiter.php::hit/clear
Механика.
Primary — всегда RedisRateLimiter, независимо от CACHE_DRIVER.
На каждом hit: isFailoverActive() (open+flock+read) → primary->hit() → при ошибке activateFallback() (open+flock+write) → fallback->hit() (третий open+flock+write) → ещё один isFailoverActive() после успеха.
Hold — per-IP, поэтому каждый новый посетитель раз в 5 минут оплачивает REDIS_CONNECT_TIMEOUT=1s.
Второй isFailoverActive() при флаппинге даёт двойной подсчёт (Redis + файл).
clear() чистит только активный бэкенд → после успешного входа Redis-счётчик остаётся.
Фикс.

// config/rate-limit.php
'driver' => $_ENV['RATE_LIMIT_DRIVER'] ?? $_SERVER['RATE_LIMIT_DRIVER'] ?? 'file', // file|redis

в dependency-rules.php собирать FileRateLimiter напрямую при file, FailoverRateLimiter — только при redis;
глобальный circuit-breaker (один файл или APCu на пул) вместо per-IP hold;
удалить второй isFailoverActive();
clear() — оба бэкенда;
.env.example: добавить RATE_LIMIT_DRIVER=file.
Приёмка. RATE_LIMIT_DRIVER=file → ни одной попытки подключения к Redis (mock RedisClient не вызван). FailoverRateLimiterTest остаётся зелёным.
CACHE-02 · Redis-ключи не изолированы по сайту
Файлы: src/System/Cache/RedisCache.php::formatKey, src/System/Redis/RedisClient.php::connection, config/redis.php
Механика. FileCache живёт в storage/cache/sites/<Site>/, а RedisCache::formatKey() = 'cache:' . $key с общим REDIS_PREFIX=vengine:. Два сайта на одном Redis DB читают ключи друг друга.
Влияние. Подмена контента и утечка данных между сайтами. Ломает главный сценарий роста («новый сайт = новая папка»).
Фикс (одна точка, покрывает и rate-limit).

// RedisClient::connection(), при сборке префикса
$prefix = (string) $this->config->get('redis', 'prefix', 'vengine:') . $site . ':';
$site внедрить через DI-параметр path.site. Плюс per-site имя cookie сессии (SES-02).
Приёмка. Тест: два RedisCache с разными path.site и одинаковым ключом не пересекаются. Интеграционный тест в CI проверяет префикс.
CACHE-03 · Torn-read файлового кэша превращается в HTTP 500
Файлы: src/System/Cache/FileCache.php::get/set, src/System/File/File.php::write, FailoverCache::get
Механика. set пишет file_put_contents(LOCK_EX), get читает file_get_contents без лока → читатель видит частично записанный файл → unserialize() emits E_WARNING → ErrorHandler::set_error_handler бросает PhpErrorException → 500. additionally: в FailoverCache вызовы $this->fallback->* не обёрнуты в try/catch — сбой файлового кэша уходит наружу как 500.
Фикс.
Атомарная запись: tmp + rename (образец уже есть в CacheFailoverJournal::writeEntries).
get: @unserialize + валидация структуры payload; любой сбой = cache-miss + delete.
Обернуть fallback-вызовы в FailoverCache в try/catch → default, а не 500.
Задокументировать контракт: в кэш кладём только скаляры/массивы (allowed_classes=false тихо ломает объекты при replay).
Приёмка. Тест: подсунуть обрезанный файл кэша → get() возвращает $default, исключения нет. Тест: падающий fallback → get() возвращает $default.
QA-01 · Репозиторий не собирается с нуля
Файлы: composer.json (scripts), tests/Unit/CreateAdminCommandTest.php, отсутствующие bin/*, отсутствие миграций.
Фикс.
bin/create-admin.php — контракт задан тестом: аргументы email name [password]; при передаче пароля аргументом → stderr содержит «пароль вводится отдельно», stdout пуст, exit code 2. Пароль читать интерактивно (скрытый ввод) или из STDIN.
bin/cache-clear.php — чистит storage/cache/sites/<Site>/{di,routes.compiled.php,rate-limit,redis-fallback} + файловый кэш; флаг --all-sites.
modules/Admin/database/migrations/001_create_admin_users.sql + bin/migrate.php:

REATE TABLE IF NOT EXISTS `admin_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Admin',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

⚠ VERIFY .gitignore (.env, log/*, storage/*, vendor/, .phpunit.cache) и наличие composer.lock.
Приёмка. composer install && composer test зелёный на чистом клоне. composer admin:create создаёт запись.
5. Карточки P1 (сжато)
ID
Фикс
Приёмка
SEC-01
Дефолт APP_ENV → prod во всех точках (.env.example, config/app.php, bootstrap.php, Application, ErrorHandler). Debug — отдельным APP_DEBUG + allowlist IP. В dev не рендерить previous для DB/Redis-исключений
Тест: без APP_ENV рендер продакшен-страницы без путей и стека
ERR-01
private bool $handled = false; + проверка в обоих обработчиках; флаг «ответ отправлен»; при headers_sent() — только лог
Тест: повторный вызов handleException не даёт второго тела
ERR-02
Content-negotiation в ErrorHandler: JSON для /api/* и Accept: json; отдельный рендер 404/403/429/500; ErrorRenderer::render(Throwable, bool, int $status)
Тест: 404 → тело «404», Content-Type: text/html; /api/x → JSON
SEC-02
app/Middleware/SecurityHeadersMiddleware.php + app.global_middleware в Application::run (оборачивает router->dispatch). Заголовки: X-Content-Type-Options: nosniff, Referrer-Policy: strict-origin-when-cross-origin, X-Frame-Options: DENY, Permissions-Policy, HSTS только при HTTPS
Тест: каждый ответ содержит набор заголовков
SEC-03
Сохранять remoteAddr из $serverParams в конструкторе; нормализация IP через inet_pton; убрать '*'; в .env.example описать Docker-сценарий
Тест: X-Forwarded-For учитывается при injected REMOTE_ADDR доверенного прокси; * не принимается
SEC-04
Всегда password_verify($password, $dummyHash) при отсутствии пользователя; в логе — хеш email вместо email; bucket admin_login_account; captcha после N неудач
Тест: authenticate вызывает verifyPassword и для неизвестного email
SEC-05
Глобальный мягкий rate limit через app.global_middleware; отдельный bucket для POST/PUT/PATCH/DELETE; validateOrFail + max:1000 в HomeController::submit
Тест: POST / сверх лимита → 429
SES-02
⚠ VERIFY в Session.php: use_strict_mode=1, use_only_cookies=1, sid_length>=48, sid_bits_per_character=5, cookie-параметры до session_start(), gc_maxlifetime = lifetime, remove() после close() перезапускает сессию. Per-site имя cookie. Idle+absolute timeout админки. Failover или явная деградация для SESSION_DRIVER=redis
Тест на ini-настройки; документируем Redis как hard dependency, если failover не делается
EXT-01
sites/<Site>/config/modules.php → allowlist; DI-фабрика роутера грузит только разрешённые модули. Дефолт для Corporate: ['Admin'], для нового сайта: []
Тест: сайт без Admin в allowlist → /admin/login = 404
SEC-06
Server-Timing только при APP_ENV=dev или для авторизованного админа
Тест: prod-ответ без Server-Timing
HTTP-01
Лимит тела по Content-Length (дефолт 1 МБ) → HttpException(413); не читать php://input для GET/HEAD; документировать multipart-ограничение
Тест: тело 2 МБ → 413; GET не читает поток
SEC-07
Ротация CSRF-токена в AuthController::login/logout после regenerate(true) (новый метод SecurityInterface::rotateCsrfToken())
Тест: токен до и после входа различается
6. Карточки P2 (сжато)
ID
Фикс
Ожидаемый эффект
OPS-01
ETag + If-None-Match → 304; Cache-Control: public, max-age=60, stale-while-revalidate=300 для анонимных GET; .htaccess: Options -Indexes, DirectoryIndex, mod_deflate, mod_expires (/assets/* → 1y immutable); self-host шрифтов вместо Google Fonts
是最大的 выигрыш: большинство визитов не доходит до PHP
OPS-02
Redis::pconnect (всегда select() при pconnect), PDO::ATTR_PERSISTENT + обработка «server has gone away»; OPcache + preload в прод-конфиге
Убирает connect-storm и TIME_WAIT
OPS-03
Ротация по дню/размеру; в prod — только error; sampling; request-id в каждой строке; redaction PII
Снимает flock-контеншен и рост диска
CACHE-04
Шардирование ab/cd/<hash>, chmod только при создании, mkdir 0700, вероятностный GC (1/1000) + cron-чистка
Плоский каталог перестаёт деградировать
OPS-04
Fast-path array для статических путей, затем chunked-regex; 405 при совпадении пути с другим методом; HEAD→GET; 301 канонизация слеша; rawurldecode параметров после матчинга
Линейный скан уходит; SEO-дубли исчезают
OPS-05
Ключ версии в имени кэша (routes.<hash>.compiled.php, hash от mtime роут-файлов + списка модулей); то же для DI; composer cache:clear в deploy-хуке
Новый модуль появляется без ручного удаления файлов
OPS-06
/metrics (Prometheus, закрыт токеном), readiness-эндпоинт с проверкой БД/Redis, X-Request-Id в ответе и логах
Инциденты разбираемы
OPS-07
DataBaseInterface::beginTransaction/commit/rollBack/transaction(callable); retry на разрыв; reset() у stateful-сервисов (Validator, View, RequestMetrics)
Разблокирует RoadRunner/FrankenPHP
SEC-08
TLS-опции для Redis/MySQL; проверка результата auth(); resetConnection() вызывает close()
Credentials не в открытом виде
VAL-01
Bail на первой ошибке поля (или как минимум пропуск unique/exists, если поле уже невалидно)
Убирает DB-амплификацию под атакой
7. Карточки P3 — целевая модель расширяемости
Целевой контракт модуля (modules/<Module>/module.php):

<?php
declare(strict_types=1);

return [
    'name'        => 'Admin',
    'version'     => '1.0.0',
    'definitions' => __DIR__ . '/config/dependency-rules.php', // DI модуля
    'config'      => __DIR__ . '/config',                      // merge в Config
    'routes'      => __DIR__ . '/routes.php',
    'migrations'  => __DIR__ . '/database/migrations',
    'assets'      => __DIR__ . '/public',                      // → bin/assets:publish
    'middleware'  => [],                                       // глобальные, если нужны
];

ID
Изменение
EXT-02
Перенести биндинги AdminUserService / EngineStatsService из config/dependency-rules.php в modules/Admin/config/dependency-rules.php. Ядро больше не знает о модулях
EXT-03
modules/Admin/public/assets/css/admin.css + bin/assets:publish.php (copy/symlink в sites/<Site>/public/assets/vendor/<Module>/). Layout ссылается на вендорный путь
EXT-04
View::resolvePath: цепочка sites/<Site>/views/vendor/<Module>/<path> → modules/<Module>/views/<path>
EXT-05
Application собирает манифесты (glob(modules/*/module.php)), фильтрует по allowlist сайта (EXT-01), мержит definitions/config, регистрирует маршруты
EXT-06
bin/create-site.php <Name> и bin/create-module.php <Name> — генерируют структуру из home/index.php-инструкции
EXT-07
Колонка role в admin_users + AdminRoleMiddleware / Gate; инфраструктурные блоки дашборда — только для role=owner
EXT-08
TranslatorInterface + lang/ru.php, lang/en.php; вынести строки шаблонов и валидатора
QA-02
PHPStan level 8 в CI; declare_strict_types (risky allowed) в php-cs-fixer; composer audit в CI; закоммитить composer.lock
8. Целевая прод-конфигурация (эталон)

APP_ENV=prod
APP_DEBUG=false
APP_SITE=Corporate
CACHE_DRIVER=redis
CACHE_REDIS_FALLBACK=true
RATE_LIMIT_DRIVER=redis          # после RL-02
SESSION_DRIVER=redis
SESSION_SECURE=true
TRUSTED_PROXIES=172.18.0.0/16    # реальная подсеть прокси, НЕ "*" и НЕ 127.0.0.1 в Docker
REDIS_PREFIX=vengine:            # site добавляется автоматически (CACHE-02)
REDIS_CONNECT_TIMEOUT=0.3
REDIS_TIMEOUT=0.2
DB_CONNECT_TIMEOUT=2

Deploy-хук (обязателен после OPS-05):
composer install --no-dev --optimize-autoloader
php bin/migrate.php
php bin/assets:publish.php
php bin/cache-clear.php --all-sites   # снимает stale DI + routes
# opcache_reset через FPM reload

9. План по фазам
Фаза 0 (разблокировка)      QA-01
        │
        ▼
Фаза 1 (P0, нагрузка)       RL-01  SES-01  CACHE-01  RL-02  CACHE-02  CACHE-03
        │                   └─ RL-02 требует CACHE-02 (общий префикс)
        ▼
Фаза 2 (P1, безопасность)   SEC-01 → ERR-01 → ERR-02 → SEC-02 → SEC-03
                            SEC-04  SEC-05  SES-02  EXT-01  SEC-06  HTTP-01  SEC-07
        │                   └─ SEC-05 требует SEC-02 (глобальный конвейер)
        ▼
Фаза 3 (P2, масштаб)        OPS-01  OPS-02  OPS-03  CACHE-04  OPS-05  OPS-04
                            OPS-06  OPS-07  SEC-08  VAL-01
        │                   └─ OPS-05 требует QA-01 (bin/cache-clear.php)
        ▼
Фаза 4 (P3, расширяемость)  EXT-05 → EXT-02  EXT-03  EXT-04  EXT-06  EXT-07  EXT-08  QA-02
        │                   └─ EXT-05 требует EXT-01 (allowlist)
        ▼
Фаза 5                      P4-правки одним PR

Фаза
Definition of Done
0
composer install && composer test зелёный на чистом клоне; БД поднимается миграцией
1
Нагрузочный прогон tests/load/http_smoke.py --workers 16 --duration 60: 0 failures; при выключенном Redis p95 не деградирует >2×; нет роста числа сессионных файлов от анонимного трафика
2
Ни один ответ в prod не содержит путей/стека; все ответы несут security-заголовки; /admin/login отсутствует на сайте без Admin в allowlist
3
Анонимная главная отдаёт 304 при повторном If-None-Match; логи ротируются; деплой не требует ручной очистки кэша
4
Новый модуль и новый сайт создаются одной командой, без правок src/ и config/

10. Матрица «фикс → тест»
ID
Тест (новый/обновляемый)
RL-01
tests/Unit/RateLimitMiddlewareTest.php — 429 без Location, без flash
SES-01
tests/Unit/SessionLazyStartTest.php (новый)
CACHE-01
tests/Unit/FailoverCacheTest.php + CacheJournalNonBlockingTest.php (новый)
RL-02
tests/Unit/RateLimitDriverConfigTest.php (новый) + существующий FailoverRateLimiterTest
CACHE-02
tests/Unit/RedisKeyNamespaceTest.php (новый)
CACHE-03
tests/Unit/FileCacheCorruptionTest.php (новый)
QA-01
существующий CreateAdminCommandTest + MigrateCommandTest (новый)
SEC-01
tests/Unit/ErrorRendererEnvTest.php (новый)
ERR-01/02
tests/Unit/ErrorHandlerTest.php (новый)
SEC-02
tests/Unit/SecurityHeadersMiddlewareTest.php (новый)
SEC-03
tests/Unit/RequestTest.php — дополнить кейсами прокси
SEC-04
tests/Unit/AdminUserServiceTest.php (новый)
EXT-01
tests/Unit/ApplicationSiteTest.php — дополнить allowlist-кейсом
OPS-05
tests/Unit/RouteCacheInvalidationTest.php (новый)
11. Команды валидации (обязательны перед завершением задачи)


php -l <каждый изменённый PHP-файл>
vendor/bin/phpunit tests/Unit/<TestClass>.php   # точечный прогон
composer cs:check                               # PHP-CS-Fixer @PER-CS
composer test                                   # весь сьют

Интеграционные (только при поднятых MySQL/Redis):

CI_INTEGRATION=1 DB_HOST=127.0.0.1 DB_USER=root DB_PASS=root \
REDIS_HOST=127.0.0.1 CACHE_DRIVER=redis composer test

Нагрузочная проверка после Фазы 1:

php -S 127.0.0.1:8000 -t sites/Corporate/public &
python3 tests/load/http_smoke.py --url http://127.0.0.1:8000/ --workers 16 --duration 60Правило отчётности (из copilot-instructions.md): сообщай только те команды, которые реально запустил, и их фактический результат. Не заявляй «прошло», если не запускал.

12. Инварианты — что нельзя ломать
Эти решения уже правильные. Не «рефактори» их:
Session::regenerate(true) при входе и выходе — защита от session fixation.
hash_equals в Security::verifyCsrfToken и AuthMiddleware.
Все SQL — через PDO-плейсхолдеры; Validator::sanitizeIdentifier (^[a-zA-Z0-9_]+$) защищает интерполяцию имён таблиц.
View::escape() (ENT_QUOTES | ENT_SUBSTITUTE), View::old() экранирует, шаблоны экранируют flash.
Validator::validateOrFail возвращает только описанные в правилах поля — защита от mass assignment.
AdminAuthMiddleware читает сессию и сразу close() — образец, который надо распространить, а не убрать.
Application валидирует APP_SITE регуляркой и проверяет обязательные каталоги.
Router::$routesByMethod — индекс по методу; Route::compilePattern экранирует литералы через preg_quote.
FileRateLimiter создаёт каталог 0700, файл 0600.
tests/load/http_smoke.py ограничен по duration/workers и требует --allow-remote для нелокальных URL.
Границы src/ ↔ app/ ↔ sites/ ↔ modules/; интерфейсы + биндинги; declare(strict_types=1).
13. Ловушки: тесты, которые сломаются от «очевидных» улучшений
Изменение
Что сломается
Как обойти
Шардирование кэша в 2 уровня (data/ab/file.cache)
FailoverCacheTest::tearDown чистит только один уровень вложенности
Обновить tearDown на рекурсивный обход в том же PR
Создание каталога/файла журнала «заранее»
FailoverCacheTest asserts assertFileDoesNotExist(pending.dat) и assertDirectoryDoesNotExist(data) на здоровом пути
Не создавать артефакты на healthy-path; проверять существование до записи
Исправление грамматики desc="1 calls" (Q-07)
RequestMetricsTest явно ассертит desc="1 calls"
Менять код и тест одновременно
Смена семантики hold с per-IP на глобальную (RL-02)
FailoverRateLimiterTest проверяет per-identifier поведение
Тест использует один identifier — проходит; но добавить кейс с двумя IP
Удаление второго isFailoverActive
Ничего не ломает (проверено по тесту)
—
Дефолт APP_ENV=prod (SEC-01)
ApplicationSiteTest, EngineStatsServiceTest мокают env; Config подхватит .env репозитория
Явно устанавливать env в setUp/tearDown новых тестов
14. Что уже хорошо (не переписывать)
Failover-архитектура кэша и rate-limit с журналом — сильная идея, проблема только в месте исполнения (request-path вместо фонового воркера) и в гранулярности лока. Индекс маршрутов по методу, атомарная запись журнала через tmp+rename, ограничение размера пакета replay, bounded load-скрипт, чёткие границы слоёв и последовательные интерфейсы — база, на которой стоит строить, а не заменять.
15. Открытые вопросы к владельцу
Есть ли Session.php, bin/*, composer.lock, .gitignore, схема БД? (блокирует Фазу 0)
Целевой профиль нагрузки: RPS пик, доля анонимного трафика, число сайтов на инстанс?
Топология: Nginx + PHP-FPM, Docker,shared Redis на несколько сайтов? (определяет TRUSTED_PROXIES и CACHE-02)
Планируется ли long-running runtime (RoadRunner/FrankenPHP)? (определяет приоритет OPS-07)
Нужны ли роли в админке и i18n в ближайших 2–3 модулях? (определяет приоритет EXT-07/EXT-08)
Рекомендуемый первый PR: QA-01 (разблокировка сборки), затем RL-01 + SES-01 одним спринтом — они вместе снимают и DoS-амплификацию, и основной потолок RPS.
