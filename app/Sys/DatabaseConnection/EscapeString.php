<?php
namespace Sys\DatabaseConnection;

/**
 * EscapeString - Orchestrator for safe SQL query processing.
 *
 * Composes four single-responsibility components:
 * - {@see QueryCache}          LRU caching of compiled query templates
 * - {@see ProtectedPatterns}   Protects special SQL constructs from sprintf processing
 * - {@see QueryTemplate}       Compiles and analyzes query templates
 * - {@see ArgumentEscaper}     Validates and escapes query arguments
 *
 * PLACEHOLDER TYPES:
 * - `%s`  or `%N$s`  => String value (auto-wrapped in single quotes if bare)
 * - `%d`  or `%N$d`  => Integer value
 * - `%i`  or `%N$i`  => SQL identifier (escaped with backticks)
 *
 * USAGE EXAMPLES:
 * ```php
 * // Sequential placeholders
 * $escape = new EscapeString([
 *     'db'        => $db,
 *     'typeQuery' => 'Select',
 *     'query'     => ['SELECT * FROM users WHERE email = %s AND id = %d', 'user@example.com', 42]
 * ]);
 * $sql = $escape->get();
 *
 * // Positional placeholders
 * $escape = new EscapeString([
 *     'db'        => $db,
 *     'typeQuery' => 'Select',
 *     'query'     => ['SELECT %1$i, %2$s FROM %3$i', 'name', 'John', 'users']
 * ]);
 * $sql = $escape->get();
 * ```
 *
 * @category _("EscapeString class")
 * @package  Sys\DatabaseConnection
 */
class EscapeString
{
    /**
     * The raw query input.
     * Either a plain SQL string or a numeric array where index 0 is the
     * SQL template and subsequent indices are the argument values.
     *
     * @var string|array|null
     */
    private $query;

    /**
     * Query type label used for logging and error context.
     * Examples: 'Select', 'Insert', 'Update', 'Delete', 'Query'.
     *
     * @var string
     */
    public string $typeQuery;

    /**
     * The raw compiled query template before argument substitution.
     * Useful for debugging — shows the query structure without values.
     *
     * @var string
     */
    private string $forquery = '';

    /**
     * The fully processed and escaped SQL query string.
     * Populated after processQuery() completes successfully.
     *
     * @var string
     */
    private string $processedQuery = '';

    /**
     * Whether the query has been successfully processed and is ready for use.
     *
     * @var bool
     */
    private bool $isValid = false;

    /**
     * The raw argument values extracted from the query array.
     * Populated during processQuery() for external access if needed.
     *
     * @var array
     */
    public array $params = [];

    /**
     * Manages regex patterns that protect special SQL constructs
     * (e.g. FORMAT(), LIKE '%...%') from sprintf processing.
     *
     * @var ProtectedPatterns
     */
    private ProtectedPatterns $protectedPatterns;

    /**
     * Compiles SQL query templates into precompiled placeholder structures.
     * Results are cached via QueryCache for reuse across identical templates.
     *
     * @var QueryTemplate
     */
    private QueryTemplate $queryTemplate;

    /**
     * Validates and escapes query arguments based on their placeholder types.
     * Delegates string escaping to DbStringEscaper.
     *
     * @var ArgumentEscaper
     */
    private ArgumentEscaper $argumentEscaper;

    /**
     * The protected pattern replacements restored into the final query.
     * Stored for reference after processQuery() completes.
     *
     * @var array
     */
    private array $protectedReplacements = [];

    // ==================== CONSTRUCTOR ====================

    /**
     * Initializes EscapeString with a database connection, query type, and query input.
     *
     * @param array $params {
     *     @type mysqli|null        $db        Active mysqli database connection for string escaping.
     *     @type string             $typeQuery Query type label for error context. Default: 'Query'.
     *     @type string|array|null  $query     SQL string or [template, arg1, arg2, ...] array.
     * }
     *
     * @throws DatabaseException If query is an associative array instead of a numeric array.
     */
    public function __construct(array $params)
    {
        $db = $params['db'] ?? null;
        $this->typeQuery = $params['typeQuery'] ?? 'Query';

        $this->protectedPatterns = new ProtectedPatterns();
        $this->queryTemplate = new QueryTemplate($this->protectedPatterns);
        $this->argumentEscaper = new ArgumentEscaper($db, $this->typeQuery); 

        $query = $params['query'] ?? null;

        if (is_array($query) && !isset($query[0])) {
            throw new DatabaseException(
                _("Invalid query structure: use numeric array ['SELECT ...', arg1, arg2], not assoc-array."),
                [
                    'keys' => array_keys($query),
                    'typequery' => $this->typeQuery
                ]
            );
        }

        $this->query = $query;
    }

