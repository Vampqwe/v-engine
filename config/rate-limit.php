<?php

declare(strict_types=1);

return [
    'fallback_hold_seconds' => 300,
    'global' => [
        'max_attempts' => 60,
        'decay_seconds' => 60,
        'fail_open'     => true,
    ],
    'admin_login' => [
        'max_attempts' => 5,
        'decay_seconds' => 300,
        'fail_open'     => false,
    ],
];
