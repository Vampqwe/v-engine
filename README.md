
# V-engine

Легковесный модульный PHP-фреймворк (PHP 8.2+) с архитектурой, построенной на строгой типизации, внедрении зависимостей (PSR-11 / PHP-DI) и полной изоляции системных компонентов.

---

## Ключевые особенности

* **Строгая модульность и SOLID.** Каждый системный компонент изолирован, имеет собственный контракт (`*Interface`) и именованное исключение, наследуемое от единого `BaseException`.
* **Автоматическое внедрение зависимостей.** Вся сборка сервисов, контроллеров и посредников (Middleware) работает поверх `php-di/php-di` с поддержкой autowiring.
* **Отказоустойчивая обработка ошибок.** Перехват ошибок до загрузки Composer, модульное логирование и стильная страница отладки с подсветкой фрагмента исходного кода.
* **Декларативная маршрутизация.** Поддержка вложенных групп маршрутов, именованных ссылок, регулярных ограничений в параметрах и конвейера посредников.
* **Безопасность из коробки.** Защита от CSRF-атак, криптографически стойкие токены, безопасное управление сессиями, защита от Path Traversal и подготовленные SQL-запросы.

---

## Требования

* **PHP** `>= 8.2`
* **Обязательные расширения PHP:** `ext-pdo`, `ext-pdo_mysql`, `ext-json`, `ext-mbstring`
* **Опционально:** `ext-redis`, если `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `RATE_LIMIT_DRIVER=redis` или `QUEUE_DRIVER=redis`
* **Composer** `2.x`
* **Веб-сервер:** Apache (с `mod_rewrite`), Nginx или встроенный сервер PHP для разработки

---

## Установка и быстрый старт

1. **Клонируйте репозиторий и установите зависимости:**
   ```bash
   git clone https://github.com/Vampqwe/v-engine.git
   cd v-engine
   composer install
   ```

2. **Настройте окружение:**
   Скопируйте шаблон переменных окружения и укажите параметры подключения к MySQL:
   ```bash
   cp .env.example .env
   ```
   В `APP_ENV=prod` флаг `SESSION_SECURE` по умолчанию включен; явно устанавливайте `SESSION_SECURE=false` только для локального HTTP.
   `ENGINE_MODE=default` оставляет обычную загрузку PHP-маршрутов. Для сайта из готовых HTML-страниц задайте `ENGINE_MODE=static` и укажите публичный origin сайта в `APP_URL`, например `https://example.com`.

3. **Запустите встроенный сервер для разработки:**
   ```bash
   php -S localhost:8000 -t sites/Corporate/public
   ```
   Приложение будет доступно по адресу `http://localhost:8000`.

4. **Проверка стандартов кодирования (PSR):**
   ```bash
   composer cs:check   # Проверка стиля кода
   composer cs:fix     # Автоматическое форматирование
   composer test       # Запуск модульных тестов
   ```

### Console-команды

Все прикладные CLI-команды регистрируются в Console-фреймворке и запускаются через единый entrypoint:

```bash
php bin/console.php list
php bin/console.php help admin:create
php bin/console.php admin:create [email] [name]
php bin/console.php cache:clear
php bin/console.php rate-limit:gc
```

Для совместимости сохранены короткие команды Composer (`composer admin:create`, `composer cache:clear`, `composer rate-limit:gc`) и PHP-обёртки в `bin/`. Пароль администратора вводится отдельно скрытым интерактивным запросом и не передаётся аргументом процесса.

В админ-панели `/admin/cli` доступен только фиксированный allowlist операций: `cache:clear` и неблокирующий `rate-limit:gc`. POST-запросы требуют авторизацию администратора и CSRF-токен. Это не shell: произвольные команды, аргументы процесса и shell-интерпретаторы из браузера не запускаются. Новые команды не становятся доступными в web-интерфейсе автоматически — для них требуется отдельное явное разрешение.

Настройки подключения находятся в `config/database.php` и `config/redis.php`; выбор кэш-драйвера, параметры сессии и лимиты запросов — в `config/cache.php`, `config/session.php` и `config/rate-limit.php`. Секреты подключения задавайте только в `.env`.

### Статический режим сайта

