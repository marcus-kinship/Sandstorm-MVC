<?
namespace Sys\Record;

/**
 * 
 * 	
 * @category _("Kernel class for Record")
 */
abstract class kernel{

    // Publika egenskaper

    public $multi = false;
    public $default = "DEFAULT NULL";
    public $data = array();              // Dataarray för att lagra information
    public $properties = array();        // Egenskapsarray för att lagra egenskaper

    // Privata egenskaper
 
    private $repairAfterSQLRun = false;  // Anger om det behövs reparation efter SQL-körning
    private $engine = "";                // Variabel för att hålla tabellmotorn

    // Skyddad egenskaper

    // Array med datatyper och deras standardlängder
    protected $dataTypes = array(
        "INT" => 11,
        "SMALLINT" => 6,
        "MEDIUMINT" => 7,
        "BIGINT" => 20,
        "FLOAT" => 8,
        "DOUBLE" => 8,
        "DECIMAL" => 8,
        "VARCHAR" => 255,
        "TEXT" => 65535,
        "MEDIUMTEXT" => 16777215,
        "LONGTEXT" => 4294967295,
        "DATE" => "YYYY-MM-DD",
        "TIME" => "HH:MM:SS",
        "DATETIME" => "YYYY-MM-DD HH:MM:SS",
        "BOOLEAN" => 1
    );
     // Array med datatyper för sekvens
    protected $sequence = array(
        0 => 'tinyint',
        1 => 'int',
        2 => 'float',
        3 => 'varchar',
        'DATE' => 'DATE',
        'TIME' => 'TIME',
        'DATETIME' => 'DATETIME',
        'BOOLEAN' => 'BOOLEAN' // Om du hanterar booleaner
    );
    protected $currentLength = 0;        // Nuvarande längd
    protected $currentMode = "";         // Nuvarande läge
    protected $currentType = null;       // Nuvarande datatyp
    protected $table = null;             // Namn på databastabellen

    /**
     * Tilldelar egenskaper baserat på en given array.
     *
     * @param array $record Associativ array där nycklarna är egenskapsnamn och värdena är de tilldelade datan.
     * @return void
     */
    public function assign(array $record, $mode = "as:obj")
    {
        if (empty($record)) {
            return null; // Om arrayen är tom, returnera null
        }
        $this->outdata = $mode;
        $this->hasRunSQL = true;

        foreach ($record as $key => $value) {
            // Tilldela varje egenskap genom define-metoden
            $this->define($key, $value);
        }

        return $this; // Returnera objektet för att möjliggöra chaining eller caching
    }
    
    /**
     * Magisk metod som används för att hämta värden från objektets egenskaper.
     *
     * @param string $name - Namnet på den egenskap som ska hämtas.
     * @return mixed|bool - Värdet för den efterfrågade egenskapen eller falskt om egenskapen inte finns.
     */
    function __get($name)
    {
  
        if (key_exists($name, $this->data)) {
            return $this->data[$name];
        } else {
            return false;
        }
    }

    /**
     * Sätter läge för flera rader.
     *
     * @param bool $value (valfritt) Boolean-värde för att aktivera/flera flera rader.
     * @return $this Nuvarande instans av klassen.
     */
    public function multi($value = true)
    {
        // flera  rader
        $this->multi = $value;
        return $this;
    }

