<?php

declare(strict_types=1);

namespace App;

use Dotenv\Dotenv;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;

class Bootstrap
{
    private static ?self $instance = null;

    private array $config;

    private LoggerInterface $logger;

    private Database $database;

    private function __construct()
    {
        $this->config = $this->loadConfig();
        $this->configureErrorReporting();
        $this->logger = $this->buildLogger($this->config['logger']);
        $this->database = new Database($this->config['db'], $this->logger);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    private function loadConfig(): array
    {
        $baseDir = dirname(__DIR__);
        $configPath = $baseDir . '/config/config.php';
        if (!file_exists($configPath)) {
            $configPath = $baseDir . '/config/config.sample.php';
        }

        $config = require $configPath;
        
        $envPath = $baseDir;
        if (file_exists($baseDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($envPath);
            $dotenv->safeLoad();
        }

        $config['app_name'] = getenv('APP_NAME') ?: $config['app_name'];
        $config['app_version'] = getenv('APP_VERSION') ?: $config['app_version'];
        $config['environment'] = getenv('APP_ENV') ?: $config['environment'];
        $config['display_errors'] = filter_var(
            getenv('APP_DEBUG') ?: ($config['environment'] === 'local'),
            FILTER_VALIDATE_BOOL
        );

        $configuredBasePath = getenv('APP_BASE_PATH');
        if ($configuredBasePath !== false) {
            $config['base_path'] = (string) $configuredBasePath;
        }

        $config['db'] = [
            'host' => getenv('DB_HOST') ?: $config['db']['host'],
            'port' => (int) (getenv('DB_PORT') ?: $config['db']['port']),
            'database' => getenv('DB_DATABASE') ?: $config['db']['database'],
            'username' => getenv('DB_USERNAME') ?: $config['db']['username'],
            'password' => getenv('DB_PASSWORD') ?: $config['db']['password'],
            'charset' => getenv('DB_CHARSET') ?: $config['db']['charset'],
        ];

        $allowedOrigins = getenv('CORS_ALLOWED_ORIGINS');
        if (is_string($allowedOrigins) && $allowedOrigins !== '') {
            $config['cors']['allowed_origins'] = array_map('trim', explode(',', $allowedOrigins));
        }

        return $config;
    }

    private function configureErrorReporting(): void
    {
        ini_set('display_errors', $this->config['display_errors'] ? '1' : '0');
        error_reporting(E_ALL);
        date_default_timezone_set('UTC');
    }

    private function buildLogger(array $loggerConfig): LoggerInterface
    {
        $logDir = dirname($loggerConfig['path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0775, true);
        }

        $logger = new Logger('echodb');
        $formatter = new LineFormatter(null, null, true, true);
        $level = Logger::toMonologLevel($loggerConfig['level'] ?? 'debug');
        $handler = new StreamHandler($loggerConfig['path'], $level);
        $handler->setFormatter($formatter);

        $logger->pushHandler($handler);
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new IntrospectionProcessor(Logger::DEBUG));

        return $logger;
    }
}
