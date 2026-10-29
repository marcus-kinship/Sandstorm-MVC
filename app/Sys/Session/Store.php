<?php

namespace Sys\Session;

/**
 * Class Store
 *
 * In-memory session store built on a snapshot model.
 *
 * On construction the entire $_SESSION superglobal is copied into an internal
 * cache. All subsequent reads operate on that cache exclusively — $_SESSION is
 * never read again after the snapshot. Writes are synced to both the cache and
 * $_SESSION immediately so data survives session_write_close().
 *
 * @category Session
 * @package  Sys\Session
 */
class Store
{
    /**
     * Whether {@see close()} has been called on this instance.
     *
     * Once true, all read/write operations will throw a RuntimeException.
     *
     * @var bool
     */
    private bool $closed = false;


    /**
     * In-memory snapshot of $_SESSION, loaded once at construction time.
     *
     * All reads and writes go through this array. $_SESSION is kept in sync
     * on writes but is never read again after {@see loadSnapshot()} runs.
     *
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * Starts the PHP session and loads the session snapshot into memory.
     *
     * @throws \RuntimeException If the PHP session fails to start.
     */
    public function __construct()
    {
        \Sys\Session\Core::start();
        $this->loadSnapshot();
    }

    /**
     * Copies the current $_SESSION superglobal into the local in-memory cache.
     *
     * Called once at construction. After this point $_SESSION is not read again —
     * all reads go through {@see $cache} exclusively.
     *
     * @return void
     */
    private function loadSnapshot(): void
    {
        $this->cache = $_SESSION ?? [];
    }

    /**
     * Stores a value in the session under the given key.
     *
     * Writes to both the local cache and $_SESSION so that the value is
     * persisted when session_write_close() is eventually called.
     *
     * @param string $key   The session key.
     * @param mixed  $value Any serializable value to store.
     *
     * @return void
     *
     * @throws \RuntimeException If the session has been closed.
     */
    public function set(string $key, mixed $value): void
    {
        $this->guard();

        $this->cache[$key] = $value;
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieves a value from the in-memory cache by key.
     *
     * Reads exclusively from the snapshot cache — $_SESSION is never accessed
     * after construction.
     *
     * @param string $key     The session key to look up.
     * @param mixed  $default Value to return when the key is absent. Defaults to null.
     *
     * @return mixed The stored value, or $default if the key does not exist.
     *
     * @throws \RuntimeException If the session has been closed.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->guard();

        return $this->cache[$key] ?? $default;
    }

    /**
     * Appends or inserts a value into a session array.
     *
     * If the key does not exist it is initialized as an empty array.
     * Pass $index to overwrite a specific position — omit it to append.
     *
     * @param string          $key   Session key for the array.
     * @param mixed           $value Value to append or insert.
     * @param int|string|null $index Optional index to overwrite. Null = append.
     *
     * @return void
     *
     * @throws \RuntimeException If the session has been closed.
     * @throws \RuntimeException If the existing value at $key is not an array.
     */
    public function add(string $key, mixed $value, mixed $index = null): void
    {
        $this->guard();

        $current = $this->cache[$key] ?? [];

        if (!is_array($current)) {
            throw new \RuntimeException("Session key '$key' is not an array.");
        }

        if ($index !== null) {
            $current[$index] = $value;
        } else {
            $current[] = $value;
        }

        $this->set($key, $current);
    }

    /**
     * Removes a key from both the in-memory cache and $_SESSION.
     *
     * @param string $key The session key to delete.
     *
     * @return void
     *
     * @throws \RuntimeException If the session has been closed.
     */
    public function remove(string $key): void
    {
        $this->guard();
        unset($this->cache[$key], $_SESSION[$key]);
    }

    /**
     * Returns all session data currently held in the in-memory cache.
     *
     * Returns a shallow copy of the internal cache — mutations to the
     * returned array do not affect the session store.
     *
     * @return array<string, mixed> The full session cache.
     */
    public function all(): array
    {
        $this->guard();
        return [...$this->cache];
    }

    /**
     * Checks whether a key exists in the in-memory cache.
     *
     * Uses array_key_exists so keys explicitly set to null are still found.
     *
     * @param string $key Session key to check.
     *
     * @return bool True if the key exists, false otherwise.
     *
     * @throws \RuntimeException If the session has been closed.
     */
    public function has(string $key): bool
    {
        $this->guard();
        return array_key_exists($key, $this->cache);
    }

    /**
     * Closes the session by flushing data and releasing the session lock.
     *
     * Calls session_write_close(), which writes any pending session data to
     * storage and frees the lock so other requests can proceed. After calling
     * this method, no further reads or writes may be performed on this store.
     *
     * Safe to call multiple times — subsequent calls are no-ops.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $this->closed = true;
    }

    /**
     * Guards against operations on a closed session store.
     *
     * Called at the top of every public read/write method. Throws immediately
     * if {@see close()} has already been called on this instance.
     *
     * @return void
     *
     * @throws \RuntimeException If the session store has been closed.
     */
    private function guard(): void
    {
        if ($this->closed) {
            throw new \RuntimeException('Session is closed.');
        }
    }
}