При `ENGINE_MODE=static` приложение не загружает PHP route registrars сайта и модулей. Оно использует `sites/<APP_SITE>/routes.json`, `sites/<APP_SITE>/route_aliases.json` и HTML-файлы в `sites/<APP_SITE>/pages/`. `routes.json` — JSON-объект вида `URL: относительный путь к HTML-файлу`; `route_aliases.json` — объект `alias URL: существующий URL из routes.json`. Алиасы разрешаются внутренне без HTTP-редиректа. Поддерживаются только GET и HEAD; неизвестный маршрут или отсутствующая страница дают 404. Страницы остаются вне web root, а файлы доступны только через явно заданные маршруты.

`sites/Corporate/routes.json`:

```json
{
  "/": "index.html",
  "/about": "about.html"
}
```

`sites/Corporate/route_aliases.json`:

```json
{
  "/home": "/",
  "/about-us": "/about"
}
```

Страница `/about-us` отдаст `pages/about.html`, сохраняя исходный URL. Пути страниц должны быть относительными `.html` путями внутри `pages/`; traversal и выход через symlink за пределы этой папки запрещены. CSS, JavaScript и изображения размещайте в `sites/<APP_SITE>/public/assets/` и используйте абсолютные URL, например `/assets/css/site.css`. По умолчанию активен обычный режим; существующие PHP-маршруты и production-кэш маршрутов при этом не меняются.

В static-режиме модуль `modules/Seo` автоматически создаёт `sitemap.xml` и `robots.txt` в корне `sites/<APP_SITE>/public/`, обновляя их атомарно только при изменении содержимого. Sitemap строится по каноническим маршрутам из `routes.json` (без aliases); `robots.txt` разрешает индексацию и указывает абсолютный URL sitemap. `APP_URL` обязателен только для static-режима и должен быть абсолютным `http://` или `https://` origin без пути, query или fragment. Для генерации PHP-процессу нужно право записи в `public/`; выделяйте доступ только этому каталогу и не делайте web root доступным для записи другим пользователям. В приложении также регистрируются GET/HEAD-маршруты для обоих файлов, чтобы они отдавались сразу после генерации.

Если приложение находится за reverse proxy, задайте `TRUSTED_PROXIES` точными IP-адресами или CIDR-сетями контролируемых прокси. Без этого заголовки forwarded IP игнорируются; не указывайте широкие или чужие подсети.

В режиме разработки ответы содержат `Server-Timing` с полной длительностью приложения и суммарным временем/числом обращений к MySQL и Redis; в production заголовок не отправляется. Для p95/p99 и частоты ошибок собирайте тайминги вместе с access-логами веб-сервера или APM; не используйте локальный файловый лог как высокочастотное хранилище метрик. Таймауты Redis (`REDIS_CONNECT_TIMEOUT`, `REDIS_TIMEOUT`) и MySQL (`DB_CONNECT_TIMEOUT`) ограничиваются конфигурацией.

Для защищённого демонстрационного API-маршрута `/api/profile` задайте `API_TOKEN` длиной не менее 32 символов и передавайте его как `Authorization: Bearer <token>`. Секрет сравнивается без передачи в контроллер или ответ. Это общий API-ключ, а не система пользовательских учётных записей; для персональной авторизации требуется отдельный механизм.

Админ-панель находится по `/admin` и показывает состояние выбранного сайта, PHP-среды, расширений, маршрутов, модулей, дискового пространства, MySQL, Redis и драйвера сессий. Счётчик администраторов доступен после создания таблицы `admin_users` командой `php bin/console.php admin:create`.

Если `CACHE_DRIVER=redis`, параметр `CACHE_REDIS_FALLBACK=true` (по умолчанию) включает переключение к файловому кэшу при ошибке Redis. Штатные обращения к Redis не создают файловую зеркальную копию. При сбое записи журналируются в файловом failover-хранилище, а после восстановления replay выполняется пакетами до 10 операций на запрос; время жизни записей сохраняется. Файловый failover-кэш находится в `storage/cache/sites/<APP_SITE>/redis-fallback`. Он предназначен для одного приложения на общем локальном диске: это best-effort failover кэша, а не репликация или транзакционное хранилище. Переключение сессий намеренно не выполняется: `SESSION_DRIVER` остаётся отдельной настройкой, и при сбое Redis-сессий запрос завершается ответом 503. Время жизни cookie сессии — абсолютные два часа с момента выдачи. Redis-ключи всех драйверов включают `APP_SITE` в namespace.

