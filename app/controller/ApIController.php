<?php

/**
 * API Controller - Renders JSON or XML responses
 *
 * NAMING CONVENTION:
 * - Class must be named with pattern: ...{js|xml}ApiController
 * - File must be named with pattern: ...{js|xml}api.class
 * - Example: UserJsApiController in userjsapi.class for JSON
 * - Example: ProductXmlApiController in productxmlapi.class for XML
 *
 * HTTP METHOD DETECTION:
 * The controller automatically sets Access-Control-Allow-Methods based on function name prefixes:
 * - delete, remove → DELETE
 * - get, read → GET
 * - post, add → POST
 * - put, update → PUT
 * - fix, patch → GET, POST, PATCH
 * - head → GET, POST, HEAD
 * - search, request, find, lookup, ask, set, save → GET, POST
 * - options, opt → GET, POST, PUT, DELETE, OPTIONS
 *
 * CORS SUPPORT:
 * - Supports domain whitelisting via allowDomains()
 * - Handles wildcard (*) for public APIs
 * - Sets appropriate Access-Control-Allow-Origin headers
 *
 * JSONP SUPPORT:
 * - Validates callback function names to prevent XSS
 * - Wraps JSON response in callback function when provided
 *
 * @file apicontroller.php
 * @author Marcus Larsson
 * @version 2024.1.0 (Fixed)
 * @category API Controller Class
 */

abstract class ApiController extends SiteRouter
{

    /**
     * @var array $header Array of HTTP headers to send in the response
     */
    private $header = array();

    /**
     * @var mixed $data The response data to send
     */
    private $data;

    /**
     * @var mixed $domains Whitelist of allowed domains for CORS
     *                     Can be a string ("*" or single domain), or array of domains
     */
    private $domains = "";

    /**
     * @var string $jsfunction JavaScript callback function name for JSONP
     */
    private $jsfunction = "";

    /**
     * Constructor
     * 
     * Validates class naming convention and sets appropriate Content-Type header.
     * Detects HTTP method from function name and sets CORS headers accordingly.
     */
    public function __construct()
    {

        // Define supported API formats and their content types
        $apif = array(
            'js' => 'Content-Type: application/json; charset=utf-8',
            'xml' => 'Content-type: text/xml; charset=utf-8'
        );

        // Build case-insensitive regex to match js or xml before 'api'
        $keys = implode('|', array_keys($apif));
        $re = "/(?:$keys)(?=api)/i";
        $str = get_called_class();

        // Validate class naming convention
        if (!preg_match($re, $str, $matches, PREG_OFFSET_CAPTURE, 0)) {
            $this->httpResponseCode(501, true);
            $val = implode(_(' eller '), array_keys($apif));
            print sprintf(
                _('Du måste ange klassnamnet ...%sapicontroller i din klass och filnamn ...%sapi.class'),
                $val,
                $val,
                get_called_class()
            );
            exit();
        }

        // Set Content-Type header based on API format (js or xml)
        $api = strtolower($matches[0][0]);
        $this->header[] = $apif[$api];

        // Get the controller/function name from server variables
        if (array_key_exists('REDIRECT_HTTP_CONTROLLER', $_SERVER)) {
            $f = explode("|", $_SERVER['REDIRECT_HTTP_CONTROLLER']);
        } else {
            $f = explode("|", $_SERVER['HTTP_CONTROLLER']);
        }

        // Extract function name (case-insensitive)
        $str = strtolower($f['2']);

        // Map function name prefixes to HTTP methods
        $methodMap = [
            'delete|remove' => 'DELETE',
            'get|read' => 'GET',
            'post|add' => 'POST',
            'put|update' => 'PUT',
            'fix|patch' => 'GET, POST, PATCH',
            'head' => 'GET, POST, HEAD',
            'search|request|find|lookup|ask|set|save' => 'GET, POST',
            'options|opt' => 'GET, POST, PUT, DELETE, OPTIONS'
        ];

        // Find matching method and set CORS header (case-insensitive)
        foreach ($methodMap as $pattern => $methods) {
            if (preg_match('/^(' . $pattern . ')/i', $str)) {
                $this->header['Access-Control-Allow-Methods'] = $methods;
                break;
            }
        }

        // Set default method if none matched
        if (!isset($this->header['Access-Control-Allow-Methods'])) {
            $this->header['Access-Control-Allow-Methods'] = 'GET, POST';
        }
    }

