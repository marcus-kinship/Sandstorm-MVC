<?

namespace Sys\Record\QueryBuilder;

/**
 * Representerar Inserts-komponenten för QueryBuilder-klassen.
 *
 * Denna klass hanterar konstruktionen av SQL INSERT-satser
 * för en specifik tabell med hjälp av angivna attribut för kolumner.
 *
 * @category _("Inserts-komponent för QueryBuilder-klassen")
 */
class Inserts
{
    /**
     * @var string $table Namnet på tabellen där data ska infogas.
     */
    private $table = "";

    /**
     * Konstruktor för Inserts-klassen.
     *
     * Initierar tabellen där SQL INSERT-satsen ska tillämpas.
     * Tabellnamnet krävs för att kunna bygga korrekt SQL-fråga.
     *
     * @param string $table Namnet på tabellen.
     * 
     */
    function __construct($table)
    {

        $this->table = $table;
    }

    /**
     * Bygger en SQL INSERT-fråga efter attr och returnerar den.
     *
     * @param array $attr - Associerad array med attribut för kolumnerna i tabellen (placeholder -> %s eller %d).
     * @return string SQL INSERT-frågan.
     */
    function row($attr = array())
    {
        $columns = array_keys($attr);
        $values = array_map(function ($item) {
            return $item['placeholder'];
        }, $attr);

        $sql = "INSERT INTO `$this->table` (`" . implode("`,`", $columns) . "`) VALUES ('" . implode("','", $values) . "')";

        return $sql;
    }

    /**
     * Funktion för att generera en SQL-fråga baserat på attribut och data.
     *
     * @param array $attr Associerad array med attribut för kolumnerna i tabellen (placeholder -> %s eller %d).
     * @param array $data Associerad array med värdena som ska infogas i tabellen.
     * @return array En array som innehåller SQL-frågan och dess tillhörande värden.
     */
    function rows(array $attr = array(), array $data = array())
    {
        // Array för att lagra SQL-frågan och dess tillhörande värden
        $sql = [];
        // Sträng för att lagra SQL-frågan
        $sql[0] = "";
        // Array för att lagra värden för varje rad
        $rowValues = [];
        // Array för att lagra kolumnnamnen
        $keys = array_keys($attr);

        // Loopa igenom varje rad i $data
        foreach ($data as $row) {
            // Array för att lagra värden för den aktuella raden
            $values = [];
            // Loopa igenom varje kolumn-värde-paret i raden
            foreach ($row as $key => $value) {
                // Kontrollera om kolumnen finns i $attr och om dess placeholder är definierad
                if (key_exists($key, $keys)) {
                    // Hämta kolumnen
                    $column = $keys[$key];
                    // Lägg till värdet enligt dess placeholder
                    if ($attr[$column]['placeholder'] == '%d') {
                        // Sätt plathållare för heltal
                        $values[] = $attr[$column]['placeholder'];
                        // Spara värdet i $sql
                        $sql[] = $value;
                    } elseif ($attr[$column]['placeholder'] == '%s') {
                        // Sätt plathållare för strängar
                        $values[] = "'" . $attr[$column]['placeholder'] . "'";
                        // Spara värdet i $sql
                        $sql[] = $value;
                    }
                }
            }
            // Bygg radvärdet med dess placeholders och lägg till det i $rowValues
            $rowValues[] = "(" . implode(",", $values) . ")";
        }

        // Bygg den kompletta SQL-frågan
        $sql[0] = "INSERT INTO `" . $this->table . "` (`" . implode("`,`", $keys) . "`) VALUES ";
        $sql[0] .= implode(",", $rowValues) . ";";

        // Returnera arrayen med SQL-frågan och dess värden
        return $sql;
    }

}
