<?php

declare(strict_types=1);

return [
    'name'     => 'VENGINE_SESSID',
    'lifetime' => 7200, // 2 часа в секундах
    'path'     => '/',
    'secure'   => false, // Установите true на продакшене с HTTPS
    'httponly' => true,
    'samesite' => 'Lax',
];
