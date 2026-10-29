<?

namespace Sys\Record\SQL;

use Sys\Record\Kernel;

/**
 * Hanterar SQL-operationer relaterade till skapande, ändring och hantering av tabeller.
 * 
 * @category _("Scheme class")
 */
class Scheme extends Kernel
{

	// Variabler för att hantera tillstånd och kolumnläggning
	private $after = "";
	private $addColumn = array();

	// Variabel för att hålla tabellmotorn
	protected $engine;

	// Variabel för att SQL tabellmotorn
	private $engineSQL;

	// Variabel för att hålla statusen för SQL-operationen
	protected $status;

	// Variabel för att spåra om en primärnyckel har angetts
	private $key = false;

	// Array för att lagra SQL-frågorna för att byta namn på kolumner 
	private $renameColumns = [];

	// Array för att ändra på en ny kolumner och egenskaper för kolumner
	private $changeLine = [];

	// En array som innehåller de tillåtna motornamnen
	private $availableEngines =	[
		'MyISAM', 
		'InnoDB', 
		'Memory', 
		'CSV', 
		'Archive', 
		'Blackhole', 
		'NDB', 
		'Merge', 
		'Federated', 
		'Example'
	];

	// Standardinställningar för kolumner
	private $defaults = [
		'id' => [
			'type' => 'BIGINT UNSIGNED',
			'default' => 'NOT NULL',
			'increment' => 'AUTO_INCREMENT',
			'primary' => 'PRIMARY KEY'
		]
	];

	/**
	 * Konstruktor för Scheme-klassen.
	 * 
	 * @param string $table Namnet på tabellen.
	 * 
	 * @throws SystemException om $table är tomt.
	 */
	public function __construct($table = "")
	{
		if ($table != "") {
			$this->table = $table;
		} else {
			throw new \SystemException('You must specify a table name');
		}
	}

	/**
	 * Metod för att sätta och ändrar en motorn för tabellen.
	 * 
	 * @param string $engine Motorn som ska användas för tabellen.
	 * 
	 * @return $this
	 */
	public function setEngine(string $engine = "InnoDB")
	{

		// Validera att den angivna motorn är en av de tillåtna
		if (in_array($engine, $this->availableEngines)) {
			$this->status = "getEngineSQL";
			// Konstruera SQL-frågan för att ändra motorn
			$this->engineSQL = 'ALTER TABLE `' . $this->table . '` ENGINE ' . $engine;
		} else {
			// Om motorn inte är tillåten, kasta ett undantag eller hantera fel på annat sätt
			throw new \SystemException(sprintf(_("Ogiltig motor: %s"), $engine));
		}
		return $this;
	}

	private function getEngineSQL(){
		return $this->engineSQL;
	}

	/**
	 * Metod för att lägga till motorn innan för skapa en tabellen.
	 * 
	 * @param string $engine Motorn som ska användas för tabellen.
	 * 
	 * @return $this
	 */
	public function addEngine(string $engine = "InnoDB")
	{
		// Validera och sätt motorn om den är tillåten
		if (in_array($engine, $this->availableEngines)) {
			$this->engine = $engine;
		} else {
			// Om motorn inte är tillåten, kasta ett undantag eller hantera fel på annat sätt
			throw new \SystemException(sprintf(_("Ogiltig motor: %s"),$engine));
		}
		return $this;
	}

	/**
	 * Metod för att sätta namnet på en egenskap.
	 * 
	 * @param string $name  Namnet på egenskapen.
	 * @param mixed  $value Värdet på egenskapen.
	 * 
	 * @return $this
	 */
	public function name($name, $value = null)
	{
		$this->define($name, $value);
		return $this;
	}

	/**
	 * Metod för att sätta statusen för SQL-operationen.
	 * 
	 * @param string $status Statusen för SQL-operationen.
	 * 
	 * @return void
	 */
	public function setStatus($status)
	{
		$this->status = $status;
	}

	/**
	 * Lägger till en ny egenskap med det angivna namnet och egenskaperna.
	 *
	 * @param string $name Namnet på kolumnen.
	 * @param array $properties En array som specifierar egenskaperna för egenskapen.
	 */
	public function addProperty(string $name, array $properties)
	{
		$this->status = "getPropertySQL";
		$this->properties[$name] = $properties;
	}

	/**
	 * Modifierar en befintlig egenskap med det angivna namnet till nya egenskaper.
	 *
	 * @param string $name Namnet på kolumnen som ska modifieras.
	 * @param array $newProperties En array som specifierar de nya egenskaperna för egenskapen.
	 */
	public function modifyProperty(string $name, array $newProperties)
	{
		if (isset($this->properties[$name])) {
			$this->status = "getPropertySQL";
			$this->properties[$name] = $newProperties;
		} else {
			// Kastar  undantag eller fel om egenskapen inte finns
			throw new \SystemException(_("Fel Den kolumnen inte finns"));
		}
	}

	/**
	 * Genererar SQL för att modifiera egenskaper hos tabellen.
	 *
	 * @return string SQL-frågan för att modifiera tabellens egenskaper.
	 */
	protected function getPropertySQL()
	{
		$partSQL = 'ALTER TABLE `' . $this->table . '` ';
		$tmp = [];

		foreach ($this->properties as $columnName => $newDataType) {
			// Kombinera $newDataType-elementen till en sträng med mellanrum emellan
			$dataTypeString = implode(" ", $newDataType);
			// Lägg till den modifierade datatypen för kolumnen till $tmp-arrayen
			$tmp[] = 'MODIFY `' . $columnName . '` ' . $dataTypeString;
		}

		// Kombinera alla modifieringar i $tmp till en enda sträng med kommatecken emellan
		$partSQL .= implode(", ", $tmp);

		return $partSQL;
	}