    // ==================== CACHE PROXY METHODS ====================

    /**
     * Enable or disable query template caching globally.
     * Disabling is useful during debugging to ensure templates are always recompiled.
     *
     * @param bool $enabled True to enable caching, false to disable.
     */
    public static function setCachingEnabled(bool $enabled): void
    {
        QueryCache::setEnabled($enabled);
    }

    /**
     * Set the maximum number of templates that can be stored in the cache.
     * When the limit is reached, the least recently used entry is evicted.
     *
     * @param int $size Maximum cache size. Minimum value is 10.
     */
    public static function setMaxCacheSize(int $size): void
    {
        QueryCache::setMaxSize($size);
    }

    /**
     * Clear all cached query templates and reset cache statistics.
     * Also called automatically when protected patterns are modified.
     */
    public static function clearCache(): void
    {
        QueryCache::clear();
    }

    /**
     * Get current cache usage statistics.
     *
     * @return array{
     *     hits: int,
     *     misses: int,
     *     evictions: int,
     *     hit_rate: float,
     *     cache_size: int,
     *     max_size: int
     * }
     */
    public static function getCacheStats(): array
    {
        return QueryCache::getStats();
    }

    // ==================== PATTERN PROXY METHODS ====================

    /**
     * Add or replace a protected regex pattern.
     *
     * Protected patterns are applied to the SQL template before sprintf processing.
     * They prevent special SQL constructs from being misinterpreted as printf directives.
     *
     * IMPORTANT: Patterns must match template structure only — not argument values.
     * They are argument-independent and safe to cache per template.
     *
     * @param string $key   Unique identifier for the pattern (e.g. 'like', 'format').
     * @param string $regex PCRE regex pattern to protect (e.g. '/(["\'`])%.*?%\1/').
     *
     * @example
     * // Protect FORMAT() from being processed by sprintf
     * $escape->setPattern('format', '/FORMAT\([^)]+\)/i');
     *
     * // Protect LIKE '%value%' wildcards
     * $escape->setPattern('like', '/(["\'`])%.*?%\1/');
     */
    public function setPattern(string $key, string $regex): void
    {
        $this->protectedPatterns->set($key, $regex);
    }

    /**
     * Remove a previously registered protected pattern by key.
     * Automatically clears the query template cache.
     *
     * @param string $key The pattern key to remove (e.g. 'like', 'format').
     */
    public function removePattern(string $key): void
    {
        $this->protectedPatterns->remove($key);
    }

    // ==================== QUERY PROCESSING ====================

    /**
     * Process and escape the SQL query.
     *
     * Handles two paths:
     * - FAST PATH: Plain string or single-element array with no arguments.
     *   Validates that no unresolved %d or %i placeholders remain.
     * - OPTIMIZED PATH: Array with template + arguments.
     *   Compiles the template (cached), builds positional argument map,
     *   escapes all arguments, and assembles the final query via vsprintf.
     *
     * @return bool True on success.
     *
     * @throws DatabaseException If the query type is invalid.
     * @throws DatabaseException If unresolved %d or %i placeholders are found without arguments.
     * @throws DatabaseException If a required argument is missing for a placeholder index.
     */
    private function processQuery(): bool
    {
        // FAST PATH: No array or no parameters
        if (!is_array($this->query) || count($this->query) < 2) {
            if (is_array($this->query)) {
                $raw = $this->query[0];
            } elseif (is_string($this->query)) {
                $raw = $this->query;
            } else {
                throw new DatabaseException(
                    _("Invalid query type: expected string or array"),
                    ['type' => gettype($this->query), 'typequery' => $this->typeQuery]
                );
            }

            // Only %d and %i trigger exception — bare %s is auto-wrapped in QueryTemplate
            if (preg_match('/%(?:\d+\$)?[di]/', $raw)) {
                throw new DatabaseException(
                    _("Query contains placeholders but no arguments were provided"),
                    ['sql' => $raw, 'typequery' => $this->typeQuery]
                );
            }

            $this->processedQuery = $raw;
            $this->isValid = true;
            return true;
        }

        $queryString = $this->query[0];
        $args = array_slice($this->query, 1);

        $this->params = $args;

        // Compile template — result is cached after first run
        $template = $this->queryTemplate->compile($queryString);

        // Build ordered args with correct positional mapping.
        // Guarantees all placeholder indices exist before escaping.
        $orderedArgs = [];
        foreach ($template['placeholders'] as $placeholder) {
            $i = $placeholder['index'];
            if (!array_key_exists($i, $args)) {
                throw new DatabaseException(
                    sprintf(_("Missing argument for placeholder index %d"), $i),
                    [
                        'sql' => $queryString,
                        'forsql' => $template['processed_query'],
                        'args' => $args,
                        'datatypes' => array_column($template['placeholders'], 'type'),
                        'type' => $this->typeQuery
                    ]
                );
            }
            $orderedArgs[$i] = $args[$i];
        }

        // Escape each argument based on its placeholder type
        $escapedArgs = $this->argumentEscaper->escape($orderedArgs, $template['placeholders']);

        // Build final query using precompiled template
        $this->forquery = $template['processed_query'];
        $this->processedQuery = $this->safe_vsprintf($template['processed_query'], $escapedArgs);

        // Restore double-percent placeholders (%%s%, %%d%, %%i%)
        if ($template['has_double_percent']) {
            $this->processedQuery = str_replace('__¤__', '%', $this->processedQuery);
        }

        // Restore protected pattern replacements (FORMAT(), LIKE, etc.)
        if ($template['has_protected']) {
            $this->protectedReplacements = $template['protected_replacements_template'];
            $this->processedQuery = $this->protectedPatterns->restore(
                $this->processedQuery,
                $this->protectedReplacements
            );
        }

        $this->isValid = true;
        return true;
    }

