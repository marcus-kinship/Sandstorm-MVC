<?php
namespace Sys\DatabaseConnection;

/**
 * Class ArgumentEscaper
 *
 * Responsible for validating and escaping SQL query arguments
 * based on placeholder metadata produced by QueryTemplate.
 *
 * Supported placeholder types:
 *  - %d → Integer
 *  - %s → String
 *  - %i → SQL Identifier (table/column)
 *
 * This class ensures:
 *  - Strict validation of incoming data
 *  - Proper SQL escaping for strings via mysqli
 *  - Identifier validation to prevent SQL injection
 *  - Safe handling for vsprintf compatibility
 *
 * Single Responsibility:
 *  Handles argument validation and escaping only.
 *
 * @package Sys\DatabaseConnection
 */
class ArgumentEscaper
{
    /**
     * Active database connection (mysqli-compatible).
     *
     * @var object|null
     */
    private $db;

    /**
     * Type of query being processed (SELECT, INSERT, etc).
     * Used for contextual error reporting.
     *
     * @var string
     */
    private string $typeQuery;

    /**
     * ArgumentEscaper constructor.
     *
     * @param object|null $db        mysqli-compatible connection
     * @param string      $typeQuery Query type for context logging
     */
    public function __construct($db, string $typeQuery)
    {
        $this->db = $db;
        $this->typeQuery = $typeQuery;
    }

    /**
     * Escape all arguments based on placeholder definitions.
     *
     * Preserves positional array keys for vsprintf compatibility.
     *
     * @param array<int, mixed> $args Arguments indexed by placeholder position
     * @param array<int, array{
     *     index:int,
     *     type:string
     * }> $placeholderInfo Metadata describing each placeholder
     *
     * @return array<int, mixed> Escaped arguments with preserved keys
     *
     * @throws DatabaseException If validation fails
     */
    public function escape(array $args, array $placeholderInfo): array
    {
        $escapedArgs = [];

        foreach ($placeholderInfo as $info) {
            $argIndex = $info['index'];
            $arg = $args[$argIndex];
            $type = $info['type'];

            switch ($type) {
                case 'd':
                    $escapedArgs[$argIndex] = $this->escapeInteger($arg, $argIndex);
                    break;

                case 'i':
                    $escapedArgs[$argIndex] = $this->escapeIdentifier($arg);
                    break;

                case 's':
                    $escapedArgs[$argIndex] = $this->escapeString($arg, $argIndex);
                    break;
            }
        }

        return $escapedArgs;
    }

    /**
     * Escape and validate a string argument.
     *
     * - NULL values return literal SQL NULL
     * - Uses mysqli::real_escape_string
     * - Protects '%' for safe vsprintf usage
     *
     * @param mixed $arg   Raw input value
     * @param int   $index Placeholder index
     *
     * @return string Escaped SQL-safe string (without surrounding quotes)
     *
     * @throws DatabaseException If no valid DB connection exists
     */
    private function escapeString($arg, int $index): string
    {
        if (is_null($arg)) {
            return 'NULL';
        }

        if ($this->db && method_exists($this->db, 'real_escape_string')) {
            $escaped = $this->db->real_escape_string((string) $arg);

            // Protect % from vsprintf placeholder interpretation
            return str_replace('%', '%%', $escaped);
        }

        throw new DatabaseException(
            sprintf(
                _("No database connection available for string escaping. Attempted value: '%s'"),
                htmlspecialchars((string) $arg)
            ),
            [
                'typequery' => $this->typeQuery,
                'placeholder_index' => $index
            ]
        );
    }

    /**
     * Validate and normalize an integer argument.
     *
     * Accepts:
     *  - Native integers
     *  - Numeric strings up to 19 digits (optional leading minus)
     *
     * @param mixed $arg   Raw input value
     * @param int   $index Placeholder index
     *
     * @return int Validated integer
     *
     * @throws DatabaseException If value is not a valid integer
     */
    private function escapeInteger($arg, int $index): int
    {
        if (is_int($arg)) {
            return $arg;
        }

        if (is_string($arg) && preg_match('/^-?\d{1,19}$/', $arg)) {
            return (int) $arg;
        }

        throw new DatabaseException(
            sprintf(
                _("Invalid value for %%d – must be an integer (int or numeric string). Value: '%s'"),
                htmlspecialchars((string) $arg)
            ),
            [
                'typequery' => $this->typeQuery,
                'placeholder_index' => $index
            ]
        );
    }

    /**
     * Validate and escape a SQL identifier.
     *
     * Supports dot notation:
     *   table.column
     *
     * Security rules:
     *  - Removes backticks
     *  - Disallows dangerous characters (= + ; " ' ( ) \)
     *  - Each segment must start with a letter
     *  - Only letters, digits, underscores and spaces allowed
     *
     * @param string $identifier Raw identifier
     *
     * @return string Backtick-wrapped safe identifier
     *
     * @throws DatabaseException If identifier is invalid
     */
    public function escapeIdentifier(string $identifier): string
    {
        $clean = str_replace('`', '', $identifier);

        if (preg_match('/[=+;"\'()\\\\]/u', $clean)) {
            throw new DatabaseException(
                _("Invalid characters in SQL identifier. Dangerous characters (= + ; \" ' ( ) \\) are not allowed."),
                ['typequery' => $this->typeQuery]
            );
        }

        $parts = explode('.', $clean);

        foreach ($parts as $part) {
            $part = trim($part);

            if (empty($part)) {
                throw new DatabaseException(
                    _("Empty part in SQL identifier (likely double dot or leading/trailing dot)."),
                    ['typequery' => $this->typeQuery]
                );
            }

            if (!preg_match('/^[\p{L}][\p{L}0-9_ ]*$/u', $part)) {
                throw new DatabaseException(
                    sprintf(
                        _("Invalid SQL field name: '%s'. Must start with a letter and may only contain letters, digits, underscores and spaces."),
                        htmlspecialchars($part)
                    ),
                    ['typequery' => $this->typeQuery]
                );
            }
        }

        return '`' . implode('`.`', $parts) . '`';
    }
}