Модульный PHP-движок. Каждый модуль изолирован, общение между модулями — только через их контракты (интерфейсы). Внутренние классы модуля считаются приватными. DI-контейнер связывает интерфейсы с реализациями (провайдерами).

---

## 🤖 ИНСТРУКЦИЯ ДЛЯ ИИ-АССИСТЕНТА

> **Этот раздел читается ПЕРВЫМ.** Все последующие разделы документа — это техническая спецификация. Нарушение правил из этого раздела = баг.

### 🔴 Жесткие ограничения (нарушение = критическая ошибка)

1. **НИКОГДА** не использовать FQCN (полные имена классов) в теле кода — только `use` + короткое имя
2. **ВСЕГДА** добавлять `declare(strict_types=1);` первой строкой после `<?php`
3. **НИКОГДА** не использовать глобальные функции-хелперы (`e()`, `dd()`, `user()`, `env()`)
4. **НИКОГДА** не создавать циклические зависимости между модулями
5. **ВСЕГДА** типизировать зависимости интерфейсами, а не классами
6. **НИКОГДА** не обращаться к `Internal/` классам других модулей
7. **ВСЕГДА** использовать `::class` вместо строк с именами классов
8. **НИКОГДА** не использовать `eval()`, `extract()`, суперглобалы вне модуля Http
9. **НИКОГДА** не создавать `new \Full\Path\Class()` — только `use` + `new Class()`
10. **ВСЕГДА** помечать сервисы как `final readonly class` (где возможно)
11. **НИКОГДА** не смешивать слои: нижний слой не зависит от верхнего
12. **НИКОГДА** не использовать ассоциативные массивы там, где должен быть DTO/объект
13. **ВСЕГДА** экранировать вывод через `Html::escape()`, а не `htmlspecialchars()`

### 🟡 Направление зависимостей (строго соблюдать)

```
APP_BASE_PATH (константа)
        ↓
   [Parser]  ← Core (читает .env, PHP-массивы через нативные функции PHP)
        ↓
   [Config]  ← Core (использует Parser, пути от APP_BASE_PATH)
        ↓
   [FileSystem]  ← Infrastructure (получает Config)
        ↓
   [остальные модули]  ← получают FileSystem и/или Config
```

**ЗАПРЕЩЕНО:**
- `Config` → `FileSystem` (циклическая зависимость)
- `Core` → `Infrastructure` / `Framework` / `Modules` (нарушение слоев)
- `Infrastructure` → `Framework` / `Modules`
- `Framework` → `Modules`
- `Modules` → `Core` напрямую (только через интерфейсы Framework)

### 🟢 Особые случаи (ИИ часто ошибается)

| Ситуация | Правильное поведение |
|---|---|
| Нужен парсер в ядре | `Core\Parser\ParserInterface` (НЕ в Infrastructure) |
| Нужны пути в Config | Использовать `APP_BASE_PATH` напрямую, НЕ `FileSystem` |
| Админка и БД | Админка НЕ использует БД, супер-админ в `home/config/admin.php` |
| Аутентификация | `Admin` ≠ `Auth`. Admin — для админки, Auth — для пользователей сайта |
| Экранирование | `Html::escape($value)` — статический метод, НЕ глобальная функция |
| dot-notation | Реализован ТОЛЬКО в `DataBag`, Config использует его внутри |
| QueryBuilder | Возвращает объекты/DTO, НЕ ассоциативные массивы |
| Сессия | `regenerateId()` автоматически при логине/логауте |

---

## ✅ Checklist для каждого PHP-файла

Перед завершением работы над каждым PHP-файлом проверь:

- [ ] Есть `declare(strict_types=1);` первой строкой после `<?php`
- [ ] Все импорты через `use` (нет `\Full\Path\Class` в теле кода)
- [ ] Все ссылки на классы через `::class` (нет строковых путей)
- [ ] Зависимости в конструкторе — интерфейсы, не классы
- [ ] Класс помечен `final readonly` (где это возможно)
- [ ] Нет обращений к `Internal/` других модулей
- [ ] Нет суперглобалов (`$_GET`, `$_POST`, `$_SESSION` и т.д.)
- [ ] Нет `eval()`, `extract()`
- [ ] Экранирование через `Html::escape()`, не `htmlspecialchars()`
- [ ] Соблюдено направление зависимостей (слои)
- [ ] Один класс/интерфейс/трейт/enum = один файл
- [ ] Имя файла = имя класса

---

## 📖 Словарь терминов

| Термин | Определение |
|---|---|
| **Module** | Бизнес-модуль (Auth, Admin, Seo). Реализует `ModuleInterface`. Имеет паспорт, провайдер, сервис-провайдер |
| **Provider** | Реализация публичного контракта модуля. Единственная точка входа в модуль извне |
| **ServiceProvider** | Регистрирует биндинги в DI (интерфейс → реализация). Имеет методы `register()` и `boot()` |
| **Contract** | Публичный интерфейс модуля, лежит в папке `Contracts/`. Доступен другим модулям |
| **Internal** | Приватные классы модуля. Недоступны извне. Лежат в `Internal/`, `Drivers/`, `Models/` |
| **Core** | Слой 1. Ядро: бутстрап, DI, конфиги, парсер, обработка ошибок |
| **Infrastructure** | Слой 2. Внешние ресурсы: БД, кэш, файлы, сессии, логи, очереди |
| **Framework** | Слой 3. Базовые абстракции: HTTP, роутер, шаблоны, события, валидация |
| **Modules** | Слой 4. Бизнес-логика: Auth, Admin, Seo, Importer, InstallApp |
| **DataBag** | Ассоциативные массивы с dot-notation (для конфигов, GET/POST, сессий) |
| **Collection** | Индексные массивы с функциональными методами (map, filter, reduce) |
| **DTO** | Data Transfer Object. `readonly class` для передачи данных между слоями |
| **Event** | Событие. `readonly class` для межмодульного общения через `EventDispatcher` |
| **Middleware** | PSR-15 промежуточный обработчик HTTP-запросов |
| **APP_BASE_PATH** | Единственная базовая константа. Путь к `/engine/` |

---

## 🔄 Граф зависимостей (визуально)

```
┌─────────────────────────────────────────────────────────────┐
│  СЛОЙ 4: Modules (Auth, Admin, Seo, Importer, InstallApp)   │
│  Зависят от: Framework, Infrastructure, Core                │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  СЛОЙ 3: Framework (Http, Router, Template, Event, ...)     │
│  Зависит от: Infrastructure, Core                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  СЛОЙ 2: Infrastructure (Database, Cache, FileSystem, ...)  │
│  Зависит от: Core                                           │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│  СЛОЙ 1: Core (Application, DI, Config, Parser, Error)      │
│  Зависит от: APP_BASE_PATH (константа)                      │
└─────────────────────────────────────────────────────────────┘
```

