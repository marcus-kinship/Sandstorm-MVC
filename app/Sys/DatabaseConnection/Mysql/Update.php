<?php

/**
 * Class MysqlUpdate
 *
 * Handles SQL UPDATE queries against the database.
 * Provides a method for executing parameterized UPDATE statements
 * and reporting whether any rows were affected.
 *
 * @package Database
 */
class MysqlUpdate
{
    /**
     * @var object $base The base driver object containing the database connection and configuration.
     */
    private $base;

    /**
     * MysqlUpdate constructor.
     *
     * Initializes the update handler with a database driver object.
     *
     * @param object $db The database driver object providing connection and query methods.
     */
    public function __construct($db)
    {
        $this->base = $db;
    }

    /**
     * Executes a SQL UPDATE query and returns whether any rows were affected.
     *
     * Accepts a SQL string with optional placeholder arguments (passed as additional
     * arguments to the method). The query is escaped and prepared via the driver's
     * escapeString method before execution.
     *
     * Returns true if one or more rows were updated, false if the query ran
     * successfully but matched no rows.
     *
     * Example usage:
     * <code>
     *   $updated = $update->row("UPDATE users SET name = %s WHERE id = %s", $name, $id);
     * </code>
     *
     * @param string $sql     The SQL UPDATE query, optionally containing placeholders.
     * @param mixed  ...$args Optional. Values to bind to query placeholders.
     *
     * @return bool True if one or more rows were affected by the update, false otherwise.
     *
     * @throws SystemException If a syntax error or other database error occurs during execution.
     */
    public function row($sql)
    {
        $sql = func_get_args();

        if (is_array($sql[0])) {
            $sql = $sql[0];
        }

        $query = $this->base->db->escapeString($sql, 'Update');

        $this->base->db->_query($query, true);

        return ($this->base->db->affected_rows > 0);
    }
}