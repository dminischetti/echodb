<?php

declare(strict_types=1);

use App\Bootstrap;
use App\Router;
use App\Support\BootstrapPaths;
use App\Support\PathResolver;

$projectRoot = realpath(__DIR__ . '/../../');
if ($projectRoot === false || !is_dir($projectRoot . '/vendor')) {
    $projectRoot = realpath(__DIR__ . '/..');
    if ($projectRoot === false || !is_dir($projectRoot . '/vendor')) {
        $projectRoot = __DIR__;
    }
}

$bootstrapPathsFile = $projectRoot . '/src/Support/BootstrapPaths.php';
if (!file_exists($bootstrapPathsFile)) {
    http_response_code(500);
    echo 'Fatal error: Bootstrap path resolver not found.';
    exit;
}

require_once $bootstrapPathsFile;

$autoload = BootstrapPaths::resolve('vendor/autoload.php');

if (!file_exists($autoload)) {
    http_response_code(500);
    echo 'Fatal error: vendor/autoload.php not found.';
    exit;
}

require_once $autoload;

require_once BootstrapPaths::resolve('src/Bootstrap.php');

$bootstrap = Bootstrap::getInstance();
$config = $bootstrap->getConfig();
$basePath = PathResolver::resolveBasePath($config['base_path'] ?? null, $_SERVER['SCRIPT_NAME'] ?? null);
$router = new Router($basePath, $bootstrap->getLogger());
$database = $bootstrap->getDatabase();

$origin = $_SERVER['HTTP_ORIGIN'] ?? null;
$allowedOrigins = $config['cors']['allowed_origins'] ?? [];
if ($origin !== null && ($allowedOrigins === [] || in_array($origin, $allowedOrigins, true))) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Headers: Content-Type, Last-Event-ID');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

$router->options('/api/events', static function (): void {
    http_response_code(204);
});

$router->get('/api', static function () use ($config, $database): void {
    header('Content-Type: application/json');
    $pdoHealthy = true;
    try {
        $database->fetch('SELECT 1');
    } catch (Throwable $exception) {
        $pdoHealthy = false;
    }

    echo json_encode([
        'status' => 'ok',
        'service' => $config['app_name'] ?? 'EchoDB',
        'version' => $config['app_version'] ?? 'dev',
        'environment' => $config['environment'] ?? 'unknown',
        'database' => $pdoHealthy ? 'connected' : 'unreachable',
    ]);
});

$router->get('/api/index', static function () use ($config): void {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'service' => $config['app_name'] ?? 'EchoDB',
        'version' => $config['app_version'] ?? 'dev',
    ]);
});

require __DIR__ . '/events.php';
register_event_routes($router, $bootstrap);

require __DIR__ . '/stats.php';
register_stats_route($router, $bootstrap);

require __DIR__ . '/stream.php';
register_stream_route($router, $bootstrap);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