**Межмодульное общение:** только через события (PSR-14) и контракты (`Contracts/`).

---

## 📝 Контекстные промпты для ИИ

### Если пишешь НОВЫЙ модуль

Создай структуру:
1. `Contracts/{Module}ProviderInterface.php` — публичный контракт
2. `{Module}Module.php` — паспорт (реализует `ModuleInterface`)
3. `{Module}Provider.php` — реализация контракта
4. `{Module}ServiceProvider.php` — регистрация в DI
5. `Internal/` — приватные классы
6. `Controllers/`, `Services/`, `Events/` — по необходимости
7. `config.php` — конфиг модуля (если нужен)

### Если пишешь КОНТРОЛЛЕР

- Все зависимости — через конструктор (интерфейсы)
- Маршруты — через атрибут `#[Route]`
- Возвращает `string` (рендер шаблона) или `ResponseInterface`
- Не работает с суперглобалами напрямую

### Если пишешь СЕРВИС

- Все зависимости — интерфейсы через конструктор
- События — через `EventDispatcherProviderInterface`
- Помечен `final readonly class`
- Не обращается к `Internal/` других модулей

### Если пишешь SERVICE PROVIDER

- Реализует `ServiceProviderInterface`
- Метод `register()` — биндинг интерфейсов на реализации
- Метод `boot()` — финальная инициализация (подписка на события и т.д.)
- Помечен `final readonly class`

### Если пишешь СОБЫТИЕ (Event DTO)

- Всегда `readonly class`
- Публичные свойства только для чтения
- Имя — с суффиксом `Event` (например, `UserCreatedEvent`)

### Если пишешь MIDDLEWARE

- Реализует `Psr\Http\Server\MiddlewareInterface`
- Метод `process(ServerRequestInterface, RequestHandlerInterface): ResponseInterface`
- Помечен `final readonly class`

---

## 🚫 Типичные ошибки ИИ (с примерами)

### ❌ Ошибка 1: FQCN в коде
```php
// НЕПРАВИЛЬНО
throw new \RuntimeException('Error');
$date = new \DateTimeImmutable();

// ПРАВИЛЬНО
use RuntimeException;
use DateTimeImmutable;
throw new RuntimeException('Error');
$date = new DateTimeImmutable();
```

### ❌ Ошибка 2: Зависимость от класса, а не интерфейса
```php
// НЕПРАВИЛЬНО
public function __construct(private CacheProvider $cache) {}

// ПРАВИЛЬНО
public function __construct(private CacheProviderInterface $cache) {}
```

### ❌ Ошибка 3: Прямой доступ к Internal
```php
// НЕПРАВИЛЬНО
use Infrastructure\Cache\Internal\CacheManager;

// ПРАВИЛЬНО
use Infrastructure\Cache\Contracts\CacheProviderInterface;
```

### ❌ Ошибка 4: Глобальный хелпер
```php
// НЕПРАВИЛЬНО
function e($value) { return htmlspecialchars($value); }
echo e($user->name);

// ПРАВИЛЬНО
use Framework\Support\Html;
echo Html::escape($user->name);
```

### ❌ Ошибка 5: Строка вместо `::class`
```php
// НЕПРАВИЛЬНО
$container->singleton('Infrastructure\Cache\Contracts\CacheProviderInterface', ...);

// ПРАВИЛЬНО
$container->singleton(CacheProviderInterface::class, CacheProvider::class);
```

### ❌ Ошибка 6: Мутабельный сервис
```php
// НЕПРАВИЛЬНО
class LoginService {
    private int $attempts = 0;
    public function incrementAttempts(): void { $this->attempts++; }
}

// ПРАВИЛЬНО
final readonly class LoginService {
    public function __construct(private CacheProviderInterface $cache) {}
}
```

### ❌ Ошибка 7: Смешение слоев
```php
// НЕПРАВИЛЬНО (Core зависит от Infrastructure)
namespace Core\Config;
use Infrastructure\FileSystem\FileSystemProvider;

// ПРАВИЛЬНО
namespace Core\Config;
// Config использует APP_BASE_PATH напрямую, без FileSystem
```

### ❌ Ошибка 8: Админка с БД
```php
// НЕПРАВИЛЬНО
class AdminAuthService {
    public function __construct(private DatabaseProviderInterface $db) {}
    public function attempt(...): bool {
        $user = $this->db->table('admins')->where(...)->first(); // ❌
    }
}

// ПРАВИЛЬНО
class AdminAuthService {
    public function __construct(
        private ConfigProviderInterface $config,
        private SessionProviderInterface $session,
    ) {}
    public function attempt(...): bool {
        $superAdmin = $this->config->get('admin.super_admin'); // ✅
    }
}
```

---

## 1. Стек технологий

| Компонент | Требование |
| --- | --- |
| PHP | 8.2+ (обязательно) |
| База данных | MySQL (через PDO) |
| Веб-сервер | Apache 2.4+ с mod_rewrite |
| Автозагрузка | PSR-4 через Composer |
| Контракты | PSR-3 (Logger), PSR-7 (HTTP), PSR-11 (Container), PSR-14 (Events), PSR-15 (Middleware) |

Используемые возможности PHP 8.2: `readonly class`, `enum`, `first-class callable syntax`, `intersection types`, `disjunctive normal form types`, атрибуты, named arguments, fibers (для Queue).

---

## 2. Структура проекта

```
/engine/ ← APP_BASE_PATH (единственная базовая константа)
│
├── /public/ ← DocumentRoot в Apache. Единственная веб-доступная папка.
│   ├── index.php ← Фронт-контроллер, точка входа
│   ├── .htaccess ← Rewrite правила
│   └── /assets/ ← Скомпилированные ассеты (результат сборки)
│       ├── /css/ ← Ассеты сайта
│       ├── /js/ ← Ассеты сайта
│       ├── /images/ ← Ассеты сайта
│       └── /admin/ ← Symlink на /src/Modules/Admin/assets/
│
├── /bin/
│   └── console ← Точка входа для CLI-команд
│
├── /home/ ← ПРИВАТНАЯ рабочая папка приложения
│   ├── /assets/ ← Исходники ассетов САЙТА (SCSS, TS, изображения)
│   ├── /views/ ← Шаблоны сайта (layouts, partials, pages)
│   ├── /resources/ ← Языковые файлы, stubs для генераторов
│   └── /config/ ← Конфиги приложения
│       ├── app.php
│       ├── database.php
│       ├── admin.php ← Конфиг админ-панели (логин, пароль супер-админа)
│       └── .env
│
├── /src/ ← Исходный код движка (PHP)
│   ├── /Core/ ← Слой 1: ядро
│   ├── /Infrastructure/ ← Слой 2: инфраструктура
│   ├── /Framework/ ← Слой 3: базовые абстракции
│   └── /Modules/ ← Слой 4: бизнес-модули
│       └── /Admin/ ← Модуль админ-панели
│           ├── /assets/ ← Ассеты админки (CSS, JS, изображения)
│           ├── /views/ ← Шаблоны админки
│           ├── /Controllers/
│           ├── /Services/
│           ├── /Internal/ ← Приватные классы
│           ├── /Contracts/ ← Публичные интерфейсы модуля
│           ├── AdminModule.php
│           ├── AdminProvider.php ← Реализует AdminProviderInterface
│           └── AdminServiceProvider.php ← Регистрация в DI
│
├── /logs/ ← Логи. Каждый модуль пишет в свой файл.
│
├── /storage/ ← Временные данные (кэш, сессии, компилированные шаблоны)
│
├── /tests/
│   ├── /Unit/
│   └── /Integration/
│
├── composer.json
└── README.md
```

