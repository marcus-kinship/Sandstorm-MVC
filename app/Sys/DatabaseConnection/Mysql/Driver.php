<?php

namespace Sys\DatabaseConnection\Mysql;

use Sys\DatabaseConnection\DatabaseException;
use Sys\DatabaseConnection\EscapeString;

/**
 * MySQL connection and queries for DatabaseConnection.
 *
 * One MySQL connection per server, user, password, database and charset is
 * opened per request and shared by every Driver; each Driver keeps its own
 * settings (type, getError, helper, ...), so they never leak between
 * DatabaseConnection objects.
 *
 * A failed query:
 * - with a helper (DatabaseConnection::notSetTable()): the helper runs, then
 *   the query is tried once more
 * - when the caller asked for soft errors: returns false, and
 *   getErrorMessage() gives the reason
 * - otherwise: throws DatabaseException
 *
 * @category _("MySQL Driver Class")
 */
class Driver
{
    /** @var \mysqli The shared connection */
    public \mysqli $mysqli;

    /** @var \mysqli Same as $mysqli (older name, used by Insert and ArgumentEscaper) */
    public \mysqli $database;

    /** @var string|int Result type: 'as:text', 'as:number', 'as:both', 'as:obj' or a MYSQLI_* constant */
    public string|int $type = MYSQLI_BOTH;

    /** @var bool Return results as DataSet ('as:obj') */
    public bool $dataset = false;

    /** @var bool True: one row (Row), null: all rows (Rows) */
    public ?bool $modesingle = null;

    /** @var bool Run $func when a query fails, then try again */
    public bool $helper = false;

    /** @var callable|false Helper for a failed query (creates a missing table, ...) */
    public $func = false;

    /** @var bool Return false instead of throwing when a query fails */
    public bool $getError = false;

    /** @var array|false [error, query] of the last failed query */
    public array|false $getMessage = false;

    /** @var array<int, array> The last query, for the error page and the SQL report */
    public array $debugSQL = [];

    /** @var int Rows changed by the last INSERT, UPDATE or DELETE */
    public int $affected_rows = 0;

    /** @var bool Kept for older code; true when the connection works */
    public bool $check = true;

    /** @var int Largest allowed query in bytes, 0 = no limit */
    protected int $lengthmax = 0;

    /** @var bool True after close() */
    protected bool $isClose = false;

    /** @var string Error of the last failed query ('' when it worked) */
    private string $lastError = '';

    /** @var array<string, \mysqli> Open connections in this request, shared by all Drivers */
    private static array $connections = [];

    /**
     * Connect, or reuse the connection that is already open.
     *
     * @param string $hostname  MySQL server
     * @param string $username  MySQL user
     * @param string $password  MySQL password
     * @param string $database  Database name; created when it does not exist
     * @param string $type      Result type ('as:text', 'as:obj', ...), '' for both
     * @param string $charset   Connection charset
     * @param int    $lengthmax Largest allowed query in bytes, 0 = no limit
     *
     * @throws \SystemException If mysqli is missing or the charset cannot be set
     * @throws \mysqli_sql_exception If the connection fails
     */
    public function __construct($hostname, $username, $password, $database, $type = "", $charset = "utf8mb4", int $lengthmax = 0)
    {
        if (!class_exists('mysqli')) {
            throw new \SystemException(_('You must install the mysqli extension'));
        }

        $this->lengthmax = $lengthmax;

        if ($type !== '') {
            $this->type = $type;
        }

        $key = implode("\0", [$hostname, $username, $password, $database, $charset]);
        if (isset(self::$connections[$key])) {
            $this->mysqli = $this->database = self::$connections[$key];
            return;
        }

        try {
            $mysqli = new \mysqli($hostname, $username, $password, $database);
        } catch (\mysqli_sql_exception $e) {
            // 1049 = unknown database: create it on the first connection
            if ($e->getCode() !== 1049) {
                throw $e;
            }
            $mysqli = $this->createDatabase($hostname, $username, $password, $database, $charset);
        }

        if (!$mysqli->set_charset($charset)) {
            throw new \SystemException(sprintf(_('Error loading character set %s: %s'), $charset, $mysqli->error));
        }

        $this->mysqli = $this->database = self::$connections[$key] = $mysqli;
    }

    /**
     * Whether a connection is already open in this request.
     *
     * Lets code that must not open a connection of its own (the error
     * logger) use the database only when the page already does.
     *
     * @return bool
     */
    public static function hasOpenConnection(): bool
    {
        return self::$connections !== [];
    }

    /**
     * Create the database when it does not exist yet, then connect to it.
     *
     * @param string $hostname MySQL server
     * @param string $username MySQL user
     * @param string $password MySQL password
     * @param string $database Database name to create
     * @param string $charset  Character set for the new database
     * @return \mysqli Connection with the new database selected
     */
    private function createDatabase(string $hostname, string $username, string $password, string $database, string $charset): \mysqli
    {
        $mysqli = new \mysqli($hostname, $username, $password);

        if (!preg_match('/^\w+$/', $charset)) {
            $charset = 'utf8mb4';
        }

        $mysqli->query(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s',
            str_replace('`', '``', $database),
            $charset
        ));
        $mysqli->select_db($database);

