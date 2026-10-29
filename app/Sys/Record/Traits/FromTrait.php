<?php

namespace Sys\Record\Traits;



/**
 * Trait för datahämtning (From-klassen)
 *
 * @category _("Trait för datahämtning Trait")
 */

trait FromTrait
{

    /**
     * Funktionen `From` används för att specificera vilken tabell eller vilka tabeller som ska användas i SQL-frågan.
     *
     * @param string $table Namnet på tabellen eller en kommaseparerad sträng med flera tabellnamn.
     * @return $this Aktuell instans av klassen för metodkedja.
     *
     * @throws SystemException Kastas om inget tabellnamn anges.
     */
    public function From(string $table = "")
    {
        if ($table == "") {
            throw new \SystemException("Du måste ange en tabell eller flera tabeller");
        }

        $this->From = "FROM " . $table;
        return $this;
    }

    /**
     * Funktionen `Select` används för att specificera vilka kolumner som ska väljas i SQL-frågan.
     *
     * @param string $Select En kommaseparerad sträng med kolumnnamn.
     * @return $this Aktuell instans av klassen för metodkedja.
     *
     * @throws SystemException Kastas om inga kolumnnamn anges.
     */
    public function Select(string $Select = "")
    {
        if ($Select == "") {
            throw new \SystemException("Du måste ange en kolumn eller flera kolumner");
        }

        $this->Select = "SELECT " . $Select;
        return $this;
    }

    /**
     * Lägg till en relation till en annan tabell.
     * 
     * @param string $table Namnet på den relaterade tabellen.
     * @param string $key1 Nyckeln i den aktuella tabellen.
     * @param string $key2 Nyckeln i den relaterade tabellen.
     * @return $this
     */
    public function addRelation($table, $key1, $key2)
    {
        $this->relations[] = [
            'table' => $table,
            'key1' => $key1,
            'key2' => $key2
        ];

        $this->useRelations = true;
        return $this;
    }

    /**
     * Funktionen `exec` används för att exekvera den sammansatta SQL-frågan och hämta data från databasen.
     *
     * @param string $default (valfritt) Standardvärde som returneras om inga data finns.
     * @return array Array med hämtade data.
     */
    public function exec($default = "")
    {
        // Instans av MySQL-klass
        $db = new \DatabaseConnection();
        $data = array();
        $sql = "";

        // Bygger upp SQL "SELECT * FROM table"
        if (!$this->Select) {
            $this->Select = "SELECT t.*";
        }

        if (!$this->From) {
            $this->From = "FROM `" . $this->table . "` t";
        }

        // Hanterar relationer och lägger till JOIN-satser om $useRelations är sant och relations arrayen inte är tom
        if ($this->useRelations) {
            foreach ($this->relations as $relation) {
                $this->From .= " JOIN {$relation['table']} ON {$this->table}.{$relation['key1']} = {$relation['table']}.{$relation['key2']}";
            }
        }

        // Lägger till total_rows i SELECT-satsen och CROSS JOIN
        if ($this->paging) {
            $this->Select .= ", ct.total_rows ";
            $this->From .= " CROSS JOIN (SELECT COUNT(*) as total_rows FROM `" . $this->table . "`) AS ct ";
        }

        // Slutligen sätter ihop hela SQL-frågan
        $sql = $this->Select . ' ' . $this->From . ' ' . $this->where . ' ' . $this->groupBy . ' ' . $this->limit . ' ' . $this->orderby;


        // Exekverar SQL-frågan och hämtar data
        $data = $db->get($this->typeGetData)->Rows($sql);
        
        $this->hasRunSQL = true;
        
        if ($this->paging) {
            if (key_exists(0, $data)) {
                $this->pagination->num_rows($data[0]['total_rows']);

                foreach ($data as $key => $row) {
                    unset($data[$key]['total_rows']);
                }
            }
        }
        return $data;
    }

    /**
     * Funktionen `orderBy` används för att specificera ordningen på resultaten i SQL-frågan.
     *
     * @param array $orderBy En associerad array där nycklarna är kolumnnamnen och värdena är sorteringsriktningarna (ASC eller DESC).
     * @return $this Aktuell instans av klassen för metodkedja.
     */
    public function orderBy(array $orderBy)
    {
        $order = '';
        foreach ($orderBy as $column => $direction) {
            $order .= "$column $direction, ";
        }
        // Ta bort det sista kommatecknet och mellanslaget från strängen
        $order = rtrim($order, ', ');

        $this->orderBy = "ORDER BY $order";
        return $this; // Returnera aktuell instans för metodkedja
    }