**Защита приватных папок:** в каждой приватной папке (`home/`, `logs/`, `storage/`, `src/`, `bin/`) разместить `.htaccess` с содержимым `Require all denied`.

**Структура ассетов:**
- `/home/assets/` — исходники ассетов сайта (фронтенд)
- `/src/Modules/{ModuleName}/assets/` — ассеты каждого модуля (включая админку)
- `/public/assets/` — скомпилированные ассеты, доступные через веб
- `/public/assets/admin/` — symlink на `/src/Modules/Admin/assets/`

---

## 3. Архитектурные слои

Зависимости идут строго сверху вниз. Модуль нижнего слоя не может зависеть от модуля верхнего. Межмодульные связи осуществляются только через интерфейсы (контракты).

### Слой 1: Core (ядро)

Бутстрап, жизненный цикл, DI, обработка ошибок, парсинг конфигов.

| Класс | Назначение |
| --- | --- |
| Application | Бутстрап, запуск, жизненный цикл |
| DIContainer | Внедрение зависимостей (PSR-11) |
| Config | Чтение конфигов, построение путей от APP_BASE_PATH |
| Parser | Парсинг файлов (JSON, INI, ENV, PHP-array) — в Core, т.к. нужен для чтения конфигов |
| ErrorHandler | Глобальный перехват ошибок и исключений |
| ExceptionRendererInterface | Контракт для рендеринга ошибок (HTML/JSON) |

### Слой 2: Infrastructure

Работа с внешними ресурсами. Не знает о бизнес-логике.

| Класс | Назначение |
| --- | --- |
| Database | MySQL (PDO, Query Builder, транзакции) |
| Cache | Файловый, Redis, Memcached |
| Queue | Очереди задач |
| Logger | Логирование с раздельными каналами по модулям |
| FileSystem | Работа с файлами |
| Session | Управление сессиями |
| HttpClient | Внешние API-запросы |

### Слой 3: Framework

Базовые абстракции для построения HTTP-приложения.

| Класс | Назначение |
| --- | --- |
| Http | Request, Response, Headers, Cookies (PSR-7) |
| Router | Маршрутизация с поддержкой PHP 8 атрибутов |
| Middleware | PSR-15 Middleware Pipeline |
| EventDispatcher | PSR-14 События и слушатели |
| Validator | Валидация входных данных |
| Security | CSRF, XSS-фильтрация, хэширование паролей |
| Template | Шаблонизатор |
| TimeDate | Обёртка над DateTimeImmutable |
| Map | Обёртки над массивами (DataBag, Collection) |
| Url | Построение URL |
| Console | CLI-команды и роутер консоли |

### Слой 4: Application Modules

Конкретная функциональность сайта.

| Модуль | Назначение |
| --- | --- |
| Auth | Аутентификация пользователей сайта |
| Admin | Админ-панель (статичная, без БД) |
| Seo | SiteMap, Robots.txt, Meta-теги |
| Importer | Импорт/экспорт данных |
| InstallApp | Мастер установки |

---

## 4. Направление зависимостей

```
APP_BASE_PATH (константа)
        ↓
   [Parser] ← парсит .env, PHP-массивы (лежит в Core)
        ↓
   [Config] ← читает пути напрямую от APP_BASE_PATH, использует Parser
        ↓
   [FileSystem] ← получает Config, строит пути через него
        ↓
   [остальные модули] ← получают FileSystem и/или Config
```

**Критически важно:**
- `Config` НЕ зависит от `FileSystem`. `Config` работает с путями напрямую через `APP_BASE_PATH`.
- `Parser` находится в Core, а не в Infrastructure, т.к. он нужен ядру для чтения конфигов.
- `Parser` для чтения файлов использует только нативные функции PHP (`file_get_contents`, `file`), а не `FileSystemProviderInterface`, чтобы избежать циклической зависимости.
- Все межмодульные связи — только через интерфейсы из папки `/Contracts`.

---

## 5. Контракт модуля

Каждый модуль состоит из:
- **Паспорт модуля** — класс, реализующий `ModuleInterface`.
- **Контракты (интерфейсы)** — публичные интерфейсы в папке `/Contracts`.
- **Провайдер** — реализует один из контрактов модуля. Единственная точка входа.
- **Service Provider** — регистрирует биндинги в DI.
- **Внутренние классы** — в namespace `Internal`, `Drivers`, `Models`. Приватные.

### 5.1. Базовые интерфейсы Core

```php
namespace Core\Module;

enum ModuleStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Maintenance = 'maintenance';
}

interface ModuleInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function getStatus(): ModuleStatus;
    /** Имена модулей, от которых зависит этот */
    public function getDependencies(): array;
    /** DTO/массивы, которые модуль принимает извне */
    public function getAcceptedDataContracts(): array;
    /** Класс Service Provider'а модуля */
    public function getServiceProvider(): string;
}

interface ServiceProviderInterface
{
    /** Регистрация биндингов в DI (интерфейс → реализация) */
    public function register(DIContainer $container): void;
    /** Вызывается после регистрации всех модулей */
    public function boot(): void;
}
```

**Примечание:** Строки в массиве `getDependencies()` должны строго совпадать со значением, возвращаемым методом `getName()` у соответствующих модулей или провайдеров, для корректной работы топологической сортировки.

### 5.2. Пример структуры модуля

```
/src/Infrastructure/Cache/
├── /Contracts/
│   ├── CacheProviderInterface.php   ← публичный контракт
│   └── CacheDriverInterface.php     ← контракт для драйверов
├── /Drivers/
│   ├── FileCacheDriver.php
│   └── RedisCacheDriver.php
├── /Internal/
│   └── CacheManager.php             ← приватный
├── CacheModule.php                  ← паспорт
├── CacheProvider.php                ← реализует CacheProviderInterface
├── CacheServiceProvider.php         ← регистрирует биндинги
└── config.php                       ← конфиг модуля
```

