<?php

/**
 * Class MysqlDelete
 *
 * Handles SQL DELETE queries against the database.
 * Provides a method for executing parameterized DELETE statements,
 * with configurable error behaviour when no rows are affected.
 *
 * Whether a "no rows deleted" situation raises an exception or silently
 * passes is controlled by the {@see $base->SkipDeleteError} flag on the
 * base driver object.
 *
 * @package Database
 */
class MysqlDelete
{
    /**
     * @var object $base The base driver object containing the database connection and configuration.
     */
    private $base;

    /**
     * MysqlDelete constructor.
     *
     * Initializes the delete handler with a database driver object.
     *
     * @param object $db The database driver object providing connection and query methods.
     */
    public function __construct($db)
    {
        $this->base = $db;
    }

    /**
     * Executes a SQL DELETE query and returns whether any rows were deleted.
     *
     * Accepts a SQL string with optional placeholder arguments (passed as additional
     * arguments to the method). The query is escaped and prepared via the driver's
     * escapeString method before execution.
     *
     * Behaviour when no rows are affected is governed by the base driver's
     * SkipDeleteError flag:
     * - If SkipDeleteError is false (default), a {@see SystemException} is thrown.
     * - If SkipDeleteError is true, the method returns false silently.
     *
     * Example usage:
     * <code>
     *   $deleted = $delete->Row("DELETE FROM users WHERE id = %s", $userId);
     * </code>
     *
     * @param string|array $sql  The SQL DELETE query, optionally containing placeholders,
     *                           or an array of query arguments when called internally.
     * @param mixed        ...$args Optional. Values to bind to query placeholders.
     *
     * @return bool True if one or more rows were deleted, false if no rows were affected
     *              and SkipDeleteError is set to true on the base driver.
     *
     * @throws SystemException If the query execution fails due to a syntax error,
     *                         or if no rows were deleted and SkipDeleteError is false.
     */
    public function Row($sql)
    {
        $sqlArgs = func_get_args();

        if (is_array($sqlArgs[0])) {
            $sqlArgs = $sqlArgs[0];
        }

        $query = $this->base->db->escapeString($sqlArgs, 'Delete');

        $stmt = $this->base->db->_query($query, false);

        if ($stmt === false) {
            $debugQuery = is_array($query) ? $query['debug'] : $query;
            throw new SystemException(sprintf(
                _('Ett Syntaxfel i delete-frågan. Felmeddelande: %s. Hela frågan: %s'),
                $stmt->error . "\n",
                $debugQuery
            ));
        }

        if ($this->base->db->affected_rows === 0) {
            if (!$this->base->SkipDeleteError) {
                throw new SystemException(_('Posten finns inte längre eller har redan tagits bort.'));
            }
            // SkipDeleteError is true — silent fail, fall through to return false
        }

        return ($this->base->db->affected_rows > 0);
    }
}