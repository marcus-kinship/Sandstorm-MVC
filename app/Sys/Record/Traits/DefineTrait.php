<?php

namespace Sys\Record\Traits;

/**
 * define(): set a value and work out the column type from it.
 *
 * Only the writing classes (\Record, Record\To) use this, so a read-only
 * Record\From does not load it.
 *
 * <code>
 * $r = new Record('people');
 * $r->define('name', 'Ada');          // VARCHAR(255)
 * $r->define('age', 36);              // INT
 * $r->text(20)->cut()->define('nick', $nick);   // VARCHAR(20), longer values are cut
 * $r->text(8)->min()->define('code', $code);    // throws when shorter than 8
 * $r->int(3)->max()->define('rank', $rank);     // throws when longer than 3 digits
 *
 * $r->multi();                        // several rows: each repeated name starts a new row
 * $r->define('name', 'Ada')->define('age', 36);
 * $r->define('name', 'Bo')->define('age', 41);
 * </code>
 *
 * @category _("Record define trait")
 */
trait DefineTrait
{
    /**
     * Column definitions: name => ['sql' => 'VARCHAR(255)', 'placeholder' => '%s', 'rank' => int]
     *
     * @var array<string, array{sql: string, placeholder: string, rank: int}>
     */
    public $properties = [];

    /**
     * Several rows (multi mode): list of name => value
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $rows = [];

    /**
     * True: define() collects several rows
     *
     * @var bool
     */
    public $multi = false;

    /**
     * Type and length for the next define(), set by text() and int()
     *
     * @var array{type: string, length: int}|null
     */
    private ?array $nextType = null;

    /**
     * Length rule for the next define(): 'max', 'min' or 'cut'
     *
     * @var string
     */
    private string $nextMode = '';

    /**
     * Column types from small to large; a column only grows
     *
     * @var array<string, int>
     */
    private const RANK = [
        'TINYINT(1)' => 1, 'INT' => 2, 'BIGINT' => 3, 'DOUBLE' => 4,
        'DATE' => 5, 'TIME' => 5, 'DATETIME' => 6,
        'VARCHAR' => 7, 'TEXT' => 8, 'MEDIUMTEXT' => 9, 'LONGTEXT' => 10,
    ];

    /**
     * Collect several rows: a name that is already in the current row starts a new row.
     *
     * @param bool $value
     * @return $this
     */
    public function multi($value = true)
    {
        $this->multi = (bool) $value;
        return $this;
    }

    /**
     * The next define() is text with this length (VARCHAR up to 255, TEXT above).
     *
     * @param int $length
     * @return $this
     */
    public function text($length)
    {
        $this->nextType = ['type' => 'text', 'length' => (int) $length];
        return $this;
    }

    /**
     * The next define() is an integer with this many digits.
     *
     * @param int $length
     * @return $this
     */
    public function int($length)
    {
        $this->nextType = ['type' => 'int', 'length' => (int) $length];
        return $this;
    }

    /**
     * The next define() throws when the value is longer than text()/int() says.
     *
     * @return $this
     */
    public function max()
    {
        $this->nextMode = 'max';
        return $this;
    }

    /**
     * The next define() throws when the value is shorter than text()/int() says.
     *
     * @return $this
     */
    public function min()
    {
        $this->nextMode = 'min';
        return $this;
    }

    /**
     * The next define() cuts the value to the length text()/int() says.
     *
     * @return $this
     */
    public function cut()
    {
        $this->nextMode = 'cut';
        return $this;
    }