	/**
	 * Metod för att skapa en SQL-fråga för att skapa en tabell.
	 * 
	 * @return string SQL-frågan för att skapa tabellen.
	 */
	public function createTable()
	{
		$this->status = "createTable";

		// Konstruera SQL-frågan för att skapa tabellen
		$sql = 'CREATE TABLE IF NOT EXISTS `' . $this->table . '` (';

		// Kontrollera om det finns en primärnyckel
		$primaryExists = false;
		foreach ($this->properties as $prop) {
			if (key_exists('primary', $prop)) {
				$primaryExists = true;
				break;
			}
		}

		// Lägg till standard "id" kolumnen om ingen primärnyckel finns
		if (!$primaryExists) {
			$sql .= '`id` ' . implode(" ", $this->defaults['id']) . ',';
		}

		// Lägg till varje egenskap som en kolumn i tabellen
		foreach ($this->properties as $k => $v) {
			$tmp = $v;
			unset($tmp['type']);
			unset($tmp['placeholder']);
			unset($tmp['length']);
			$sql .= ' `' . $k . '`  ' . implode(" ", $tmp) . ',';
		}

		$sql = rtrim($sql, ',');
		$sql .= ') ENGINE=' . $this->engine . ';';

		return $sql;
	}

	/**
	 * Byter namn på en befintlig kolumn i tabellen och lagrar SQL-frågan.
	 * 
	 * @param string $old Det gamla namnet på kolumnen.
	 * @param string $new Det nya namnet på kolumnen.
	 * 
	 * @return void
	 */
	public function rename($old, $new)
	{
		$this->status = "getRenameSQL";
		$this->renameColumns[] = 'ALTER TABLE `' . $this->table . '` CHANGE `' . $old . '` `' . $new . '` ' . implode(" ", $this->properties[$old]);
	}

	/**
	 * Genererar SQL för att byta namn på alla kolumner som har ändrats.
	 * 
	 * @return string SQL-frågan för att byta namn på kolumner.
	 */
	protected function getRenameSQL()
	{
		return implode('; ', $this->renameColumns) . ';';
	}

	/**
	 * Skyddad metod för att skapa en SQL-fråga för att lägga till kolumner i en befintlig tabell.
	 * 
	 * @return string SQL-frågan för att lägga till kolumner.
	 */
	protected function createColumns()
	{
		$sql = 'ALTER TABLE `' . $this->table . '` ';
		$sql .= implode(', ', $this->addColumn);
		return $sql;
	}

	/**
	 * Metod för att ändra på en ny kolumn och egenskaper för kolumnen i tabellen.
	 * 
	 * @param string $old Det gamla namnet på kolumnen.
	 * @param string $new Det nya namnet på kolumnen.
	 * @param array  $properties Egenskaper för kolumnen.
	 * 
	 * @return void
	 */

	public function changeLine(string $old, string $new, array $properties)
	{
		// Sätter status för aktionen
		$this->status = "changeLineSQL";

		// Konstruerar delen av SQL-frågan för att ändra kolumnen
		$partSQL = "CHANGE `$old` `$new` " . implode(" ", $properties);

		// Lägger till den färdiga delen av SQL-frågan till arrayen
		$this->changeLine[] = $partSQL;
	}

	public function changeLineSQL()
	{
		// Bygger upp SQL-frågan för att ändra kolumnen
		$sql = 'ALTER TABLE `' . $this->table . '` ';

		// Lägger till alla delar av SQL-frågan från changeLine-arrayen
		$sql .= implode(', ', $this->changeLine);

		// Returnerar den färdiga SQL-frågan
		return $sql;
	}

	/**
	 * Metod för att lägga till en ny kolumn i tabellen.
	 * 
	 * @param string $name       Namnet på kolumnen.
	 * @param array  $properties Egenskaper för kolumnen.
	 * 
	 * @return void
	 */
	public function addColumn(string $name, array $properties)
	{
		$this->status = "createColumns";
		$partSQL = "";
		// Lägg till kolumnen om den inte redan finns
		$partSQL .= 'ADD COLUMN IF NOT EXISTS `' . $name . '`';

	
		unset($properties['type']);
		unset($properties['length']);
		unset($properties['placeholder']);
		// Lägg till kolumnens egenskaper
		$partSQL .= ' ' . implode(" ", $properties);

		// Kontrollera om det finns en specifik kolumn att lägga till efter
		if ($this->after) {
			$partSQL .= ' AFTER `' . $this->after . '`';
		}

		// Lägg till kolumnen i listan över kolumner att lägga till
		$this->after = $name;
		$this->addColumn[] = $partSQL;
	}

	/**
	 * Hämtar den relevanta SQL-frågan baserat på statusen.
	 * 
	 * @return string Den relevanta SQL-frågan.
	 */
	public function get()
	{
		switch ($this->status) {
			case "createTable":
				return $this->createTable();
				break;
			case "createColumns":
				return $this->createColumns();
				break;
			case "getRenameSQL":
				return $this->getRenameSQL();
				break;
			case "getPropertySQL":
				return $this->getPropertySQL();
				break;
			case "changeLineSQL":
				return $this->changeLineSQL();
				break;
			case "getEngineSQL":
				return $this->getEngineSQL();
				break;
			default:
				return ''; // Returnera tom sträng om statusen inte är satt korrekt
				break;
		}
	}
}
