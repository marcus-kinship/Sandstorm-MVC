<?

/**
 * Pagination
 *
 * Skapa en klickbar lista med länkar till sidor när du
 * behöver visa stora mängder data en bit i taget.
 *
 * <code>
 *  $p = new Pagination(3);
 *  $p->currentPage(Request::req('page'));
 *  $products = $product->getProductByUser($id, $p->limit());
 *  $p->num_rows($product->getNumberOfProductByUser($id));
 *
 *  vyn
 *
 *  <li class="prev">
 *      <? if($p->IsPrev()){ ?>
 *      <a href="?page=<?=$p->prev_page?>"><?=_('Föregående');?></a>
 *      <?}else{?>
 *          <span><?=_('Föregående');?></span>
 *      <? }?>
 *  
 *      </li>
 *      <? $number = 1;
 *      for($number; $number <= $p->num_pages; $number +=1 ){ ?>
 *      
 *      <li>
 *          <a href="?page=<?=$number?>"><?=$number?></a>
 *  </li>
 *      <? } ?>
 * 
 * 
 *      <li class="next">
 *      <? if($p->IsNext()){ ?>
 *      <a href="?page=<?=$p->next_page?>"><?=_('Nästa');?></a>
 *      <?}else{?>
 *          <span><?=_('Nästa');?></span>
 *      <? }?>
 *      </li>
 * </code>
 * 
 * @category _("Pagination Class")
 */

namespace dll;

class Pagination
{

	private $start = "";
	/**
	 * @var integer Antal poster per sida
	 * @access private
	 */
	private $per_page = "";
	/**
	 * @var integer Antal sidor
	 * @access public
	 */
	public $num_pages = "";
	/**
	 * @var integer Totalt antal poster
	 * @access public
	 */
	public $num_rows = "";

	public $next_page = "";
	public $prev_page = "";
	public $order = array();
	/**
	 * @var integer Aktuell sida
	 * @access private
	 */
	public $page;

	/**
	 * Sätta Antal poster per sida
	 * @param integer $per_page Antal poster per sida
	 */
	function __construct($per_page)
	{
		if (is_numeric($per_page)) {
			$this->start = 0;
			$this->per_page = $per_page;
		}
	}

	/**
	 * Sätt ordningen för resultatet.
	 *
	 * @return void
	 */
	function setOrder()
	{
		$args = func_get_args();
		if (array_key_exists(0, $args) && is_array($args[0])) {
			$this->order = $args[0];
		} else {
			$this->order = $args;
		}
	}


	/**
	 * Hämta ordningen för resultatet.
	 *
	 * @param mixed $value Värdet för ordningen
	 * @param mixed $def Standardvärde för ordningen
	 * @return string
	 */
	function getOrder($value = "", $def = "")
	{
		// Hantera om 'desc' eller 'asc' är inställt
		$valuea = $valued = $value;
		if ($value == "" && Request::req('desc') !== false) {
			$valued = Request::req('desc');
		}
		// Kolla om nyckeln finns i ordningen
		if (key_exists($valued, $this->order)) {
			$pos = strpos($valued, '.');
			if ($pos === false) {
				return 'ORDER BY `' . $this->order[$valued] . '` DESC ';
			} else {
				return 'ORDER BY ' . $this->order[$valued] . ' DESC ';
			}
		}
		// Hantera 'asc'
		if ($value == "" && Request::req('asc') !== false) {
			$valuea = Request::req('asc');
		}
		// Kolla om nyckeln finns i ordningen
		if (key_exists($valuea, $this->order)) {
			$pos = strpos($valuea, '.');
			if ($pos === false) {
				return 'ORDER BY `' . $this->order[$valuea] . '` ASC ';
			} else {
				return 'ORDER BY ' . $this->order[$valuea] . ' ASC ';
			}
		}
		// Returnera standardordningen om inget matchas
		if ($def == "") {
			return "";
		}
		return 'ORDER BY ' . $def;
	}

	/**
	 * Skriv ut ordningen som en HTTP GET-parameter.
	 *
	 * @param mixed $value Värdet för ordningen
	 * @return string
	 */
	function printOrder($value)
	{
		$key = array_search($value, $this->order);

		if (Request::req('desc') == $key) {

			return 'asc=' . $key;
		}

		if (Request::req('asc') == $key || Request::req('asc') == false
		) {

			return 'desc=' . $key;
		} else {
			return 'desc=' . $key;
		}
	}
	/**
	 * Sätta aktuell sida 
	 * @param integer $page Aktuell sida
	 */
	function currentPage($page)
	{

		if ($page == false || !isset($page)) {
			$this->page = 1;
		} else {
			$this->page = $page;
		}

		if ($page <= 1) {
			$this->start = 0;
		} else {
			$this->start = $page * $this->per_page - $this->per_page;
		}

		$this->prev_page = $this->page - 1;
		$this->next_page = $this->page + 1;
	}

	/**
	 * Kollar om nästa sidan är midre än antal sidor
	 * @return bool
	 */
	function IsNext()
	{
		if ($this->page < ceil($this->num_rows / $this->per_page)) {
			return true;
		} else {
			return false;
		}
	}
	/**
	 * Kollar om Föregående sidan är större än 0
	 * @return bool
	 */
	function IsPrev()
	{
		if ($this->prev_page > 0) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Hämta färdig SQL LIMIT/OFFSET-sträng
	 * @return string
	 */
	function limit()
	{
		return sprintf('LIMIT %d, %d', $this->start, $this->per_page);
	}

	/**
	 * Totalt antal poster
	 * @param integer $data   Totalt antal poster
	 */
	function num_rows($data)
	{
		$this->num_rows = $data;
		if ($this->num_rows == 0 || $this->per_page == 0) {
			$this->num_pages = $this->num_rows;
		} else {
			$this->num_pages = ceil(($this->num_rows / $this->per_page));
		}
	}
}
