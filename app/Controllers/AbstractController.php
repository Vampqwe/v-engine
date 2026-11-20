<?php

declare(strict_types=1);

namespace App\Controllers;

use VEngine\System\Http\Response;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\View\ViewInterface;

abstract class AbstractController
{
    public function __construct(
        protected readonly RouterInterface $router,
        protected readonly ViewInterface $view,
    ) {}

    protected function view(string $template, array $data = [], ?string $layout = 'layouts/main', int $status = 200): ResponseInterface
    {
        return Response::html($this->view->render($template, $data, $layout), $status);
    }

    protected function html(string $content, int $status = 200): ResponseInterface
    {
        return Response::html($content, $status);
    }

    protected function json(array $data, int $status = 200): ResponseInterface
    {
        return Response::json($data, $status);
    }

    protected function redirect(string $url, int $status = 302): ResponseInterface
    {
        return Response::redirect($url, $status);
    }

    protected function redirectToRoute(string $routeName, array $params = [], int $status = 302): ResponseInterface
    {
        return Response::redirect($this->router->route($routeName, $params), $status);
    }
}
