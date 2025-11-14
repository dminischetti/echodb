<?php

declare(strict_types=1);

namespace App\Support;

final class BootstrapPaths
{
    public static function projectRoot(): string
    {
        $root = realpath(__DIR__ . '/../../');

        if ($root === false) {
            throw new \RuntimeException('Unable to determine project root path.');
        }

        return $root;
    }

    public static function resolve(string $path): string
    {
        return self::projectRoot() . '/' . ltrim($path, '/');
    }
}
