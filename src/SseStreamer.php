<?php

declare(strict_types=1);

namespace App;

use DateTimeImmutable;
use Psr\Log\LoggerInterface;

class SseStreamer
{
    private int $heartbeatSeconds = 15;

    private int $maxDurationSeconds = 60;

    public function __construct(private EventStore $eventStore, private LoggerInterface $logger)
    {
    }

    public function stream(?int $lastEventId = null): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');

        $start = time();
        $lastSentId = $lastEventId ?? 0;
        $lastHeartbeat = time();

        while ((time() - $start) < $this->maxDurationSeconds) {
            $events = $this->eventStore->getEvents(100, $lastSentId);
            foreach ($events as $event) {
                $lastSentId = (int) $event['id'];
                $payload = [
                    'id' => $lastSentId,
                    'type' => $event['type'],
                    'table' => $event['table_name'],
                    'row_id' => (int) $event['row_id'],
                    'diff' => json_decode((string) $event['diff'], true),
                    'actor' => $event['actor'],
                    'created_at' => $event['created_at'],
                ];

                $this->sendEvent($event['type'], $payload, $lastSentId);
            }

            if ((time() - $lastHeartbeat) >= $this->heartbeatSeconds) {
                $this->sendHeartbeat();
                $lastHeartbeat = time();
            }

            if (connection_aborted()) {
                $this->logger->info('SSE client disconnected.');
                break;
            }

            usleep(750000);
        }

        $this->logger->info('SSE stream cycle closed.', ['last_event_id' => $lastSentId]);
    }

    private function sendEvent(string $type, array $payload, int $eventId): void
    {
        echo 'id: ' . $eventId . "
";
        echo 'event: ' . $type . "
";
        echo 'data: ' . json_encode($payload, JSON_THROW_ON_ERROR) . "

";
        @ob_flush();
        flush();
    }

    private function sendHeartbeat(): void
    {
        echo ':keep-alive ' . (new DateTimeImmutable())->format(DateTimeImmutable::ATOM) . "

";
        @ob_flush();
        flush();
    }
}