Rate limiter по умолчанию использует файловые счётчики; Redis можно включить через `RATE_LIMIT_DRIVER=redis`, после чего при его недоступности активируется файловый fallback. После восстановления Redis файловый fallback остаётся активным для каждого IP ещё в течение окна ограничения, затем автоматически возвращается к Redis. Счётчики хранятся в `storage/cache/sites/<APP_SITE>/rate-limit`; каталоги шардированы, устаревшие состояния периодически удаляются. Файловый backend рассчитан на один сервер с локальным диском, не на несколько узлов без общего хранилища.

Для гарантированной очистки просроченных файлов rate limiter настройте периодический CLI-запуск `composer rate-limit:gc` от пользователя приложения. Команда ждёт освобождения блокировки текущих запросов и ограничивает количество проверяемых файлов за один запуск.

### Очередь заданий

Модуль `Modules\Queue` поддерживает `QUEUE_DRIVER=file` (по умолчанию), `sync` для немедленного выполнения в dev/test и `redis`. В Redis-режиме ready-задания хранятся в LIST, отложенные — в ZSET; резервирование, возврат просроченных lease и подтверждение выполняются атомарными Lua-скриптами. Для Redis требуется `ext-redis`. Файловый драйвер использует атомарное перемещение файлов, рассчитан на узлы с общим локальным диском; для нескольких серверов используйте Redis.

Чтобы разрешить задание для worker, добавьте его FQCN в `QUEUE_JOBS` через запятую. Каждый класс-наследник `Modules\Queue\Job` реализует `handle()`, `payload()` и `static fromPayload(array $payload, ContainerInterface $container): static`. Payload сохраняется и загружается только как JSON; worker принимает только явно разрешённые классы. `tries()` задаёт число попыток (по умолчанию 3), `backoff($attempt)` — задержку перед следующей попыткой в секундах. После исчерпания попыток JSON-запись попадает в `storage/cache/sites/<APP_SITE>/queue/failed/<queue>/`.

Запуск worker:

```bash
php bin/queue-worker.php --queue=default --timeout=60 --tries=3
# Эквивалентно: php bin/console.php queue:work --queue=default --timeout=60 --tries=3
```

`--timeout` ограничивает время жизни процесса, а не длительность отдельного задания: worker завершит текущее задание и затем остановится. SIGTERM и SIGINT обрабатываются через `pcntl`, если расширение доступно; настройте `QUEUE_VISIBILITY_TIMEOUT` больше максимальной ожидаемой длительности задания, чтобы не допускать повторного резервирования долгой работы. Доставка имеет семантику at-least-once: обработчики должны быть идемпотентны. `QUEUE_DRIVER=sync` выполняет `push()` немедленно; `pushDelayed()` с положительной задержкой в sync-режиме явно отклоняется.

### Шина событий

`Modules\Events\EventsInterface` предоставляет общую шину для синхронных и асинхронных listeners. Модуль регистрирует listeners в `modules/<Module>/module.php`; файл возвращает callable, принимающий `EventsInterface`:

```php
<?php

use Modules\Events\EventsInterface;
use Modules\Users\Events\UserRegistered;
use Modules\Users\Listeners\SendWelcomeMail;

return static function (EventsInterface $events): void {
    $events->listen(UserRegistered::class, SendWelcomeMail::class, async: true);
};
```

Без `async: true` listener запускается прямо при `dispatch()`. Для асинхронного listener шина кладёт `Modules\Events\Queue\ListenerJob` в очередь; его регистрация в allowlist worker выполняется автоматически. Событие наследуется от `Modules\Events\Event` и реализует `payload()`/`fromPayload()`. Payload async-событий должен быть JSON-сериализуемым; передавайте минимально необходимые данные и не помещайте туда секреты. При `QUEUE_DRIVER=sync` queued listener выполняется немедленно, что удобно для разработки и тестов.

CI запускает интеграционные проверки MySQL и Redis. Локально `composer test` пропускает их, если не задано `CI_INTEGRATION=1`.

### Проверка производительности

Поднимите приложение через целевой веб-сервер/PHP-FPM и запустите ограниченный smoke-нагрузочный тест:

```bash
python3 tests/load/http_smoke.py --url http://127.0.0.1/ --duration 10 --workers 4
```

