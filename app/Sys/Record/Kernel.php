<?php

namespace Sys\Record;

/**
 * Base of the Record ORM: the table, the data and the database connection.
 *
 * Kept small, because every entry point loads it:
 * - \Record       reads and writes (FromTrait + ToTrait)
 * - Record\From   reads only (FromTrait)
 * - Record\To     writes only (ToTrait)
 *
 * Options (second constructor argument):
 * - 'pool' => 'default'   database pool (app/config/mysql/<pool>.db.php)
 *
 * @category _("Record Kernel class")
 */
abstract class Kernel
{
    /**
     * Table name. A model can set it as a default: class User extends \Record { protected $table = 'users'; }
     *
     * @var string|null
     */
    protected $table = null;

    /**
     * Options given to the constructor
     *
     * @var array<string, mixed>
     */
    protected array $options = [];

    /**
     * The values: one row (name => value), or the fetched rows after a read
     *
     * @var array
     */
    public $data = [];

    /**
     * True when the data came from (or went to) the database
     *
     * @var bool
     */
    protected bool $hasRunSQL = false;

    /**
     * Result type for assign(): 'as:obj' or 'as:text'
     *
     * @var string
     */
    protected string $outdata = 'as:obj';

    /**
     * Position for Iterator
     *
     * @var int
     */
    protected int $position = 0;

    /**
     * The connection, opened on first use
     *
     * @var \DatabaseConnection|null
     */
    private ?\DatabaseConnection $connection = null;

    /**
     * @param string $table   Table name ('' keeps the model's default)
     * @param array  $options See the class comment
     */
    public function __construct(string $table = "", array $options = [])
    {
        $this->options = $options;

        if ($table !== '') {
            $this->table = $table;
        }
    }

    /**
     * Fill the record with a row, for example from a query.
     *
     * @param array  $record name => value
     * @param string $mode   'as:obj' or 'as:text'
     * @return $this|null Null when the row is empty
     */
    public function assign(array $record, $mode = "as:obj")
    {
        if ($record === []) {
            return null;
        }

        $this->outdata = $mode;
        $this->hasRunSQL = true;
        $this->data = $record;

        return $this;
    }

    /**
     * A value of the row: $user->name. False when there is no such value.
     *
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return array_key_exists($name, $this->data) ? $this->data[$name] : false;
    }

    /**
     * The table name.
     *
     * @return string
     * @throws \SystemException When no table is set
     */
    protected function table(): string
    {
        if ($this->table === null || $this->table === '') {
            throw new \SystemException(_('Record needs a table name: new Record("table") or protected $table in the model.'));
        }

        return $this->table;
    }

    /**
     * The database connection for this record's pool (shared per request).
     *
     * @return \DatabaseConnection
     */
    protected function db(): \DatabaseConnection
    {
        return $this->connection ??= new \DatabaseConnection($this->options['pool'] ?? 'default');
    }

    /**
     * Quote a column or table name: name -> `name`, t.name -> `t`.`name`.
     *
     * @param string $name
     * @return string
     * @throws \InvalidArgumentException For anything but letters, digits, _ and one dot
     */
    protected static function quote(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $name)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid column or table name: %s'), $name));
        }

        return '`' . str_replace('.', '`.`', $name) . '`';
    }

    /**
     * Build a WHERE clause with placeholders from a list of conditions.
     *
     * <code>
     * ['name', '=', 'Ada']                              // `name` = 'Ada'
     * ['age', '>', 30, 'OR', 'name', 'LIKE', 'A%']      // AND between conditions unless 'OR' is given
     * ['id', 'IN', [1, 2, 3]]                           // `id` IN (1, 2, 3)
     * ['deleted', 'IS', null]                           // `deleted` IS NULL
     * ['name' => 'Ada', 'age' => 36]                    // equal, joined with AND
     * </code>
     *
     * @param array $conditions
     * @return array{0: string, 1: array} [clause without "WHERE", arguments]
     * @throws \InvalidArgumentException For an unknown operator or an invalid column name
     */
    protected static function buildWhere(array $conditions): array
    {
        $operators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT'];

        if ($conditions !== [] && !array_is_list($conditions)) {
            $list = [];
            foreach ($conditions as $column => $value) {
                array_push($list, $column, '=', $value);
            }
            $conditions = $list;
        }

        $sql = '';
        $args = [];
        $count = count($conditions);

        for ($i = 0; $i < $count;) {
            $column = (string) $conditions[$i];
            $operator = strtoupper(trim((string) ($conditions[$i + 1] ?? '')));
            $value = $conditions[$i + 2] ?? null;
            $i += 3;

            if (!in_array($operator, $operators, true)) {
                throw new \InvalidArgumentException(sprintf(_('Unknown operator in WHERE: %s'), $operator));
            }

            if (is_array($value)) {
                if ($value === []) {
                    $part = $operator === 'IN' ? '0 = 1' : '1 = 1';
                } else {
                    $part = self::quote($column) . " $operator (" . implode(', ', array_fill(0, count($value), '%s')) . ')';
                    array_push($args, ...array_values($value));
                }
            } elseif ($value === null) {
                $part = self::quote($column) . ($operator === '=' || $operator === 'IS' ? ' IS NULL' : ' IS NOT NULL');
            } else {
                $part = self::quote($column) . " $operator " . (is_int($value) ? '%d' : '%s');
                $args[] = $value;
            }

            $sql .= $part;

            if ($i < $count) {
                $glue = strtoupper((string) $conditions[$i]);
                if ($glue === 'AND' || $glue === 'OR') {
                    $i++;
                } else {
                    $glue = 'AND';
                }
                $sql .= " $glue ";
            }
        }

        return [$sql, $args];
    }

    /**
     * Whether automatic table repair is allowed (devmode only).
     *
     * @return bool
     */
    protected static function canRepair(): bool
    {
        return ($_SERVER['HTTP_devmode'] ?? '') === 'true';
    }
}
