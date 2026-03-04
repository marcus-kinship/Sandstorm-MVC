<?

namespace Sys\DatabaseConnection\Mysql;

use Error;

/**
 * Klassen `Driver` hanterar MySQL-databasanslutningar och SQL-frågor.
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
    /**
     * Om man sätter getError till true och anropa getErrorMessage()
     * då får man tillbaka felmeddelande när SQL-frågan felar.
     */
    public $getError = false;

    /**
     * Konstruktör för Mysql-klassen.
     *
     * @param string $hostname - Servernamn eller IP-adress för MySQL-servern.
     * @param string $username - Användarnamn för MySQL-användaren.
     * @param string $password - Lösenord för MySQL-användaren.
     * @param string $database - Databasnamn som ska användas.
     * @param string $type - Datatyp för resultaten (standard: "").
     * @param string $charset - teckenuppsättning för resultaten (standard: utf8mb4).
     * 
     * 
     * @throws SystemException - Kastas om mysqli-tillägget inte är installerat.
     */
    public function __construct($hostname, $username, $password, $database, $type = "",$charset = "utf8mb4")
    {

        if (!class_exists('mysqli')) {
            throw new SystemException(_('Du måste installera mysqli-tillägget'));
        }

        $this->mysqli = new \Mysqli($hostname, $username, $password, $database);
        $this->database = $this->mysqli;
        $this->isClose = false;

        if ($type != "") {
            $this->type = $type;
            $this->dataset = ($this->type === 'as:obj');
        } else {
            $this->type = MYSQLI_BOTH;
        }

         if (!$this->mysqli->set_charset($charset)) {
            throw new \SystemException(
                sprintf('Fel vid inläsning av teckenuppsättning %s: %s', $charset, $this->mysqli->error),
                0,
                __FILE__,
                __LINE__
            );
        }
        
        if ($this->mysqli->connect_error) {
            throw new \SystemException(
                sprintf('Kunde inte ansluta till MySQL-databasen. Felkod: %s, Fel: %s', $this->mysqli->connect_errno, $this->mysqli->connect_error),
                0,
                __FILE__,
                __LINE__
            );
        }

    }

    /**
     * Escape:a och förbereda SQL-strängen med placeholders.
     *
     * @param mixed $query - En eller flera parametrar som ska skickas till databasen.
     * @return array - Associativ array med färdig SQL-sträng, debug-info, typer och argument.
     * 
     * @throws SystemException - Kastas om inga frågor skickas in.
     */
    function escapeString($query, $typeQuery = "")
    {
        if (empty($query)) {
            throw new \SystemException(_('Frågan kan inte köras eftersom du har inte skickat med någon SQL-fråga'));
        }

        $string = new \Sys\DatabaseConnection\EscapeString(([
            "db" => $this->database,
            "query" => $query,
            "typeQuery" => $typeQuery
        ]));

        if ($string->valid()) {
            $sql = $string->get();
            $forquery = $string->getForQuery();
        }
  
        // Spara för felsökning
        $this->debugSQL[0] = [
            'sql' => $query[0],
            'forsql' => $forquery,
            'args' => $string->params,
            'type' => $typeQuery
        ];
        \DatabaseConnection::$allSQL[] = $this->debugSQL[0];

        return $sql;
    }

    /**
     * Kontrollera om anslutningen är framgångsrik.
     *
     * @return bool - Returnera true om anslutningen är framgångsrik, annars false.
     */
    public function check()
    {

        if ($this->mysqli->connect_error === null) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Sätt datatyp för resultaten.
     *
     * Resultatdatatypen (valfri). Tillgängliga alternativ:
     *   - 'as:text'   => (associerad array)
     *   - 'as:number' => (numerisk array)
     *   - 'as:both'   => (både associerad och numerisk array)
     *   - 'as:obj'    => (alias, används som objekt om det stöds)
     * 
     * @return Select En instans av Select-klassen.
     */
    public function type()
    {

        $validTypes = ['as:text' => MYSQLI_ASSOC, 'as:number' => MYSQLI_NUM, 'as:both' => MYSQLI_BOTH, 'as:obj' => MYSQLI_BOTH];

        if (array_key_exists($this->type, $validTypes)) {
            $this->type = $validTypes[$this->type];
            $this->dataset = ($this->type === 'as:obj');
        }

        return $this;
    }

    /**
     * Kör en SQL-fråga och returnera det förberedda uttalandet.
     *
     * @param array $query - Associativ array med SQL-sträng, debug-info, typer och argument.
     * @param bool $error - Kasta undantag om det finns ett fel (standard: true).
     * @return mysqli_stmt|bool - Returnera det förberedda uttalandet om det är framgångsrikt, annars false.
     * 
     * @throws DatabaseException - Kastas om det finns ett fel vid användning av förberedda SQL-frågor.
     */
    public function _query(string $query, bool $error = false)
    {
        try {
        
            $result = $this->database->query( $query);
            
            if ($error && $this->database->errno) {
                \DatabaseConnection::smakeDataTypes();
                $lastKey = array_key_last(\DatabaseConnection::$allSQL);
                throw new \Sys\DatabaseConnection\DatabaseException(
                    sprintf(_('Ett Syntaxfel i frågan. Felmeddelande: %s'), $this->mysqli->error),
                    \DatabaseConnection::$allSQL[$lastKey]
                );
            }

            // Spara antal påverkade rader
            $this->affected_rows = $this->database->affected_rows;

            return $result;

        } catch (\mysqli_sql_exception $e) {
            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_("Ett fel uppstod vid körning av SQL-fråga: %s"), $e->getMessage()),
                \DatabaseConnection::$allSQL[$lastKey]
            );
        }
    }

    /**
     * Hämta resultatet från det förberedda uttalandet.
     *
     * 
     * @param array $query - Associativ array med SQL-sträng, debug-info, typer och argument.
     * @return array - Array med resultatet från SQL-frågan.
     * 
     * 
     */
    private function fetchMode($result)
    {
        // Hämta flera rader om inget specifikt läge är satt, annars en enskild rad
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
     * Hämta resultaten från SQL-frågan och uppdatera debug-info.
     *
     * @param array $query - Associativ array med SQL-sträng, debug-info, typer och argument.
     * @return array - Array med resultatet från SQL-frågan.
     * @throws SystemException - Vid SQL- eller anslutningsfel.
     */
    public function fetchResults($query)
    {
        // Försök köra frågan med prepared statement eller direkt
        $stmt = $this->_query($query);

        if ($stmt === false) {
            $this->getMessage = [$this->mysqli->error, $query];

            if ($this->helper) {
                $func = $this->func;
                $func();

                $stmt = $this->_query($query, false);
                if ($stmt === false) {
                    throw new \SystemException(_('Misslyckades att köra frågan även efter hjälpfunktion.'));
                }

                $this->getError = false;
                return $this->fetchResults($query); // kör igen med samma query
            }

            $this->getError = false;
            
            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_('Misslyckades att köra SQL-frågan: %s'), $this->mysqli->error),
                \DatabaseConnection::$allSQL[$lastKey]
            );
           
        }
       
        // Kontrollera SQL-syntaxfel
        if ($this->mysqli->error) {
        
            \DatabaseConnection::smakeDataTypes();
            $lastKey = array_key_last(\DatabaseConnection::$allSQL);
            throw new \Sys\DatabaseConnection\DatabaseException(
                sprintf(_('Misslyckades att köra SQL-frågan: %s'), $this->mysqli->error),
                \DatabaseConnection::$allSQL[$lastKey]
            );
        }

        // Hämta resultat beroende på objektets typ
        if ($stmt instanceof \mysqli_stmt) {
            $result = $stmt->get_result();
        } elseif ($stmt instanceof \mysqli_result) {
            $result = $stmt;
        } else {
            throw new \SystemException(_('Oväntad returtyp från databasfrågan.'));
        }

        $row = $this->fetchMode($result);

        // Stäng statement om det är ett mysqli_stmt
        if ($stmt instanceof \mysqli_stmt) {
            $stmt->close();
        }

        return $row;
    }


    /**
     * Sätta dataset.
     *
     * @param array $row - Resultatet från SQL-frågan.
     * @return DataSet - Ett DataSet-objekt med resultatet från SQL-frågan.
     */
    public function setDataset($row)
    {
        $dat = new DataSet($row);
        return $dat;
    }

    /**
     * Kör en SQL-fråga och hämta resultaten.
     *
     * @param array $query - Associativ array med SQL-sträng, debug-info, typer och argument.
     * @return array|DataSet - Array eller DataSet-objekt beroende på datatypen.
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
     * Hämta felmeddelandet från senaste SQL-frågan.
     *
     * @return string - Felmeddelandet från senaste SQL-frågan.
     */
    function getErrorMessage()
    {
        return $this->mysqli->error;
    }

    /**
     * Stäng anslutningen till MySQL-databasen.
     */
    public function close()
    {
        $this->mysqli->close();
    }
}
