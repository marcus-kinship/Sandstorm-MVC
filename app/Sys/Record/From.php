<?

namespace Sys\Record;

use Sys\Record\Kernel;
use Sys\Record\Traits\FromTrait;

/**
 * 
 * Orm Klass 
 * 
 *
 * @category _("Record class för Hämta data")
 */
class From extends Kernel implements \ArrayAccess, \Iterator, \Countable
{

    use FromTrait;
     // Privata egenskaper

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

    /**
     * Konstruktormetod för From.
     *
     * @param string $table Namnet på tabellen.
     * @param array $options (valfritt) Alternativ för konfiguration.
     */
    public function __construct(string $table = "", array $options = array())
    {
        $this->options = $options; // Sätt alternativ för instansen
        $this->table = $table;     // Sätt tabellnamnet för instansen
        $this->data = array();     // Initialisera data-arrayen
    }

    /**
     * Kontrollerar om en given offset finns.
     *
     * @param mixed $offset Den offset som ska kontrolleras.
     * @return bool Returnerar true om offseten finns, annars false.
     */
    public function offsetExists($offset): bool
    {
        // Kontrollera om den angivna offseten finns i datan
        return isset($this->data[$offset]);
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

    public function current(): mixed
    {
        return $this->data[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
    }

    public function valid(): bool
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
        return isset($this->data[$this->position]);
    }

    public function count(): int
    {
        if (!$this->hasRunSQL) {
            $this->data = $this->exec();
        }
        return count($this->data);
    }
}
