<?php

namespace Sys\Record\QueryBuilder;

/**
 * Builds INSERT queries for Record.
 *
 * @category _("Record Inserts class")
 */
class Inserts
{
    /**
     * INSERT of one or more rows.
     *
     * A value that is missing in a row, or null, is written as NULL.
     *
     * @param string $table   Table name
     * @param array  $columns Column definitions (name => ['placeholder' => '%s'|'%d', ...])
     * @param array  $rows    List of rows (name => value)
     * @return array [query with placeholders, arg1, arg2, ...] for DatabaseConnection
     */
    public static function rows(string $table, array $columns, array $rows): array
    {
        $names = array_keys($columns);
        $args = [];
        $values = [];

        foreach ($rows as $row) {
            $parts = [];
            foreach ($names as $name) {
                $value = $row[$name] ?? null;
                if ($value === null) {
                    $parts[] = 'NULL';
                } else {
                    $parts[] = $columns[$name]['placeholder'];
                    $args[] = $value;
                }
            }
            $values[] = '(' . implode(', ', $parts) . ')';
        }

        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $names) . '`) VALUES ' . implode(', ', $values);

        return array_merge([$sql], $args);
    }
}
