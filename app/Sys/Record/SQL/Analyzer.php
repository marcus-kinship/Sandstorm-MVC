<?

namespace Sys\Record\SQL;
use \Sys\Record\SQL\Scheme;

/**
 * Analyzer för SQL.
 * En klass som hanterar reparation av olika typer av databasfel.
 * @category _("Analyzer class for SQL")
 */
class Analyzer
{
    // Attribut för databasfelreparation
    private $errorMessage; // Det mottagna felmeddelandet
    public $getError = true; // En flagga som anger om ett fel uppstod
    private $tableName; // Namnet på den berörda tabellen
    private $fields; // Fälten som ska användas för att skapa eller modifiera tabellen
    private $db; // Databasanslutningsobjekt
    private $sqlType; // Den typ av SQL-operation som ska utföras
    private $data = array(); // Data som ska användas i SQL-operationen

    /**
     * Konstruktormetod för att skapa ett nytt DatabaseErrorRepairer-objekt.
     * 
     * @param string $errorMessage Det felmeddelande som ska hanteras.
     * 
     * @throws \SystemException Om ett tomt felmeddelande skickas med.
     */
    public function __construct(string $errorMessage = "")
    {
        if ($errorMessage == "") {
            throw new \SystemException(_("Du skickade med ett tomt felmeddelande."));
        }

        $this->errorMessage = $errorMessage;
    }