    /**
     * Safe vsprintf wrapper — compatible with PHP 7 and PHP 8.
     *
     * On PHP 8+, catches ValueError thrown by vsprintf on invalid format strings.
     * On PHP 7, uses a custom error handler to convert warnings to exceptions.
     *
     * @param string $format The printf-style format string.
     * @param array  $args   The arguments to substitute into the format string.
     *
     * @return string The formatted SQL string.
     *
     * @throws DatabaseException If vsprintf fails due to a format mismatch.
     */
    public function safe_vsprintf(string $format, array $args): string
    {
        if (version_compare(PHP_VERSION, '8.0.0') >= 0) {
            try {
                return vsprintf($format, $args);
            } catch (\ValueError $e) {
                throw new DatabaseException(
                    sprintf(
                        _("Error preparing SQL – could not validate printf format. Message: %s"),
                        $e->getMessage()
                    ),
                    [
                        'sql' => $format,
                        'args' => $args,
                        'typequery' => $this->typeQuery
                    ]
                );
            }
        }

        set_error_handler(function ($errno, $errstr) use ($format, $args) {
            throw new DatabaseException(
                sprintf(
                    _("Error preparing SQL – printf could not be used safely. Message: %s"),
                    $errstr
                ),
                [
                    'sql' => $format,
                    'args' => $args,
                    'typequery' => $this->typeQuery
                ]
            );
        });

        try {
            $result = vsprintf($format, $args);
        } finally {
            restore_error_handler();
        }

        return $result;
    }

    // ==================== PUBLIC GETTERS ====================

    /**
     * Get the fully processed and escaped SQL query string.
     * Triggers processQuery() on first call if not already processed.
     *
     * @return string The final escaped SQL query ready for execution.
     *
     * @throws DatabaseException If query processing fails.
     */
    public function getQuery(): string
    {
        if (!$this->isValid) {
            $this->processQuery();
        }
        return $this->processedQuery;
    }

    /**
     * Check whether the query has been successfully processed.
     * Triggers processQuery() on first call if not already processed.
     *
     * @return bool True if the query is valid and ready for use.
     *
     * @throws DatabaseException If query processing fails.
     */
    public function valid(): bool
    {
        if (!$this->isValid) {
            $this->processQuery();
        }
        return $this->isValid;
    }

    /**
     * Get the raw compiled query template before argument substitution.
     * Useful for debugging — shows the query structure without escaped values.
     *
     * @return string The precompiled query template string.
     */
    public function getForQuery(): string
    {
        return $this->forquery;
    }

    /**
     * Alias for getQuery().
     * Provided for convenience and backwards compatibility.
     *
     * @return string The final escaped SQL query ready for execution.
     *
     * @throws DatabaseException If query processing fails.
     */
    public function get(): string
    {
        return $this->getQuery();
    }
}