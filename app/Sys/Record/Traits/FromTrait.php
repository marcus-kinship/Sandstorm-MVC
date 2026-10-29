<?php

namespace Sys\Record\Traits;

/**
 * Reading for Record: build a SELECT step by step and fetch the rows.
 *
 * <code>
 * $people = new Record\From('people');
 * $rows = $people->where(['age', '>', 30])->orderBy(['name' => 'ASC'])->limit(0, 10)->all();
 * $ada  = $people->searchInField('name', '=', 'Ada', 'single');
 * $last = $people->last();
 *
 * foreach (new Record\From('people') as $row) { ... }    // fetched on first use
 *
 * $page = (new Record\From('people'))->paging($_GET['page'] ?? 1, 20);
 * foreach ($page as $row) { ... }
 * echo $page->pagination->links();
 *
 * class Person extends Record { protected $table = 'people'; }
 * $rows = Person::all();                                   // static use from a model
 * </code>
 *
 * The table gets the alias t: write t.name when you join other tables.
 *
 * @category _("Record read trait")
 */
trait FromTrait
{
    /** @var string SELECT part, without "SELECT" */
    private string $qSelect = 't.*';

    /** @var string|null FROM part set with From(), without "FROM" */
    private ?string $qFrom = null;

    /** @var string[] JOIN parts */
    private array $qJoins = [];

    /** @var string WHERE part, without "WHERE" */
    private string $qWhere = '';

    /** @var array Arguments for the WHERE placeholders */
    private array $qWhereArgs = [];

    /** @var string GROUP BY part, without "GROUP BY" */
    private string $qGroupBy = '';

    /** @var string ORDER BY part, without "ORDER BY" */
    private string $qOrderBy = '';

    /** @var string LIMIT part, without "LIMIT" */
    private string $qLimit = '';

    /** @var string Result type for exec(): 'as:text', 'as:obj', ... */
    private string $typeGetData = 'as:text';

    /** @var bool True after paging() */
    private bool $paging = false;

    /**
     * Pagination after paging(): ->links(), ->prev(), ->next()
     *
     * @var \Sys\Record\Pagination|null
     */
    public $pagination = null;

    /**
     * Use your own FROM, for example several tables (trusted SQL, no user input).
     *
     * @param string $table Without "FROM"
     * @return $this
     * @throws \SystemException When empty
     */
    public function From(string $table = "")
    {
        if (trim($table) === '') {
            throw new \SystemException(_('From() needs one or more tables'));
        }

        $this->qFrom = $table;
        return $this;
    }

    /**
     * Columns to fetch (trusted SQL, no user input), default t.*.
     *
     * @param string $Select Without "SELECT", for example "t.id, t.name"
     * @return $this
     * @throws \SystemException When empty
     */
    public function Select(string $Select = "")
    {
        if (trim($Select) === '') {
            throw new \SystemException(_('Select() needs one or more columns'));
        }

        $this->qSelect = $Select;
        return $this;
    }

    /**
     * JOIN another table: t.<key1> = <table>.<key2>.
     *
     * @param string $table
     * @param string $key1 Column in this table
     * @param string $key2 Column in the other table
     * @return $this
     */
    public function addRelation($table, $key1, $key2)
    {
        $this->qJoins[] = 'JOIN ' . self::quote($table) . ' ON ' . self::quote('t.' . $key1) . ' = ' . self::quote($table . '.' . $key2);
        return $this;
    }

    /**
     * Only rows that match (see Kernel::buildWhere() for the format).
     *
     * @param array $where For example ['age', '>', 30, 'OR', 'name', '=', 'Ada']
     * @return $this
     */
    public function where(array $where)
    {
        [$this->qWhere, $this->qWhereArgs] = self::buildWhere($where);
        return $this;
    }

    /**
     * Sort the rows.
     *
     * @param array $orderBy column => 'ASC' or 'DESC'
     * @return $this
     */
    public function orderBy(array $orderBy)
    {
        $parts = [];
        foreach ($orderBy as $column => $direction) {
            $direction = strtoupper((string) $direction) === 'DESC' ? 'DESC' : 'ASC';
            $parts[] = self::quote((string) $column) . ' ' . $direction;
        }

        $this->qOrderBy = implode(', ', $parts);
        return $this;
    }

    /**
     * Group the rows.
     *
     * @param string $groupby One or more columns, separated by commas
     * @return $this
     */
    public function groupBy(string $groupby)
    {
        $this->qGroupBy = implode(', ', array_map(fn($column) => self::quote(trim($column)), explode(',', $groupby)));
        return $this;
    }

    /**
     * Fetch $max rows, starting at row $min.
     *
     * @param int $min First row (0 = the first)
     * @param int $max Number of rows
     * @return $this
     */
    public function limit(int $min, int $max)
    {
        $this->qLimit = max(0, $min) . ', ' . max(0, $max);
        return $this;
    }

