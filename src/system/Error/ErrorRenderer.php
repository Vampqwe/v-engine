<?php

declare(strict_types=1);

namespace VEngine\System\Error;

use Throwable;

final class ErrorRenderer implements ErrorRendererInterface
{
    public function render(Throwable $exception, bool $isDebug): string
    {
        if (!$isDebug) {
            return $this->renderProduction();
        }

        $codeSnippet = $this->getCodeSnippet($exception->getFile(), $exception->getLine());
        $trace = $exception->getTrace();
        $previous = $exception->getPrevious();

        ob_start();
        require __DIR__ . '/views/500.php';
        return (string) ob_get_clean();
    }

    private function getCodeSnippet(string $file, int $errorLine, int $padding = 8): array
    {
        if (!is_file($file) || !is_readable($file)) {
            return [];
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $start = max(0, $errorLine - $padding - 1);
        $length = ($padding * 2) + 1;
        $slice = array_slice($lines, $start, $length, true);

        $result = [];
        foreach ($slice as $index => $content) {
            $lineNum = $index + 1;
            $result[$lineNum] = [
                'code' => $content,
                'is_error' => ($lineNum === $errorLine),
            ];
        }

        return $result;
    }

    private function renderProduction(): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html lang="ru">
        <head><meta charset="UTF-8"><title>500 Internal Server Error</title></head>
        <body style="background:#0d1117;color:#c9d1d9;font-family:sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;">
            <div style="text-align:center;">
                <h1 style="color:#f85149;font-size:48px;margin:0;">500</h1>
                <p>Внутренняя ошибка сервера. Информация записана в журнал.</p>
            </div>
        </body>
        </html>
        HTML;
    }
}
