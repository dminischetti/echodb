<?php

declare(strict_types=1);

use App\Bootstrap;
use App\EventStore;
use App\Router;
use Psr\Log\LoggerInterface;
use RuntimeException;

if (!function_exists('register_event_routes')) {
    function register_event_routes(Router $router, Bootstrap $bootstrap): void
    {
        $eventStore = new EventStore($bootstrap->getDatabase(), $bootstrap->getLogger());
        $config = $bootstrap->getConfig();
        $logger = $bootstrap->getLogger();

        $router->get('/api/events', static function () use ($eventStore): void {
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            $afterId = isset($_GET['after_id']) ? (int) $_GET['after_id'] : null;
            $events = $eventStore->getEvents($limit, $afterId);

            header('Content-Type: application/json');
            echo json_encode(['data' => $events]);
        });

        $router->post('/api/events', static function () use ($eventStore, $config, $logger): void {
            header('Content-Type: application/json');
            try {
                enforce_rate_limit($config, $logger);
            } catch (RuntimeException $exception) {
                http_response_code(429);
                echo json_encode(['error' => $exception->getMessage()]);

                return;
            }

            $input = file_get_contents('php://input');
            $payload = json_decode($input ?: '[]', true);
            if (!is_array($payload)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON payload.']);

                return;
            }

            try {
                $event = $eventStore->appendEvent($payload);
                http_response_code(201);
                echo json_encode(['data' => $event]);
            } catch (Throwable $exception) {
                http_response_code(400);
                $logger->warning('Failed to append event', [
                    'error' => $exception->getMessage(),
                ]);
                echo json_encode(['error' => $exception->getMessage()]);
            }
        });
    }
}

if (!function_exists('enforce_rate_limit')) {
    function enforce_rate_limit(array $config, LoggerInterface $logger): void
    {
        $rateConfig = $config['rate_limit'] ?? ['requests' => 30, 'per_seconds' => 60];
        $requests = (int) ($rateConfig['requests'] ?? 30);
        $perSeconds = (int) ($rateConfig['per_seconds'] ?? 60);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $windowFile = sys_get_temp_dir() . '/echodb_' . md5($ip);
        $now = time();
        $timestamps = [];
        if (file_exists($windowFile)) {
            $decoded = json_decode((string) file_get_contents($windowFile), true);
            if (is_array($decoded)) {
                $timestamps = $decoded;
            }
        }

        $timestamps = array_values(array_filter($timestamps, static fn ($ts) => ($now - (int) $ts) < $perSeconds));
        if (count($timestamps) >= $requests) {
            $logger->warning('Rate limit exceeded', ['ip' => $ip]);
            throw new RuntimeException('Rate limit exceeded. Please slow down.');
        }

        $timestamps[] = $now;
        file_put_contents($windowFile, json_encode($timestamps), LOCK_EX);
    }
}
