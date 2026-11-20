<?php

declare(strict_types=1);

$rawMaxBodyBytes = $_ENV['HTTP_MAX_BODY_BYTES'] ?? $_SERVER['HTTP_MAX_BODY_BYTES'] ?? '1048576';
if (!is_string($rawMaxBodyBytes) && !is_int($rawMaxBodyBytes)) {
    throw new \RuntimeException('HTTP_MAX_BODY_BYTES должен быть положительным целым числом');
}

$maxBodyBytes = filter_var($rawMaxBodyBytes, FILTER_VALIDATE_INT);
if ($maxBodyBytes === false || $maxBodyBytes < 1) {
    throw new \RuntimeException('HTTP_MAX_BODY_BYTES должен быть положительным целым числом');
}

return [
    'max_body_bytes' => $maxBodyBytes,
];
