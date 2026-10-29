<?

namespace Sys\Record\QueryBuilder;

/**
 * Represents Updates component for Querybuilder class.
 *
 * @category _("Updates component for Querybuilder class")
 */
class Updates
{
    private $table = "";
    private $where = array();

    function __construct($table)
    {
        $this->table = $table;
    }

    function where(array $where)
    {

        $this->where = $where;
        return $this;
    }

    /**
     * Generates an SQL query for inserting or updating data with ON DUPLICATE KEY UPDATE.
     *
     * @param array $attr An array containing column names as keys and a array with placeholder values as values.
     * @param array $data An array containing values for WHERE conditions.
     * @param array $id An array containing column names and values for UPDATE clause.
     * @return array An array containing the generated SQL query.
     * 
     * 
     */
    function force(array $attr = array(), array $data = array())
    {
        // Kontrollera om antalet attribut och data matchar
        if (count($attr) !== count($data)) {
            return array(); // Returnera en tom array om de inte matchar
        }
    
        // Börja bygga SQL-frågan
        $sql = "INSERT INTO `$this->table` ("; // Börja med INSERT INTO-satsen
        $columns = [];    // Håller kolumnnamn
        $placeholders = []; // Håller placeholders för värden
        $values = [];     // Håller faktiska värden
    
        // Bygg kolumnnamn och placeholders
        foreach ($attr as $column => $meta) {
            $columns[] = "`$column`";               // Lägg till kolumnnamn
            $placeholders[] = $meta['placeholder']; // Lägg till placeholder (%d, %s)
            $values[] = $data[$column];             // Lägg till faktiska värden från $data
        }
    
        // Lägg till kolumnnamn i SQL-frågan
        $sql .= implode(", ", $columns) . ") VALUES (";
    
        // Lägg till placeholders i SQL-frågan
        $sql .= implode(", ", $placeholders) . ") ON DUPLICATE KEY UPDATE ";
    
        // Bygg ON DUPLICATE KEY UPDATE-delen
        $updateParts = [];
        foreach ($attr as $column => $meta) {
            $updateParts[] = "`$column` = VALUES(`$column`)"; // t.ex. `online = VALUES(online)`
        }
    
        // Lägg till ON DUPLICATE KEY UPDATE-delen i SQL-frågan
        $sql .= implode(", ", $updateParts);
    
        // Returnera SQL-frågan och värdena
        return array_merge([$sql], $values);
    }


    function multiForce(array $data = array(), array $where = array(), array $options = array())
    {
        if (!array_key_exists('case', $options)) {
            $options['case'] = 'id';
        }

        $sql = array();
        $sql[] = "UPDATE `%s` SET ";
        $sqlParams = array();
        $sqlParams[] = $this->table;

        foreach ($data as $key => $row) {
            $cases = [];
            foreach ($row as $id => $v) {
                $cases[] = "WHEN '%s' THEN '%s'";
                $sqlParams[] = $id;
                $sqlParams[] = $v;
            }
            $sqlParts[] = "`$key` = CASE `$options[case]` " . implode(' ', $cases) . " END";
        }

        $sql[0] .= implode(', ', $sqlParts);

        $whereConditions = [];
        $params = [];

        foreach ($where as $kv => $d) {
            foreach ($d as $key => $row) {
                if (strcasecmp($key, 'in') === 0) {
                    $inParams = implode(',', array_fill(0, count($row), '%s'));
                    $whereConditions[] = "`$kv` IN ($inParams)";
                    $params = array_merge($params, $row);
                } elseif (strcasecmp($key, 'and') === 0 || strcasecmp($key, 'or') === 0) {
                    foreach ($row as $k => $v) {
                        $whereConditions[] = "`$k` = '%s'";
                        $params[] = $v;
                    }
                }
            }
        }

        if (!empty($whereConditions)) {
            $sql[0] .= " WHERE " . implode(' AND ', $whereConditions);
        }

        $sql = array_merge($sql, $sqlParams, $params);

        return $sql;
    }

    /**
     * Bygger en SQL UPDATE-fråga med sprintf-platshållare och värden från en associativ array.
     *
     * @param array $attr - Associativ array med attributinformation (placeholder -> %s eller %d).
     * @param array $data - Array med ytterligare data för WHERE-villkor.
     * @return array SQL UPDATE-frågan och dess värden.
     */
    function row(array $attr = array(), array $data = array())
    {
        $sql = array();

        // Bygg SET-delen av frågan
        $sql[0] = "UPDATE `%s` SET ";
        $sql[] = $this->table; // Lägg till tabellnamnet

        // Loopa genom attributen för att bygga SET-delen
        foreach ($attr as $column => $value) {
            // Lägg till varje attribut i SET-delen med dess platsmarkör
            $sql[0] .= "`$column` = '" . $value['placeholder'] . "' ,";
        }
        $sql[0] = rtrim($sql[0], ','); // Ta bort sista kommat

        // Lägg till data för WHERE-villkoren
        foreach ($data as $value) {
            $sql[] = $value; // Lägg till värden för WHERE-villkoren
        }

        // Bygg WHERE-delen av frågan
        $whereConditions = array();
        $count = count($data);

        // Loopa igenom WHERE-villkorsarrayen
        for ($i = 0; $i < $count; $i += 4) {
            $column = $data[$i];
            $operator = $data[$i + 1];
            $value = $data[$i + 2];
            $logicalOperator = isset($data[$i + 3]) ? $data[$i + 3] : '';

            // Avgör vilken platsmarkör som ska användas baserat på värdet
            $placeholder = is_numeric($value) ? '%d' : '%s';

            // Lägg till WHERE-villkor i arrayen
            $whereConditions[] = "`$column` $operator $placeholder";

            // Lägg till värdet till värdena
            $sql[] = $value;

            // Lägg till den logiska operatorn om det inte är sista villkoret
            if ($i + 4 < $count) {
                $whereConditions[] = $logicalOperator;
            }
        }

        // Sammanfoga WHERE-villkoren med den logiska operatorn
        $sql[0] .= " WHERE " . implode(' ', $whereConditions);

        return $sql;
    }

    function rows($attr = array(), array $data = array())
    {
        $sql = array();

        // Bygg SET-delen av frågan
        $sql[0] = "UPDATE `%s` SET ";

        return $sql;
    }
}
