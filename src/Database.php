<?php

declare(strict_types=1);

namespace App;

use PDO;
use PDOException;
use PDOStatement;
use Psr\Log\LoggerInterface;

class Database
{
    private PDO $pdo;

    public function __construct(private array $config, private LoggerInterface $logger)
    {
        $this->pdo = $this->connect();
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function execute(string $sql, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $params);

        return $stmt->execute();
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    private function bindValues(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $stmt->bindValue($param, $value);
        }
    }

    private function connect(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'],
            $this->config['database'],
            $this->config['charset']
        );

        try {
            $pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            $this->logger->error('Database connection failed', [
                'exception' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        return $pdo;
    }
}