    /**
     * Fetch one page; $this->pagination gives the links afterwards.
     *
     * @param int|array $currentPage Page number, or ['page' => 1, 'max' => 10, 'type' => 'as:text', 'params' => [...]]
     * @param int       $max         Rows per page
     * @param string    $type        Result type
     * @return $this
     */
    public function paging($currentPage = 1, int $max = 10, $type = "as:text")
    {
        $extraParams = [];

        if (is_array($currentPage)) {
            $options = $currentPage;
            $currentPage = $options['page'] ?? 1;
            $max = (int) ($options['max'] ?? $max);
            $type = $options['type'] ?? $type;
            $extraParams = $options['params'] ?? [];
        }

        $this->pagination = new \Sys\Record\Pagination($max);
        $this->pagination->extraParams($extraParams);
        $this->pagination->currentPage($currentPage);

        $this->qLimit = trim(str_ireplace('LIMIT', '', $this->pagination->limit()));
        $this->typeGetData = $type;
        $this->paging = true;

        return $this;
    }

    /**
     * Run the query built so far.
     *
     * @param mixed $default Returned when no rows match ('' = return [])
     * @return mixed The rows
     */
    public function exec($default = "")
    {
        $rows = $this->fetch($this->typeGetData);

        if ($this->paging && $this->pagination !== null) {
            $this->pagination->num_rows($this->countRows());
        }

        $this->data = $rows;
        $this->hasRunSQL = true;

        return ($rows === [] && $default !== '') ? $default : $rows;
    }

    /**
     * All rows that match.
     *
     * @param string $type Result type
     * @return mixed
     */
    public function all($type = "as:text")
    {
        return $this->fetch($type);
    }

    /**
     * The row with the highest id (that matches).
     *
     * @param string $type Result type
     * @return mixed The row, or null
     */
    public function last($type = "as:text")
    {
        $this->qOrderBy = '`t`.`id` DESC';
        $this->qLimit = '1';

        return $this->fetch($type, true);
    }

    /**
     * Rows where one column matches a value.
     *
     * @param string     $field    Column
     * @param string     $operator =, LIKE, >, ...
     * @param string|int $term     Value
     * @param string     $mode     'single' for one row, 'multiple' for all
     * @param string     $type     Result type
     * @return mixed
     */
    public function searchInField($field, $operator, $term, $mode = "multiple", $type = "as:text")
    {
        $this->where([$field, $operator, $term]);

        return $this->fetch($type, $mode === 'single');
    }

    /**
     * Rows that match the conditions (same format as where()).
     *
     * @param array  $kriterier Conditions
     * @param string $type      Result type
     * @return mixed
     */
    public function search(array $kriterier, $type = "as:text")
    {
        $this->where($kriterier);

        return $this->fetch($type);
    }

    /**
     * Static use from a model: Person::all(), Person::last(), Person::search([...]), ...
     *
     * @param string $method
     * @param array  $arguments
     * @return mixed
     * @throws \SystemException For other methods, or when the model has no table
     */
    public static function __callStatic($method, $arguments)
    {
        $allowed = ['all', 'last', 'search', 'searchInField', 'where', 'orderBy', 'limit', 'paging', 'delete'];

        if (!in_array($method, $allowed, true) || !method_exists(static::class, $method)) {
            throw new \SystemException(sprintf(_('%s::%s() cannot be called statically'), static::class, $method));
        }

        return (new static())->$method(...$arguments);
    }

    /**
     * Build and run the SELECT.
     *
     * @param string $type   Result type
     * @param bool   $single One row instead of all rows
     * @return mixed
     */
    private function fetch(string $type, bool $single = false)
    {
        $sql = 'SELECT ' . $this->qSelect . ' FROM ' . $this->fromPart()
            . ($this->qWhere !== '' ? ' WHERE ' . $this->qWhere : '')
            . ($this->qGroupBy !== '' ? ' GROUP BY ' . $this->qGroupBy : '')
            . ($this->qOrderBy !== '' ? ' ORDER BY ' . $this->qOrderBy : '')
            . ($this->qLimit !== '' ? ' LIMIT ' . $this->qLimit : '');

        $select = $this->db()->get($type);
        $result = $single
            ? $select->row($sql, ...$this->qWhereArgs)
            : $select->rows($sql, ...$this->qWhereArgs);

        $this->hasRunSQL = true;
        return $result;
    }

    /**
     * Number of rows that match, without LIMIT (for paging).
     *
     * @return int
     */
    private function countRows(): int
    {
        $sql = 'SELECT COUNT(*) AS `total` FROM ' . $this->fromPart()
            . ($this->qWhere !== '' ? ' WHERE ' . $this->qWhere : '');

        $row = $this->db()->get('as:text')->row($sql, ...$this->qWhereArgs);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * FROM part with joins.
     *
     * @return string
     */
    private function fromPart(): string
    {
        $from = $this->qFrom ?? self::quote($this->table()) . ' t';

        return $this->qJoins === [] ? $from : $from . ' ' . implode(' ', $this->qJoins);
    }
}
