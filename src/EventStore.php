<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use Psr\Log\LoggerInterface;

class EventStore
{
    public function __construct(private Database $database, private LoggerInterface $logger)
    {
    }

    public function getEvents(int $limit = 50, ?int $afterId = null): array
    {
        $limit = max(1, min($limit, 200));
        $sql = 'SELECT * FROM events WHERE 1=1';
        if ($afterId !== null) {
            $sql .= ' AND id > :after_id';
        }
        $sql .= ' ORDER BY id ASC LIMIT :limit';

        $stmt = $this->database->getPdo()->prepare($sql);
        if ($afterId !== null) {
            $stmt->bindValue(':after_id', $afterId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function appendEvent(array $payload): array
    {
        $table = $this->validateTable($payload['table'] ?? '');
        $type = $this->validateType($payload['type'] ?? '');
        $rowId = (int) ($payload['row_id'] ?? 0);
        if ($rowId < 1) {
            throw new InvalidArgumentException('Row ID must be a positive integer.');
        }

        $actor = $this->sanitizeActor($payload['actor'] ?? null);
        $changes = $payload['changes'] ?? [];
        if (!is_array($changes)) {
            throw new InvalidArgumentException('Changes payload must be an object.');
        }
        if ($type !== 'delete' && $changes === []) {
            throw new InvalidArgumentException('Changes payload must be a non-empty object.');
        }
        $changes = $this->sanitizeChanges($table, $changes, $type);

        $existingRow = $this->fetchRow($table, $rowId);
        if ($type === 'insert' && $existingRow !== null) {
            throw new InvalidArgumentException('Row already exists. Choose a different row_id or use an update mutation.');
        }

        if ($existingRow === null && $type !== 'insert') {
            throw new InvalidArgumentException('Target row not found.');
        }

        $diff = $this->computeDiff($existingRow, $changes, $type);
        $diffJson = json_encode($diff, JSON_THROW_ON_ERROR);
        if (strlen($diffJson) > 8000) {
            throw new InvalidArgumentException('Diff payload too large.');
        }

        $this->applyMutation($table, $rowId, $changes, $type, $existingRow);

        $eventId = $this->persistEvent($type, $table, $rowId, $diffJson, $actor);

        $event = $this->findEventById((int) $eventId);

        return $event ?? [];
    }

    private function validateTable(string $table): string
    {
        $allowedTables = ['orders'];
        if (!in_array($table, $allowedTables, true)) {
            throw new InvalidArgumentException('Unsupported table.');
        }

        return $table;
    }

    private function validateType(string $type): string
    {
        $allowedTypes = ['insert', 'update', 'delete'];
        if (!in_array($type, $allowedTypes, true)) {
            throw new InvalidArgumentException('Unsupported event type.');
        }

        return $type;
    }

    private function sanitizeActor(?string $actor): ?string
    {
        if ($actor === null) {
            return null;
        }

        $actor = trim($actor);
        if ($actor === '') {
            return null;
        }

        return substr($actor, 0, 120);
    }

    private function sanitizeChanges(string $table, array $changes, string $type): array
    {
        if ($table === 'orders') {
            $allowed = ['status', 'amount', 'user_id'];
            $validStatuses = ['pending', 'processing', 'shipped', 'cancelled'];
            $sanitized = [];
            foreach ($changes as $field => $value) {
                if (!in_array($field, $allowed, true)) {
                    continue;
                }

                if ($field === 'status') {
                    if (!in_array($value, $validStatuses, true)) {
                        throw new InvalidArgumentException('Invalid order status.');
                    }
                    $sanitized[$field] = $value;
                    continue;
                }

                if ($field === 'amount') {
                    $numeric = filter_var($value, FILTER_VALIDATE_FLOAT);
                    if ($numeric === false) {
                        throw new InvalidArgumentException('Invalid amount.');
                    }
                    $sanitized[$field] = round((float) $numeric, 2);
                    continue;
                }

                if ($field === 'user_id') {
                    $userId = (int) $value;
                    if ($userId < 1) {
                        throw new InvalidArgumentException('Invalid user reference.');
                    }
                    $sanitized[$field] = $userId;
                }
            }

            if ($type === 'insert' && !isset($sanitized['user_id'])) {
                throw new InvalidArgumentException('Insert requires user_id.');
            }

            if ($type !== 'delete' && $sanitized === []) {
                throw new InvalidArgumentException('No valid changes provided.');
            }

            return $sanitized;
        }

        return $changes;
    }

    private function fetchRow(string $table, int $rowId): ?array
    {
        $sql = sprintf('SELECT * FROM %s WHERE id = :id', $table);

        return $this->database->fetch($sql, ['id' => $rowId]);
    }

    private function computeDiff(?array $originalRow, array $changes, string $type): array
    {
        if ($type === 'insert') {
            return array_map(static fn ($value) => ['old' => null, 'new' => $value], $changes);
        }

        if ($type === 'delete' && $originalRow !== null) {
            $diff = [];
            foreach ($originalRow as $field => $value) {
                $diff[$field] = ['old' => $value, 'new' => null];
            }

            return $diff;
        }

        $diff = [];
        foreach ($changes as $field => $newValue) {
            $oldValue = $originalRow[$field] ?? null;
            if ($oldValue == $newValue) {
                continue;
            }

            $diff[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        return $diff;
    }

    private function applyMutation(string $table, int $rowId, array $changes, string $type, ?array $originalRow): void
    {
        if ($type === 'delete') {
            $this->database->execute(
                sprintf('DELETE FROM %s WHERE id = :id', $table),
                ['id' => $rowId]
            );

            return;
        }

        if ($type === 'insert') {
            $columns = array_keys($changes);
            $placeholders = array_map(static fn ($col) => ':' . $col, $columns);
            $params = array_combine($placeholders, array_values($changes));
            $params[':id'] = $rowId;
            $columnsList = implode(', ', array_merge(['id'], $columns));
            $placeholdersList = implode(', ', array_merge([':id'], $placeholders));

            $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, $columnsList, $placeholdersList);
            $this->database->execute($sql, $params);

            return;
        }

        $setClauses = [];
        $params = ['id' => $rowId];
        foreach ($changes as $column => $value) {
            $setClauses[] = sprintf('%s = :%s', $column, $column);
            $params[$column] = $value;
        }
        $setClauses[] = 'updated_at = :updated_at';
        $params['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = sprintf('UPDATE %s SET %s WHERE id = :id', $table, implode(', ', $setClauses));
        $this->database->execute($sql, $params);
    }

    private function persistEvent(string $type, string $table, int $rowId, string $diffJson, ?string $actor): string
    {
        $sql = 'INSERT INTO events (type, table_name, row_id, diff, actor) VALUES (:type, :table_name, :row_id, :diff, :actor)';
        $params = [
            'type' => $type,
            'table_name' => $table,
            'row_id' => $rowId,
            'diff' => $diffJson,
            'actor' => $actor,
        ];

        $this->database->execute($sql, $params);

        return $this->database->lastInsertId();
    }

    private function findEventById(int $id): ?array
    {
        return $this->database->fetch('SELECT * FROM events WHERE id = :id', ['id' => $id]);
    }
}
