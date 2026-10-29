<?php

/**
 * HTTP requests with cURL.
 *
 * <code>
 * $html = Curl::get('https://example.com/search', ['q' => 'sand']);
 * $data = Curl::post('https://example.com/api', ['name' => 'Ada'], [], fn($body) => json_decode($body, true));
 * $status = Curl::status();   // HTTP status of the last request
 * </code>
 *
 * Safe defaults: the server's certificate is checked, only http and https
 * are allowed (also when following redirects, so a URL cannot reach
 * file:// or other protocols), at most 5 redirects, 10 s to connect and
 * 30 s in total.
 *
 * @category _("HTTP Request Handling class")
 */
class Curl
{
    /**
     * Information about the last request (curl_getinfo())
     *
     * @var array
     */
    private static array $info = [];

    /**
     * GET request.
     *
     * @param string        $url
     * @param array         $data     Added to the URL as query string
     * @param array         $options  CURLOPT_* => value, on top of the defaults
     * @param callable|null $callback Gets the response body, its result is returned
     * @return mixed The response body (or what the callback returns)
     * @throws \RuntimeException When the request fails (no connection, timeout, bad certificate, ...)
     */
    public static function get($url, $data = [], $options = [], $callback = null)
    {
        if ($data !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }

        return self::request($url, [CURLOPT_HTTPGET => true] + $options, $callback);
    }

    /**
     * POST request with form data (application/x-www-form-urlencoded).
     *
     * @param string        $url
     * @param array         $data     Form fields
     * @param array         $options  CURLOPT_* => value, on top of the defaults
     * @param callable|null $callback Gets the response body, its result is returned
     * @return mixed The response body (or what the callback returns)
     * @throws \RuntimeException When the request fails
     */
    public static function post($url, $data = [], $options = [], $callback = null)
    {
        return self::request($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
        ] + $options, $callback);
    }

    /**
     * HTTP status code of the last request, 0 before the first.
     *
     * @return int
     */
    public static function status(): int
    {
        return (int) (self::$info['http_code'] ?? 0);
    }

    /**
     * Everything curl_getinfo() knows about the last request.
     *
     * @return array
     */
    public static function info(): array
    {
        return self::$info;
    }

    /**
     * Run a request.
     *
     * @param string        $url
     * @param array         $options
     * @param callable|null $callback
     * @return mixed
     * @throws \RuntimeException
     */
    private static function request(string $url, array $options, ?callable $callback)
    {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException(_('The curl extension is not enabled in PHP'));
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \InvalidArgumentException(sprintf(_('Only http and https URLs are allowed: %s'), $url));
        }

        $protocols = defined('CURLPROTO_HTTP') ? CURLPROTO_HTTP | CURLPROTO_HTTPS : null;

        // + keeps the CURLOPT_* keys (array_merge would renumber them)
        $options += [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
        ];
        if ($protocols !== null) {
            $options += [CURLOPT_PROTOCOLS => $protocols, CURLOPT_REDIR_PROTOCOLS => $protocols];
        }

        $handle = curl_init();
        curl_setopt_array($handle, $options);

        $result = curl_exec($handle);
        self::$info = curl_getinfo($handle);

        if ($result === false) {
            throw new \RuntimeException(sprintf(_('Request to %s failed: %s'), $url, curl_error($handle)));
        }

        return $callback !== null ? $callback($result) : $result;
    }
}