    /**
     * Set the column definitions yourself.
     *
     * @param array $properties name => ['sql' => ..., 'placeholder' => ..., 'rank' => ...]
     * @return $this
     */
    public function properties(array $properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * Set a value and work out its column type.
     *
     * Arrays and objects are stored as JSON. A column only grows: a value
     * that needs a larger type (longer text, a decimal in an int column)
     * changes the type for the whole column.
     *
     * @param string $name Column name
     * @param mixed  $data The value (null for NULL)
     * @return $this
     * @throws \SystemException When max() or min() is broken, or an array is cut
     * @throws \InvalidArgumentException For an invalid column name
     */
    public function define($name, $data = null)
    {
        self::quote($name);

        $length = $this->nextType['length'] ?? 0;
        $mode = $this->nextMode;
        $this->nextMode = '';

        if (is_array($data) || is_object($data)) {
            if ($mode === 'cut') {
                $this->nextType = null;
                throw new \SystemException(_('An array or object cannot be cut'));
            }
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        } elseif (is_bool($data)) {
            $data = (int) $data;
        }

        if ($data !== null && $length > 0) {
            $size = mb_strlen((string) $data);
            if ($mode === 'max' && $size > $length) {
                $this->nextType = null;
                throw new \SystemException(sprintf(_('%s is longer than the allowed %d characters'), $name, $length));
            }
            if ($mode === 'min' && $size < $length) {
                $this->nextType = null;
                throw new \SystemException(sprintf(_('%s is shorter than the required %d characters'), $name, $length));
            }
            if ($mode === 'cut' && $size > $length) {
                $data = mb_substr((string) $data, 0, $length);
            }
        }

        $column = $this->columnFor($data, $this->nextType);
        $this->nextType = null;

        $this->properties[$name] = isset($this->properties[$name])
            ? $this->widen($this->properties[$name], $column)
            : $column;

        if ($this->multi) {
            $last = array_key_last($this->rows);
            if ($last === null || array_key_exists($name, $this->rows[$last])) {
                $this->rows[] = [];
                $last = array_key_last($this->rows);
            }
            $this->rows[$last][$name] = $data;
        } else {
            $this->data[$name] = $data;
        }

        return $this;
    }

    /**
     * Column definition for a value.
     *
     * @param mixed      $value
     * @param array|null $forced Type from text()/int()
     * @return array{sql: string, placeholder: string, rank: int}
     */
    private function columnFor(mixed $value, ?array $forced): array
    {
        if ($forced !== null) {
            if ($forced['type'] === 'int') {
                $sql = $forced['length'] > 9 ? 'BIGINT' : 'INT';
                return ['sql' => $sql, 'placeholder' => '%d', 'rank' => self::RANK[$sql]];
            }
            return $this->textColumn($forced['length']);
        }

        if ($value === null) {
            return $this->textColumn(255);
        }

        if (is_int($value) || (is_string($value) && preg_match('/^-?\d{1,18}$/', $value) && ($value === '0' || $value[0] !== '0'))) {
            $number = (int) $value;
            if ($number >= 0 && $number <= 1 && is_int($value)) {
                return ['sql' => 'TINYINT(1)', 'placeholder' => '%d', 'rank' => self::RANK['TINYINT(1)']];
            }
            $sql = abs($number) > 2147483647 ? 'BIGINT' : 'INT';
            return ['sql' => $sql, 'placeholder' => '%d', 'rank' => self::RANK[$sql]];
        }

        if (is_float($value) || (is_string($value) && preg_match('/^-?\d+\.\d+$/', $value))) {
            return ['sql' => 'DOUBLE', 'placeholder' => '%s', 'rank' => self::RANK['DOUBLE']];
        }

        $value = (string) $value;

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return ['sql' => 'DATETIME', 'placeholder' => '%s', 'rank' => self::RANK['DATETIME']];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return ['sql' => 'DATE', 'placeholder' => '%s', 'rank' => self::RANK['DATE']];
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return ['sql' => 'TIME', 'placeholder' => '%s', 'rank' => self::RANK['TIME']];
        }

        return $this->textColumn(strlen($value));
    }

    /**
     * The column type that fits both the old and the new value.
     *
     * Numbers grow (TINYINT -> INT -> BIGINT -> DOUBLE), text grows
     * (VARCHAR -> TEXT -> ...); numbers mixed with dates, or different
     * date types, become text.
     *
     * @param array $old
     * @param array $new
     * @return array{sql: string, placeholder: string, rank: int}
     */
    private function widen(array $old, array $new): array
    {
        if ($old['sql'] === $new['sql']) {
            return $old;
        }

        $group = fn(array $c) => $c['rank'] <= 4 ? 'number' : ($c['rank'] <= 6 ? 'date' : 'text');

        if ($group($old) === $group($new) && $group($old) !== 'date') {
            return $new['rank'] > $old['rank'] ? $new : $old;
        }

        // Different kinds: text that is at least as large as both
        $text = $this->textColumn(255);
        foreach ([$old, $new] as $column) {
            if ($column['rank'] > $text['rank']) {
                $text = $column;
            }
        }

        return $text;
    }

    /**
     * Text column large enough for $bytes bytes.
     *
     * @param int $bytes
     * @return array{sql: string, placeholder: string, rank: int}
     */
    private function textColumn(int $bytes): array
    {
        if ($bytes <= 255) {
            return ['sql' => 'VARCHAR(255)', 'placeholder' => '%s', 'rank' => self::RANK['VARCHAR']];
        }
        if ($bytes <= 65535) {
            return ['sql' => 'TEXT', 'placeholder' => '%s', 'rank' => self::RANK['TEXT']];
        }
        if ($bytes <= 16777215) {
            return ['sql' => 'MEDIUMTEXT', 'placeholder' => '%s', 'rank' => self::RANK['MEDIUMTEXT']];
        }
        return ['sql' => 'LONGTEXT', 'placeholder' => '%s', 'rank' => self::RANK['LONGTEXT']];
    }
}
