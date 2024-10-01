<?php

namespace TondbadSwoole\Core;

class Config
{
    /**
     * @var array $searchPaths
     */
    protected static array $searchPaths = [];

    /**
     * @var array $config Static array to store all configuration values
     */
    protected static array $config = [];

    /**
     * @var array $loadedFiles Keeps track of loaded configuration files
     */
    protected static array $loadedFiles = [];

    /**
     * Get a configuration value. If it doesn't exist, return the default value.
     * Automatically loads the config file if it hasn't been loaded yet.
     *
     * @param string $key The configuration key (e.g., 'app.name')
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // Split the key by dot notation (e.g., 'app.name' -> 'app', 'name')
        $segments = explode('.', $key);
        $file = $segments[0]; // The first part of the key is the file name (e.g., 'app')

        // Check if the config file has been loaded, if not, load it
        if (!in_array($file, self::$loadedFiles)) {
            self::load($file);
        }

        // Check if the config key exists in environment variables first
        if ($envValue = getenv(self::convertDotNotationToEnvKey($key))) {
            return $envValue;
        }

        // Fallback to config array
        return self::getFromArray($key, self::$config, $default);
    }

    /**
     * Load configuration values from files or arrays into the static config array.
     * This function merges both framework and project config files.
     *
     * @param string $file The config file name (e.g., 'app')
     */
    protected static function load(string $file)
    {
        $configs = [];

        foreach (self::getSearchPaths() as $path) {
            $configPath = $path . "/$file.php";
            $configs[] = file_exists($configPath) ? require $configPath : [];
        }

        // Merge project config to override framework defaults
        self::$config[$file] = array_merge(...$configs);
        self::$loadedFiles[] = $file; // Mark file as loaded
    }

    /**
     * Returns the complete list of search paths, including the default paths and any additional paths added.
     *
     * @return array An array of all search paths, including default and user-added paths.
     */
    protected static function getSearchPaths(): array
    {
        return array_merge(
            [
                __DIR__ . "/../../config",
                __DIR__ . "/../../../../../config"
            ],
            self::$searchPaths
        );
    }

    /**
     * Convert dot notation config key to environment variable format.
     *
     * @param string $key
     * @return string
     */
    protected static function convertDotNotationToEnvKey(string $key): string
    {
        return strtoupper(str_replace('.', '_', $key));
    }

    /**
     * Helper function to get a config value from a nested array using dot notation.
     *
     * @param string $key
     * @param array $array
     * @param mixed $default
     * @return mixed
     */
    protected static function getFromArray(string $key, array $array, $default): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $keys = explode('.', $key);
        foreach ($keys as $segment) {
            if (!isset($array[$segment])) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Adds a new path to the search paths array if it is not already present.
     *
     * @param string $path The path to be added to the search paths array.
     * @return void
     */
    public static function addToSearchPaths(string $path): void
    {
        if (in_array($path, self::$searchPaths, true))
            return;

        self::$searchPaths[] = $path;
    }

    /**
     * Dynamically set a configuration value.
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value)
    {
        $segments = explode('.', $key);
        $file = $segments[0]; // First part is the file name (e.g., 'app')

        // Ensure the file is loaded first
        if (!in_array($file, self::$loadedFiles)) {
            self::load($file);
        }

        self::setInArray($key, $value, self::$config);
    }

    /**
     * Helper function to set a config value in a nested array using dot notation.
     *
     * @param string $key
     * @param mixed $value
     * @param array &$array
     */
    protected static function setInArray(string $key, $value, array &$array)
    {
        $keys = explode('.', $key);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($array[$key]) || !is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;
    }
}
