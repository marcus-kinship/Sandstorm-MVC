<?php

namespace Sys\Record\Traits;

use Sys\DatabaseConnection\DatabaseException;
use Sys\Record\QueryBuilder\Inserts;
use Sys\Record\QueryBuilder\Updates;
use Sys\Record\SQL\Analyzer;
use Sys\Record\SQL\Scheme;

/**
 * Writing for Record: save, delete and table changes.
 *
 * <code>
 * $r = new Record('people');
 * $id = $r->define('name', 'Ada')->define('age', 36)->save();    // INSERT, returns the id
 * $r->define('age', 37)->save($id);                              // UPDATE row $id (INSERT if it is gone)
 * $r->define('age', 38)->save(['name', '=', 'Ada']);             // UPDATE where name = 'Ada'
 * $r->delete($id);                                               // DELETE row $id
 * $r->delete(['age', '<', 18]);                                  // DELETE where age < 18
 * </code>
 *
 * In devmode a failed write repairs the table (creates it, adds a missing
 * column, makes a column larger) and tries again. Outside devmode the error
 * is thrown, so a production database is never changed by a request.
 *
 * @category _("Record write trait")
 */
trait ToTrait
{
    use DefineTrait;

    /**
     * Called after a repair, with MySQL's error message
     *
     * @var callable|null
     */
    private $repairAfterSQLRun = null;

    /**
     * Run $function after each automatic repair (for example to log it).
     *
     * @param callable $function Gets MySQL's error message
     * @return $this
     */
    public function repairAfterSQLRun($function)
    {
        $this->repairAfterSQLRun = $function;
        return $this;
    }

    /**
     * Save the defined values.
     *
     * - save()              INSERT; returns the new id (the first id with multi())
     * - save(5)             UPDATE row 5, or INSERT it with id 5 when it does not exist; returns 5
     * - save([conditions])  UPDATE the rows that match (see buildWhere()); returns true
     *
     * Only the values set with define()/set() are written.
     *
     * @param int|string|array|null $id
     * @return int|string|bool
     * @throws \SystemException When nothing is defined
     * @throws DatabaseException When the write fails (and cannot be repaired)
     */
    public function save($id = null)
    {
        if ($this->properties === []) {
            throw new \SystemException(_('Nothing to save: use define() or set() first.'));
        }

        $table = $this->table();
        $this->hasRunSQL = true;

        if ($id === null) {
            $rows = $this->multi ? $this->rows : [$this->values()];
            $newId = $this->write(Inserts::rows($table, $this->properties, $rows), 'Insert');

            if (!$this->multi) {
                $this->data['id'] = $newId;
            }
            return $newId;
        }

        if (is_array($id)) {
            if ($this->multi) {
                throw new \SystemException(_('save() with conditions works on one row; save several rows one at a time.'));
            }
            [$where, $args] = self::buildWhere($id);
            $this->write(Updates::row($table, $this->properties, $this->values(), $where, $args), 'Update');
            return true;
        }

        if (!is_numeric($id)) {
            throw new \SystemException(sprintf(_('save() needs an id, conditions or nothing, not %s'), get_debug_type($id)));
        }

        $values = $this->values();
        $matched = 0;

        if (count($values) > 0 && array_diff(array_keys($values), ['id']) !== []) {
            $this->write(Updates::row($table, $this->properties, $values, '`id` = %d', [(int) $id]), 'Update');
            $matched = $this->matchedRows();
        }

        if ($matched === 0) {
            $columns = ['id' => ['sql' => 'BIGINT', 'placeholder' => '%d', 'rank' => 3]] + $this->properties;
            $this->write(Inserts::rows($table, $columns, [['id' => (int) $id] + $values]), 'Insert');
        }

        $this->data['id'] = $id;
        return $id;
    }

    /**
     * Create the table for the defined columns, if it does not exist.
     *
     * @return bool
     * @throws DatabaseException If MySQL refuses
     */
    public function createTableIfNotExists()
    {
        $this->db()->query(Scheme::createTable($this->table(), $this->properties));
        return true;
    }

    /**
     * Set a value, optionally through a callback first.
     *
     * @param string        $name
     * @param mixed         $value
     * @param callable|null $callback Gets the value, returns the value to store
     * @return $this
     */
    public function set($name, $value, $callback = null)
    {
        if (is_callable($callback)) {
            $value = $callback($value);
        }

        return $this->define($name, $value);
    }

