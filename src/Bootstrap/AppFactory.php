<?php

declare(strict_types=1);

namespace TondbadSwoole\Bootstrap;

use Exception;
use RuntimeException;

class AppFactory
{
    public static function create(?string $basePath = null): App
    {
        $basePath ??= self::detectBasePath();

        return new App($basePath);
    }

    /**
     * @throws RuntimeException
     */
    private static function detectBasePath(): string
    {
        $cwd = getcwd();

        if ($cwd === false) {
            throw new RuntimeException('Unable to determine the current working directory.');
        }

        return $cwd;
    }
}
