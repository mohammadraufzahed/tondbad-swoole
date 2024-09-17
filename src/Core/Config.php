<?php

namespace TondbadSwoole\Core;

class Config
{
    /**
     * @var array $config Static array to store all configuration values
     */
    protected static array $config = [];

    /**
     * Load configuration values into the static config array.
     *
     * @param array $config Optional array of default config values
     */
    public static function load(array $config = [])
    {
        self::$config = array_merge(self::$config, $config);
    }

    /**
     * Get a configuration value. If it doesn't exist, return the default value.
     *
     * @param string $key The configuration key (e.g., 'app.name')
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // Check if the config key exists in environment variables first
        if ($envValue = getenv($key)) {
            return $envValue;
        }

        // Otherwise, fallback to config array
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
}
