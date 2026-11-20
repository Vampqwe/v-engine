<?php

declare(strict_types=1);

namespace VEngine\System\View;

use Throwable;
use VEngine\System\Router\RouterInterface;
use VEngine\System\Security\SecurityInterface;
use VEngine\System\Session\SessionInterface;

final class View implements ViewInterface
{
    /** @var array<string, mixed> */
    private array $sharedData = [];
    /** @var array<string, mixed>|null */
    private ?array $cachedOldInput = null;

    /** @var array<string, string[]>|null */
    private ?array $cachedErrors = null;

    public function __construct(
        private readonly string $viewsDir,
        private readonly string $modulesDir,
        private readonly SecurityInterface $security,
        private readonly RouterInterface $router,
        private readonly SessionInterface $session,
    ) {}

    public function old(string $field, mixed $default = ''): string
    {
        if ($this->cachedOldInput === null) {
            $flashed = $this->session->getFlash('_old_input', []);
            $this->cachedOldInput = is_array($flashed) ? $flashed : [];
        }

        $value = $this->cachedOldInput[$field] ?? $default;
        return $this->escape(is_scalar($value) ? (string) $value : '');
    }

    public function error(string $field): ?string
    {
        if ($this->cachedErrors === null) {
            $flashed = $this->session->getFlash('_errors', []);
            $this->cachedErrors = is_array($flashed) ? $flashed : [];
        }

        $error = $this->cachedErrors[$field][0] ?? null;
        return is_string($error) ? $this->escape($error) : null;
    }

    public function share(string $key, mixed $value): void
    {
        $this->sharedData[$key] = $value;
    }

    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $mergedData = array_merge($this->sharedData, $data);
        $content = $this->renderFile($template, $mergedData);

        if ($layout !== null && $layout !== '') {
            $mergedData['content'] = $content;
            return $this->renderFile($layout, $mergedData);
        }

        return $content;
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data, null);
    }

    public function escape(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return $this->security->escape((string) $value);
    }

    public function csrfField(): string
    {
        $token = $this->escape($this->security->csrfToken());
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    public function route(string $name, array $params = []): string
    {
        return $this->escape($this->router->route($name, $params));
    }

    public function flash(string $key, mixed $default = null): mixed
    {
        return $this->session->getFlash($key, $default);
    }

    private function renderFile(string $template, array $data): string
    {
        $filePath = $this->resolvePath($template);

        $renderer = function (string $__path, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            try {
                require $__path;
                return (string) ob_get_clean();
            } catch (Throwable $e) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                throw $e;
            }
        };

        $boundRenderer = $renderer->bindTo($this, self::class);
        return $boundRenderer($filePath, $data);
    }

    private function resolvePath(string $template): string
    {
        if (str_starts_with($template, '@')) {
            [$module, $subPath] = explode('/', substr($template, 1), 2);
            $cleanModule = preg_replace('/[^a-zA-Z0-9_-]/', '', $module);
            $cleanSub = str_replace(['..', '\\'], ['', '/'], trim($subPath, '/'));
            if (!str_ends_with($cleanSub, '.php')) {
                $cleanSub .= '.php';
            }
            $baseDir = $this->modulesDir . "/{$cleanModule}/views";
            $fullPath = "{$baseDir}/{$cleanSub}";
        } else {
            $clean = str_replace(['..', '\\'], ['', '/'], trim($template, '/'));
            if (!str_ends_with($clean, '.php')) {
                $clean .= '.php';
            }
            $fullPath = rtrim($this->viewsDir, '/\\') . '/' . $clean;
        }

        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new ViewException("Файл шаблона не найден: {$template} ({$fullPath})");
        }

        return $fullPath;
    }

}