### 5.3. Service Provider пример

```php
namespace Infrastructure\Cache;

use Core\DI\DIContainer;
use Core\Module\ServiceProviderInterface;
use Infrastructure\Cache\Contracts\CacheProviderInterface;

final readonly class CacheServiceProvider implements ServiceProviderInterface
{
    public function __construct(private DIContainer $container) {}

    public function register(DIContainer $container): void
    {
        // Биндим интерфейс на реализацию
        $container->singleton(
            CacheProviderInterface::class,
            CacheProvider::class
        );
        // Биндим драйвер
        $container->singleton(
            Contracts\CacheDriverInterface::class,
            fn() => $this->resolveDriver()
        );
    }

    public function boot(): void {}

    private function resolveDriver(): Contracts\CacheDriverInterface
    {
        $driver = $this->container->get(ConfigProviderInterface::class)
            ->get('cache.driver', 'file');

        return match ($driver) {
            'redis' => new Drivers\RedisCacheDriver(),
            default => new Drivers\FileCacheDriver(
                $this->container->get(ConfigProviderInterface::class)
            ),
        };
    }
}
```

---

## 6. Правила кода (обязательные)

### 6.1. Импорты — только `use`

Все классы, интерфейсы, трейты, enum подключаются через `use` в начале файла. Полные имена (FQCN) в теле кода запрещены без исключений — даже для глобальных классов PHP.

✅ **Правильно:**
```php
<?php
declare(strict_types=1);

namespace Modules\Auth\Services;

use Framework\Config\Contracts\ConfigProviderInterface;
use Infrastructure\Cache\Contracts\CacheProviderInterface;
use RuntimeException;
use DateTimeImmutable;

final readonly class LoginService
{
    public function __construct(
        private ConfigProviderInterface $config,
        private CacheProviderInterface $cache,
    ) {}

    public function fail(): never
    {
        throw new RuntimeException('Auth failed');
    }
}
```

❌ **Неправильно:**
```php
$config = new \Framework\Config\ConfigProvider();
throw new \RuntimeException('Auth failed');
$date = new \DateTimeImmutable();
```

Конфликт имён решается алиасом, а не полным путём:
```php
use Modules\Auth\Models\User;
use Modules\System\Models\User as SystemUser;
```

### 6.2. Ссылки на классы — только через `::class`

```php
use Infrastructure\Cache\Contracts\CacheProviderInterface;

$container->singleton(CacheProviderInterface::class, CacheProvider::class); // ✅
$container->singleton('Infrastructure\Cache\Contracts\CacheProviderInterface', ...); // ❌
```

### 6.3. Зависимости — только через интерфейсы

Все зависимости в конструкторах типизируются интерфейсами, а не классами. DI-контейнер связывает интерфейс с конкретной реализацией через ServiceProvider.

✅ **Правильно:**
```php
public function __construct(
    private CacheProviderInterface $cache,
    private ConfigProviderInterface $config,
) {}
```

❌ **Неправильно:**
```php
public function __construct(
    private CacheProvider $cache,      // ❌ конкретный класс
    private ConfigProvider $config,    // ❌ конкретный класс
) {}
```

### 6.4. Общие правила

- `declare(strict_types=1);` в каждом PHP-файле.
- Один класс / интерфейс / трейт / enum — один файл. Имя файла = имя класса.
- Классы модулей по умолчанию `final`. Состояние — `readonly`, где возможно.
- Обращение к другому модулю — только через его интерфейс-контракт.
- Никаких `eval()`, `extract()`, суперглобалов вне модуля Http.
- Никаких `new \Full\Path\Class()` — только `use` + `new Class()`.
- Никаких глобальных функций-хелперов. Хелперы — статические методы классов.

---

## 7. Модуль Admin (админ-панель)

### 7.1. Особенности

Админ-панель полностью автономна и не зависит от БД:
- Супер-администратор вшит в конфиг (`home/config/admin.php`).
- Аутентификация через сессию + хэш пароля из конфига.
- Нет таблиц `users`, `roles`, `permissions` для админки.
- Все данные админки (настройки, логи, кэш) читаются напрямую из файлов/конфигов.
- URL доступа: `https://{domain}/_system`

### 7.2. Структура модуля

```
/src/Modules/Admin/
├── /assets/                    ← Ассеты админки
│   ├── /css/
│   │   └── admin.css
│   ├── /js/
│   │   └── admin.js
│   └── /images/
│       └── logo.png
│
├── /views/                     ← Шаблоны админки
│   ├── /layouts/
│   │   └── admin.php
│   ├── /partials/
│   │   ├── sidebar.php
│   │   └── header.php
│   └── /pages/
│       ├── dashboard.php
│       ├── login.php
│       ├── config.php
│       └── modules.php
│
├── /Controllers/
│   ├── DashboardController.php
│   ├── LoginController.php
│   ├── ConfigController.php
│   └── ModulesController.php
│
├── /Services/
│   ├── AdminAuthService.php    ← Аутентификация супер-админа
│   └── AdminAssetsService.php  ← Отдача ассетов админки
│
├── /Contracts/
│   └── AdminProviderInterface.php   ← публичный контракт
│
├── /Internal/
│   └── AdminRouter.php         ← Внутренний роутер админки
│
├── AdminModule.php             ← Паспорт модуля
├── AdminProvider.php           ← Реализует AdminProviderInterface
├── AdminServiceProvider.php    ← Регистрация в DI
└── config.php                  ← Конфиг модуля
```

### 7.3. Контракт модуля Admin

```php
namespace Modules\Admin\Contracts;

interface AdminProviderInterface
{
    public function isAuthenticated(): bool;
    public function currentLogin(): ?string;
    public function logout(): void;
    public function attempt(string $login, string $password): bool;
}
```

### 7.4. Конфиг админки

`home/config/admin.php`:
```php
<?php
return [
    // URL доступа к админке
    'url_prefix' => '/_system',
    // Супер-администратор (вшит в движок)
    'super_admin' => [
        'login' => 'admin',
        // Хэш пароля генерируется через: password_hash('your_password', PASSWORD_DEFAULT)
        'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2SlheSvfZ/u',
    ],
    // Настройки сессии админки
    'session' => [
        'lifetime' => 120, // минут
        'name' => 'admin_session',
        'cookie_httponly' => true,
        'cookie_secure' => true, // Для HTTPS
        'cookie_samesite' => 'Strict',
    ],
    // Разрешённые IP для доступа (пустой массив = все IP)
    'allowed_ips' => [],
];
```

