<?php

/**
 * Class MysqlSelect
 *
 * Handles SQL SELECT queries against the database.
 * Provides methods for fetching a single row or multiple rows,
 * with support for parameterized queries via placeholder escaping.
 *
 * @package Database
 */
class MysqlSelect
{
    /**
     * @var object $base The base driver object containing the database connection and configuration.
     */
    private $base;

    /**
     * MysqlSelect constructor.
     *
     * Initializes the select handler with a database driver object.
     * Optionally sets the result data type (e.g. "as:both", "as:assoc", "as:num").
     *
     * @param object $db       The database driver object providing connection and query methods.
     * @param string $dataType Optional. The fetch mode for query results. Defaults to an empty string
     *                         (uses the driver's existing setting). Accepted values depend on the driver
     *                         implementation (e.g. "as:assoc", "as:num", "as:both").
     */
    public function __construct($db, $dataType = "")
    {
        $this->base = $db;

        if ($dataType != "") {
            $this->base->db->type = $dataType;
        }
    }

    /**
     * Executes a SQL SELECT query and returns a single row.
     *
     * Accepts a SQL string with optional placeholder arguments (passed as additional
     * arguments to the method). The query is escaped and prepared before execution.
     * The driver is set to single-row mode before fetching.
     *
     * Example usage:
     * <code>
     *   $row = $select->row("SELECT * FROM users WHERE id = %s", $userId);
     * </code>
     *
     * @param string $sql     The SQL SELECT query, optionally containing placeholders.
     * @param mixed  ...$args Optional. Values to bind to query placeholders.
     *
     * @return array|null An associative (or typed, based on $dataType) array representing
     *                    the matched row, or null if no row was found.
     */
    public function row($sql)
    {
        $arg = func_get_args();

        if (is_array($sql[0])) {
            $sql = $sql[0];
        }

        $sql = $this->base->db->escapeString($arg, 'Row');

        $this->base->db->modesingle = true;

        $result = $this->base->db->queryAndFetch($sql);

        return $result;
    }

    /**
     * Executes a SQL SELECT query and returns all matching rows.
     *
     * Accepts a SQL string with optional placeholder arguments (passed as additional
     * arguments to the method). The query is escaped and prepared before execution.
     * The driver is set to multi-row mode before fetching.
     *
     * Example usage:
     * <code>
     *   $rows = $select->rows("SELECT * FROM users WHERE active = ?", 1);
     * </code>
     *
     * @param string $sql     The SQL SELECT query, optionally containing placeholders.
     * @param mixed  ...$args Optional. Values to bind to query placeholders.
     *
     * @return array An array of associative (or typed) arrays, each representing one
     *               matched row. Returns an empty array if no rows were found.
     */
    public function rows($sql)
    {
        $arg = func_get_args();

        $sql = $this->base->db->escapeString($arg, 'Rows');

        $this->base->db->modesingle = null;

        $result = $this->base->db->queryAndFetch($sql);

        return $result;
    }
}