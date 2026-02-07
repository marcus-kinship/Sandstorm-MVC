<?php

namespace Sys\Request;

/**
 * Get class for handling GET requests
 * 
 * Specialized class for handling GET data with validation support and htmlentities protection.
 *
 * @category _("Request Handling")
 * @package  Sys\Request
 */
class Get
{
    /**
     * @var array|null Cached GET parameters
     */
    private static $params = null;

    /**
     * Parse and cache GET parameters from URL
     *
     * @return array Parsed GET parameters with htmlentities applied
     */
    private static function parseParams()
    {
        if (self::$params !== null) {
            return self::$params;
        }

        $url = urldecode($_SERVER["REQUEST_URI"]);
        $url = parse_url($url);

        if (key_exists('query', $url) && $url['query'] !== "") {
            parse_str($url['query'], $data);

            // Apply htmlentities to each value in the array
            foreach ($data as $key => $value) {
                $data[$key] = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }

            self::$params = $data;
            return $data;
        }

        self::$params = [];
        return [];
    }

    /**
     * Get a variable from the GET array with optional validation
     *
     * @param string      $key     Variable name (key)
     * @param string|null $mode    Optional validation mode ('valid' triggers validation)
     * @param mixed       $default Default value if key doesn't exist
     * @return mixed               Variable content, default value, or Validator object
     */
    public static function get(string $key, string $mode = null, $default = null)
    {
        $params = self::parseParams();

        if ($mode === 'valid') {
            return new Validator($params[$key] ?? null, 'get', $key);
        }

        if (array_key_exists($key, $params)) {
            return $params[$key];
        }

        return $default ?? false;
    }

    /**
     * Get all GET data
     *
     * @return array All GET data with htmlentities applied
     */
    public static function all()
    {
        return self::parseParams();
    }

    /**
     * Check if a GET key exists
     *
     * @param string $key Key to check
     * @return bool       True if key exists
     */
    public static function has(string $key)
    {
        $params = self::parseParams();
        return array_key_exists($key, $params);
    }

    /**
     * Check if GET data is not empty
     *
     * @return bool True if GET contains data
     */
    public static function hasData()
    {
        $params = self::parseParams();
        return !empty($params);
    }

    /**
     * Get multiple GET values
     *
     * @param array $keys Array of keys to retrieve
     * @return array      Associative array of key-value pairs
     */
    public static function only(array $keys)
    {
        $params = self::parseParams();
        $result = [];
        
        foreach ($keys as $key) {
            if (array_key_exists($key, $params)) {
                $result[$key] = $params[$key];
            }
        }
        
        return $result;
    }

    /**
     * Get all GET data except specified keys
     *
     * @param array $keys Keys to exclude
     * @return array      Filtered GET data
     */
    public static function except(array $keys)
    {
        $params = self::parseParams();
        return array_diff_key($params, array_flip($keys));
    }

    /**
     * Get the raw query string
     *
     * @return string The query string
     */
    public static function queryString()
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }
}