Скрипт по умолчанию разрешает только локальный адрес; удалённый разрешается только с явным `--allow-remote` и при наличии прав на тестирование. Начинайте с малых значений, наблюдая за PHP-FPM, MySQL, Redis и диском. Встроенный сервер PHP однопоточный и не подходит для оценки пропускной способности.

Настраивайте `pm.max_children` PHP-FPM с учётом памяти и бюджета соединений БД: при одном синхронном PDO на запрос число одновременно занятых DB-соединений не должно превышать `max_connections` MySQL за вычетом резерва для администрирования и других приложений. Файловые сессии/кэш пригодны для одного узла; при нескольких узлах используйте общее хранилище (обычно Redis), поскольку локальные файлы не разделяются между серверами. Ротацию файлов в `log/` настраивайте средствами ОС/контейнерной платформы. Долгие операции, не требующие немедленного HTTP-ответа, обрабатывайте через модуль очередей.

---

## Структура проекта

```text
v-engine/
├── bootstrap.php              # Bootstrap общего ядра, вне document root
├── app/                        # Общие базовые классы приложения
├── config/                     # Конфигурационные файлы
│   ├── database.php            # Параметры подключения к БД
│   ├── dependency-rules.php    # Правила связывания интерфейсов для PHP-DI
│   └── session.php             # Параметры сессии
├── log/                        # Журналы, вне публичной директории
├── modules/                    # Переиспользуемые модули, например Admin и Queue
│   └── Seo/                    # SEO-файлы для static режима
├── sites/                      # Сайты приложения: каждый сайт самодостаточен
│   └── Corporate/
│       ├── App/                # Контроллеры и middleware (namespace Sites\Corporate\App\)
│       ├── routes/             # Маршруты только этого сайта
│       ├── pages/              # Готовые HTML-страницы для ENGINE_MODE=static
│       ├── routes.json         # URL → HTML page для static режима
│       ├── route_aliases.json  # URL alias → URL маршрута для static режима
│       ├── views/              # Шаблоны и layout сайта
│       └── public/             # Document root: index.php, assets и генерируемые sitemap/robots
├── src/                        # Ядро фреймворка (namespace VEngine\)
│   ├── Kernel/
│   │   └── Application.php     # Инициализация путей, DI-контейнера и жизненный цикл
│   └── System/                 # Системные модули ядра
├── storage/
│   └── cache/                  # Кэш ядра и выбранного сайта
```

