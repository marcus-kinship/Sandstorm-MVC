<?php

namespace Sys\Record\SQL;

/**
 * Repairs a table after a failed Record write (devmode only, see Record).
 *
 * Reads MySQL's error message and changes the table to fit the data:
 * - Table '...' doesn't exist                 -> CREATE TABLE
 * - Unknown column 'x'                        -> ADD COLUMN x
 * - Data too long / Out of range / Data truncated /
 *   Incorrect ... value for column 'x'        -> MODIFY x to the type the data needs
 *
 * @category _("Record SQL Analyzer class")
 */
class Analyzer
{
    /**
     * Change the table so the failed write can work, if the error is one of the known kinds.
     *
     * @param \DatabaseConnection $db      Connection to run the repair on
     * @param string              $table   Table name
     * @param array               $columns Column definitions from Record::define()
     * @param string              $error   MySQL's error message
     * @return bool True when a repair was run
     */
    public static function repair(\DatabaseConnection $db, string $table, array $columns, string $error): bool
    {
        if (str_contains($error, "doesn't exist")) {
            $db->query(Scheme::createTable($table, $columns));
            return true;
        }

        if (preg_match("/Unknown column '(?:[^']*\.)?([^'.]+)'/", $error, $m) && isset($columns[$m[1]])) {
            $db->query(Scheme::addColumns($table, [$m[1] => $columns[$m[1]]]));
            return true;
        }

        if (preg_match("/(?:Data too long|Out of range value|Data truncated|Incorrect \w+ value).*?column '(?:[^']*\.)?([^'.]+)'/", $error, $m)
            && isset($columns[$m[1]])) {
            $db->query(Scheme::modifyColumn($table, $m[1], $columns[$m[1]]));
            return true;
        }

        return false;
    }
}