    /**
     * Dynamiskt anrop av statiska metoder baserat på metoden och dess argument.
     * 
     * @param string $method Namnet på den anropade metoden.
     * @param array $arguments Eventuella argument som skickas med metoden.
     * @return mixed Resultatet av den utförda metoden.
     * @throws SystemException Om en ogiltig funktion anropas eller om tabellnamnet inte är definierat i klassen.
     */
    public static function __callStatic($method, $arguments)
    {
        // Hämta namnet på den klass som metoden anropas från
        $modelName = get_called_class();

        // Kontrollera om inte anropet görs från record-klassen direkt
        if ($modelName != __CLASS__) {
            throw new \SystemException(_('Du kan inte anropa en statisk funktion direkt från record-klassen'));
        }

        // Skapa en instans av modellklassen för att komma åt dess egenskaper
        $modelInstance = new $modelName();

        // Kontrollera om tabellnamnet är definierat i klassen
        if (!property_exists($modelInstance, 'table')) {
            throw new \SystemException(_('Du måste sätta variabeln table med tabellnamn i din klass'));
        }

        // Hämta tabellnamnet från modellklassen
        $tableName = $modelInstance->table;

        // Skapa en instans av Record2-klassen för att utföra operationer på tabellen
        $r = new \Record($tableName);

        // Utför den lämpliga operationen baserat på den angivna metoden
        switch ($method) {
            case 'all':
                return $r->all(array_key_exists(0, $arguments) ? $arguments[0] : "as:text");
                break;
            case 'delete':
                return $r->delete(array_key_exists(0, $arguments) ? $arguments[0] : "");
                break;
            case 'last':
                return $r->last(array_key_exists(0, $arguments) ? $arguments[0] : "as:text");
                break;
            case 'searchInField':
                return $r->searchInField(array_key_exists(0, $arguments) ? $arguments[0] : "as:text");
                break;
            case 'search':
                return $r->search(array_key_exists(0, $arguments) ? $arguments[0] : "as:text");
                break;
            case 'paging':
                return $r->search(array_key_exists(0, $arguments) ? $arguments[0] : "as:text");
                break;
            default:
                throw new \SystemException(_('Ogiltig funktion'));
        }
    }

    /**
     * Hämtar alla rader från tabellen.
     * 
     * @param string $type Typ av resultat som ska returneras, standard är "as:text".
     * @return array Resultatet av SQL-frågan som en associativ array.
     */

    public function all($type = "as:text")
    {

        // instans av MySQL-klass
        $db = new \DatabaseConnection();

        // Standard SELECT-sats om inte tidigare satt
        if (!$this->Select) {
            $this->Select = "SELECT t.*";
        }

        // Standard FROM-sats om inte tidigare satt
        if (!$this->From) {
            $this->From = "FROM `" . $this->table . "` t";
        }

        // Hantera relationer och lägg till JOIN-satser
        if ($this->useRelations && !empty($this->relations)) {
            foreach ($this->relations as $relation) {
                $this->From .= " JOIN {$relation['table']} ON {$this->table}.{$relation['key1']} = {$relation['table']}.{$relation['key2']}";
            }
        }

        // Slutligen sätter ihop hela SQL-frågan
        $sql = $this->Select . ' ' . $this->From . ' ' . $this->where . ' ' . $this->groupBy . ' ' . $this->limit . ' ' . $this->orderBy;

        // Utför SQL-frågan och hämta data
        $result = $db->get($type)->rows($sql);

        return $result;
    }