    /**
     * Metod för att generera SQL för att ta bort en tabell.
     * 
     * @return string SQL-frågan för att ta bort tabellen.
     */
    public function removeTable()
    {
        // Konstruera SQL-frågan för att ta bort tabellen
        $sql = 'DROP TABLE IF EXISTS `' . $this->table . '`;';
        $db = new \DatabaseConnection();
        // Utför SQL-frågan för att lägga till den okända kolumnen
        $stmt = $db->query($sql);

        // Returnerar true om frågan utfördes utan problem, annars false
        if ($stmt !== null && $stmt->errno === 0) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Funktion för att definiera en åtgärd som ska utföras efter att en SQL-fråga har körts.
     *
     * @param callable $function Callback-funktionen som ska utföras efter att SQL-frågan har körts.
     */
    function repairAfterSQLRun($function)
    {
        $this->repairAfterSQLRun = $function;
    }

    /**
     * Funktion för att ta bort en eller flera kolumner från en databastabell.
     *
     * @param string|array $columns - En sträng med namnet på en kolumn eller en array med namnen på flera kolumner som ska tas bort.
     * @return bool - Returnerar true om kolumnerna har tagits bort, annars false.
     */
    public function removeColumns($columns)
    {
        // Skapa en ny instans av DatabaseConnection2
        $db = new DatabaseConnection();

        // Konvertera $columns till en array om det inte redan är det
        if (!is_array($columns)) {
            $columns = array($columns);
        }

        // Konstruera SQL-frågan för att ta bort kolumnerna
        $sql = "ALTER TABLE $this->table DROP COLUMN " . implode(", DROP COLUMN ", $columns);

        // Anropa metoden query från $db-objektet för att utföra SQL-frågan
        // Funktionen returnerar resultatet från anropet till query-metoden
        $result = $db->query($sql);

        // Kontrollera resultatet av operationen
        if ($result) {
            return true; // Kolumnerna har tagits bort från tabellen
        } else {
            return false; // Kolumnerna har inte tagits bort från tabellen
        }
    }

    /**
     * Sätter läge för maximalt värde.
     */
    public function max()
    {
        $this->currentMode = "max";
    }

    /**
     * Sätter läge för minimalt värde.
     */
    public function min()
    {
        $this->currentMode = "min";
    }

    /**
     * Sätter läge för att klippa värde.
     */
    public function cut()
    {
        $this->currentMode = "cut";
    }

    /**
     * Sätter egenskaper för tabell.
     *
     * @param array $properties Egenskaper för tabell.
     * @return $this Nuvarande instans av klassen.
     */
    function properties(array $properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * Sätter datatyp för text.
     *
     * @param int $length Längden på text.
     * @return $this Nuvarande instans av klassen.
     */
    public function text($length)
    {
        // text
        $this->currentType = 'text';
        $this->currentLength = $length;
        return $this;
    }

    /**
     * Sätter datatyp för heltal.
     *
     * @param int $length Längden på heltal.
     * @return $this Nuvarande instans av klassen.
     */
    public function int($length)
    {
        // number
        $this->currentType = 'int';
        $this->currentLength = $length;
        return $this;
    }

    /**
     * Definierar egenskapen och tilldelar dess värde.
     *
     * @param string $name Namnet på egenskapen.
     * @param mixed $data (valfritt) Värdet som ska tilldelas. Standard är null.
     * @return void
     *
     * @throws SystemException Kastas om längden på data överskrider max- eller mingränsen eller om datan inte kan klippas.
     */
    function define($name, $data = null)
    {

    // Standard placeholder för variabler
        $placeholder = "%s";

        // Hämta standardvärden
        $default = $this->default;
        $currentLength = 0; // Säkerställ att $currentLength alltid är definierad
        $currentType = 3; // Default to text type
        $d = "";
        
        // Kontrollera om det finns data att bearbeta
        if ($data !== null) {
            // Använd en temporär variabel för att hantera data
            $d = $data; // Använd en temporär variabel

            // Identifiera datatypen för $data
            if (is_array($data) || is_object($data)) {
                // Om $data är en array eller objekt, konvertera till sträng
                $currentType = 3; // Anpassad typ för array eller objekt
                $d = var_export($data, true);
                $currentLength = strlen($d);
            } elseif (preg_match('/^\d+\.\d+$/', $data)) {
                // Om $data är en decimal
                $currentType = 2; // float
                $currentLength = strlen($data);
            } elseif (preg_match('/^\d+$/', $data, $matches, PREG_OFFSET_CAPTURE, 0)) {
                // Om $data är ett heltal
                $currentType = 1; // int
                $placeholder = "%d"; // Updatera platsmarkören för heltal
                $currentLength = strlen($matches[0][0]);
            } elseif (is_bool($data)) {
                // Om $data är en boolesk variabel
                $currentType = 0; // tinyint
                $currentLength = 1;
                $placeholder = "%d"; // Updatera platsmarkören för booleska värden
            } elseif (is_string($data)) {
                // Om $data är en sträng
                if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $data)) {
                    // Om strängen representerar ett datum i formatet YYYY-MM-DD
                    $data = date('Y-m-d', strtotime($data));
                    $currentType = "DATE";
                } elseif (preg_match('/\b\d{2}:\d{2}:\d{2}\b/', $data)) {
                    // Om strängen representerar en tid i formatet HH:MM:SS
                    $currentType = "TIME";
                } elseif (preg_match('/\b\d{2}:\d{2}\b/', $data)) {
                    // Om strängen representerar en tid i formatet HH:MM (24-timmarsformat)
                    $data .= ":00"; // Lägg till sekunder
                    $currentType = "TIME";
                } elseif (preg_match('/\b(?:\d{2}-\d{2}-\d{4}|\d{2}\/\d{2}\/\d{4})\b/', $data)) {
                    // Om strängen representerar ett datum i formatet DD-MM-YYYY eller DD/MM/YYYY
                    $data = date('Y-m-d', strtotime(str_replace('/', '-', $data)));
                    $currentType = "DATE";
                } elseif (preg_match('/\b\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\b/', $data)) {
                    // Om strängen representerar en tidstämpel i formatet YYYY-MM-DD HH:MM:SS
                    $currentType = "DATETIME";
                } else {
                    // Om strängen inte matchar något specifikt format
                    $currentType = 3; // Anpassad typ för vanlig text
                    $currentLength = strlen($data); // Beräkna längden av strängen
                }
            }
        }

        // Sätt den aktuella datatypen baserat på ordningen i sekvensen
        $this->currentType = $this->sequence[$currentType];

        // Om flera värden är aktiverade
        if ($this->multi == true) {
            // Jämför längden för att se om den är större än $this->currentLength och uppdatera om så är fallet
            if ($currentLength > $this->currentLength) {
                $this->currentLength = $currentLength;
            }
            
            $this->adjustDataType($currentType);

            // Kontrollera om egenskapen $name redan är satt, om inte skapa en tom array
            if (!isset($this->data[$name])) {
                $this->data[$name] = [];
            }

            // Lägg till värdet från den temporära variabeln till egenskapen
            $this->data[][$name] = $d;
        } else {
            // Annars, om flera värden inte är aktiverade
            $this->currentLength = $currentLength;

            $this->adjustDataType($currentType);

            // Tilldela värdet direkt till egenskapen
            $this->data[$name] = $d;
        }

        // Kontrollera om längden av data överstiger gränsvärdet för aktuellt läge
        if ($this->currentMode === "min" && $currentLength < $this->currentLength) {
            throw new SystemException(sprintf(_("Data exceeds the min allowed length of %s"), $this->currentLength));
        } else if ($this->currentMode === "max" && $currentLength > $this->currentLength) {
            throw new SystemException(sprintf(_("Data exceeds the maximum allowed length of %s"), $this->currentLength));
        } elseif ($this->currentMode === "cut" && $currentLength > $this->currentLength) {
            if ($currentType == 3) {
                throw new SystemException(sprintf(_("Data cannot be cut as it is an array or object")));
            } else {
                $d = substr($d, 0, $this->currentLength);
            }
        }

        // Definiera egenskaperna för den aktuella variabeln
        $this->properties[$name] = [
            'type' => $this->currentType,
            'length' => $this->currentLength,
            '_length' => $this->currentType . '(' . $this->currentLength . ')',
            'placeholder' => $placeholder,
            'default' => $default,
            'currentMode' => $this->currentMode
        ];
    }

    /**
     * Anpassar datatypen baserat på nuvarande typ och längd.
     *
     * @param string $currentType Nuvarande datatyp.
     * @return void
     */
    private function adjustDataType($currentType)
    {

        // Jämför om currentType är större än $this->$currentType, om sant, uppdatera $this->currentType med hjälp av $sequence
        if (array_search($currentType, $this->sequence) > array_search($this->currentType, $this->sequence)) {
            $this->currentType = $this->sequence[$currentType];
        }

        // Jämför längden och typen för att se om ändringar behövs för datatypen
        if ($this->currentType === "int" && $this->currentLength > 20) {
            $this->currentType = "varchar";
        } elseif ($this->currentType === "varchar" && $this->currentLength > 255) {
            $this->currentType = "text";
        }
    }
}