<?php

/**
 * Represents a database connection using a driver.
 *
 * The DatabaseConnection class manages the database connection and provides access to
 * various component classes (Select, Update, Insert, Delete) for performing SQL operations.
 *
 * @category _("DatabaseConnection class")
 */
class DatabaseConnection
{
    private $conn = null;
    public $db;
    private $driver;
    public static $allSQL = [];

    /**
     * If getError is set to true and getErrorMessage() is called,
     * it will return the error message when a SQL query fails.
     */
    public $getError = false;

    public $SkipDeleteError = true;

    /**
     * Destructor that closes the database connection when the object is destroyed.
     */
    public function __destruct()
    {
        $this->db->close();
    }

    /**
     * Constructor for the DatabaseConnection class.
     *
     * @param string $connectionstring Name of the database configuration. Default is 'default'.
     */
    public function __construct($connectionstring = 'default')
    {
        $config = DatabaseManager::getConnection($connectionstring);

        $this->driver = $uname = ucfirst($config->driver);

        $className = "\\Sys\\DatabaseConnection\\$uname\\Driver";

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
     * Get the number of rows affected by the last UPDATE, INSERT, or DELETE query.
     *
     * @return int Number of affected rows.
     */
    public function affectedRows()
    {
        return $this->db->affected_rows;
    }

    /**
     * Escape a string for safe use in a SQL query.
     *
     * @param string $data The string to escape.
     * @return string The escaped string.
     */
    public function real_escape_string($data)
    {
        return $this->db->database->real_escape_string($data);
    }

    /**
     * Emulate a SQL query for debugging purposes.
     *
     * @param string $sql The SQL query with placeholders.
     */
    public function emulate($sql)
    {
        $arg = func_get_args();

        $sql = $this->db->escapeString($arg, _('Query'));

        $this->makeDataTypes();

        $report = new Sys\DatabaseConnection\Report($this->db->debugSQL);
        $report->sql = $this->db;
        $report->showEmulateSQL();
    }

    /**
     * Get an instance of the Select class for performing SELECT operations.
     *
     * @param string $dataType Optional result datatype:
     *   - 'as:text'   => associative array
     *   - 'as:number' => numeric array
     *   - 'as:both'   => both associative and numeric array
     *   - 'as:obj'    => object
     *
     * @return Select An instance of the Select class.
     */
    public function get($dataType = "")
    {
        $path = $_SERVER['HTTP_path_sys'] . "DatabaseConnection/$this->driver/Select.php";
        if (file_exists($path)) {
            config::registry("Select", sprintf(_('%s Select component class'), $this->driver));
            include_once($path);
        }

        $classname = $this->driver . 'Select';
        if (class_exists($classname, false)) {
            $this->task = new $classname($this, $dataType);
            return $this->task;
        } else {
            throw new SystemException(_("Could not load the Select class."));
        }
    }

    /**
     * Execute a SQL query.
     *
     * @param string $sql   The SQL query with placeholders.
     * @param bool   $error Throw exception on error (default: true).
     *
     * @return mysqli_stmt|bool Returns the prepared statement if successful, otherwise false.
     */
    public function query($sql, $error = true)
    {
        $arg = func_get_args();

        $sql = $this->db->escapeString($arg, _('Query'));

        $result = $this->db->_query($sql, $error);

        return $result;
    }

    /**
     * Get an instance of the Update class for performing UPDATE operations.
     *
     * @return Update An instance of the Update class.
     */
    public function update()
    {
        $path = $_SERVER['HTTP_path_sys'] . "DatabaseConnection/$this->driver/Update.php";
        if (file_exists($path)) {
            config::registry('Update', sprintf(_('%s Update component class'), $this->driver));
            include_once($path);
        }

        $classname = $this->driver . 'Update';
        if (class_exists($classname, false)) {
            $this->task = new $classname($this);
            return $this->task;
        } else {
            throw new SystemException(_("Could not load the Update class."));
        }
    }

    /**
     * Used when a table is suspected to be missing.
     * Provides the ability to build the table before executing SQL.
     *
     * @param Closure $function A closure to build the table.
     *
     * @return $this Returns this object for chaining.
     */
    public function notSetTable(Closure $function)
    {
        $this->db->func = $function;
        $this->db->getError = true;
        $this->db->helper = true;
        return $this;
    }

    /**
     * Get an instance of the Insert class for performing INSERT operations.
     *
     * @param string $sql SQL query for insertion.
     *
     * @return mixed Result of insertion, usually the auto-generated ID.
     */
    public function insertRow($sql)
    {
        $path = $_SERVER['HTTP_path_sys'] . "DatabaseConnection/$this->driver/Insert.php";
        if (file_exists($path)) {
            config::registry('Insert', sprintf(_('%s Insert component class'), $this->driver));
            include_once($path);
        }

        $classname = $this->driver . 'Insert';
        if (class_exists($classname, false)) {
            $this->task = new $classname($this);
            $arg = func_get_args();
            return $this->task->row($arg);
        } else {
            throw new SystemException(_("Could not load the Insert class."));
        }
    }

    /**
     * Get an instance of the Delete class for performing DELETE operations.
     *
     * @param string $sql SQL query for deletion.
     *
     * @return bool True if rows were affected, otherwise false.
     */
    public function delete($sql)
    {
        $path = $_SERVER['HTTP_path_sys'] . "DatabaseConnection/$this->driver/Delete.php";
        if (file_exists($path)) {
            config::registry('Delete', sprintf(_('%s Delete component class'), $this->driver));
            include_once($path);
        }

        $classname = $this->driver . 'Delete';
        if (class_exists($classname, false)) {
            $this->task = new $classname($this);
            $arg = func_get_args();
            $this->db->debugSQL = $arg;
            self::$allSQL[] = $arg;
            return $this->task->row($arg);
        } else {
            throw new SystemException(_("Could not load the Delete class."));
        }
    }

    /**
     * Check if a database connection is active.
     *
     * @param string $connectionstring Database configuration name. Default is 'default'.
     *
     * @return bool True if the connection is active, otherwise false.
     */
    public static function check($connectionstring = 'default')
    {
        $config = DatabaseManager::getConnection($connectionstring);

        $className = "\\Sys\\DatabaseConnection\\" . ucfirst($config->driver) . "\\Driver";

        // A failed connection means "not active", not an error
        try {
            $db = new $className(
                $config->hostname,
                $config->user,
                $config->password,
                $config->name,
                $config->type,
                $config->charset,
                $config->lengthmax
            );
        } catch (\Throwable $e) {
            return false;
        }

        return $db->check();
    }

    /**
     * Extract datatypes from a SQL query with placeholders.
     * Supports %s (string), %d (integer), %i (identifier),
     * and positional variants %1$s, %2$d, %3$i.
     *
     * @param string $query SQL query with placeholders.
     *
     * @return array Array of datatypes in order.
     */
    private function extractDataTypes($query)
    {
        $datatypes = [];
        preg_match_all('/%(?:\d+\$)?([sdi])/', $query, $matches);

        if (!empty($matches[1])) {
            foreach ($matches[1] as $type) {
                $datatypes[] = $type; // ← rå 's', 'd', 'i' — översättning sker i DatabaseException
            }
        }

        return $datatypes;
    }

    /**
     * Process all SQL queries in debugSQL and add their datatypes.
     */
    public function makeDataTypes()
    {
        if (!isset($this->db->debugSQL) || !is_array($this->db->debugSQL)) {
            return;
        }

        foreach ($this->db->debugSQL as $index => $query) {
            $this->db->debugSQL[$index]['datatypes'] = isset($query['forsql'])
                ? $this->extractDataTypes($query['forsql'])
                : [];
        }
    }

    /**
     * Process all SQL queries in the static allSQL list and add their datatypes.
     */
    public static function smakeDataTypes()
    {
        foreach (self::$allSQL as $index => $query) {
            if (isset($query['forsql'])) {
                $temp = new self();
                self::$allSQL[$index]['datatypes'] = $temp->extractDataTypes($query['forsql']);
            } else {
                self::$allSQL[$index]['datatypes'] = [];
            }
        }
    }

    /**
     * Display the most recently executed SQL query for debugging purposes.
     */
    public function getSQL()
    {
        $this->makeDataTypes();

        $report = new Sys\DatabaseConnection\Report($this->db->debugSQL);
        $report->showAllSQL();
    }

    /**
     * Display a list of all executed SQL queries.
     */
    public static function getAllSQL()
    {
        foreach (self::$allSQL as $index => $query) {
            if (isset($query['forsql'])) {
                $temp = new self();
                self::$allSQL[$index]['datatypes'] = $temp->extractDataTypes($query['forsql']);
            } else {
                self::$allSQL[$index]['datatypes'] = [];
            }
        }

        $report = new Sys\DatabaseConnection\Report(self::$allSQL);
        $report->showAllSQL();
    }

    /**
     * Get the most recent error message from the database.
     *
     * @return string Latest error message.
     */
    public function getErrorMessage()
    {
        return $this->db->getErrorMessage();
    }
}