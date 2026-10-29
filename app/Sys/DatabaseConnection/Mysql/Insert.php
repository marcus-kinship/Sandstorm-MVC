<?php

/**
 * INSERT for MySQL.
 *
 * @category _("MySQL Insert class")
 */
class MysqlInsert
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
     * Insert a row.
     *
     * @param array $sql [query with placeholders, arg1, ...] (or [[query, arg1, ...]])
     * @return int|string|false The new id; false when it failed and the
     *                          connection's getError is true
     * @throws Sys\DatabaseConnection\DatabaseException If it fails and getError is false
     */
    public function row($sql)
    {
        if (is_array($sql[0] ?? null)) {
            $sql = $sql[0];
        }

        $query = $this->base->db->escapeString($sql, 'Insert');

        if ($this->base->db->_query($query, !$this->base->getError) === false) {
            return false;
        }

        return $this->base->db->mysqli->insert_id;
    }
}
