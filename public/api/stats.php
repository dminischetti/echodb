<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Router;
use App\StatsService;

if (!function_exists('register_stats_route')) {
    function register_stats_route(Router $router, Bootstrap $bootstrap): void
    {
        $statsService = new StatsService($bootstrap->getDatabase());

        $router->get('/api/stats', static function () use ($statsService): void {
            header('Content-Type: application/json');
            echo json_encode(['data' => $statsService->getStats()]);
        });
    }
}
