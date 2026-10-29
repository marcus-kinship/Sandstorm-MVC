<?php

namespace Sys\Record\SQL;

/**
 * Builds the table SQL for Record (MySQL and MariaDB).
 *
 * Columns are the column definitions from Record::define():
 * name => ['sql' => 'VARCHAR(255)', 'placeholder' => '%s', 'rank' => int].
 * Every column except id may be NULL, so rows can leave columns out.
 *
 * @category _("Record Scheme class")
 */
class Scheme
{
    /**
     * The id column every Record table has
     */
    private const ID = '`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';

    /**
     * CREATE TABLE for the columns, with an id column.
     *
     * @param string $table
     * @param array  $columns
     * @return string
     */
    public static function createTable(string $table, array $columns): string
    {
        $parts = [self::ID];

        foreach ($columns as $name => $column) {
            if ($name !== 'id') {
                $parts[] = self::column($name, $column);
            }
        }

        return 'CREATE TABLE IF NOT EXISTS ' . self::name($table) . ' (' . implode(', ', $parts)
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    }

    /**
     * ALTER TABLE ... ADD COLUMN for each column.
     *
     * @param string $table
     * @param array  $columns
     * @return string
     */
    public static function addColumns(string $table, array $columns): string
    {
        $parts = [];
        foreach ($columns as $name => $column) {
            $parts[] = 'ADD COLUMN ' . self::column($name, $column);
        }

        return 'ALTER TABLE ' . self::name($table) . ' ' . implode(', ', $parts);
    }

    /**
     * ALTER TABLE ... MODIFY a column to a new type.
     *
     * @param string $table
     * @param string $name
     * @param array  $column
     * @return string
     */
    public static function modifyColumn(string $table, string $name, array $column): string
    {
        return 'ALTER TABLE ' . self::name($table) . ' MODIFY ' . self::column($name, $column);
    }

    /**
     * ALTER TABLE ... DROP COLUMN.
     *
     * @param string   $table
     * @param string[] $names
     * @return string
     */
    public static function dropColumns(string $table, array $names): string
    {
        $parts = array_map(fn($name) => 'DROP COLUMN ' . self::name($name), $names);

        return 'ALTER TABLE ' . self::name($table) . ' ' . implode(', ', $parts);
    }

    /**
     * DROP TABLE IF EXISTS.
     *
     * @param string $table
     * @return string
     */
    public static function dropTable(string $table): string
    {
        return 'DROP TABLE IF EXISTS ' . self::name($table);
    }

    /**
     * `name` TYPE NULL DEFAULT NULL
     *
     * @param string $name
     * @param array  $column
     * @return string
     */
    private static function column(string $name, array $column): string
    {
        if (!preg_match('/^[A-Z]+(\(\d+\))?$/', $column['sql'] ?? '')) {
            throw new \InvalidArgumentException(sprintf(_('Invalid column type for %s'), $name));
        }

        return self::name($name) . ' ' . $column['sql'] . ' NULL DEFAULT NULL';
    }

    /**
     * Quote a table or column name.
     *
     * @param string $name
     * @return string
     * @throws \InvalidArgumentException For anything but letters, digits and _
     */
    private static function name(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid column or table name: %s'), $name));
        }

        return '`' . $name . '`';
    }
}