### 7.5. Роутинг админки с Middleware

Все маршруты админки начинаются с `/_system` и защищены middleware:

```php
namespace Modules\Admin\Controllers;

use Framework\Router\Attributes\Route;
use Framework\Template\Contracts\TemplateProviderInterface;
use Modules\Admin\Contracts\AdminProviderInterface;
use Modules\Admin\Middleware\AdminAuthMiddleware;

final readonly class DashboardController
{
    public function __construct(
        private TemplateProviderInterface $template,
        private AdminProviderInterface $auth,
    ) {}

    #[Route(
        '/_system',
        methods: ['GET'],
        name: 'admin.dashboard',
        middleware: [AdminAuthMiddleware::class]
    )]
    public function index(): string
    {
        return $this->template->render('admin/pages/dashboard', [
            'title' => 'Панель управления',
            'login' => $this->auth->currentLogin(),
        ]);
    }
}
```

### 7.6. Аутентификация супер-админа

```php
namespace Modules\Admin\Services;

use Framework\Config\Contracts\ConfigProviderInterface;
use Infrastructure\Session\Contracts\SessionProviderInterface;
use Modules\Admin\Contracts\AdminProviderInterface;

final readonly class AdminAuthService implements AdminProviderInterface
{
    public function __construct(
        private ConfigProviderInterface $config,
        private SessionProviderInterface $session,
    ) {}

    public function attempt(string $login, string $password): bool
    {
        $superAdmin = $this->config->get('admin.super_admin');

        if ($login !== $superAdmin['login']) {
            return false;
        }

        if (!password_verify($password, $superAdmin['password_hash'])) {
            return false;
        }

        // Регенерация ID сессии для защиты от Session Fixation
        $this->session->regenerateId();
        $this->session->set('admin_authenticated', true);
        $this->session->set('admin_login', $login);

        return true;
    }

    public function isAuthenticated(): bool
    {
        return (bool) $this->session->get('admin_authenticated', false);
    }

    public function logout(): void
    {
        $this->session->regenerateId();
        $this->session->remove('admin_authenticated');
        $this->session->remove('admin_login');
    }

    public function currentLogin(): ?string
    {
        return $this->session->get('admin_login');
    }
}
```

### 7.7. Service Provider модуля Admin

```php
namespace Modules\Admin;

use Core\DI\DIContainer;
use Core\Module\ServiceProviderInterface;
use Modules\Admin\Contracts\AdminProviderInterface;
use Modules\Admin\Services\AdminAuthService;

final readonly class AdminServiceProvider implements ServiceProviderInterface
{
    public function register(DIContainer $container): void
    {
        $container->singleton(AdminProviderInterface::class, AdminAuthService::class);
    }

    public function boot(): void {}
}
```

### 7.8. Паспорт модуля Admin

```php
namespace Modules\Admin;

use Core\Module\ModuleInterface;
use Core\Module\ModuleStatus;

final class AdminModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Admin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getStatus(): ModuleStatus
    {
        return ModuleStatus::Active;
    }

    public function getDependencies(): array
    {
        return [
            'Config', 'Session', 'Template', 'FileSystem',
            'Http', 'Router', 'Middleware',
        ];
    }

    public function getAcceptedDataContracts(): array
    {
        return [];
    }

    public function getServiceProvider(): string
    {
        return AdminServiceProvider::class;
    }
}
```

### 7.9. Шаблон админки

`src/Modules/Admin/views/layouts/admin.php`:
```php
<?php
/** @var array $data */
use Framework\Support\Html;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= Html::escape($data['title'] ?? 'Админ-панель') ?></title>
    <link rel="stylesheet" href="/assets/admin/css/admin.css">
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar">
            <div class="admin-logo">
                <img src="/assets/admin/images/logo.png" alt="Logo">
            </div>
            <nav class="admin-nav">
                <a href="/_system">Панель управления</a>
                <a href="/_system/config">Конфигурация</a>
                <a href="/_system/modules">Модули</a>
                <a href="/_system/logout">Выход</a>
            </nav>
        </aside>
        <main class="admin-content">
            <?= $data['content'] ?? '' ?>
        </main>
    </div>
    <script src="/assets/admin/js/admin.js"></script>
</body>
</html>
```

---

## 8. Описание остальных модулей

### 8.1. Application (Core)

Бутстрап приложения. Порядок запуска:
1. Определить `APP_BASE_PATH` в `public/index.php`.
2. Подключить `vendor/autoload.php`.
3. Создать `Application`, вызвать `boot()`.
4. `boot()` загружает Service Provider'ы всех модулей.
5. Каждый `ServiceProvider::register()` регистрирует биндинги в DI.
6. Вызывается `ServiceProvider::boot()` у каждого модуля.
7. `run()` обрабатывает HTTP-запрос через Middleware Pipeline → Router, отдаёт Response.

### 8.2. DIContainer (Core, PSR-11)

```php
namespace Core\DI;

interface DIContainerInterface
{
    public function bind(string $abstract, mixed $concrete): void;
    public function singleton(string $abstract, mixed $concrete): void;
    public function get(string $id): mixed;          // PSR-11
    public function has(string $id): bool;           // PSR-11
    public function make(string $class): object;     // авто-wiring через рефлексию
}
```

**Важно:** `make()` автоматически резолвит зависимости через Reflection, включая скалярные параметры из атрибутов `#[Inject]`.

### 8.3. Config (Core)

НЕ зависит от `FileSystem`. Работает с путями напрямую через `APP_BASE_PATH`. Внутри использует `DataBag` для dot-notation (единая реализация, без дублирования).

```php
namespace Framework\Config\Contracts;

interface ConfigProviderInterface
{
    public function get(string $key, mixed $default = null): mixed; // dot-notation
    public function has(string $key): bool;
    public function all(): array;
    public function path(string $relative = ''): string;
    public function homePath(string $path = ''): string;
    public function publicPath(string $path = ''): string;
    public function logsPath(string $path = ''): string;
    public function storagePath(string $path = ''): string;
    public function srcPath(string $path = ''): string;
    public function viewsPath(string $path = ''): string;
    public function assetsPath(string $path = ''): string;
    public function resourcesPath(string $path = ''): string;
}
```

Зависимости: `Core\Parser\ParserInterface` (лежит в Core, не в Infrastructure).

### 8.4. Parser (Core)

Перенесён в Core, т.к. необходим ядру для чтения конфигов. Паттерн «Стратегия». Драйверы: `JsonParser`, `IniParser`, `EnvParser`, `PhpArrayParser`.

```php
namespace Core\Parser;

interface ParserInterface
{
    public function parseFile(string $path): array;   // выбор драйвера по расширению
    public function parseString(string $content, string $format): array;
}
```

