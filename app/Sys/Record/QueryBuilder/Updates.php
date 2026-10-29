<?php

namespace Sys\Record\QueryBuilder;

/**
 * Builds UPDATE queries for Record.
 *
 * @category _("Record Updates class")
 */
class Updates
{
    /**
     * UPDATE of the given columns, for the rows the WHERE matches.
     *
     * @param string $table     Table name
     * @param array  $columns   Column definitions (name => ['placeholder' => '%s'|'%d', ...])
     * @param array  $data      name => value (null is written as NULL)
     * @param string $where     WHERE clause with placeholders, without "WHERE"
     * @param array  $whereArgs Arguments for the WHERE placeholders
     * @return array [query with placeholders, arg1, arg2, ...] for DatabaseConnection
     */
    public static function row(string $table, array $columns, array $data, string $where, array $whereArgs = []): array
    {
        $set = [];
        $args = [];

        foreach ($columns as $name => $column) {
            if ($name === 'id' || !array_key_exists($name, $data)) {
                continue;
            }
            if ($data[$name] === null) {
                $set[] = "`$name` = NULL";
            } else {
                $set[] = "`$name` = " . $column['placeholder'];
                $args[] = $data[$name];
            }
        }

        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . $where;

        return array_merge([$sql], $args, $whereArgs);
    }
}
