<?php

declare(strict_types=1);

namespace App;

use DateInterval;
use DateTimeImmutable;

class StatsService
{
    public function __construct(private Database $database)
    {
    }

    public function getStats(): array
    {
        return [
            'counts' => $this->getCounts(),
            'events_per_minute' => $this->getEventsPerMinute(),
            'rpm' => $this->calculateRpm(),
        ];
    }

    private function getCounts(): array
    {
        $sql = 'SELECT table_name, type, COUNT(*) as total FROM events GROUP BY table_name, type';
        $rows = $this->database->fetchAll($sql);
        $result = [];
        foreach ($rows as $row) {
            $table = $row['table_name'];
            $type = $row['type'];
            $result[$table][$type] = (int) $row['total'];
        }

        return $result;
    }

    private function getEventsPerMinute(): array
    {
        $sql = "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:00') as minute_bucket, COUNT(*) as total
                FROM events
                WHERE created_at >= (NOW() - INTERVAL 15 MINUTE)
                GROUP BY minute_bucket
                ORDER BY minute_bucket ASC";
        $rows = $this->database->fetchAll($sql);

        $buckets = [];
        $now = new DateTimeImmutable('now');
        for ($i = 14; $i >= 0; $i--) {
            $minute = $now->sub(new DateInterval('PT' . $i . 'M'))->format('Y-m-d H:i:00');
            $buckets[$minute] = 0;
        }

        foreach ($rows as $row) {
            $buckets[$row['minute_bucket']] = (int) $row['total'];
        }

        return $buckets;
    }

    private function calculateRpm(): float
    {
        $sql = 'SELECT COUNT(*) as total FROM events WHERE created_at >= (NOW() - INTERVAL 1 MINUTE)';
        $row = $this->database->fetch($sql);
        $count = (int) ($row['total'] ?? 0);

        return round($count / 1, 2);
    }
}
