<?php

/**
 * Cookies with safe defaults.
 *
 * Defaults: path /, HttpOnly (JavaScript cannot read it), Secure on HTTPS
 * (also behind a proxy that sends X-Forwarded-Proto: https), SameSite=Lax.
 * Values may be strings, numbers, booleans or arrays (arrays are stored as
 * JSON, never serialized, so a cookie cannot create PHP objects).
 *
 * Names starting with __Host- get the browser's strongest protection: always
 * Secure, path /, no domain, so no other site or subdomain can set them.
 *
 * Cookie values come from the visitor: validate them like any other input.
 * Signed cookies cannot be changed, but they can be read: never put secrets in them.
 *
 * <code>
 * CookieManager::set('theme', 'dark', 86400 * 30);   // 30 days
 * $theme = CookieManager::get('theme', 'light');
 * CookieManager::delete('theme');
 *
 * // Signed: the visitor can read it but not change it
 * CookieManager::setSigned('plan', 'pro', 3600);
 * $plan = CookieManager::getSigned('plan');           // null when it was changed
 * </code>
 *
 * Signed cookies need a secret of at least 32 characters in
 * app/config/settings/cookie.ini.php (config::set('cookie.secret', ...)).
 *
 * @category _("Cookie Management class")
 */
class CookieManager
{
    /**
     * Default cookie flags
     *
     * @var array{path: string, domain: string, httponly: bool, samesite: string}
     */
    private const DEFAULTS = [
        'path' => '/',
        'domain' => '',
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    /**
     * Set a cookie.
     *
     * @param string                      $name     Letters, digits, _ and -
     * @param string|int|float|bool|array $value
     * @param int                         $lifetime Seconds; 0 = until the browser closes
     * @param array                       $options  path, domain, secure, httponly, samesite
     * @return bool False when the headers are already sent
     * @throws \InvalidArgumentException For an invalid name or SameSite value
     */
    public static function set(string $name, string|int|float|bool|array $value, int $lifetime = 0, array $options = []): bool
    {
        self::checkName($name);

        $value = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;

        // Browsers silently drop cookies over about 4 KB
        if (strlen($name) + strlen(rawurlencode($value)) > 4000) {
            throw new \InvalidArgumentException(sprintf(_('The cookie %s is larger than 4 KB'), $name));
        }

        if (headers_sent()) {
            return false;
        }

        $sent = setcookie($name, $value, ['expires' => $lifetime > 0 ? time() + $lifetime : 0] + self::options($options, $name));
        if ($sent) {
            $_COOKIE[$name] = $value;
        }

        return $sent;
    }

    /**
     * Read a cookie.
     *
     * A value that was stored as an array (JSON) comes back as an array.
     *
     * @param string $name
     * @param mixed  $default Returned when the cookie is not there
     * @return mixed
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        if (!isset($_COOKIE[$name]) || !is_string($_COOKIE[$name])) {
            return $default;
        }

        $value = $_COOKIE[$name];

        if ($value !== '' && ($value[0] === '[' || $value[0] === '{')) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Whether the cookie is there.
     *
     * @param string $name
     * @return bool
     */
    public static function has(string $name): bool
    {
        return isset($_COOKIE[$name]);
    }

    /**
     * Delete a cookie (use the same path and domain as when it was set).
     *
     * @param string $name
     * @param array  $options path, domain
     * @return bool False when the headers are already sent
     */
    public static function delete(string $name, array $options = []): bool
    {
        self::checkName($name);
        unset($_COOKIE[$name]);

        if (headers_sent()) {
            return false;
        }

        return setcookie($name, '', ['expires' => time() - 3600] + self::options($options, $name));
    }

    /**
     * All cookies the visitor sent.
     *
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $all = [];
        foreach (array_keys($_COOKIE) as $name) {
            $all[$name] = self::get((string) $name);
        }

        return $all;
    }

    /**
     * Set a signed cookie: readable, but any change makes getSigned() return null.
     *
     * The expiry time is signed too, so the server rejects the cookie after
     * it, even if someone kept a copy. Without a lifetime the cookie lasts
     * until the browser closes, and the server accepts it for $maxAge seconds.
     *
     * @param string                      $name
     * @param string|int|float|bool|array $value
     * @param int                         $lifetime Seconds; 0 = until the browser closes
     * @param array                       $options
     * @param int                         $maxAge   Server-side limit when $lifetime is 0 (default 1 day)
     * @return bool
     * @throws \RuntimeException When no cookie secret is set
     */
    public static function setSigned(string $name, string|int|float|bool|array $value, int $lifetime = 0, array $options = [], int $maxAge = 86400): bool
    {
        $expires = time() + ($lifetime > 0 ? $lifetime : $maxAge);
        $payload = rtrim(strtr(base64_encode(json_encode(['v' => $value, 'e' => $expires], JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');

        return self::set($name, $payload . '.' . self::sign($name, $payload), $lifetime, $options);
    }

    /**
     * Read a signed cookie.
     *
     * @param string $name
     * @param mixed  $default Returned when the cookie is missing, was changed or has expired
     * @return mixed
     * @throws \RuntimeException When no cookie secret is set
     */
    public static function getSigned(string $name, mixed $default = null): mixed
    {
        $cookie = $_COOKIE[$name] ?? null;
        if (!is_string($cookie) || !str_contains($cookie, '.')) {
            return $default;
        }

        [$payload, $signature] = explode('.', $cookie, 2);

        if (!hash_equals(self::sign($name, $payload), $signature)) {
            return $default;
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (!is_array($data) || !isset($data['e']) || (int) $data['e'] < time()) {
            return $default;
        }

        return $data['v'] ?? $default;
    }

    /**
     * HMAC of the cookie name and value.
     *
     * @param string $name
     * @param string $payload
     * @return string
     * @throws \RuntimeException When no cookie secret is set
     */
    private static function sign(string $name, string $payload): string
    {
        $secret = config::get('cookie.secret', true);

        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException(_('Signed cookies need a secret of at least 32 characters: config::set("cookie.secret", ...)'));
        }

        return hash_hmac('sha256', $name . '|' . $payload, $secret);
    }

    /**
     * Whether the request came over HTTPS (also behind a proxy).
     *
     * X-Forwarded-Proto can be sent by anyone, but it only turns Secure on,
     * which never weakens a cookie.
     *
     * @return bool
     */
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    /**
     * The defaults with the given options on top.
     *
     * @param array  $options
     * @param string $name    Cookie name (__Host- cookies get fixed flags)
     * @return array
     * @throws \InvalidArgumentException For an invalid SameSite value
     */
    private static function options(array $options, string $name = ''): array
    {
        $options = array_intersect_key($options, array_flip(['path', 'domain', 'secure', 'httponly', 'samesite'])) + self::DEFAULTS;
        $options['secure'] ??= self::isHttps();

        // The browser only accepts __Host- cookies with exactly these flags
        if (str_starts_with($name, '__Host-')) {
            $options['secure'] = true;
            $options['path'] = '/';
            $options['domain'] = '';
        }

        if (!in_array($options['samesite'], ['Strict', 'Lax', 'None'], true)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid SameSite value: %s'), $options['samesite']));
        }
        // Browsers drop SameSite=None cookies that are not Secure
        if ($options['samesite'] === 'None') {
            $options['secure'] = true;
        }

        return $options;
    }

    /**
     * @param string $name
     * @return void
     * @throws \InvalidArgumentException
     */
    private static function checkName(string $name): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid cookie name: %s'), $name));
        }
    }
}
