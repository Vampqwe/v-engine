<?php

declare(strict_types=1);

namespace VEngine\System\DataBase;

use PDO;
use PDOStatement;

interface DataBaseInterface
{
    public function connection(): PDO;
    public function query(string $sql, array $params = []): PDOStatement;
}
