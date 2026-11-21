# Project instructions

## Project overview

- This is a PHP 8.2+ Composer application built around a small modular framework.
- PSR-4 mappings are `VEngine\` → `src/`, shared `App\` → `app/`, `Sites\` → `sites/`, `Modules\` → `modules/`, and `Tests\` → `tests/`.
- Read [README.md](../README.md) for the architecture and usage examples. Keep framework, application, and module code in their existing boundaries.

## PHP and architecture conventions

- Put framework primitives and reusable system services under `src/System/`.
- Put reusable application base classes and middleware under `app/`. Put site-specific controllers and middleware under `sites/<Site>/App/` using the `Sites\<Site>\App\` namespace.
- Put isolated feature code, controllers, services, middleware, views, and route registrars under `modules/<Module>/`.
- Start PHP files with `declare(strict_types=1);` and follow the existing namespace and class style.
- Match the surrounding style and use the PSR-12-oriented rules enforced by PHP-CS-Fixer rather than introducing another formatting convention.
- Core framework services expose an interface and implementation. Add or update the interface, implementation, exception, and `config/dependency-rules.php` binding together.
- Module-specific exceptions should extend `VEngine\System\Exception\BaseException`.
- Use PHP-DI for constructor and route-handler dependencies; do not create parallel container or service-locator mechanisms.
- Use immutable or readonly data and final classes where consistent with existing code.

## Routing, HTTP, and views

- Select one site per application instance with `APP_SITE`. Store its routes, templates, and public assets under `sites/<Site>/routes/`, `sites/<Site>/views/`, and `sites/<Site>/public/assets/`; use `sites/<Site>/public/` as the web-server document root.
- Register selected-site routes in `sites/<Site>/routes/*.php`; register reusable module routes in `modules/<Module>/routes.php`.
- Route files return an `is_callable` registrar that accepts `VEngine\System\Router\RouterInterface`.
- Route handlers and controllers must return `VEngine\System\Http\ResponseInterface`.
- Reuse `AbstractController` helpers and `AbstractMiddleware` helpers before duplicating HTTP response behavior.
- Route parameters and middleware should follow the patterns in [sites/Corporate/routes/web.php](../sites/Corporate/routes/web.php) and [modules/Admin/routes.php](../modules/Admin/routes.php).
- Render application views through `ViewInterface`; escape untrusted values with the view's `escape()` helper.
- Use `RequestInterface` and `ResponseInterface` abstractions rather than reading or constructing raw superglobals and responses ad hoc.

## Security and data access

- Parameterize SQL with PDO placeholders through `DataBaseInterface`; never concatenate request data into SQL.
- Preserve CSRF middleware on state-changing routes and retain `_csrf_token` or `X-CSRF-Token` handling.
- Preserve session, security, rate-limit, logger, and config abstractions; do not add parallel implementations.
- Keep credentials and environment-specific values in `.env` or config, not source files.
- Explicitly define failure behavior for database, Redis, cache, and external-service operations.

## Configuration and modules

- Add dependency bindings to [config/dependency-rules.php](../config/dependency-rules.php), especially when introducing or replacing an interface implementation.
- Keep route loading, DI container setup, environment handling, and bootstrap behavior compatible with [src/Kernel/Application.php](../src/Kernel/Application.php).
- Do not modify public entrypoints or generated route/DI cache files without a framework-level reason.
- Before adding a module, follow the structure and route/Middleware/Service patterns under [modules/Admin/](../modules/Admin/).

## Console commands

- Register framework commands under `src/System/Console/` and keep domain-specific commands with their module or subsystem.
- Implement commands with the shared `Command`, `InputInterface`, and `OutputInterface`; do not parse `argv` or write directly to `STDOUT` from command handlers.
- Register commands through the shared Console application in `config/dependency-rules.php`; keep legacy `bin/` scripts as thin compatibility wrappers.
- Never execute arbitrary shell commands or expose the full CLI registry to the web. Admin web execution must use an explicit allowlist, authenticated admin routes, CSRF protection, and buffered output.
- Treat destructive or blocking commands as CLI-only unless a bounded, non-blocking web mode is explicitly designed and tested.

## Tests

- PHPUnit 11 tests live under `tests/Unit/` and use the `Tests\Unit` namespace.
- Add or update focused PHPUnit coverage for behavior changes, preferably beside related existing tests.
- Reuse framework interfaces and small test doubles when a unit test does not need the real container or infrastructure.
- For one test file, run `vendor/bin/phpunit tests/Unit/<TestClass>.php`.
- For the full suite, run `composer test`.

## Validation

- Before finishing PHP changes, run `composer cs:check`.
- Run `php -l` on every changed PHP file.
- Run the smallest relevant PHPUnit test command, then `composer test` for broader changes.
- Report exactly which validation commands passed or failed; do not claim a command passed unless it was run.