        return $mysqli;
    }

    /**
     * Build the final SQL from a query with placeholders and its arguments.
     *
     * The query is kept in $debugSQL for the error page. It is also added to
     * DatabaseConnection::$allSQL (the SQL report), but only in devmode or with
     * HTTP_mysql_log true, so a long script does not collect every query.
     *
     * @param mixed  $query     'SQL' or ['SQL with %s', arg1, ...]
     * @param string $typeQuery Kind of query for the error page (Row, Insert, ...)
     * @return string The SQL to run
     * @throws DatabaseException If the query is empty, invalid or too large
     */
    public function escapeString($query, $typeQuery = ""): string
    {
        if (empty($query)) {
            throw new DatabaseException(_('The query cannot be executed because no SQL query was provided'), ['type' => $typeQuery]);
        }

        $string = new EscapeString([
            'db' => $this->database,
            'query' => $query,
            'typeQuery' => $typeQuery,
        ]);

        $rawQuery = is_array($query) ? $query[0] : $query;

        if (!$string->valid()) {
            throw new DatabaseException(_('The SQL query could not be prepared'), ['sql' => $rawQuery, 'type' => $typeQuery]);
        }

        $this->debugSQL[0] = [
            'sql' => $rawQuery,
            'forsql' => $string->getForQuery(),
            'args' => $string->params ?? [],
            'type' => $typeQuery,
        ];

        if (($_SERVER['HTTP_devmode'] ?? '') === 'true' || ($_SERVER['HTTP_mysql_log'] ?? '') === 'true') {
            \DatabaseConnection::$allSQL[] = $this->debugSQL[0];
        }

        if ($this->lengthmax > 0 && strlen($rawQuery) > $this->lengthmax) {
            throw new DatabaseException(sprintf(_('Query too large (> %d bytes)'), $this->lengthmax), $this->lastQuery());
        }

        return $string->get();
    }

    /**
     * Whether the connection works.
     *
     * @return bool
     */
    public function check(): bool
    {
        return !$this->isClose && $this->mysqli->connect_error === null;
    }

    /**
     * Turn the 'as:...' type into the MYSQLI_* constant for fetch_array().
     *
     * @return $this
     */
    public function type(): static
    {
        $validTypes = [
            'as:text' => MYSQLI_ASSOC,
            'as:number' => MYSQLI_NUM,
            'as:both' => MYSQLI_BOTH,
            'as:obj' => MYSQLI_ASSOC,
        ];

        if (is_string($this->type) && isset($validTypes[$this->type])) {
            $this->dataset = $this->type === 'as:obj';
            $this->type = $validTypes[$this->type];
        }

        return $this;
    }

    /**
     * Run a query.
     *
     * @param string $query The SQL to run
     * @param bool   $error True: throw DatabaseException on failure; false: return false
     * @return \mysqli_result|bool The result, true for queries without rows, false on failure
     * @throws DatabaseException On failure when $error is true
     */
    public function _query(string $query, bool $error = false)
    {
        $this->lastError = '';

        try {
            $result = $this->mysqli->query($query);
        } catch (\mysqli_sql_exception $e) {
            $result = false;
            $this->lastError = $e->getMessage();
        }

        if ($result === false) {
            if ($this->lastError === '') {
                $this->lastError = $this->mysqli->error;
            }
            if ($error) {
                throw new DatabaseException(sprintf(_('Error executing SQL query: %s'), $this->lastError), $this->lastQuery());
            }
            return false;
        }

        $this->affected_rows = (int) $this->mysqli->affected_rows;
        return $result;
    }

    /**
     * Run a SELECT and fetch one row (modesingle true) or all rows.
     *
     * @param string $query The SQL to run
     * @return array|null|false The row(s); null when Row finds nothing; false when
     *                          the query failed and getError is true
     * @throws DatabaseException If the query fails
     */
    public function fetchResults($query)
    {
        $result = $this->_query($query);
        $helped = false;

        if ($result === false && $this->helper && is_callable($this->func)) {
            // Run the helper once (for example: create the missing table), then try again
            $this->helper = false;
            $helped = true;
            ($this->func)();
            $result = $this->_query($query);
        }

        if ($result === false) {
            $this->getMessage = [$this->lastError, $query];

            // Soft errors, but not when even the helper could not fix it
            if ($this->getError && !$helped) {
                return false;
            }

            throw new DatabaseException(sprintf(_('Failed to execute SQL query: %s'), $this->lastError), $this->lastQuery());
        }

        if (!$result instanceof \mysqli_result) {
            throw new \SystemException(_('Unexpected return type from database query.'));
        }

        $type = $this->type()->type;

        if ($this->modesingle === null) {
            $rows = $result->fetch_all($type);
        } else {
            $rows = $result->fetch_array($type);
        }

        $result->free();
        return $rows;
    }

    /**
     * Wrap a row in a DataSet ('as:obj').
     *
     * @param mixed $row
     * @return \DataSet
     */
    public function setDataset($row)
    {
        return new \DataSet($row);
    }

    /**
     * Run a SELECT and return the rows, as DataSet when the type is 'as:obj'.
     *
     * @param string $query The SQL to run
     * @return mixed
     * @throws DatabaseException If the query fails
     */
    public function queryAndFetch($query)
    {
        $row = $this->fetchResults($query);

        if ($this->dataset && $row !== false && $row !== null) {
            return $this->setDataset($row);
        }

        return $row;
    }

    /**
     * Error of the last failed query, '' when it worked.
     *
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->lastError !== '' ? $this->lastError : $this->mysqli->error;
    }

    /**
     * The last query with the types of its placeholders, for DatabaseException.
     *
     * @return array
     */
    private function lastQuery(): array
    {
        $query = $this->debugSQL[0] ?? [];
        $query['datatypes'] = \DatabaseConnection::dataTypes($query['forsql'] ?? '');

        return $query;
    }

    /**
     * Close the connection to the MySQL database.
     *
     * The connection is shared: closing it closes it for every Driver in the
     * request, and the next Driver opens a new one. PHP closes it by itself
     * when the request ends, so this is rarely needed.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->isClose) {
            return;
        }

        $key = array_search($this->mysqli, self::$connections, true);
        if ($key !== false) {
            unset(self::$connections[$key]);
        }

        $this->mysqli->close();
        $this->isClose = true;
    }
}