    /**
     * Hämtar den sista raden från databastabellen.
     * 
     * @param string $type Typ av resultat som ska returneras, standard är "as:text".
     * @return array Den sista raden från databastabellen som en associativ array.
     */
    public function last($type = "as:text")
    {

        // Skapa instans av MySQL-klassen
        $db = new \DatabaseConnection();

        // Standard SELECT-sats om inte tidigare satt
        if (!$this->Select) {
            $this->Select = "SELECT t.*";
        }

        // Standard FROM-sats om inte tidigare satt
        if (!$this->From) {
            $this->From = "FROM `" . $this->table . "` t";
        }

        // Hantera relationer och lägg till JOIN-satser
        if ($this->useRelations && !empty($this->relations)) {
            foreach ($this->relations as $relation) {
                $this->From .= " JOIN {$relation['table']} ON {$this->table}.{$relation['key1']} = {$relation['table']}.{$relation['key2']}";
            }
        }

        // Slutligen sätter ihop hela SQL-frågan
        $sql = $this->Select . ' ' . $this->From . ' ' . $this->where . ' ' . $this->groupBy . ' ' . $this->limit . ' ' . $this->orderBy;

        // Lägg till ORDER BY och LIMIT för att få den sista posten
        $sql .= " ORDER BY t.id DESC LIMIT 1";

        // Utför SQL-frågan och hämta data
        $result = $db->get($type)->row($sql);
        $this->hasRunSQL = true;
        return $result;
    }

    /**
     * Söker efter en specifik term i ett givet fält i databastabellen och returnerar resultat baserat på valt läge.
     *
     * @param string $field Fältet att söka i.
     * @param string $operator Operatorn att använda för sökningen, t.ex. "=" eller "LIKE".
     * @param string | int $term Söktermen.
     * @param string $type Typ av resultat som ska returneras (standard är "as:text").
     * @param string $mode Anger om en eller flera rader ska returneras ("single" för en rad, "multiple" för flera).
     * @return array|false Returnerar en associerad array med resultatet eller `false` om inget hittades.
     */
    public function searchInField($field, $operator, $term,  $mode = "multiple", $type = "as:text")
    {
        // Skapa instans av databasanslutningsklassen
        $db = new \DatabaseConnection();
    
        // Sätt standard SELECT-sats om inte tidigare satt
        if (!$this->Select) {
            $this->Select = "SELECT t.*";
        }
    
        // Sätt standard FROM-sats om inte tidigare satt
        if (!$this->From) {
            $this->From = "FROM `" . $this->table . "` t";
        }
    
        // Om relationer används, lägg till JOIN-satser
        if ($this->useRelations && !empty($this->relations)) {
            foreach ($this->relations as $relation) {
                $this->From .= " JOIN {$relation['table']} ON {$this->table}.{$relation['key1']} = {$relation['table']}.{$relation['key2']}";
            }
        }
        // Bygg WHERE-satsen
        $this->where = "WHERE $field $operator '%s'";
        
        if(preg_match('/^-?\d{1,19}$/', $term)){
            $this->where = "WHERE $field $operator %d";
        }
       
    
        // Sätt ihop hela SQL-frågan
        $sql = $this->Select . ' ' . $this->From . ' ' . $this->where . ' ' . $this->groupBy . ' ' . $this->limit . ' ' . $this->orderBy;
     

        // Utför SQL-frågan baserat på angivet läge
        if ($mode === "single") {
            // Returnera en enskild rad
            
            return $db->get($type)->row($sql, $term);
        } else {
            // Returnera flera rader
            return $db->get($type)->rows($sql, $term);
        }
    }

    /**
     * Utför en sökning i databastabellen baserat på angivna kriterier.
     *
     * @param array $kriterier Kriterier för sökningen. Förväntas vara en array där varannan post är en kolumn och den följande är ett sökvärde, och var tredje är en operator (t.ex. 'AND' eller 'OR').
     * @param string $typeTyp av resultat som ska returneras, standard är "as:text".
     * @return array Resultatet av sökningen som en associativ array.
     */
    public function search(array $kriterier, $type = "as:text")
    {
        // Skapa instans av MySQL-klassen
        $db = new \DatabaseConnection();
    
        // Standard SELECT-sats om inte tidigare satt
        if (!$this->Select) {
            $this->Select = "SELECT t.*";
        }
    
        // Standard FROM-sats om inte tidigare satt
        if (!$this->From) {
            $this->From = "FROM `" . $this->table . "` t";
        }
    
        // Hantera relationer och lägg till JOIN-satser
        if ($this->useRelations && !empty($this->relations)) {
            foreach ($this->relations as $relation) {
                $this->From .= " JOIN {$relation['table']} ON {$this->table}.{$relation['key1']} = {$relation['table']}.{$relation['key2']}";
            }
        }
    
        // Bygg WHERE-satsen
        $this->where = "WHERE ";
        $villkor = array();
        $params = [];
        for ($i = 0; $i < count($kriterier); $i += 3) {
            $kolumn = $kriterier[$i];
            $operator = $kriterier[$i + 1];
            $värde = $kriterier[$i + 2];
            $villkor[] = "$kolumn $operator '%s'";
            $params[] = $värde;
        }
        $this->where .= implode(" AND ", $villkor);
    
        // Slutligen sätter ihop hela SQL-frågan
        $sql = $this->Select . ' ' . $this->From . ' ' . $this->where . ' ' . $this->groupBy . ' ' . $this->limit . ' ' . $this->orderBy;
    
        // Utför SQL-frågan och hämta data
        $resultat = $db->get($type)->rows($sql, ...$params);
        $this->hasRunSQL = true;
        return $resultat;
    }
    
