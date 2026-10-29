<?php

namespace Sys\DatabaseConnection\Mysql;

use Error;

/**
 * The `Driver` class handles MySQL database connections and SQL queries.
 * 
 * @category _("MySQL Driver Class")
 */
class Driver
{
    public $mysqli;
    public $database;
    public $type;
    public $check;
    public $helper = false;
    public $func = false;
    public $dataset = false;
    public $getMessage = false;
    public $modesingle = null;
    public $debugSQL = array();
    protected $isClose = false;
    protected $lengthmax; // Default 4 MB
    /**
     * If you set getError to true and call getErrorMessage(),
     * it returns the error message when an SQL query fails.
     */
    public $getError = false;

    /**
     * Constructor for the MySQL class.
     *
     * @param string $hostname - Server name or IP address of the MySQL server.
     * @param string $username - Username for the MySQL user.
     * @param string $password - Password for the MySQL user.
     * @param string $database - Database name to use.
     * @param string $type - Data type for the results (default: "").
     * @param string $charset - Character set for the results (default: utf8mb4).
     * @param int $lengthmax - Maximum query length (default: 0 for no limit).
     * 
     * @throws SystemException - Thrown if the mysqli extension is not installed.
     */
    public function __construct($hostname, $username, $password, $database, $type = "", $charset = "utf8mb4", int $lengthmax = 0)
    {
        if (!class_exists('mysqli')) {
            throw new SystemException(_('You must install the mysqli extension'));
        }

        $this->mysqli = new \Mysqli($hostname, $username, $password, $database);
        $this->database = $this->mysqli;
        $this->isClose = false;
        $this->lengthmax = $lengthmax;

        if ($type != "") {
            $this->type = $type;
            $this->dataset = ($this->type === 'as:obj');
        } else {
            $this->type = MYSQLI_BOTH;
        }

        if (!$this->mysqli->set_charset($charset)) {
            throw new \SystemException(
                sprintf('Error loading character set %s: %s', $charset, $this->mysqli->error),
                0,
                __FILE__,
                __LINE__
            );
        }

        if ($this->mysqli->connect_error) {
            throw new \SystemException(
                sprintf('Could not connect to MySQL database. Error code: %s, Error: %s', $this->mysqli->connect_errno, $this->mysqli->connect_error),
                0,
                __FILE__,
                __LINE__
            );
        }
    }

    /**
     * Escape and prepare an SQL string with placeholders.
     *
     * @param mixed $query - One or more parameters to send to the database.
     * @param string $typeQuery - Optional query type for debugging.
     * @return array - Associative array with prepared SQL string, debug info, types, and arguments.
     * 
     * @throws SystemException - Thrown if no query is provided.
     */
    function escapeString($query, $typeQuery = "")
    {
        if (empty($query)) {
            throw new \SystemException(_('The query cannot be executed because no SQL query was provided'));
        }

        $string = new \Sys\DatabaseConnection\EscapeString([
            "db" => $this->database,
            "query" => $query,
            "typeQuery" => $typeQuery
        ]);

        if ($string->valid()) {
            $sql = $string->get();
            $forquery = $string->getForQuery();
        }

        // Save for debugging
        $rawQuery = is_array($query) ? $query[0] : $query;
        $this->debugSQL[0] = [
            'sql' => $rawQuery,
            'forsql' => $forquery,
            'args' => $string->params ?? [],
            'type' => $typeQuery
        ];
        \DatabaseConnection::$allSQL[] = $this->debugSQL[0];

        if ($this->lengthmax > 0) {
            $len = strlen($rawQuery);
            $max = $this->lengthmax;

            if ($len > $max) {
                throw new \Sys\DatabaseConnection\DatabaseException(
                    sprintf(_("Query too large (> %d bytes)"), $max),
                    $this->debugSQL[0]
                );
            }
        }

        return $sql;
    }

    /**
     * Check if the connection is successful.
     *
     * @return bool - Returns true if the connection is successful, otherwise false.
     */
    public function check()
    {
        return $this->mysqli->connect_error === null;
    }

