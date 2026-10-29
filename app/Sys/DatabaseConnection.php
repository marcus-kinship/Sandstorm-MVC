<?php

/**
 * A database connection for one pool, with Select, Insert, Update and Delete.
 *
 * <code>
 * $db = new DatabaseConnection();                       // pool 'default'
 * $row  = $db->get()->row('SELECT * FROM `users` WHERE id = %d', 1);
 * $rows = $db->get('as:obj')->rows('SELECT * FROM `users`');
 * $id   = $db->insertRow('INSERT INTO `users` (`name`) VALUES (%s)', 'Ada');
 * $db->update()->row('UPDATE `users` SET `name` = %s WHERE id = %d', 'Ada', $id);
 * $db->delete('DELETE FROM `users` WHERE id = %d', $id);
 * </code>
 *
 * All DatabaseConnection objects for a pool share one MySQL connection per
 * request (see the Mysql Driver), so creating many of them is cheap.
 *
 * @category _("DatabaseConnection class")
 */
class DatabaseConnection
{
    /**
     * The driver for this connection (its own settings, shared MySQL link)
     *
     * @var \Sys\DatabaseConnection\Mysql\Driver
     */
    public $db;

    /**
     * Queries of this request, for the SQL report (devmode or HTTP_mysql_log only)
     *
     * @var array<int, array>
     */
    public static $allSQL = [];

    /**
     * True: a failed insert returns false (read the reason with
     * getErrorMessage()) instead of throwing DatabaseException.
     *
     * @var bool
     */
    public $getError = false;

    /**
     * True: deleting a row that is already gone is not an error.
     *
     * @var bool
     */
    public $SkipDeleteError = true;

    /**
     * The last Select, Insert, Update or Delete component that was used
     *
     * @var object|null
     */
    public $task = null;

    /**
     * Driver name, for example 'Mysql'
     *
     * @var string
     */
    private string $driver;

    /**
     * Open a connection to a pool (or reuse the open one).
     *
     * @param string $connectionstring Pool name: app/config/mysql/<name>.db.php
     * @throws SystemException If the pool settings are missing
     * @throws mysqli_sql_exception If MySQL refuses the connection
     */
    public function __construct($connectionstring = 'default')
    {
        $config = DatabaseManager::getConnection($connectionstring);

        $this->driver = ucfirst($config->driver);
        $className = "\\Sys\\DatabaseConnection\\{$this->driver}\\Driver";

        $this->db = new $className(
            $config->hostname,
            $config->user,
            $config->password,
            $config->name,
            $config->type,
            $config->charset,
            $config->lengthmax
        );
    }

    /**
     * Close the shared database connection for the whole request.
     *
     * Not done automatically: the connection is reused by every
     * DatabaseConnection in the request and PHP closes it when the request ends.
     *
     * @return void
     */
    public function close()
    {
        $this->db->close();
    }

    /**
     * Rows changed by the last INSERT, UPDATE or DELETE.
     *
     * @return int
     */
    public function affectedRows()
    {
        return $this->db->affected_rows;
    }

    /**
     * Escape a string for a SQL query.
     *
     * Prefer placeholders (%s, %d, %i) in the query instead.
     *
     * @param string $data
     * @return string
     */
    public function real_escape_string($data)
    {
        return $this->db->database->real_escape_string($data);
    }

    /**
     * Show the SQL a query would run, without running it (debugging).
     *
     * @param string $sql Query with placeholders, followed by its arguments
     * @return void
     */
    public function emulate($sql)
    {
        $this->db->escapeString(func_get_args(), _('Query'));
        $this->makeDataTypes();

        $report = new Sys\DatabaseConnection\Report($this->db->debugSQL);
        $report->sql = $this->db;
        $report->showEmulateSQL();
    }

    /**
     * SELECT: ->row(...) for one row, ->rows(...) for all rows.
     *
     * @param string $dataType 'as:text', 'as:number', 'as:both' or 'as:obj' ('' = the pool's type)
     * @return MysqlSelect
     */
    public function get($dataType = "")
    {
        $class = $this->component('Select');
        return $this->task = new $class($this, $dataType);
    }

    /**
     * Run any query.
     *
     * @param string $sql   Query with placeholders, followed by its arguments
     * @param bool   $error Kept for older code (errors always throw here)
     * @return mysqli_result|bool
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     */
    public function query($sql, $error = true)
    {
        $sql = $this->db->escapeString(func_get_args(), _('Query'));
        return $this->db->_query($sql, true);
    }

