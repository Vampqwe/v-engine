<?php

declare(strict_types=1);

namespace VEngine\System\DataBase;

use PDO;
use PDOException;
use PDOStatement;
use VEngine\System\Config\ConfigInterface;
use VEngine\System\Logger\LoggerInterface;

final class DataBase implements DataBaseInterface
{
    private ?PDO $pdo = null;
    private LoggerInterface $logger;
    public function __construct(
        private readonly ConfigInterface $config,
        LoggerInterface $logger
    ) {
        $this->logger = $logger->forModule('DataBase');
    }
    public function connection(): PDO
    {
        if ($this->pdo === null) {
            $host = $this->config->env('DB_HOST', '127.0.0.1');
            $port = $this->config->env('DB_PORT', '3306');
            $name = $this->config->env('DB_NAME', 'v_engine');
            $user = $this->config->env('DB_USER', 'root');
            $pass = $this->config->env('DB_PASS', '');
            $charset = $this->config->get('database', 'charset', 'utf8mb4');
            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset={$charset}";
            try {
                $this->pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } catch (PDOException $e) {
                $this->logger->error('Ошибка подключения к MySQL', ['error' => $e->getMessage()]);
                throw new DataBaseException('Не удалось подключиться к базе данных', 0, $e);
            }
        }
        return $this->pdo;
    }
    public function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->connection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logger->error('Ошибка выполнения SQL', ['sql' => $sql, 'error' => $e->getMessage()]);
            throw new DataBaseException('Ошибка выполнения запроса к БД', 0, $e);
        }
    }
}