    /**
     * Sätter namnet på den tabell som påverkas av felet.
     * 
     * @param string $tableName Namnet på den berörda tabellen.
     * 
     * @return $this
     */
    function table(string $tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * Sätter de argument som ska användas för SQL-operationen.
     * 
     * @param array $data Data att användas för SQL-operationen.
     * 
     * @return $this
     */
    function arguments(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Sätter den typ av SQL-operation som ska utföras.
     * 
     * @param mixed $type Typen av SQL-operation.
     * 
     * @return $this
     */
    public function typeSQL($type)
    {
        $this->sqlType = $type;
        return $this;
    }

    /**
     * Sätter de fält som ska användas för att skapa eller modifiera tabellen.
     * 
     * @param array $fields Fälten som ska användas för att skapa eller modifiera tabellen.
     * 
     * @return $this
     */
    function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Utför reparation av det aktuella databasfelet.
     * 
     * @param string $dbPool Namnet på databasanslutningspoolen att använda för reparationen.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     * 
     * @throws \SystemException Om det uppstår ett okänt fel som inte kan hanteras.
     */
    public function repair(string $dbPool = "default2")
    {
        // Skapar en ny databasanslutning
        $this->db = new \DatabaseConnection2($dbPool);

        // Väljer rätt reparation baserat på typen av felmeddelande
        if (strpos($this->errorMessage, "doesn't exist") !== false) {
            return $this->repairTableNotExist();
        } else if (strpos($this->errorMessage, "Unknown column") !== false) {
            return $this->repairUnknownColumn();
        } else if (strpos($this->errorMessage, "Data too long for column") !== false) {
            return $this->repairDataTooLong();
        } else if (strpos($this->errorMessage, "Out of range value for column") !== false) {
            return $this->repairOutOfRangeValue();
        } else if (strpos($this->errorMessage, "Data truncated for column") !== false) {
            return $this->repairDataTruncated();
        } else {
            // Kastar undantag för okänt fel som inte kan hanteras
            throw new \SystemException("Okänt fel: " . $this->errorMessage);
        }
    }

    /**
     * Reparerar en situation där en tabell inte existerar genom att försöka skapa den med de tillgängliga fälten.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     * 
     * @throws \SystemException Om ingen matchande tabell hittades för reparation.
     */
    public function repairTableNotExist()
    {
       
        // Matchar felmeddelandet för att få tabellens namn
        if (preg_match("/\.(.*)' doesn't /i", $this->errorMessage, $out)) {
            // Skapar ett Scheme-objekt för den hittade tabellen och sätter dess egenskaper
            $sql = new Scheme($out[1]);
            $sql->properties($this->fields);
            // Skapar en SQL-fråga för att skapa tabellen
            $sql->addEngine();
            $sql->createTable();
        

            // Utför SQL-frågan för att skapa den icke-existerande tabellen
            $stmt = $this->db->query($sql->get(), $this->getError);

            // Returnerar true om frågan utfördes utan problem, annars false
            if ($stmt !== null && $stmt->errno === 0) {
                return true;
            } else {
                return false;
            }
        }

        // Kastar undantag om ingen matchande tabell hittades för reparation
        throw new \SystemException(_("Reparation för tabell, ingen matchande tabell hittades"));
    }

    /**
     * Reparerar en okänd kolumn genom att lägga till den i tabellen med dess egenskaper.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     */
    private function repairUnknownColumn()
    {
        
        // Matchar felmeddelandet för att få kolumnens namn
        if (preg_match("/Unknown column '(.*)' in 'field list'/i", $this->errorMessage, $matches)) {
            // Skapar ett Scheme-objekt för den aktuella tabellen
            $sql = new Scheme($this->tableName);
            // Loopar igenom alla fält och lägger till dem som kolumner i SQL-frågan
            foreach ($this->fields as $columns => $properties) {
                $sql->addColumn($columns, $properties);
            }
        
            // Utför SQL-frågan för att lägga till den okända kolumnen
            $stmt = $this->db->query($sql->get(), $this->getError);

            // Returnerar true om frågan utfördes utan problem, annars false
            if ($stmt !== null && $stmt->errno === 0) {
                return true;
            } else {
                return false;
            }
        }

        // Returnerar false om preg_match misslyckas med att hitta en matchning
        return false;
    }

    /**
     * Reparerar en kolumn där datan är för lång genom att ändra dess datatyp.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     */
    private function repairDataTooLong()
    {
        // Matchar felmeddelandet för att få kolumnens namn
        if (preg_match("/Data too long for column '(.*)' at row /i", $this->errorMessage, $matches)) {
            return $this->perform($matches[1]);
        }

        // Returnerar false om preg_match misslyckas med att hitta en matchning
        return false;
    }

    /**
     * Reparerar en kolumn där värdet är utanför kolumnens tillåtna intervall.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     */
    private function repairOutOfRangeValue()
    {
        // Matchar felmeddelandet för att få kolumnens namn
        if (preg_match("/Out of range value for column '(.*)' at row /i", $this->errorMessage, $matches)) {
            return $this->perform($matches[1]);
        }

        // Returnerar false om preg_match misslyckas med att hitta en matchning
        return false;
    }

    /**
     * Reparerar en kolumn där data har blivit avkortad.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     */
    private function repairDataTruncated()
    {
        // Matchar felmeddelandet för att få kolumnens namn
        if (preg_match("/Data truncated for column '(.+)' at row /i", $this->errorMessage, $matches)) {
            return $this->perform($matches[1]);
        }

        // Returnerar false om preg_match misslyckas med att hitta en matchning
        return false;
    }

    /**
     * Utför den faktiska reparationen för den givna kolumnen.
     * 
     * @param string $field Namnet på kolumnen som ska repareras.
     * 
     * @return bool Returnerar true om reparationen lyckades, annars false.
     */
    private function perform(string $field)
    {
        // Skapar ett Scheme-objekt för den aktuella tabellen
        $sql = new Scheme($this->tableName);
        // Modifierar egenskapen för den aktuella kolumnen med dess ursprungliga egenskaper
        $sql->modifyProperty($field, $this->fields[$field]);

        // Utför SQL-frågan för att lägga till den okända kolumnen
        $stmt = $this->db->query($sql->get(), $this->getError);

        // Returnerar true om frågan utfördes utan problem, annars false
        return ($stmt !== null && $stmt->errno === 0);
    }
}
