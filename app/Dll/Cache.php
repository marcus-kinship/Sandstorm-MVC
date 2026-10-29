<?php

/**
 * Cache: keep data or HTML that is slow to make, and reuse it.
 *
 * Data:
 * <code>
 * $menu = Cache::remember('menu', 600, fn() => (new Record\From('pages'))->all());   // 10 minutes
 * Cache::set('stats', $stats, 3600);
 * $stats = Cache::get('stats', []);
 * Cache::forget('menu');
 * Cache::clear();
 * </code>
 *
 * A piece of a page (the older way, still works):
 * <code>
 * (new Cache('app/cache/footer.html'))->save(fn() => $this->embed('default/footer.php'));
 * </code>
 *
 * Data is stored in app/cache/ as a PHP file with var_export(), so opcache
 * keeps it in memory: reading it costs about as much as an include, no
 * unserialize(). Only arrays and scalars are stored that way; anything else
 * (objects) is serialized. Writes go to a temporary file first and are then
 * renamed, so another request never reads half a file.
 *
 * @category _("Caching class")
 */
class Cache
{
    /** @var string|null Folder for set()/remember() (null = app/cache) */
    public static ?string $folderPath = null;

    /** @var string Cached file (the older page-piece cache) */
    private string $filename;

    /** @var bool Show the cached file after writing it */
    private bool $include;

    /** @var int Seconds before the piece is made again (0 = keep until deleted) */
    private int $lifetime;

    /**
     * Cache a piece of a page in a file.
     *
     * @param string $filename Path in the project
     * @param bool   $include  Print the piece after save()
     * @param int    $lifetime Seconds before it is made again (0 = keep until the file is deleted)
     */
    public function __construct($filename, $include = true, int $lifetime = 0)
    {
        $this->filename = \Filesystem::resolve((string) $filename);
        $this->include = (bool) $include;
        $this->lifetime = max(0, $lifetime);
    }

    /**
     * Make the piece with $callback when it is missing (or old), then print it.
     *
     * The piece is printed as text, never run as PHP, so text from users in
     * it cannot become code.
     *
     * @param callable $callback Returns the HTML (or prints it)
     * @return string The piece
     */
    public function save(callable $callback)
    {
        $fresh = is_file($this->filename) && ($this->lifetime === 0 || filemtime($this->filename) > time() - $this->lifetime);

        if (!$fresh) {
            ob_start();
            $returned = $callback();
            $printed = (string) ob_get_clean();
            self::writeFile($this->filename, is_string($returned) ? $returned : $printed);
        }

        $content = (string) @file_get_contents($this->filename);
        if ($this->include) {
            echo $content;
        }

        return $content;
    }

    /**
     * Value for $key; when missing or expired, make it with $callback and keep it.
     *
     * @param string   $key
     * @param int      $ttl      Seconds to keep it (0 = until forgotten)
     * @param callable $callback Makes the value
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $entry = self::read($key);
        if ($entry !== null) {
            return $entry['value'];
        }

        $value = $callback();
        self::set($key, $value, $ttl);

        return $value;
    }

    /**
     * Value for $key, or $default when it is missing or expired.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $entry = self::read($key);
        return $entry === null ? $default : $entry['value'];
    }

    /**
     * Whether $key is stored and not expired.
     *
     * @param string $key
     * @return bool
     */
    public static function has(string $key): bool
    {
        return self::read($key) !== null;
    }

    /**
     * Store a value.
     *
     * @param string $key
     * @param mixed  $value
     * @param int    $ttl Seconds to keep it (0 = until forgotten)
     * @return void
     */
    public static function set(string $key, mixed $value, int $ttl = 0): void
    {
        $expires = $ttl > 0 ? time() + $ttl : 0;
        $plain = is_scalar($value) || $value === null || (is_array($value) && self::isPlain($value));

        $data = $plain
            ? ['expires' => $expires, 'value' => $value]
            : ['expires' => $expires, 'serialized' => serialize($value)];

        self::writeFile(self::path($key), "<?php\nreturn " . var_export($data, true) . ";\n");
    }

    /**
     * Remove $key.
     *
     * @param string $key
     * @return void
     */
    public static function forget(string $key): void
    {
        $file = self::path($key);
        if (is_file($file)) {
            @unlink($file);
            self::invalidate($file);
        }
    }

    /**
     * Remove everything stored with set() and remember().
     *
     * @return int Number of entries removed
     */
    public static function clear(): int
    {
        $count = 0;
        foreach (glob(self::folder() . '/*.cache.php') ?: [] as $file) {
            if (@unlink($file)) {
                self::invalidate($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Folder for the data cache (created when missing).
     *
     * @return string
     */
    public static function folder(): string
    {
        $folder = rtrim(self::$folderPath ?? dirname($_SERVER['HTTP_path_sys']) . '/cache', '/\\');
        if (!is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }

        return $folder;
    }

    /**
     * Read an entry, null when it is missing or expired.
     *
     * @return array{value: mixed}|null
     */
    private static function read(string $key): ?array
    {
        $file = self::path($key);
        if (!is_file($file)) {
            return null;
        }

        $data = @include $file;
        if (!is_array($data)) {
            return null;
        }
        if (($data['expires'] ?? 0) !== 0 && $data['expires'] < time()) {
            // Expired: remove it so old keys do not pile up
            if (@unlink($file)) {
                self::invalidate($file);
            }
            return null;
        }

        return ['value' => array_key_exists('serialized', $data) ? unserialize($data['serialized'], ['allowed_classes' => true]) : $data['value']];
    }

    /**
     * File for a key: a hash, so any key is safe as a file name.
     */
    private static function path(string $key): string
    {
        return self::folder() . '/' . hash('sha256', $key) . '.cache.php';
    }

    /**
     * Whether an array holds only arrays and scalars (safe and fast with var_export()).
     */
    private static function isPlain(array $array): bool
    {
        foreach ($array as $value) {
            if (is_array($value) ? !self::isPlain($value) : !(is_scalar($value) || $value === null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Write a file atomically (temporary file, then rename).
     *
     * @throws \SystemException When it cannot be written
     */
    private static function writeFile(string $file, string $content): void
    {
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0775, true) && !is_dir(dirname($file))) {
            throw new \SystemException(sprintf(_('Could not create the folder %s'), dirname($file)));
        }

        $temp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $content) === false || !rename($temp, $file)) {
            @unlink($temp);
            throw new \SystemException(sprintf(_('Could not write the cache %s'), $file));
        }
        self::invalidate($file);
    }

    /**
     * Tell opcache the file changed.
     */
    private static function invalidate(string $file): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }
}
