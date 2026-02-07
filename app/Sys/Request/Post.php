<?php

namespace Sys\Request;

/**
 * Post class for handling POST requests
 * 
 * Specialized class for handling POST data with validation support.
 *
 * @category _("Request Handling")
 * @package  Sys\Request
 */
class Post
{
    /**
     * Get a variable from the POST array with optional validation
     *
     * @param string      $key     Variable name (key)
     * @param string|null $mode    Optional validation mode ('valid' triggers validation)
     * @param mixed       $default Default value if key doesn't exist
     * @return mixed               Variable content, default value, or Validator object
     */
    public static function get(string $key, string $mode = null, $default = null)
    {
        if ($mode === 'valid') {
            return new Validator($_POST[$key] ?? null, 'post', $key);
        }

        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $default ?? false;
    }

    /**
     * Get all POST data
     *
     * @return array All POST data
     */
    public static function all()
    {
        return $_POST;
    }

    /**
     * Check if a POST key exists
     *
     * @param string $key Key to check
     * @return bool       True if key exists
     */
    public static function has(string $key)
    {
        return array_key_exists($key, $_POST);
    }

    /**
     * Check if POST data is not empty
     *
     * @return bool True if POST contains data
     */
    public static function hasData()
    {
        return !empty($_POST);
    }

    /**
     * Get multiple POST values
     *
     * @param array $keys Array of keys to retrieve
     * @return array      Associative array of key-value pairs
     */
    public static function only(array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $_POST)) {
                $result[$key] = $_POST[$key];
            }
        }
        return $result;
    }

    /**
     * Get all POST data except specified keys
     *
     * @param array $keys Keys to exclude
     * @return array      Filtered POST data
     */
    public static function except(array $keys)
    {
        return array_diff_key($_POST, array_flip($keys));
    }
}
