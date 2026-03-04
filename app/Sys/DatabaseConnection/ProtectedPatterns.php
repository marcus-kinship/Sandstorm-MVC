<?php
namespace Sys\DatabaseConnection;

/**
 * ProtectedPatterns - Manages regex patterns that protect special SQL constructs
 * from being processed by sprintf (e.g. FORMAT(), LIKE '%...%').
 *
 * IMPORTANT: Patterns must match template structure only — not argument values.
 * They are applied before sprintf and are safe to cache per template.
 */
class ProtectedPatterns
{
    private array $patterns;

    public function __construct()
    {
        // Default patterns
        $this->patterns = [
            'format' => '/FORMAT\(([^)]*)\)/i',
            'like' => '/(["\'`])%.*?%\1/',
        ];
    }

    public function set(string $key, string $regex): void
    {
        $this->patterns[$key] = $regex;
        QueryCache::clear();
    }

    public function remove(string $key): void
    {
        unset($this->patterns[$key]);
        QueryCache::clear();
    }

    public function getAll(): array
    {
        return $this->patterns;
    }

    /**
     * Apply all patterns to the query string, replacing matches with
     * placeholder tokens. Returns modified query and replacement map.
     *
     * @param string $queryString
     * @return array{query: string, replacements: array}
     */
    public function apply(string $queryString): array
    {
        $replacements = [];

        foreach ($this->patterns as $pattern) {
            $queryString = preg_replace_callback(
                $pattern,
                function ($matches) use (&$replacements) {
                    $key = '__REPLACE' . count($replacements) . '__';
                    $replacements[$key] = $matches[0];
                    return $key;
                },
                $queryString
            );
        }

        return [
            'query' => $queryString,
            'replacements' => $replacements
        ];
    }

    /**
     * Restore protected replacements back into the query string.
     *
     * @param string $queryString
     * @param array  $replacements
     * @return string
     */
    public function restore(string $queryString, array $replacements): string
    {
        return strtr($queryString, $replacements);
    }

    /**
     * Generate a serialized fingerprint of current patterns (for cache keys).
     */
    public function fingerprint(): string
    {
        return serialize($this->patterns);
    }
}