`APP_SITE` выбирает один сайт на экземпляр приложения; по умолчанию используется `Corporate`. Для обычного режима создайте сайт с каталогами `App/`, `routes/`, `views/` и `public/`; для `ENGINE_MODE=static` нужны `pages/` и `public/`, а также `routes.json` и `route_aliases.json`. Веб-сервер должен использовать `sites/<APP_SITE>/public` как document root. Общие базовые классы (`App\`), ядро (`VEngine\`) и модули (`Modules\`) остаются общими. Кэш приложения также разделён по сайтам.

---

## Карта системных модулей (`src/System/`)

| Модуль | Назначение | Основные классы и интерфейсы |
| :--- | :--- | :--- |
| **Cache** | Файловое кэширование с поддержкой TTL и защитой от небезопасной десериализации | `CacheInterface`, `FileCache`, `AbstractCache` |
| **Config** | Загрузка переменных `.env` и чтение PHP-конфигов с поддержкой точечной нотации | `ConfigInterface`, `Config` |
| **DataBase** | Ленивое PDO-подключение к MySQL с отключенной эмуляцией `prepare` | `DataBaseInterface`, `DataBase` |
| **Error** | Перехват исключений и фатальных ошибок, рендер HTML-страницы 500/404 в режиме `dev` | `ErrorHandlerInterface`, `ErrorRendererInterface` |
| **Exception** | Базовая иерархия исключений ядра | `BaseException` |
| **File** | Безопасная работа с файловой системой и блокировками записи (`LOCK_EX`) | `FileInterface`, `File` |
| **Http** | Объектно-ориентированные обертки над HTTP-запросом и иммутабельным ответом | `RequestInterface`, `ResponseInterface`, `HttpException` |
| **Logger** | Иммутабельное модульное логирование (`forModule()`) в изолированные файлы | `LoggerInterface`, `Logger` |
| **Map** | Работа со вложенными массивами по точечной нотации (`user.profile.email`) | `MapInterface`, `Map` |
| **Middleware** | Слой перехвата и фильтрации HTTP-запросов по паттерну Pipeline | `MiddlewareInterface`, `AbstractMiddleware` |
| **Router** | Маршрутизация с поддержкой групп, регулярных параметров, именованных URL и DI | `RouterInterface`, `Router`, `Route` |
| **Security** | Хеширование паролей, криптостойкие токены, защита от XSS и CSRF | `SecurityInterface`, `Security` |
| **Session** | Управление PHP-сессиями и одноразовыми Flash-сообщениями | `SessionInterface`, `Session` |
| **TimeDate** | Обертка над временем и `DateTimeImmutable` для предсказуемого тестирования | `TimeDateInterface`, `TimeDate` |
| **Validator** | Валидация массивов данных по правилам (`required`, `email`, `unique`, `exists` и др.) | `ValidatorInterface`, `Validator`, `ValidationException` |
| **View** | Нативный PHP-шаблонизатор с макетами (`layouts`), экранированием и CSRF-хелперами | `ViewInterface`, `View` |

---

## Примеры использования

### 1. Описание маршрутов (`sites/Corporate/routes/web.php`)
Все `.php` файлы из директории выбранного сайта `sites/<APP_SITE>/routes/` подключаются автоматически:

```php
<?php

declare(strict_types=1);

use Sites\Corporate\App\Controllers\HomeController;
use App\Middleware\CsrfMiddleware;
use VEngine\System\Router\RouterInterface;

return static function (RouterInterface $router): void {
    $router->get('/', [HomeController::class, 'index'])->name('home');
    $router->get('/user/{id:\d+}', [HomeController::class, 'show'])->name('user.show');

    $router->group(['prefix' => '/profile', 'middleware' => [CsrfMiddleware::class]], static function (RouterInterface $router): void {
        $router->post('/update', [HomeController::class, 'update'])->name('profile.update');
    });
};
```

### 2. Контроллер с валидацией и рендерингом (`sites/Corporate/App/Controllers/HomeController.php`)
Зависимости автоматически подставляются в конструктор и методы контроллера через `PHP-DI`:

```php
<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Controllers;

use App\Controllers\AbstractController;
use VEngine\System\DataBase\DataBaseInterface;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Security\SecurityInterface;
use VEngine\System\Session\SessionInterface;
use VEngine\System\Validator\ValidatorInterface;

final class HomeController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->view('home/index', [
            'title'    => 'Главная страница',
            'username' => 'Developer',
        ]);
    }

    public function register(
        RequestInterface $request,
        ValidatorInterface $validator,
        SecurityInterface $security,
        SessionInterface $session,
        DataBaseInterface $db
    ): ResponseInterface {
        $data = $validator->validateOrFail($request->all(), [
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $db->query(
            'INSERT INTO users (email, password) VALUES (:email, :password)',
            [
                'email'    => $data['email'],
                'password' => $security->hashPassword($data['password']),
            ]
        );

        $session->flash('notice', 'Регистрация прошла успешно!');
        return $this->redirectToRoute('home');
    }
}
```

### 3. Создание Middleware сайта (`sites/Corporate/app/Middleware/SiteAuthMiddleware.php`)

```php
<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\Session\SessionInterface;

final class SiteAuthMiddleware extends AbstractMiddleware
{
    public function __construct(
        private readonly SessionInterface $session
    ) {}

    public function process(RequestInterface $request, callable $next): ResponseInterface
    {
        if (!$this->session->has('user.id')) {
            return $this->isJsonRequest($request)
                ? $this->jsonError('Требуется авторизация', 401)
                : $this->redirect('/');
        }

        return $next($request);
    }
}
```

### 4. Использование шаблона (`views/home/index.php`)

```php
<?php
/** @var \VEngine\System\View\ViewInterface $this */
/** @var string $username */
?>
<h1>Привет, <?= $this->escape($username) ?>!</h1>

<form method="POST" action="<?= $this->route('profile.update') ?>">
    <?= $this->csrfField() ?>
    <input type="text" name="name" required>
    <button type="submit">Сохранить</button>
</form>
```

---

## Лицензия

Проект распространяется под лицензией MIT.
