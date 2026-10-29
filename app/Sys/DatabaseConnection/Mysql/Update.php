<?php

/**
 * UPDATE for MySQL.
 *
 * @category _("MySQL Update class")
 */
class MysqlUpdate
{
    /**
     * @var DatabaseConnection
     */
    private DatabaseConnection $base;

    /**
     * @param DatabaseConnection $db The connection
     */
    public function __construct($db)
    {
        $this->base = $db;
    }

    /**
     * Update rows.
     *
     * @param string|array $sql Query with placeholders, followed by its arguments
     *                          (or one array with both)
     * @return bool True when at least one row changed
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     */
    public function row($sql)
    {
        $args = is_array($sql) ? $sql : func_get_args();

        $query = $this->base->db->escapeString($args, 'Update');
        $this->base->db->_query($query, true);

        return $this->base->db->affected_rows > 0;
    }
}