    /**
     * Read a defined value, optionally through a callback.
     *
     * - get('name')                  value of the row
     * - get('name', $callback)       value through the callback (stored back)
     * - get('name', 1)               multi(): value of row 1
     * - get('name', [0, 1])          multi(): [0 => ['name' => ...], 1 => [...]]
     *
     * @param string                $name
     * @param int|array|callable|'' $ids
     * @param callable|null         $callback
     * @return mixed
     * @throws \SystemException When the name (or row) is not there
     */
    public function get($name = "", $ids = "", $callback = null)
    {
        if (!$this->multi) {
            if (!array_key_exists($name, $this->data)) {
                throw new \SystemException(sprintf(_('No value with the name %s'), $name));
            }
            if (is_callable($ids)) {
                $this->data[$name] = $ids($this->data[$name]);
            }
            return $this->data[$name];
        }

        if ($ids === '' || $ids === null) {
            throw new \SystemException(_('multi(): give the row number(s) to get().'));
        }

        $result = [];
        foreach ((array) $ids as $row) {
            if (!isset($this->rows[$row]) || !array_key_exists($name, $this->rows[$row])) {
                throw new \SystemException(sprintf(_('No value %s in row %s'), $name, $row));
            }
            if (is_callable($callback)) {
                $this->rows[$row][$name] = $callback($this->rows[$row][$name]);
            }
            $result[$row][$name] = $this->rows[$row][$name];
        }

        return is_array($ids) ? $result : $result[$ids][$name];
    }

    /**
     * Delete rows.
     *
     * @param int|array|string $where Id, conditions (see buildWhere()), or an SQL
     *                                condition you trust (never with user input)
     * @return bool True when a row was deleted
     * @throws \SystemException When no condition is given
     * @throws DatabaseException If the query fails
     */
    public function delete($where)
    {
        $table = self::quote($this->table());

        if (is_int($where) || (is_string($where) && ctype_digit($where))) {
            return $this->db()->delete("DELETE FROM $table WHERE `id` = %d", (int) $where);
        }

        if (is_array($where) && $where !== []) {
            [$sql, $args] = self::buildWhere($where);
            return $this->db()->delete("DELETE FROM $table WHERE $sql", ...$args);
        }

        if (is_string($where) && trim($where) !== '') {
            return $this->db()->delete("DELETE FROM $table WHERE $where");
        }

        throw new \SystemException(_('delete() needs an id or conditions, for example delete(5) or delete(["id", "=", 5]).'));
    }

    /**
     * Drop the table.
     *
     * @return bool
     */
    public function removeTable()
    {
        $this->db()->query(Scheme::dropTable($this->table()));
        return true;
    }

    /**
     * Drop one or more columns.
     *
     * @param string|string[] $columns
     * @return bool
     */
    public function removeColumns($columns)
    {
        $this->db()->query(Scheme::dropColumns($this->table(), (array) $columns));
        return true;
    }

    /**
     * The values of the defined columns (one row).
     *
     * @return array
     */
    private function values(): array
    {
        return array_intersect_key($this->data, $this->properties);
    }

    /**
     * Rows the last UPDATE matched (also those that did not change).
     *
     * @return int
     */
    private function matchedRows(): int
    {
        $info = $this->db()->db->mysqli->info ?? '';

        return preg_match('/Rows matched: (\d+)/', (string) $info, $m) ? (int) $m[1] : $this->db()->affectedRows();
    }

    /**
     * Run a write; in devmode repair the table and try again when it fails.
     *
     * @param array  $query [query with placeholders, args...]
     * @param string $type  'Insert' or 'Update'
     * @return int|string|bool The new id for Insert, true for Update
     * @throws DatabaseException When it fails and cannot be repaired
     */
    private function write(array $query, string $type)
    {
        $db = $this->db();
        $driver = $db->db;
        $sql = $driver->escapeString($query, $type);
        $attempts = count($this->properties) + 2;

        for ($i = 0; ; $i++) {
            if ($driver->_query($sql, false) !== false) {
                return $type === 'Insert' ? $driver->mysqli->insert_id : true;
            }

            $error = $driver->getErrorMessage();

            if (!self::canRepair() || $i >= $attempts || !Analyzer::repair($db, $this->table(), $this->properties, $error)) {
                throw new DatabaseException(sprintf(_('Could not save to %s: %s'), $this->table(), $error), $driver->debugSQL[0] ?? []);
            }

            if (is_callable($this->repairAfterSQLRun)) {
                ($this->repairAfterSQLRun)($error);
            }
        }
    }
}
