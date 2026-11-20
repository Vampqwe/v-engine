<?php

declare(strict_types=1);

$apiToken = $_ENV['API_TOKEN'] ?? $_SERVER['API_TOKEN'] ?? '';

if (!is_string($apiToken) || ($apiToken !== '' && strlen($apiToken) < 32)) {
    throw new \RuntimeException('API_TOKEN должен содержать не менее 32 символов');
}

return [
    'api_token' => $apiToken,
];
