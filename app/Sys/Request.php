<?php


/**
 * Main Request class - handles all incoming requests and provides methods to access GET, POST, and FILES data.
 * 
 * A simple wrapper around the global $_REQUEST variable (GET, POST). Returns
 * an empty string, or a custom default value, if the variable is not set.
 * This is to easily avoid error messages without setting your own conditions.
 *
 * @category Request Handling
 * @package  Dll
 */
class Request
{
    private static $req = null;

    /**
     * Check if the request is a POST request
     *
     * @return bool Returns true if it's a POST request, otherwise false
     */
    public static function isPost(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
    }

    /**
     * Check if the request is a GET request
     *
     * @return bool Returns true if it's a GET request, otherwise false
     */
    public static function isGet(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
    }

    /**
     * Check if the request is an AJAX request
     *
     * @return bool Returns true if it's an AJAX request, otherwise false
     */
    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get the request method
     *
     * @return string The request method (GET, POST, PUT, DELETE, etc.)
     */
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * Create a merged array of request parameters from URL and POST data
     *
     * @return array A merged array of URL parameters and POST data
     */
    public static function makereq()
    {
        $url = urldecode($_SERVER["REQUEST_URI"]);
        $url = parse_url($url);

        if (key_exists('query', $url) && $url['query'] !== "") {
            parse_str($url['query'], $data);

            // Apply htmlentities to each value in the array
            foreach ($data as $key => $value) {
                $data[$key] = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }

            return array_merge($data, $_POST);
        } else {
            return $_POST;
        }
    }

    /**
     * Get parameters from URL and apply htmlentities to them
     *
     * @return array An array of URL parameters with htmlentities applied
     */
    public static function makeget()
    {
        $url = urldecode($_SERVER["REQUEST_URI"]);
        $url = parse_url($url);

        if (key_exists('query', $url) && $url['query'] !== "") {
            parse_str($url['query'], $data);

            // Apply htmlentities to each value in the array
            foreach ($data as $key => $value) {
                $data[$key] = htmlentities($value, ENT_QUOTES, 'UTF-8');
            }

            return $data;
        } else {
            return array();
        }
    }

    /**
     * Get a variable from the POST array with optional validation
     *
     * If a specific key is provided as an argument ($key), the value for that key
     * is returned from the POST array. If no key is provided or if $key is set to "*",
     * the entire POST array is returned. If the key doesn't exist, false is returned.
     *
     * @param string $key    Variable name (key)
     * @param string $mode   Optional validation mode ('valid' triggers validation)
     * @return mixed         Variable content (value), false, or Validator object
     */
    public static function post(string $key = "*", string $mode = null)
    {
        if ($mode === 'valid') {
            return new Sys\Request\Validator($_POST[$key] ?? null, 'post', $key);
        }

        if ($key == "*") {
            return $_POST;
        }

        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        } else {
            return false;
        }
    }

    /**
     * Get a variable from POST or GET array with optional validation
     * Applies htmlentities to the value if it exists
     *
     * @param string $key    Variable name (key)
     * @param string $mode   Optional validation mode ('valid' triggers validation)
     * @return mixed         Value for the specified key, false if key doesn't exist, or Validator object
     */
    public static function req(string $key = "*", string $mode = null)
    {
        if (self::$req == null) {
            self::$req = self::makereq();
        }
        $_REQUEST = self::$req;

        if ($mode === 'valid') {
            return new Sys\Request\Validator($_REQUEST[$key] ?? null, 'request', $key);
        }

        if ($key == "*") {
            return $_REQUEST;
        }

        if (array_key_exists($key, $_REQUEST)) {
            return htmlentities($_REQUEST[$key], ENT_QUOTES, 'UTF-8');
        } else {
            return false;
        }
    }

    /**
     * Get a variable from GET array with optional validation
     * Applies htmlentities to the value if it exists
     *
     * @param string $key    Variable name (key)
     * @param string $mode   Optional validation mode ('valid' triggers validation)
     * @return mixed         Value for the specified key, false if key doesn't exist, or Validator object
     */
    public static function get(string $key = "*", string $mode = null)
    {
        if (self::$req == null) {
            self::$req = self::makereq();
        }
        $_GET = self::$req;

        if ($mode === 'valid') {
            return new Sys\Request\Validator($_GET[$key] ?? null, 'get', $key);
        }

        if ($key == "*") {
            return $_GET;
        }

        if (array_key_exists($key, $_GET)) {
            return htmlentities($_GET[$key], ENT_QUOTES, 'UTF-8');
        } else {
            return false;
        }
    }

    /**
     * Get a variable from FILES array
     *
     * @param string $key    Variable name (key)
     * @return mixed         Value for the specified key or false if key doesn't exist
     */
    public static function files(string $key = "*")
    {
        if ($key == "*") {
            return $_FILES;
        }

        if (array_key_exists($key, $_FILES)) {
            return $_FILES[$key];
        } else {
            return false;
        }
    }

    /**
     * Parse URL and extract parameters
     *
     * @param string $url           URL to parse
     * @return array|false          Associative array of parameters or false if no match
     */
    public static function parse_url($url)
    {
        if (preg_match_all("#(\?|&)([^=&]+)=([^=&]+)#is", $url, $data)) {
            foreach ($data[2] as $k => $v) {
                $tmp[$v] = htmlentities($data[3][$k], ENT_QUOTES, 'UTF-8');
                $tmp[] = htmlentities($data[3][$k], ENT_QUOTES, 'UTF-8');
            }
            return $tmp;
        } else {
            return false;
        }
    }
}