    /**
     * Set the data type for results.
     *
     * Available options:
     *   - 'as:text'   => (associative array)
     *   - 'as:number' => (numeric array)
     *   - 'as:both'   => (both associative and numeric array)
     *   - 'as:obj'    => (alias, treated as object if supported)
     * 
     * @return Select - Returns an instance of the Select class.
     */
    public function type()
    {
        $validTypes = [
            'as:text' => MYSQLI_ASSOC,
            'as:number' => MYSQLI_NUM,
            'as:both' => MYSQLI_BOTH,
            'as:obj' => MYSQLI_BOTH
        ];

        if (array_key_exists($this->type, $validTypes)) {
            $this->type = $validTypes[$this->type];
            $this->dataset = ($this->type === 'as:obj');
        }

        return $this;
    }

    /**
     * Execute an SQL query and return the prepared statement.
     *
     * @param string $query - SQL string to execute.
     * @param bool $error - Throw exception on error (default: true).
     * @return mysqli_stmt|bool - Returns prepared statement on success, otherwise false.
     * 
     * @throws DatabaseException - Thrown on error during prepared SQL query execution.
     */
    public function _query(string $query, bool $error = false)
    {
        try {
            $result = $this->database->query($query);

            if ($error && $this->database->errno) {
                \DatabaseConnection::smakeDataTypes();
                $lastKey = array_key_last(\DatabaseConnection::$allSQL);
                throw new \Sys\DatabaseConnection\DatabaseException(
                    sprintf(_('Syntax error in query. Error message: %s'), $this->mysqli->error),
                    \DatabaseConnection::$allSQL[$lastKey]
                );
            }

            // Save affected rows
            $this->affected_rows = $this->database->affected_rows;

            return $result;

        } catch (\mysqli_sql_exception $e) {
            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_("Error executing SQL query: %s"), $e->getMessage()),
                \DatabaseConnection::$allSQL[$lastKey]
            );
        }
    }

    /**
     * Fetch results from a prepared statement.
     *
     * @param mixed $result - mysqli_result object.
     * @return array - Array of query results.
     */
    private function fetchMode($result)
    {
        $type = $this->type()->type;

        if ($this->modesingle === null) {
            $rows = [];
            while ($row = $result->fetch_array($type)) {
                $rows[] = $row;
            }
        } else {
            $rows = $result->fetch_array($type);
        }

        $result->free();
        return $rows;
    }

    /**
     * Fetch results from an SQL query and update debug info.
     *
     * @param string $query - SQL string to execute.
     * @return array - Array of query results.
     * @throws SystemException - On SQL or connection error.
     */
    public function fetchResults($query)
    {
        $stmt = $this->_query($query);

        if ($stmt === false) {
            $this->getMessage = [$this->mysqli->error, $query];

            if ($this->helper) {
                $func = $this->func;
                $func();

                $stmt = $this->_query($query, false);
                if ($stmt === false) {
                    throw new \SystemException(_('Failed to execute query even after helper function.'));
                }

                $this->getError = false;
                return $this->fetchResults($query);
            }

            $this->getError = false;

            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_('Failed to execute SQL query: %s'), $this->mysqli->error),
                \DatabaseConnection::$allSQL[$lastKey]
            );
        }

        if ($this->mysqli->error) {
            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_('Failed to execute SQL query: %s'), $this->mysqli->error),
                \DatabaseConnection::$allSQL[$lastKey]
            );
        }

        if ($stmt instanceof \mysqli_stmt) {
            $result = $stmt->get_result();
        } elseif ($stmt instanceof \mysqli_result) {
            $result = $stmt;
        } else {
            throw new \SystemException(_('Unexpected return type from database query.'));
        }

        $row = $this->fetchMode($result);

        if ($stmt instanceof \mysqli_stmt) {
            $stmt->close();
        }

        return $row;
    }

    /**
     * Set dataset.
     *
     * @param array $row - Result from SQL query.
     * @return DataSet - DataSet object containing query results.
     */
    public function setDataset($row)
    {
        $dat = new DataSet($row);
        return $dat;
    }

    /**
     * Execute an SQL query and fetch results.
     *
     * @param string $query - SQL query string.
     * @return array|DataSet - Array or DataSet object depending on data type.
     */
    public function queryAndFetch($query)
    {
        $row = $this->fetchResults($query);

        if ($this->dataset) {
            return $this->setDataset($row);
        }
        return $row;
    }

    /**
     * Get the error message from the last SQL query.
     *
     * @return string - Error message from last SQL query.
     */
    function getErrorMessage()
    {
        return $this->mysqli->error;
    }

    /**
     * Close the connection to the MySQL database.
     */
    public function close()
    {
        $this->mysqli->close();
    }
}