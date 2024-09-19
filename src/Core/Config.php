<?php

namespace TondbadSwoole\Core;

class Config
{
    /**
     * @var array $config Static array to store all configuration values
     */
    protected static array $config = [];

    /**
     * @var array $loadedFiles Keeps track of loaded configuration files
     */
    protected static array $loadedFiles = [];

    /**
     * Load configuration values from files or arrays into the static config array.
     * This function merges both framework and project config files.
     *
     * @param string $file The config file name (e.g., 'app')
     */
    protected static function load(string $file)
    {
        // Load framework config
        $frameworkConfigPath = __DIR__ . "/../../config/{$file}.php";
        $projectConfigPath = __DIR__ . "/../../../../config/{$file}.php"; // Assuming the project config is in a higher directory

        $frameworkConfig = file_exists($frameworkConfigPath) ? require $frameworkConfigPath : [];
        $projectConfig = file_exists($projectConfigPath) ? require $projectConfigPath : [];

        // Merge project config to override framework defaults
        self::$config[$file] = array_merge($frameworkConfig, $projectConfig);
        self::$loadedFiles[] = $file; // Mark file as loaded
    }

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
     * Helper function to get a config value from a nested array using dot notation.
     *
     * @param string $key
     * @param array $array
     * @param mixed $default
     * @return mixed
     */
    protected static function getFromArray(string $key, array $array, $default)
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
}
