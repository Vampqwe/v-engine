# Project instructions

- This is a PHP 8.2+ Composer project. Use `declare(strict_types=1);`, PSR-4 namespaces (`VEngine\` → `src/`, `App\` → `app/`, `Modules\` → `modules/`), and match the surrounding PHP style.
- Keep framework code in `src/`, application code in `app/`, and isolated feature code in `modules/`. Preserve the existing module boundaries.
- Framework services generally expose an interface alongside their implementation. When adding or changing a core service, follow that pattern, use a module-specific exception extending `BaseException` where appropriate, and update `config/dependency-rules.php` for interface bindings.
- Declare application routes in `config/routes/*.php` and module routes in that module's `routes.php`. Follow the existing registrar pattern and return `ResponseInterface` from route handlers/controllers.
- Use the framework's existing HTTP, DI, database, security, session, and view abstractions instead of introducing parallel mechanisms. Keep SQL parameterized; escape untrusted output with the view's `escape()` helper; preserve CSRF protections on state-changing routes.
- For PHP changes, run `composer cs:check` and `php -l` on changed PHP files. There is no configured automated test suite in this repository; don't claim tests were run unless one is added and executed.