### 8.5. FileSystem (Infrastructure)

Зависит от `ConfigProviderInterface` (для построения путей).

```php
namespace Infrastructure\FileSystem\Contracts;

interface FileSystemProviderInterface
{
    public function read(string $path): string;
    public function write(string $path, string $content): void;
    public function exists(string $path): bool;
    public function delete(string $path): void;
    public function isDirectory(string $path): bool;
    public function makeDirectory(string $path, int $permissions = 0755): void;
    public function files(string $directory): array;
    public function viewPath(string $file = ''): string;
    public function resourcePath(string $file = ''): string;
    public function assetsPath(string $file = ''): string;
    public function storagePath(string $file = ''): string;
    public function logsPath(string $file = ''): string;
    public function publicPath(string $file = ''): string;
}
```

### 8.6. Logger (Infrastructure, PSR-3)

Каждый модуль пишет в свой файл `logs/{module}.log`. Все ошибки уровня ERROR+ дублируются в `logs/app.log`.

```php
namespace Infrastructure\Logger\Contracts;

use Psr\Log\LoggerInterface;

interface LoggerProviderInterface
{
    public function channel(string $channel): LoggerInterface;
    public function default(): LoggerInterface; // канал app
}
```

### 8.7. Map (Framework)

Две сущности:
- **DataBag** — ассоциативные массивы, dot-notation, для конфигов/GET/POST/сессий.
- **Collection** — индексные массивы, функциональные методы (`map`, `filter`, `reduce`).

Используются generics в PHPDoc (`@template TKey`, `@template TValue`). `DataBag` — единая реализация dot-notation, которую использует `Config` внутри себя.

### 8.8. Template (Framework)

Простой, гибкий, безопасный. Без `eval()` и `extract()`.

**Принцип работы:**
1. Шаблон компилируется в PHP-файл в `storage/compiled/`.
2. Скомпилированный файл возвращает `Closure`.
3. `Closure` вызывается с массивом данных как параметром.
4. Внутри шаблона данные доступны как `$data['key']`.
5. Для экранирования используется `Html::escape()` (статический метод, не глобальная функция).

**Хелпер `Html::escape()` (вместо глобальной функции `e()`):**
```php
namespace Framework\Support;

final class Html
{
    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
```

```php
namespace Framework\Template\Contracts;

interface TemplateProviderInterface
{
    public function render(string $view, array $data = []): string;
    public function compile(string $view): string;
    public function exists(string $view): bool;
}
```

Зависимости: `FileSystemProviderInterface`, `CacheProviderInterface`.

### 8.9. Http (Framework, PSR-7)

```php
namespace Framework\Http\Contracts;

use Psr\Http\Message\ResponseInterface;

interface HttpProviderInterface
{
    public function request(): \Psr\Http\Message\ServerRequestInterface;
    public function response(): ResponseInterface;
    public function redirect(string $url, int $status = 302): void;
    public function json(mixed $data, int $status = 200): ResponseInterface;
    public function header(string $name, string $value): void;
    public function cookie(string $name, string $value, int $ttl = 0): void;
}
```

### 8.10. Router (Framework)

Поддержка PHP 8 атрибутов для контроллеров:
```php
#[Route('/users/{id}', methods: ['GET'], name: 'user.show', middleware: [AuthMiddleware::class])]
```

```php
namespace Framework\Router\Contracts;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface RouterProviderInterface
{
    public function dispatch(RequestInterface $request): ResponseInterface;
    public function url(string $name, array $params = []): string;
    public function current(): string;
}
```

**Action Resolver:** Router получает экземпляр контроллера через `DIContainer::make()`, параметры из URL (например, `{id}`) передаются в метод через Reflection.

### 8.11. Middleware (Framework, PSR-15)

Промежуточные обработчики для HTTP-запросов. Используются для:
- CSRF-проверки
- Авторизации (группы роутов)
- Логирования
- CORS

```php
namespace Framework\Middleware\Contracts;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;

interface MiddlewarePipelineInterface
{
    public function dispatch(ServerRequestInterface $request, array $middlewares): ResponseInterface;
}
```

**Пример middleware:**
```php
namespace Modules\Admin\Middleware;

use Framework\Http\Contracts\HttpProviderInterface;
use Framework\Middleware\Contracts\MiddlewareInterface;
use Modules\Admin\Contracts\AdminProviderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class AdminAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AdminProviderInterface $auth,
        private HttpProviderInterface $http,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!$this->auth->isAuthenticated()) {
            $this->http->redirect('/_system/login');
        }
        return $handler->handle($request);
    }
}
```

### 8.12. Event Dispatcher (Framework, PSR-14)

События — основа истинной модульности. Модули общаются через события, а не через прямые вызовы.

**Пример:** модуль Auth создал пользователя → модуль Seo обновляет Sitemap, Mail отправляет приветствие.

```php
namespace Framework\Event\Contracts;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

interface EventDispatcherProviderInterface extends EventDispatcherInterface
{
    public function listen(string $event, callable|array $listener): void;
    public function dispatch(object $event): object;
}
```

**Пример использования:**
```php
// Модуль Auth генерирует событие
$this->events->dispatch(new UserCreatedEvent($user));

// Модуль Mail подписывается в своём ServiceProvider
public function boot(): void
{
    $this->events->listen(UserCreatedEvent::class, fn($e) => $this->sendWelcome($e->user));
}
```

**Рекомендация:** Все классы событий (Event DTO) должны быть объявлены как `readonly class` для гарантии неизменяемости данных при передаче между слушателями.

### 8.13. Security (Framework)

```php
namespace Framework\Security\Contracts;

interface SecurityProviderInterface
{
    public function hashPassword(string $password): string;
    public function verifyPassword(string $password, string $hash): bool;
    public function csrfToken(): string;
    public function verifyCsrfToken(string $token): bool;
    public function encrypt(string $data): string;
    public function decrypt(string $data): string;
    public function randomString(int $length = 32): string;
}
```

### 8.14. Validator (Framework)

```php
namespace Framework\Validator\Contracts;

interface ValidatorProviderInterface
{
    public function make(array $data, array $rules): ValidatorInterface;
}

interface ValidatorInterface
{
    public function fails(): bool;
    public function errors(): array;
}
```

### 8.15. Database (Infrastructure)

**Важно:** QueryBuilder возвращает DTO / Entity, а не ассоциативные массивы, чтобы сохранить строгую типизацию.

