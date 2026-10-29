<?

/**
 * Class to handle HTTP requests using cURL.
 *
 * @category _("HTTP Request Handling class")
 */
class Curl {
    /**
     * Helper function to make a curl request.
     *
     * @param string $url The URL to request.
     * @param string $method The HTTP method to use (GET or POST).
     * @param array $data An associative array of data to send with the request.
     * @param array $options An array of options to pass to curl_setopt_array().
     * @param callable $callback A function to call with the response data.
     *
     * @return mixed The response data from the server.
     */
    private static function makeRequest($url, $method, $data = [], $options = [], $callback = null) {
        $ch = curl_init();
        $def = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded')
        ];

        $def = array_merge($def, $options);

        curl_setopt_array($ch, $def);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } elseif ($method === 'GET' && count($data) > 0) {
            $url .= '?' . http_build_query($data);
        }

        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        curl_close($ch);

        if (isset($callback) && is_callable($callback)) {
            $result = call_user_func($callback, $result);
        }

        return $result;
    }

    /**
     * Makes an HTTP GET request.
     *
     * @param string $url The URL to request.
     * @param array $data An associative array of data to send with the request.
     * @param array $options An array of options to pass to curl_setopt_array().
     * @param callable $callback A function to call with the response data.
     *
     * @return mixed The response data from the server.
     */
    public static function get($url, $data = [], $options = [], $callback = null) {
        return self::makeRequest($url, 'GET', $data, $options, $callback);
    }

    /**
     * Makes an HTTP POST request.
     *
     * @param string $url The URL to request.
     * @param array $data An associative array of data to send with the request.
     * @param array $options An array of options to pass to curl_setopt_array().
     * @param callable $callback A function to call with the response data.
     *
     * @return mixed The response data from the server.
     */
    public static function post($url, $data = [], $options = [], $callback = null) {
        return self::makeRequest($url, 'POST', $data, $options, $callback);
    }
}
