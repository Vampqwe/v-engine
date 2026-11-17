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

    public function __construct(
        private readonly string $viewsDir,
        private readonly SecurityInterface $security,
        private readonly RouterInterface $router,
        private readonly SessionInterface $session
    ) {}

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
        $clean = str_replace(['..', '\\'], ['', '/'], trim($template, '/'));
        if (!str_ends_with($clean, '.php')) {
            $clean .= '.php';
        }

        $fullPath = rtrim($this->viewsDir, '/\\') . '/' . $clean;
        if (!is_file($fullPath) || !is_readable($fullPath)) {
            throw new ViewException("Файл шаблона не найден: {$template} ({$fullPath})");
        }

        return $fullPath;
    }
}
