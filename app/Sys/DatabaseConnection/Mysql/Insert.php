<?php

/**
 * Class MysqlInsert
 *
 * Handles SQL INSERT queries against the database.
 * Provides a method for executing parameterized INSERT statements
 * and returning the auto-generated ID of the newly inserted row.
 *
 * @package Database
 */
class MysqlInsert
{
    /**
     * @var object $base The base driver object containing the database connection and configuration.
     */
    private $base;

    /**
     * MysqlInsert constructor.
     *
     * Initializes the insert handler with a database driver object.
     *
     * @param object $db The database driver object providing connection and query methods.
     */
    public function __construct($db)
    {
        $this->base = $db;
    }

    /**
     * Executes a SQL INSERT query and returns the auto-generated insert ID.
     *
     * Accepts a SQL string with optional placeholder arguments (passed as additional
     * arguments to the method). The query is escaped and prepared via the driver's
     * escapeString method before execution.
     *
     * If an error occurred during execution and error reporting is enabled on the
     * base driver ($base->getError is true), the method returns false instead of
     * an insert ID. The error message can be retrieved via $base->getErrorMessage().
     *
     * Example usage:
     * <code>
     *   $newId = $insert->row("INSERT INTO users (name, email) VALUES (%s, %s)", $name, $email);
     *   if ($newId === false) {
     *       $error = $base->getErrorMessage();
     *   }
     * </code>
     *
     * @param string $sql     The SQL INSERT query, optionally containing placeholders.
     * @param mixed  ...$args Optional. Values to bind to query placeholders.
     *
     * @return int|false The auto-generated primary key (insert_id) of the newly inserted row,
     *                   or false if an error occurred and $base->getError is enabled.
     *
     * @throws SystemException If a syntax error or unhandled database error occurs during execution
     *                         and error handling is not suppressed.
     */
    public function row($sql)
    {
        if (is_array($sql[0])) {
            $sql = $sql[0];
        }

        $query = $this->base->db->escapeString($sql, 'Insert');

        $this->base->db->_query($query, $this->base->getError);

        if ($this->base->getErrorMessage() && $this->base->getError) {
            return false;
        }

        $insertId = $this->base->db->database->insert_id;

        return $insertId;
    }
}