    /**
     * UPDATE: ->row('UPDATE ...', ...) returns true when a row changed.
     *
     * @return MysqlUpdate
     */
    public function update()
    {
        $class = $this->component('Update');
        return $this->task = new $class($this);
    }

    /**
     * When the next SELECT fails, run $function (for example to create the
     * missing table) and try once more.
     *
     * @param Closure $function
     * @return $this
     */
    public function notSetTable(Closure $function)
    {
        $this->db->func = $function;
        $this->db->getError = true;
        $this->db->helper = true;
        return $this;
    }

    /**
     * INSERT a row.
     *
     * @param string $sql Query with placeholders, followed by its arguments
     * @return int|string|false The new id, or false when it failed and getError is true
     * @throws Sys\DatabaseConnection\DatabaseException If it fails and getError is false
     */
    public function insertRow($sql)
    {
        $class = $this->component('Insert');
        $this->task = new $class($this);
        return $this->task->row(func_get_args());
    }

    /**
     * DELETE rows.
     *
     * @param string $sql Query with placeholders, followed by its arguments
     * @return bool True when a row was deleted
     * @throws Sys\DatabaseConnection\DatabaseException If the query fails
     * @throws SystemException If no row was deleted and SkipDeleteError is false
     */
    public function delete($sql)
    {
        $class = $this->component('Delete');
        $this->task = new $class($this);
        return $this->task->row(func_get_args());
    }

    /**
     * Whether the pool can connect.
     *
     * Reuses the open connection; a refused connection gives false, not an error.
     *
     * @param string $connectionstring Pool name
     * @return bool
     */
    public static function check($connectionstring = 'default')
    {
        try {
            return (new self($connectionstring))->db->check();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Types of the placeholders in a query: 's' (string), 'd' (integer),
     * 'i' (identifier), also for positional %1$s, %2$d, %3$i.
     *
     * @param string $query
     * @return string[]
     */
    public static function dataTypes(string $query): array
    {
        if ($query === '' || !str_contains($query, '%')) {
            return [];
        }

        preg_match_all('/%(?:\d+\$)?([sdi])/', $query, $matches);
        return $matches[1];
    }

    /**
     * Add the placeholder types to the last query (for the SQL report).
     *
     * @return void
     */
    public function makeDataTypes()
    {
        foreach ($this->db->debugSQL as $index => $query) {
            $this->db->debugSQL[$index]['datatypes'] = self::dataTypes($query['forsql'] ?? '');
        }
    }

    /**
     * Add the placeholder types to all queries in $allSQL.
     *
     * @return void
     */
    public static function smakeDataTypes()
    {
        foreach (self::$allSQL as $index => $query) {
            self::$allSQL[$index]['datatypes'] = self::dataTypes($query['forsql'] ?? '');
        }
    }

    /**
     * Show the last query of this connection (debugging).
     *
     * @return void
     */
    public function getSQL()
    {
        $this->makeDataTypes();
        (new Sys\DatabaseConnection\Report($this->db->debugSQL))->showAllSQL();
    }

    /**
     * Show all queries of this request (debugging; collected in devmode or with HTTP_mysql_log).
     *
     * @return void
     */
    public static function getAllSQL()
    {
        self::smakeDataTypes();
        (new Sys\DatabaseConnection\Report(self::$allSQL))->showAllSQL();
    }

    /**
     * Error of the last failed query, '' when it worked.
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->db->getErrorMessage();
    }

    /**
     * Class name of a component (Select, Insert, Update, Delete) for the
     * driver, loaded once.
     *
     * @param string $name Component name
     * @return string Class name, for example 'MysqlSelect'
     * @throws SystemException If the component does not exist for the driver
     */
    private function component(string $name): string
    {
        $class = $this->driver . $name;

        if (!class_exists($class, false)) {
            $file = $_SERVER['HTTP_path_sys'] . "DatabaseConnection/{$this->driver}/$name.php";
            if (is_file($file)) {
                include_once $file;
                config::registry($class, $file);
            }
            if (!class_exists($class, false)) {
                throw new SystemException(sprintf(_('Could not load the %s class.'), $name));
            }
        }

        return $class;
    }
}
