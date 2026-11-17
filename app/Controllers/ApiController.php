<?php

declare(strict_types=1);

namespace App\Controllers;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\TimeDate\TimeDateInterface;

final class ApiController extends AbstractController
{
    public function __construct(
        RouterInterface $router,
        private readonly TimeDateInterface $timeDate
    ) {
        parent::__construct($router);
    }

    public function health(): ResponseInterface
    {
        return $this->json([
            'status' => 'ok',
            'engine' => 'V-engine',
            'time'   => $this->timeDate->now(),
        ]);
    }

    public function profile(RequestInterface $request): ResponseInterface
    {
        return $this->json([
            'message' => 'Доступ разрешен',
            'token'   => $request->attribute('auth.token'),
        ]);
    }
}
