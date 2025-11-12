<?php

declare(strict_types=1);

namespace App\Support;

final class PathResolver
{
    public static function resolveBasePath(?string $configuredPath, ?string $scriptName = null): string
    {
        $normalizedConfigured = self::normalize($configuredPath);
        if ($normalizedConfigured !== '') {
            return $normalizedConfigured;
        }

        return self::normalizeFromScript($scriptName ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
    }

    private static function normalize(?string $path): string
    {
        if ($path === null) {
            return '';
        }

        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return '';
        }

        return '/' . trim($trimmed, '/');
    }

    private static function normalizeFromScript(?string $scriptName): string
    {
        if ($scriptName === null || $scriptName === '') {
            return '';
        }

        $directory = str_replace('\\', '/', dirname($scriptName));
        if ($directory === '.' || $directory === '/') {
            return '';
        }

        if (preg_match('#/api$#', $directory) === 1) {
            $directory = dirname($directory);
        }

        if ($directory === '.' || $directory === '/' || $directory === '') {
            return '';
        }

        return '/' . trim($directory, '/');
    }
}
