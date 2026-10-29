<?php

/**
 * SELECT for MySQL: one row or all rows.
 *
 * <code>
 * $user  = $db->get()->row('SELECT * FROM `users` WHERE id = %d', 1);
 * $users = $db->get('as:obj')->rows('SELECT * FROM `users`');
 * </code>
 *
 * @category _("MySQL Select class")
 */
class MysqlSelect
{
    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $base;

    /**
     * @param DatabaseConnection $db       The connection
     * @param string             $dataType 'as:text', 'as:number', 'as:both', 'as:obj' or '' (the pool's type)
     */
    public function __construct($db, $dataType = "")
    {
        $this->base = $db;

        if ($dataType !== '') {
            $this->base->db->type = $dataType;
        }
    }

    /**
     * Fetch one row.
     *
     * @param string|array $sql Query with placeholders, followed by its arguments
     *                          (or one array with both)
     * @return mixed The row, null when nothing matched, DataSet for 'as:obj'
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     */
    public function row($sql)
    {
        $args = is_array($sql) ? $sql : func_get_args();

        $query = $this->base->db->escapeString($args, 'Row');
        $this->base->db->modesingle = true;

        return $this->base->db->queryAndFetch($query);
    }

    /**
     * Fetch all rows.
     *
     * @param string|array $sql Query with placeholders, followed by its arguments
     *                          (or one array with both)
     * @return mixed The rows ([] when nothing matched), DataSet for 'as:obj'
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     */
    public function rows($sql)
    {
        $args = is_array($sql) ? $sql : func_get_args();

        $query = $this->base->db->escapeString($args, 'Rows');
        $this->base->db->modesingle = null;

        return $this->base->db->queryAndFetch($query);
    }
}
