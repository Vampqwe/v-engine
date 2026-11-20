# Security review

This report records confirmed security findings identified during the codebase review and related hardening. This was a source review, not a penetration test; environment-dependent risks require review of the actual deployment topology.

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟠 HIGH | [src/System/Http/Request.php](src/System/Http/Request.php) | 89-148 | Untrusted proxy headers could be trusted for client IP resolution, allowing IP-based rate-limit bypass. Fixed by using injected server parameters, rejecting wildcard proxy trust, validating proxy configuration, and matching equivalent IPv6 addresses. | 9/10 |
| 2 | 🟡 MEDIUM | [modules/Admin/Services/AdminUserService.php](modules/Admin/Services/AdminUserService.php) | 49-58 | Unknown accounts skipped password verification, making email enumeration possible through timing differences. Fixed by verifying against a dummy password hash. | 9/10 |
| 3 | ⚪ LOW | [modules/Admin/Controllers/AuthController.php](modules/Admin/Controllers/AuthController.php) | 80-84 | Authentication logs included administrator email addresses. Fixed by removing email from success and failure log context. | 9/10 |
| 4 | 🟡 MEDIUM | [sites/Corporate/App/Controllers/HomeController.php](sites/Corporate/App/Controllers/HomeController.php) | 22-38 | The public form endpoint lacked a request-rate limit and message-length validation. Fixed with a dedicated rate-limit policy and a 1,000-character validation limit. | 8/10 |
| 5 | 🟡 MEDIUM | [src/System/Http/Request.php](src/System/Http/Request.php) | 197-238 | Non-POST request bodies could be read without a size bound, enabling excessive memory use. Fixed with a configurable body limit and a 413 response. | 9/10 |
| 6 | 🟡 MEDIUM | [modules/Admin/Controllers/AuthController.php](modules/Admin/Controllers/AuthController.php) | 97-116 | The CSRF token persisted across authentication privilege changes. Fixed by rotating it after session regeneration on login and logout. | 8/10 |
| 7 | 🟡 MEDIUM | [src/System/Redis/RedisClient.php](src/System/Redis/RedisClient.php) | 45-52 | Redis authentication failure was not checked, so connection setup did not fail explicitly when credentials were rejected. Fixed by checking `AUTH` and `SELECT` results. | 8/10 |

## Additional related hardening

- Session-store failures with the Redis session driver now return HTTP 503; session garbage-collection probability is explicitly configured.
- The rate limiter defaults to the file driver, reports Redis usage accurately, avoids a second failover hit, shards state files, and removes expired entries probabilistically. `composer rate-limit:gc` provides a blocking CLI cleanup for scheduled runs; it is not configured automatically.
- Production route-cache entries are fingerprinted against the route-file list and contents, so route changes invalidate stale compiled routes. Failures to write the cache are logged.
- Production startup now reports explicitly when the DI compilation directory cannot be created or written.
- Error responses preserve their HTTP status and API JSON format. HTTP response headers are validated against control-character injection.
- File and Redis cache reads now handle malformed payloads consistently; file-cache reads treat concurrent deletion as a cache miss, and non-positive TTLs do not create entries.
- Production defaults, security headers, request-body limits, and error rendering were tightened.

## Validation

- `composer test`: passed; 70 tests, 167 assertions, 1 skipped.
- Focused regression tests for rate-limit cleanup, route-cache invalidation, and DI-cache setup: passed; 14 tests, 37 assertions.
- `composer cs:check`: passed on PHP 8.5.1 (PHP-CS-Fixer warned that the project minimum is PHP 8.2).
- `php -l` on changed PHP files, `composer validate --no-check-publish`, and `git -c core.whitespace=cr-at-eol diff --check`: passed.
- Load testing for rate-limit cleanup and MySQL/Redis integration tests were not run as part of this validation.
