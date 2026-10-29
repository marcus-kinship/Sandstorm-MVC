<?

namespace Sys\Record;

use Sys\Record\Kernel;
use Sys\Record\Traits\ToTrait; 
/**
 * 
 * Orm Klass 
 * 
 *
 * @category _("Record class för lägga till och uppdatera data")
 */
class To extends Kernel{

    use ToTrait;
    // Publika egenskaper

    public $db;                          // Databasanslutningsobjekt
    public $_type = false;               // Typförvaring för att bestämma specifika operationer
    

    // Privata egenskaper

    private $options;                    // Diverse alternativ för olika funktioner
    private $where = array();            // Array för lagring av villkor för SQL-frågor
    private $cache = false;              // Anger om cachning är aktiverad eller inte
    private $lengthofdata = "";          // Variabel för att lagra längden på data
    private $limit = "";                 // Begränsningen för antalet rader som ska hämtas från en databasfråga.
    private $repairAfterSQLRun = false;  // Anger om det behövs reparation efter SQL-körning
    /**
     * Konstruktormetod för To.
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

}