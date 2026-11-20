<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Controllers;

use App\Controllers\AbstractController;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Session\SessionInterface;

final class HomeController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->view('home/index', [
            'title' => 'V-engine — создавайте сайты по-своему',
        ]);
    }

    public function submit(RequestInterface $request, SessionInterface $session): ResponseInterface
    {
        $message = (string) $request->input('message', '');
        $session->flash('notice', 'Получено сообщение: ' . $message);

        return $this->redirectToRoute('home');
    }

    public function hello(string $name): ResponseInterface
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this->html("<h1>Привет, {$safeName}!</h1>");
    }
}