    /**
     * Set Access-Control-Max-Age header
     * 
     * Specifies how long the results of a preflight request can be cached.
     *
     * @param int $seconds Number of seconds the preflight can be cached
     * @return void
     */
    public function maxAge($seconds)
    {
        $this->header['Access-Control-Max-Age'] = $seconds;
    }

    /**
     * Set Access-Control-Allow-Credentials header
     * 
     * Indicates whether the response can be exposed when credentials are present.
     *
     * @param bool $bool True to allow credentials, false otherwise
     * @return void
     */
    public function credentials($bool)
    {
        $this->header['Access-Control-Allow-Credentials'] = $bool ? 'true' : 'false';
    }

    /**
     * Destructor
     * 
     * Sends all headers and outputs the response data.
     * Handles CORS, JSONP, and various response formats.
     */
    function __destruct()
    {

        // Return 204 No Content if no data to send
        if (empty($this->data)) {
            $this->httpResponseCode(204);
            return;
        }

        // Handle CORS domain configuration
        if ($this->domains != "") {

            // Allow all domains with wildcard
            if ($this->domains === "*") {
                $this->header['Access-Control-Allow-Origin'] = '*';
            }
            // Check if origin is in whitelist array
            else if (is_array($this->domains) && isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $this->domains)) {
                $this->header['Access-Control-Allow-Origin'] = $_SERVER['HTTP_ORIGIN'];
            }
            // Check if origin matches single domain string
            else if (is_string($this->domains) && isset($_SERVER['HTTP_ORIGIN']) && $this->domains == $_SERVER['HTTP_ORIGIN']) {
                $this->header['Access-Control-Allow-Origin'] = $_SERVER['HTTP_ORIGIN'];
            }
            // Fallback: if domains are set but no match, allow first domain in array or the string domain
            else if (is_array($this->domains) && count($this->domains) > 0) {
                $this->header['Access-Control-Allow-Origin'] = $this->domains[0];
            } else if (is_string($this->domains) && $this->domains != "") {
                $this->header['Access-Control-Allow-Origin'] = $this->domains;
            }

        }

        // Send all headers
        foreach ($this->header as $header => $val) {
            if (is_numeric($header)) {
                // Numeric key means the value contains the complete header
                header($val);
            } else {
                // String key is the header name
                header($header . ': ' . $val);
            }
        }

        // Validate JSONP callback function name (prevents XSS)
        // Allows standard JavaScript identifier: starts with letter, $, or _
        // followed by letters, digits, $, or _
        $re = '/^[a-zA-Z_$][a-zA-Z0-9_$]*$/';

