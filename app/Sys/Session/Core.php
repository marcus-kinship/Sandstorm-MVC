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
     * @return void
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Strict',
            ]);

            if (!@session_start()) {
                throw new \RuntimeException("Failed to start session");
            }
        }
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

            // delete cookie också (viktigt!)
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            session_destroy();
        }
    }
}