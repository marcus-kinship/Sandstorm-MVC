<?php

/**
 * DELETE for MySQL.
 *
 * @category _("MySQL Delete class")
 */
class MysqlDelete
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
     * Delete rows.
     *
     * @param array $sql [query with placeholders, arg1, ...] (or [[query, arg1, ...]])
     * @return bool True when at least one row was deleted
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     * @throws SystemException If no row was deleted and SkipDeleteError is false
     */
    public function row($sql)
    {
        $args = is_array($sql) ? $sql : func_get_args();
        if (is_array($args[0] ?? null)) {
            $args = $args[0];
        }

        $query = $this->base->db->escapeString($args, 'Delete');
        $this->base->db->_query($query, true);

        if ($this->base->db->affected_rows === 0 && !$this->base->SkipDeleteError) {
            throw new SystemException(_('The row no longer exists or has already been deleted.'));
        }

        return $this->base->db->affected_rows > 0;
    }
}
