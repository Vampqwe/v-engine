<?php

declare(strict_types=1);

namespace VEngine\System\View;

interface ViewInterface
{
    public function render(string $template, array $data = [], ?string $layout = 'layouts/main'): string;
    public function share(string $key, mixed $value): void;

    // Вспомогательные методы для использования внутри шаблонов ($this->...)
    public function escape(mixed $value): string;
    public function csrfField(): string;
    public function route(string $name, array $params = []): string;
    public function flash(string $key, mixed $default = null): mixed;
    public function partial(string $template, array $data = []): string;
}
