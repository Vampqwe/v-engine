<?php

declare(strict_types=1);

namespace Sites\Corporate\App\Controllers;

use App\Controllers\AbstractController;
use VEngine\System\Http\RequestInterface;
use VEngine\System\Http\ResponseInterface;
use VEngine\System\Session\SessionInterface;
use VEngine\System\Validator\ValidatorInterface;

final class HomeController extends AbstractController
{
    public function index(): ResponseInterface
    {
        return $this->view('home/index', [
            'title' => 'V-engine — создавайте сайты по-своему',
        ]);
    }

    public function submit(
        RequestInterface $request,
        SessionInterface $session,
        ValidatorInterface $validator,
    ): ResponseInterface {
        $validated = $validator->validateOrFail(
            ['message' => $request->input('message')],
            ['message' => 'required|string|max:1000'],
            [
                'message.required' => 'Введите сообщение.',
                'message.string'   => 'Сообщение должно быть текстом.',
                'message.max'      => 'Сообщение не должно превышать 1000 символов.',
            ],
        );
        $message = (string) $validated['message'];
        $session->flash('notice', 'Получено сообщение: ' . $message);

        return $this->redirectToRoute('home');
    }

    public function hello(string $name): ResponseInterface
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        return $this->html("<h1>Привет, {$safeName}!</h1>");
    }
}
