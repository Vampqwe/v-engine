<?php

declare(strict_types=1);

namespace VEngine\System\Router;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;

final class StaticPageController
{
    public function __construct(private readonly string $pagesPath) {}

    public function show(RequestInterface $request, string $page): ResponseInterface
    {
        if ($request->method() !== 'GET' && $request->method() !== 'HEAD') {
            throw new RouteNotFoundException();
        }

        $pagesRoot = realpath($this->pagesPath);
        $pagePath = realpath($this->pagesPath . DIRECTORY_SEPARATOR . $page);
        if (
            $pagesRoot === false
            || $pagePath === false
            || !str_starts_with($pagePath, $pagesRoot . DIRECTORY_SEPARATOR)
            || !is_file($pagePath)
        ) {
            throw new RouteNotFoundException();
        }

        $content = file_get_contents($pagePath);
        if ($content === false) {
            throw new RouterException("Не удалось прочитать статическую страницу: {$page}");
        }

        return new Response(
            $request->method() === 'HEAD' ? '' : $content,
            200,
            [
                'Content-Type'   => 'text/html; charset=UTF-8',
                'Content-Length' => (string) strlen($content),
            ],
        );
    }
}
