<?php

/**
 * Session
 *
 * Public static façade for session management in the MVC framework.
 *
 * Usage follows a simple lifecycle:
 *
 * ```php
 * Session::set('user_id', 42);              // Starts the session on first use
 * $id = Session::get('user_id');           // 42
 * Session::remove('user_id');
 * Session::kill();                         // Destroy session on logout
 * ```
 *
 * Internally delegates to {@see \Sys\Session\Store} for data operations
 * and {@see \Sys\Session\Core} for the PHP session engine.
 *
 * @file     Session.php
 * @author   Marcus Larsson
 * @category _("Session Management class")
 * @package  Sys
 */
class Session
{
    /**
     * Active session store instance.
     *
     * Null until {@see Session::start()} has been called.
     *
     * @var \Sys\Session\Store|null
     */
    private static ?\Sys\Session\Store $store = null;

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Initializes the session.
     *
     * Not needed in normal code: set(), get(), add() and remove() start the
     * session the first time they are used, so pages that never touch the
     * session get no session file, no cookie and no session lock.
     * Creates the {@see \Sys\Session\Store}, which in turn starts the
     * underlying PHP session via {@see \Sys\Session\Core::start()}.
     *
     * Safe to call multiple times — subsequent calls are no-ops.
     *
     * @return void
     */
    public static function start(): void
    {
        self::store();
    }

    /**
     * Whether the visitor already has a session (a session cookie was sent).
     *
     * Lets code read optional session data without starting a session for
     * visitors who have none.
     *
     * @return bool
     */
    public static function exists(): bool
    {
        return self::$store !== null || isset($_COOKIE[session_name()]);
    }

    /**
     * The session store, started on first use.
     *
     * @return \Sys\Session\Store
     */
    private static function store(): \Sys\Session\Store
    {
        return self::$store ??= new \Sys\Session\Store();
    }

    /**
     * Returns the current PHP session ID.
     *
     * Delegates to {@see \Sys\Session\Core::id()}.
     *
     * @return string Active session ID, or empty string if no session is running.
     */
    public static function getSessionId(): string
    {
        return \Sys\Session\Core::id();
    }

    /**
     * Destroys the session completely.
     *
     * Closes the store, clears $_SESSION, removes the session cookie,
     * and calls session_destroy(). Should be called on logout.
     *
     * @return void
     */
    public static function kill(): void
    {
        if (self::$store) {
            self::$store->close();
        }

        \Sys\Session\Core::destroy();
        self::$store = null;
    }

    // -------------------------------------------------------------------------
    // Data access
    // -------------------------------------------------------------------------

    /**
     * Stores a value in the session.
     *
     * @param string $key   Session key.
     * @param mixed  $value Value to store. Must be serializable.
     * @return void
     */
    public static function set(string $key, mixed $value): void
    {
        self::store()->set($key, $value);
    }

    /**
     * Appends a value to a session array.
     *
     * If the key does not yet exist in the session it is created as an array.
     * Pass $i to overwrite a specific index instead of appending.
     *
     * @param string     $key   Session key for the array.
     * @param mixed      $value Value to append or insert.
     * @param int|string|null $i Optional index to overwrite. Null = append.
     * @return void
     */
    public static function add(string $key, mixed $value, int|string|null $i = null): void
    {
        self::store()->add($key, $value, $i);
    }

    /**
     * Retrieves a value from the session.
     *
     * @param string $key Session key.
     * @param mixed  $def Default value returned when the key does not exist.
     * @return mixed Stored value, or $def if the key is missing.
     */
    public static function get(string $key, mixed $def = false): mixed
    {
        return self::store()->get($key, $def);
    }

    /**
     * Removes a key from the session.
     *
     * Silently does nothing if the key does not exist.
     *
     * @param string $key Session key to remove.
     * @return void
     */
    public static function remove(string $key): void
    {
        self::store()->remove($key);
    }

    // -------------------------------------------------------------------------
    // Language helpers  (used by Start::initializeDefaultLanguage)
    // -------------------------------------------------------------------------

    /**
     * Returns the user's stored language preference.
     *
     * @return string|false Language code (e.g. 'sv_SE'), or false if not set.
     */
    public static function getLanguage(): string|false
    {
        return self::get('language', false);
    }

    /**
     * Stores the user's language preference in the session.
     *
     * @param string $lang Language code to store (e.g. 'sv_SE').
     * @return void
     */
    public static function setLanguage(string $lang): void
    {
        self::set('language', $lang);
    }

    /**
     * Updates the online status for the user.
     */
    public static function updateOnline()
    {

        $userid = self::get('userid');
        if ($userid !== false) {

            $db = new DatabaseConnection();
            $count = $db->get("as:text")->Row("SELECT * FROM `online` WHERE userid='%s'", $userid);

            if (isset($count)) {
                self::typeOnline($userid);
            } else {
                $db->insertRow("INSERT INTO `online` (`userid`,`time`, `typ`) VALUES ('%d',CURRENT_TIMESTAMP , '1')", $userid);
            }
        }
    }

    /**
     * Updates the user's online type.
     *
     * @param int $userid User ID.
     * @param int $type Online type.
     */
    public static function typeOnline($userid, $type = 1)
    {
        $db = new DatabaseConnection();
        $db->updateRow("UPDATE `online` SET `typ` =  '%d' ,`time` = '%s' WHERE userid='%d'", $type, date("Y-m-d H:i:s"), $userid);
    }

    /**
     * Removes the user's online status.
     */
    public static function removeOnline($SkipDeleteError = true)
    {
        $db = new DatabaseConnection();
        $userid = self::get('userid');

        if ($userid) {
            $db->SkipDeleteError = $SkipDeleteError;
            $db->delete("DELETE FROM `online` WHERE userid='%d'", $userid);
        }
    }
    /**
     * Retrieves information about all users who are online.
     *
     * @return array Information about online users.
     */
    public static function getAllOnline()
    {
        $db = new DatabaseConnection();
        return $db->get("as:text")->Rows("SELECT * FROM `online`");
    }
}