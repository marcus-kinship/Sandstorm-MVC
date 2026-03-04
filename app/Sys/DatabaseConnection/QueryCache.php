<?php
namespace Sys\DatabaseConnection;

/**
 * QueryCache - LRU cache for compiled query templates
 */
class QueryCache
{
    private static array $cache = [];
    private static int $maxSize = 1000;
    private static bool $enabled = true;
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'evictions' => 0
    ];

    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    public static function setMaxSize(int $size): void
    {
        self::$maxSize = max(10, $size);
    }

    public static function clear(): void
    {
        self::$cache = [];
        self::$stats = ['hits' => 0, 'misses' => 0, 'evictions' => 0];
    }

    public static function getStats(): array
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return [
            'hits' => self::$stats['hits'],
            'misses' => self::$stats['misses'],
            'evictions' => self::$stats['evictions'],
            'hit_rate' => $total > 0 ? round((self::$stats['hits'] / $total) * 100, 2) : 0,
            'cache_size' => count(self::$cache),
            'max_size' => self::$maxSize
        ];
    }

    public static function get(string $key): ?array
    {
        if (!self::$enabled || !isset(self::$cache[$key])) {
            self::$stats['misses']++;
            return null;
        }

        self::$stats['hits']++;
        self::$cache[$key]['last_used'] = microtime(true);
        return self::$cache[$key];
    }

    public static function set(string $key, array $data): void
    {
        if (!self::$enabled) {
            return;
        }

        if (count(self::$cache) >= self::$maxSize) {
            self::evictOldest();
        }

        $data['last_used'] = microtime(true);
        self::$cache[$key] = $data;
    }

    private static function evictOldest(): void
    {
        $oldestKey = null;
        $oldestTime = PHP_FLOAT_MAX;

        foreach (self::$cache as $key => $data) {
            if ($data['last_used'] < $oldestTime) {
                $oldestTime = $data['last_used'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset(self::$cache[$oldestKey]);
            self::$stats['evictions']++;
        }
    }
}