        // Output JSONP response if callback function is valid
        if ($this->jsfunction != "" && preg_match($re, $this->jsfunction)) {
            print $this->jsfunction . '(' . json_encode($this->data) . ');';
        }
        // Otherwise output plain JSON
        else if ($this->data) {
            print json_encode($this->data);
        }

    }

    /**
     * Set a custom HTTP header
     *
     * @param string $word Header name (without colon)
     * @param string $value Header value
     * @return object Returns $this for method chaining
     */
    final function setHeader($word, $value)
    {
        $this->header[$word] = $value;
        return $this;
    }

    /**
     * Get a previously set header value
     *
     * @param string $word Header name
     * @return string|null Header value or null if not found
     */
    final function getHeader($word)
    {
        if (array_key_exists($word, $this->header)) {
            return $this->header[$word];
        }
        return null;
    }

    /**
     * Set allowed domains for CORS
     * 
     * Configure which domains can access this API endpoint.
     *
     * @param string|array $domains Single domain string, "*" for all, or array of domains
     * @return object Returns $this for method chaining
     * 
     * @example
     * // Allow all domains
     * $this->allowDomains("*");
     * 
     * // Allow single domain
     * $this->allowDomains("https://example.com");
     * 
     * // Allow multiple domains
     * $this->allowDomains(["https://example.com", "https://app.example.com"]);
     */
    final function allowDomains($domains)
    {
        $this->domains = $domains;
        return $this;
    }

    /**
     * Set response data and optional JSONP callback
     *
     * @param mixed $data Data to be JSON-encoded and returned
     * @param string $jsfunction Optional JSONP callback function name
     * @return void
     * 
     * @example
     * // Regular JSON response
     * $this->callback(['status' => 'success', 'data' => $results]);
     * 
     * // JSONP response
     * $this->callback(['status' => 'success'], 'myCallback');
     */
    final function callback($data, $jsfunction = "")
    {
        $this->data = $data;
        $this->jsfunction = $jsfunction;
    }

    /**
     * Set HTTP response code
     * 
     * Sets the HTTP status code for the response. Can either immediately
     * send the header or store it for later output.
     *
     * @param int|null $code HTTP status code (100-505)
     * @param bool $call If true, immediately send header; if false, store for later
     * @return int The HTTP status code that was set
     * 
     * @example
     * // Set 404 and send immediately
     * $this->httpResponseCode(404, true);
     * 
     * // Set 200 for later output
     * $this->httpResponseCode(200);
     */
    function httpResponseCode($code = NULL, $call = false)
    {

        if ($code !== NULL) {

            // Map status codes to their text descriptions
            switch ($code) {
                case 100:
                    $text = 'Continue';
                    break;
                case 101:
                    $text = 'Switching Protocols';
                    break;
                case 200:
                    $text = 'OK';
                    break;
                case 201:
                    $text = 'Created';
                    break;
                case 202:
                    $text = 'Accepted';
                    break;
                case 203:
                    $text = 'Non-Authoritative Information';
                    break;
                case 204:
                    $text = 'No Content';
                    break;
                case 205:
                    $text = 'Reset Content';
                    break;
                case 206:
                    $text = 'Partial Content';
                    break;
                case 300:
                    $text = 'Multiple Choices';
                    break;
                case 301:
                    $text = 'Moved Permanently';
                    break;
                case 302:
                    $text = 'Moved Temporarily';
                    break;
                case 303:
                    $text = 'See Other';
                    break;
                case 304:
                    $text = 'Not Modified';
                    break;
                case 305:
                    $text = 'Use Proxy';
                    break;
                case 400:
                    $text = 'Bad Request';
                    break;
                case 401:
                    $text = 'Unauthorized';
                    break;
                case 402:
                    $text = 'Payment Required';
                    break;
                case 403:
                    $text = 'Forbidden';
                    break;
                case 404:
                    $text = 'Not Found';
                    break;
                case 405:
                    $text = 'Method Not Allowed';
                    break;
                case 406:
                    $text = 'Not Acceptable';
                    break;
                case 407:
                    $text = 'Proxy Authentication Required';
                    break;
                case 408:
                    $text = 'Request Time-out';
                    break;
                case 409:
                    $text = 'Conflict';
                    break;
                case 410:
                    $text = 'Gone';
                    break;
                case 411:
                    $text = 'Length Required';
                    break;
                case 412:
                    $text = 'Precondition Failed';
                    break;
                case 413:
                    $text = 'Request Entity Too Large';
                    break;
                case 414:
                    $text = 'Request-URI Too Large';
                    break;
                case 415:
                    $text = 'Unsupported Media Type';
                    break;
                case 500:
                    $text = 'Internal Server Error';
                    break;
                case 501:
                    $text = 'Not Implemented';
                    break;
                case 502:
                    $text = 'Bad Gateway';
                    break;
                case 503:
                    $text = 'Service Unavailable';
                    break;
                case 504:
                    $text = 'Gateway Time-out';
                    break;
                case 505:
                    $text = 'HTTP Version not supported';
                    break;
                default:
                    print _('Unknown http status code, you must enter a different status code');
                    exit();
                    break;
            }

            // Get protocol version
            $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

            // Either send header immediately or store for later
            if ($call) {
                header($protocol . ' ' . $code . ' ' . $text);
            } else {
                $this->header[$protocol] = $code . ' ' . $text;
            }

            // Store in global for compatibility
            $GLOBALS['http_response_code'] = $code;
        } else {
            // If no code provided, return current code or default 200
            $code = (isset($GLOBALS['http_response_code']) ? $GLOBALS['http_response_code'] : 200);
        }

        return $code;
    }

}