```php
namespace Infrastructure\Database\Contracts;

interface DatabaseProviderInterface
{
    public function query(string $sql, array $bindings = []): array;
    public function execute(string $sql, array $bindings = []): int;
    public function table(string $name): QueryBuilderInterface;
    public function transaction(callable $callback): mixed;
    public function lastInsertId(): string;
}

interface QueryBuilderInterface
{
    public function where(string $column, mixed $value): static;
    public function first(): ?object;              // возвращает stdClass или DTO
    public function get(): array;                  // массив объектов
    public function insert(array $data): string;
    public function update(array $data): int;
    public function delete(): int;
}
```

### 8.16. Cache (Infrastructure)

```php
namespace Infrastructure\Cache\Contracts;

interface CacheProviderInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
}
```

### 8.17. Session (Infrastructure)

**Важно:** `SessionProvider` автоматически вызывает `regenerateId()` при изменении статуса авторизации (логин/логаут) — защита от Session Fixation.

```php
namespace Infrastructure\Session\Contracts;

interface SessionProviderInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function flash(string $key, mixed $value): void;
    public function destroy(): void;
    public function regenerateId(): void;
}
```

### 8.18. Console (Framework)

CLI-команды для управления движком. Точка входа: `bin/console`.

```php
namespace Framework\Console\Contracts;

interface ConsoleProviderInterface
{
    public function register(string $name, string $commandClass): void;
    public function run(array $argv): int;
}

interface CommandInterface
{
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args): int;
}
```

**Примеры команд:**
- `php bin/console cache:flush` — очистка кэша
- `php bin/console template:compile` — компиляция шаблонов
- `php bin/console queue:work` — запуск воркера очередей
- `php bin/console module:list` — список модулей

### 8.19. ErrorHandler и Exception Renderers (Core)

`ErrorHandler` перехватывает ошибки, но не рендерит их сам. Для рендеринга используются `ExceptionRendererInterface`, выбираемые по контексту (на основе заголовка Accept, префикса URL или типа запуска CLI).

```php
namespace Core\Error;

use Throwable;

interface ExceptionRendererInterface
{
    public function canHandle(Throwable $e): bool;
    public function render(Throwable $e): void;
}
```

**Стандартные рендереры:**
- `HtmlExceptionRenderer` — для HTML-страниц (по `Accept: text/html`)
- `JsonExceptionRenderer` — для API (по `Accept: application/json` или префиксу `/api/`)
- `CliExceptionRenderer` — для консольных команд

### 8.20. Auth (Modules) — аутентификация пользователей сайта

```php
namespace Modules\Auth\Contracts;

interface AuthProviderInterface
{
    public function attempt(array $credentials): bool;
    public function login(object $user): void;
    public function logout(): void;
    public function user(): ?object;
    public function check(): bool;
    public function id(): int|string|null;
    public function hasRole(string $role): bool;
}
```

**Важно:** Этот модуль для пользователей сайта, НЕ для админки. Админка использует свой `AdminProviderInterface`.

### 8.21. Остальные модули (Seo, Importer, InstallApp)

Реализуются по аналогии. Каждый имеет:
- `Contracts/` — публичные интерфейсы
- `Module.php` — паспорт
- `Provider.php` — реализация интерфейса
- `ServiceProvider.php` — регистрация в DI

---

## 9. Порядок бутстрапа

`Application::boot()` выполняет шаги строго в этом порядке:

1. Загрузить `.env` через `ParserInterface`.
2. Инициализировать `ConfigProviderInterface` (читает `home/config/*.php`).
3. Инициализировать `ErrorHandler` (регистрирует `set_error_handler`, `set_exception_handler`).
4. Инициализировать `LoggerProviderInterface`.
5. Собрать список всех модулей (сканирование `src/Modules/`, `src/Infrastructure/`, `src/Framework/`).
6. Построить граф зависимостей по `ModuleInterface::getDependencies()`.
7. Топологически отсортировать модули.
8. Для каждого модуля:
   - Создать `ServiceProvider` через DI.
   - Вызвать `ServiceProvider::register()` (биндинг интерфейсов на реализации).
9. Для каждого модуля вызвать `ServiceProvider::boot()` (финальная инициализация).
10. Запустить `Application::run()`.

---

## 10. Антипаттерны (что НЕ делать)

- ❌ **Service Locator / Registry.** Не создавать глобальный реестр. Все зависимости — через DI.
- ❌ **Прямой доступ к внутренностям модуля.** Нельзя `new \Infrastructure\Database\Internal\QueryBuilder()`. Только через контракт `DatabaseProviderInterface`.
- ❌ **Зависимости от классов, а не интерфейсов.** Все зависимости типизируются интерфейсами.
- ❌ **Циклические зависимости.** `Config` не зависит от `FileSystem`. `FileSystem` зависит от `Config`.
- ❌ **Хелперы-помойки.** Нет класса `Helper`. Нет глобальных функций (`e()`, `dd()`). Если нужна функция — это статический метод класса (`Html::escape()`) или сервис внутри модуля.
- ❌ **Магические строки с именами классов.** Только `::class`.
- ❌ **Полные имена в коде.** Только `use` + короткое имя.
- ❌ **`eval()`, `extract()`, суперглобалы** вне модуля Http.
- ❌ **Мутабельные сервисы.** Все провайдеры и сервисы — `readonly class`, где возможно.
- ❌ **Смешение слоёв.** Модуль бизнес-логики не может напрямую обращаться к PDO. Только через `DatabaseProviderInterface`.
- ❌ **Админка с БД.** Админ-панель не использует таблицы `users`/`roles`. Супер-админ вшит в конфиг.
- ❌ **Дублирование логики.** dot-notation реализован только в `DataBag`. `Config` использует его внутри.
- ❌ **Массивы вместо DTO в QueryBuilder.** БД возвращает объекты, не ассоциативные массивы.

---

## 11. Пример использования

### 11.1. Контроллер сайта

```php
<?php
declare(strict_types=1);

namespace Modules\Site\Controllers;

use Framework\Http\Contracts\HttpProviderInterface;
use Framework\Router\Attributes\Route;
use Framework\Template\Contracts\TemplateProviderInterface;
use Modules\Auth\Contracts\AuthProviderInterface;

final readonly class HomeController
{
    public function __construct(
        private HttpProviderInterface $http,
        private TemplateProviderInterface $template,
        private AuthProviderInterface $auth,
    ) {}

    #[Route('/', methods: ['GET'], name: 'home')]
    public function index(): string
    {
        return $this->template->render('pages/home', [
            'title' => 'Главная',
            'user' => $this->auth->user(),
        ]);
    }
}
```

### 11.2. Сервис с событиями

