<?php

namespace TondbadSwoole\Core;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

class Env
{
    /**
     * @var array $envCache Cache for storing environment variables
     */
    protected static array $envCache = [];

    /**
     * @var array $loadedFiles Keeps track of loaded environment files
     */
    protected static array $loadedFiles = [];

    /**
     * Load the environment variables from framework and application files.
     *
     * This function loads both framework and application environment files. Application variables override framework ones.
     *
     * @param array $paths Array of paths to environment files.
     * @param string $filename Name of the environment file (default is '.env')
     */
    public static function load(array $paths, string $filename = '.env'): void
    {
        foreach ($paths as $path) {
            $filePath = "{$path}/{$filename}";
            if (file_exists($filePath) && !in_array($filePath, self::$loadedFiles)) {
                try {
                    $dotenv = Dotenv::createImmutable($path, $filename);
                    $dotenv->load();
                    self::$loadedFiles[] = $filePath;
                    self::$envCache = array_merge($_ENV, $_SERVER, self::$envCache);
                } catch (InvalidPathException $e) {
                    throw new \Exception("Environment file not found: {$filePath}");
                }
            }
        }
    }

    /**
     * Retrieve an environment variable value.
     *
     * @param string $key The environment variable key (dot notation is supported)
     * @param mixed $default Default value if the key doesn't exist
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // Convert dot notation to environment key (if needed)
        $envKey = self::convertDotNotationToEnvKey($key);

        // Search for the key in the cache first
        if (isset(self::$envCache[$envKey])) {
            return self::parseValue(self::$envCache[$envKey]);
        }

        // Fallback to getenv if the value is not cached
        $value = getenv($envKey);
        return $value !== false ? self::parseValue($value) : $default;
    }

    /**
     * Load all environment variables from default and custom search paths.
     *
     * @param array $paths Array of additional search paths
     */
    public static function loadAll(array $paths = []): void
    {
        $defaultPaths = [
            __DIR__ . '/../..',
            __DIR__ . '/../../../../..'
        ];

        // Combine default and additional paths
        $combinedPaths = array_merge($defaultPaths, $paths);

        // Load the environment variables
        self::load($combinedPaths);
    }

    /**
     * Convert dot notation key (e.g., 'app.name') to an environment variable format ('APP_NAME').
     *
     * @param string $key The dot notation key
     * @return string The environment key
     */
    protected static function convertDotNotationToEnvKey(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }

    /**
     * Parse the environment variable value.
     *
     * @param string $value The raw environment variable value
     * @return mixed The parsed value
     */
    protected static function parseValue(string $value): mixed
    {
        $lower = strtolower($value);

        return match (true) {
            $lower === 'true' => true,
            $lower === 'false' => false,
            $lower === 'null' => null,
            is_numeric($value) => strpos($value, '.') === false ? (int) $value : (float) $value,
            default => $value,
        };
    }
}
