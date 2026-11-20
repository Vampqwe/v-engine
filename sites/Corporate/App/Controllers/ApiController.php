<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Controllers;

use App\Controllers\AbstractController;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Router\RouterInterface;
use VEngine\System\TimeDate\TimeDateInterface;
use VEngine\System\View\ViewInterface;

final class ApiController extends AbstractController
{
    public function __construct(
        RouterInterface $router,
        ViewInterface $view,
        private readonly TimeDateInterface $timeDate,
    ) {
        parent::__construct($router, $view);
    }

    public function health(): ResponseInterface
    {
        return $this->json([
            'status' => 'ok',
            'engine' => 'V-engine',
            'time'   => $this->timeDate->now(),
        ]);
    }

    public function profile(): ResponseInterface
    {
        return $this->json([
            'message' => 'Доступ разрешен',
        ]);
    }
}
