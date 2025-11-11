<?php

declare(strict_types=1);

use App\Bootstrap;
use App\EventStore;
use App\Router;
use App\SseStreamer;

if (!function_exists('register_stream_route')) {
    function register_stream_route(Router $router, Bootstrap $bootstrap): void
    {
        $router->get('/api/stream', static function () use ($bootstrap): void {
            $eventStore = new EventStore($bootstrap->getDatabase(), $bootstrap->getLogger());
            $streamer = new SseStreamer($eventStore, $bootstrap->getLogger());
            $lastEventId = null;
            if (isset($_SERVER['HTTP_LAST_EVENT_ID'])) {
                $lastEventId = (int) $_SERVER['HTTP_LAST_EVENT_ID'];
            } elseif (isset($_GET['lastEventId'])) {
                $lastEventId = (int) $_GET['lastEventId'];
            }

            $streamer->stream($lastEventId);
            exit;
        });
    }
}