    /**
     * Lägger till en WHERE-klausul till databasfrågan.
     *
     * @param array $where En associativ array med villkor för WHERE-klausulen. Förväntas vara en array där varannan post är en kolumn och den följande är ett sökvärde, och var tredje är en operator (t.ex. 'AND' eller 'OR').
     * @return $this Referens till den aktuella instansen för kedjning.
     */

    public function where(array $where)
    {
        // Implementation för att hämta sista raden från databasen

        $this->where = "WHERE ";

        $villkor = array();
        // Loopar igenom varje kolumn, värde och operator i kriterierna
        for ($i = 0; $i < count($where); $i += 3) {
            $kolumn = $where[$i];
            $operator = $where[$i + 1];
            $value = $where[$i + 2];

            // Lägg till ett placeholders för värdet
            $this->data[] = $value;

            // Bygg upp sökvillkoret med placeholders för att undvika SQL-injektioner
            $villkor[] = "$kolumn $operator %s";
        }

        // Sammanfogar sökvillkoren med angiven operator
        $this->where .= implode(" ", $villkor);

        return $this;
    }

    /**
     * Skapar en pagineringsfunktion för att begränsa antalet poster per sida vid databashämtning.
     *
     * @param mixed $params Antingen ett heltal för currentPage, eller en array med nycklarna 'page', 'max' och 'params'.
     * @param int $max (valfritt) Det maximala antalet poster per sida, standard är 10.
     * @param string $type (valfritt) Typ av resultat som ska returneras, standard är "as:text".
     * @return $this Referens till den aktuella instansen för kedjning.
     */
    public function paging($currentPage = 1, int $max = 10, $type = "as:text")
    {

        if (is_array($currentPage)) {
            $currentPage = $currentPage['page'] ?? 1;
            $max = $currentPage['max'] ?? 10;
            $type = $currentPage['type'] ?? "as:text";
            $extraParams = $currentPage['params'] ?? [];
        } else {
            $extraParams = [];
        }

        // Skapa en instans av Pagination-klassen med det maximala antalet poster per sida

        $pagination = new \Sys\Record\Pagination($max);


        // Ange extra Params
        $pagination->extraParams($extraParams);

        // Ange aktuell sida 
        $pagination->currentPage($currentPage);

        // Sätt limit-egenskapen för pagineringen
        $this->limit = $pagination->limit();

        // Sätt typen för data som ska hämtas
        $this->typeGetData = $type;

        $this->paging = true;
        // Spara instansen av Pagination-klassen för framtida användning
        $this->pagination = $pagination;

        // Returnera instansen av record
        return $this;
    }

    /**
     * Sätter begränsningen för antalet rader som ska hämtas från en databasfråga.
     *
     * @param int $min Den minsta raden att hämta.
     * @param int $max Den största raden att hämta.
     * @return $this Referens till den aktuella instansen för kedjning.
     */
    public function limit(int $min, int $max)
    {
        $this->limit = "LIMIT $min, $max";
        return $this;
    }

    /**
     * Sätter en GROUP BY-klausul för databasfrågan.
     *
     * @param string $groupby Kolumnen som ska grupperas efter.
     * @return $this Referens till den aktuella instansen för kedjning.
     */
    public function groupBy(string $groupby)
    {
        // Implementation för att sätta en GROUP BY-klausul för databasfrågan
        $this->groupBy = "GROUP BY " . $groupby . ' ';
        return $this;
    }
}
