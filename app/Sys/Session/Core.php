<?php

namespace Sys\Session;

/**
 * Class Core
 *
 * Low-level stateless wrapper around PHP's native session engine.
 * Handles session lifecycle: starting, identifying, regenerating, and destroying.
 *
 * All methods guard against operating on inactive sessions.
 *
 * @category Session
 * @package  Sys\Session
 */
class Core
{
    /**
     * Starts the session with secure cookie parameters.
     *
     * Applies HttpOnly, Secure, and SameSite=Strict cookie flags before
     * session_start(). Safe to call multiple times — no-op if already active.
     *
     * Strict mode: PHP only accepts session ids it created itself, so an id
     * an attacker made up (session fixation) starts a new, empty session.
     * The id only travels in the cookie, never in the URL.
     *
     * @return void
     * @throws \RuntimeException If the session cannot be started
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');

            session_set_cookie_params(self::cookieParams());

            // @: session_start() warns on headers already sent; the exception below reports it
            if (!@session_start()) {
                throw new \RuntimeException("Failed to start session");
            }
        }
    }

    /**
     * Cookie flags for the session cookie.
     *
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    private static function cookieParams(): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => \CookieManager::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ];
    }

    /**
     * Returns the current session ID.
     *
     * @return string Active session ID, or empty string if no session is running.
     */
    public static function id(): string
    {
        return session_id();
    }

    /**
     * Rotates the session ID to prevent session fixation attacks.
     *
     * MUST be called immediately after a successful login or any privilege
     * escalation — before writing any post-login session data.
     *
     * Example:
     * ```php
     * if ($auth->verify($user, $pass)) {
     *     Core::regenerate();          // <-- rotate ID first
     *     Session::set('user_id', $user->id);
     * }
     * ```
     *
     * @param bool $deleteOld Delete the old session file on disk. Defaults to true.
     *
     * @return void
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        session_regenerate_id($deleteOld);
    }

    /**
     * Destroys the session entirely.
     *
     * Clears $_SESSION, rotates the ID (invalidating the old one server-side),
     * then calls session_destroy(). Safe to call even if no session is active.
     *
     * @return void
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            // Remove the cookie too, with the same flags it was set with
            if (ini_get('session.use_cookies') && !headers_sent()) {
                $params = self::cookieParams();
                unset($params['lifetime']);
                setcookie(session_name(), '', ['expires' => time() - 42000] + $params);
            }

            session_destroy();
        }
    }
}