```php
<?php
declare(strict_types=1);

namespace Modules\Auth\Services;

use Framework\Config\Contracts\ConfigProviderInterface;
use Framework\Event\Contracts\EventDispatcherProviderInterface;
use Infrastructure\Cache\Contracts\CacheProviderInterface;
use Infrastructure\Database\Contracts\DatabaseProviderInterface;
use Modules\Auth\Events\LoginFailedEvent;
use Modules\Auth\Events\UserLoggedInEvent;
use RuntimeException;

final readonly class LoginService
{
    public function __construct(
        private ConfigProviderInterface $config,
        private CacheProviderInterface $cache,
        private DatabaseProviderInterface $db,
        private EventDispatcherProviderInterface $events,
    ) {}

    public function attempt(string $email, string $password): bool
    {
        $cacheKey = "login_attempts_{$email}";
        $attempts = $this->cache->get($cacheKey, 0);

        if ($attempts >= 5) {
            throw new RuntimeException('Too many attempts');
        }

        $user = $this->db->table('users')->where('email', $email)->first();

        if (!$user || !password_verify($password, $user->password_hash)) {
            // Использование named arguments (PHP 8.0+)
            $this->cache->set($cacheKey, $attempts + 1, ttl: 3600);
            $this->events->dispatch(new LoginFailedEvent($email));
            return false;
        }

        $this->cache->delete($cacheKey);
        $this->events->dispatch(new UserLoggedInEvent($user));
        return true;
    }
}
```

### 11.3. Шаблон сайта

```php
<?php
/** @var array $data */
use Framework\Support\Html;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title><?= Html::escape($data['title']) ?></title>
</head>
<body>
    <h1><?= Html::escape($data['title']) ?></h1>
    <?php if ($data['user'] !== null): ?>
        <p>Привет, <?= Html::escape($data['user']->name) ?>!</p>
    <?php else: ?>
        <p>Гость</p>
    <?php endif; ?>
</body>
</html>
```

### 11.4. CLI-команда

```php
<?php
declare(strict_types=1);

namespace Infrastructure\Cache\Console;

use Framework\Console\Contracts\CommandInterface;
use Infrastructure\Cache\Contracts\CacheProviderInterface;

final readonly class FlushCacheCommand implements CommandInterface
{
    public function __construct(private CacheProviderInterface $cache) {}

    public function getName(): string
    {
        return 'cache:flush';
    }

    public function getDescription(): string
    {
        return 'Очистка всего кэша';
    }

    public function execute(array $args): int
    {
        $this->cache->flush();
        echo "Cache flushed successfully.\n";
        return 0;
    }
}
```

---

## 12. Тестирование

Благодаря изоляции модулей и контрактам, тестирование делится на два уровня:

### 12.1. Unit-тесты

Каждый модуль покрывается Unit-тестами с моками его зависимостей. Зависимости мокаются через интерфейсы (PHPUnit / Mockery).

```php
class LoginServiceTest extends TestCase
{
    public function test_attempt_fails_after_5_tries(): void
    {
        $cache = $this->createMock(CacheProviderInterface::class);
        $cache->method('get')->willReturn(5);

        $this->expectException(RuntimeException::class);

        $service = new LoginService(
            $this->createMock(ConfigProviderInterface::class),
            $cache,
            $this->createMock(DatabaseProviderInterface::class),
            $this->createMock(EventDispatcherProviderInterface::class),
        );

        $service->attempt('test@example.com', 'password');
    }
}
```

### 12.2. Интеграционные тесты

Поднимают весь DI-контейнер с реальными (или in-memory) реализациями. Проверяют взаимодействие модулей через события и контракты.

### 12.3. Структура тестов

```
/tests/
├── /Unit/
│   ├── /Core/
│   ├── /Infrastructure/
│   ├── /Framework/
│   └── /Modules/
└── /Integration/
    └── /Modules/
```

---

## 13. Критерии приёмки

- ✅ Все модули реализуют `ModuleInterface` и имеют `ServiceProvider`.
- ✅ Все зависимости типизированы интерфейсами, а не классами.
- ✅ DI-контейнер связывает интерфейсы с реализациями через `ServiceProvider`.
- ✅ Нет циклических зависимостей между модулями.
- ✅ Направление зависимостей: `FileSystem` → `Config` → `Parser` → `APP_BASE_PATH`.
- ✅ В коде нет FQCN — только `use`.
- ✅ В коде нет `eval()`, `extract()`, суперглобалов вне Http.
- ✅ В коде нет глобальных функций-хелперов.
- ✅ Каждый модуль пишет в свой файл `logs/{module}.log`.
- ✅ Шаблонизатор не использует `eval()` и `extract()`.
- ✅ Экранирование через `Html::escape()` (статический метод).
- ✅ Все классы сервисов — `final readonly`.
- ✅ `declare(strict_types=1)` в каждом PHP-файле.
- ✅ Приватные папки защищены `.htaccess`.
- ✅ `composer.json` содержит только PSR-контракты.
- ✅ Админ-панель доступна по `/_system`.
- ✅ Админ-панель не использует БД, супер-админ вшит в конфиг.
- ✅ Ассеты админки доступны через symlink на `/src/Modules/Admin/assets/`.
- ✅ Межмодульное общение — через события (`EventDispatcher`).
- ✅ Middleware (PSR-15) используются для защиты роутов.
- ✅ QueryBuilder возвращает объекты (DTO), не массивы.
- ✅ Session автоматически регенерирует ID при логине/логауте.
- ✅ CLI-команды доступны через `bin/console`.
- ✅ ErrorHandler использует `ExceptionRendererInterface` для рендеринга.
- ✅ Покрытие Unit-тестами всех сервисов.

---

## 14. Установка и настройка

### 14.1. Создание symlink для ассетов админки

```bash
# Выполнять из папки /engine/public/assets/
ln -s ../../src/Modules/Admin/assets admin
```

### 14.2. Генерация хэша пароля для супер-админа

```bash
php -r "echo password_hash('your_secure_password', PASSWORD_DEFAULT) . PHP_EOL;"
```

Скопировать результат в `home/config/admin.php` → `super_admin.password_hash`.

### 14.3. Настройка Apache

`public/.htaccess`:
```apache
RewriteEngine On
RewriteBase /

# Все запросы направляем в index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

DocumentRoot должен указывать на `/engine/public/`.

### 14.4. Установка зависимостей

```bash
composer install
```

`composer.json` должен содержать только PSR-контракты:
```json
{
    "require": {
        "php": "^8.2",
        "psr/container": "^2.0",
        "psr/http-message": "^2.0",
        "psr/log": "^3.0",
        "psr/event-dispatcher": "^1.0",
        "psr/http-server-handler": "^1.0",
        "psr/http-server-middleware": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Core\\": "src/Core/",
            "Infrastructure\\": "src/Infrastructure/",
            "Framework\\": "src/Framework/",
            "Modules\\": "src/Modules/"
        }
    }
}

---

