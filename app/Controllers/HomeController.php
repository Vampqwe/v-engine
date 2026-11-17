<?php

declare(strict_types=1);

namespace App\Controllers;

use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;

final class HomeController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->view('home/index', [
            'title'    => 'Главная страница',
            'username' => 'Developer',
        ]);
    }

    public function hello(string $name): ResponseInterface
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this->html("<h1>Привет, {$safeName}!</h1>");
    }
}
