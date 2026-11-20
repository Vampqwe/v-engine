
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
* **Опционально:** `ext-redis`, если `CACHE_DRIVER` или `SESSION_DRIVER` установлены в `redis`
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

3. **Запустите встроенный сервер для разработки:**
   ```bash
   php -S localhost:8000 -t public
   ```
   Приложение будет доступно по адресу `http://localhost:8000`.

4. **Проверка стандартов кодирования (PSR):**
   ```bash
   composer cs:check   # Проверка стиля кода
   composer cs:fix     # Автоматическое форматирование
   composer test       # Запуск модульных тестов
   ```

Для создания администратора запустите `php bin/create-admin.php [email] [name]`. Пароль вводится отдельно в скрытом интерактивном запросе и не передается аргументом процесса.

Настройки подключения находятся в `config/database.php` и `config/redis.php`; выбор кэш-драйвера, параметры сессии и лимиты запросов — в `config/cache.php`, `config/session.php` и `config/rate-limit.php`. Секреты подключения задавайте только в `.env`.

Для защищённого демонстрационного API-маршрута `/api/profile` задайте `API_TOKEN` длиной не менее 32 символов и передавайте его как `Authorization: Bearer <token>`. Секрет сравнивается без передачи в контроллер или ответ. Это общий API-ключ, а не система пользовательских учётных записей; для персональной авторизации требуется отдельный механизм.

Если `CACHE_DRIVER=redis`, параметр `CACHE_REDIS_FALLBACK=true` (по умолчанию) включает переключение к файловому кэшу при ошибке Redis. Записи и удаления, сделанные во время сбоя, журналируются и повторяются в Redis после восстановления; время жизни записей сохраняется. Файловый failover-кэш находится в `storage/cache/redis-fallback`. Он предназначен для одного приложения на общем локальном диске: это best-effort failover кэша, а не репликация или транзакционное хранилище. Переключение сессий намеренно не выполняется: `SESSION_DRIVER` остаётся отдельной настройкой, и при сбое Redis-сессий запрос завершается ошибкой.

CI запускает интеграционные проверки MySQL и Redis. Локально `composer test` пропускает их, если не задано `CI_INTEGRATION=1`.

---

## Структура проекта

```text
v-engine/
├── app/                        # Код пользовательского приложения (namespace App\)
│   ├── Controllers/            # Контроллеры (наследуются от AbstractController)
│   └── Middleware/             # Пользовательские посредники (наследуются от AbstractMiddleware)
├── config/                     # Конфигурационные файлы
│   ├── routes/                 # Декларативные файлы маршрутов (web.php, api.php)
│   ├── database.php            # Настройки кодировки БД
│   ├── dependency-rules.php    # Правила связывания интерфейсов для PHP-DI
│   └── session.php             # Параметры cookie и времени жизни сессии
├── log/                        # Журналы событий (раздельные .log файлы по модулям)
├── public/                     # Публичная директория веб-сервера
│   └── index.php               # Единая точка входа
├── src/                        # Ядро фреймворка (namespace VEngine\)
│   ├── Kernel/
│   │   └── Application.php     # Инициализация путей, DI-контейнера и жизненный цикл
│   └── System/                 # Системные модули ядра
├── storage/
│   └── cache/                  # Директория файлового кэша
└── views/                      # PHP-шаблоны и макеты (layouts)
```

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

### 1. Описание маршрутов (`config/routes/web.php`)
Все `.php` файлы из директории `config/routes/` подключаются автоматически:

```php
<?php

declare(strict_types=1);

use App\Controllers\HomeController;
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

### 2. Контроллер с валидацией и рендерингом (`app/Controllers/HomeController.php`)
Зависимости автоматически подставляются в конструктор и методы контроллера через `PHP-DI`:

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

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

### 3. Создание собственного Middleware (`app/Middleware/AuthMiddleware.php`)

```php
<?php

declare(strict_types=1);

namespace App\Middleware;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Middleware\AbstractMiddleware;
use VEngine\System\Session\SessionInterface;

final class AuthMiddleware extends AbstractMiddleware
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
