<?

use \Sys\Record\Kernel;
use \Sys\Record\Traits\ToTrait;
use \Sys\Record\Traits\FromTrait;


/**
 * ORM Klass
 * 
 * @category _("Record class")
 */
class Record extends Kernel implements \ArrayAccess, \Iterator, \Countable
{
    use ToTrait, FromTrait;

    // Publika egenskaper
    public $db;  // Databasanslutningsobjekt
    public $_type = false;  // Typförvaring för att bestämma specifika operationer

    // Privata egenskaper
    private $options;                    // Diverse alternativ för olika funktioner
    private $where = "";                 // Array för lagring av villkor för SQL-frågor
    private $cache = false;              // Anger om cachning är aktiverad eller inte
    private $lengthofdata = "";          // Variabel för att lagra längden på data
    private $limit = "";                 // Begränsningen för antalet rader som ska hämtas från en databasfråga
    protected $hasRunSQL = false;          // Anger om SQL-frågan har körts
    private $paging = false;             // Anger om paginering är aktiverad
    private $pagination = null;          // Lagrar pagineringsinställningar
    private $Select = "";                // SELECT-sats för SQL-frågan
    private $From = "";                  // FROM-sats för SQL-frågan
    private $typeGetData = "as:text";    // Typ av data som ska hämtas, standard är text
    private $groupBy = "";               // GROUP BY-sats för SQL-frågan
    private $orderBy = "";               // ORDER BY-sats för SQL-frågan
    private $relations = [];             // För att specificera och hantera relationer mellan flera tabeller
    private $useRelations = false;       // Anger om relationer är aktiverad
    private $position = 0;               // Aktuell position för Iterator
    protected $outdata = "as:obj";         // Utdata 

    /**
     * Konstruktormetod för Record-klassen.
     *
     * @param string $table Namnet på tabellen.
     * @param array $options (valfritt) Alternativ för konfiguration.
     */
    public function __construct(string $table = "", array $options = [])
    {
        $this->options = $options;  // Sätt alternativ för instansen
        $this->table = $table;  // Sätt tabellnamnet för instansen
    }

    /**
     * Kontrollerar om en given offset finns.
     *
     * @param mixed $offset Den offset som ska kontrolleras.
     * @return bool Returnerar true om offseten finns, annars false.
     */
    public function offsetExists($offset): bool
    {

        return isset($this->data[$offset]);
    }

    /**
     * check data.
     *
     * @param mixed $offset Offseten vars värde ska hämtas.
     * @return mixed Returnerar värdet för offseten, eller null om offseten inte finns.
     */
    public function print_r()
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }

        print_r($this->data);
    }

    /**
     * Hämtar värdet för en given offset.
     *
     * @param mixed $offset Offseten vars värde ska hämtas.
     * @return mixed Returnerar värdet för offseten, eller null om offseten inte finns.
     */
    public function offsetGet($offset): mixed
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }


        return $this->data[$offset] ?? null;
    }

    /**
     * Sätter värdet för en given offset.
     *
     * @param mixed $offset Offseten vars värde ska sättas.
     * @param mixed $value Värdet som ska sättas.
     * @return void
     */
    public function offsetSet($offset, $value): void
    {

        $this->data[$offset] = $value;
    }

    /**
     * Tar bort en given offset.
     *
     * @param mixed $offset Offseten som ska tas bort.
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Returnerar det aktuella elementet.
     *
     * @return mixed Det aktuella elementet.
     */
    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    /**
     * Returnerar nyckeln för det aktuella elementet.
     *
     * @return int Nyckeln för det aktuella elementet.
     */
    public function key(): int
    {
        return $this->position;
    }

    /**
     * Flyttar fram till nästa element.
     *
     * @return void
     */
    public function next(): void
    {
        ++$this->position;
    }

    /**
     * Återställer iteratorn till första elementet.
     *
     * @return void
     */
    public function rewind(): void
    {
        $this->position = 0;
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
    }

    /**
     * Kontrollerar om den aktuella positionen är giltig.
     *
     * @return bool Returnerar true om den aktuella positionen är giltig, annars false.
     */
    public function valid(): bool
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
        return isset($this->data[$this->position]);
    }

    /**
     * Returnerar antalet element.
     *
     * @return int Antalet element.
     */
    public function count(): int
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
        return count($this->data);
    }
}
