<?php

/**
 * Pagination
 *
 * Skapa en klickbar lista med länkar till sidor när du
 * behöver visa stora mängder data en bit i taget.
 *
 * <code>
 *
 *  vyn
 *
 *    <div class="pagination">
 *         <ul>
 *           <li class="prev">
 *               <?php echo $pagination->prev(_('Föregående')); ?>
 *           </li>
 *           <?php echo $pagination->links(); ?>
 *           <li class="next">
 *               <?php echo $pagination->next(_('Nästa')); ?>
 *           </li>
 *       </ul>
 *   </div> 
 * </code>
 * 
 * @category _("Pagination Class")
 */

namespace Sys\Record;

class Pagination
{

	/**
     * @var int Startindex för datahämtning
     * @access private
     */
    private $start = 0;

    /**
     * @var int Antal poster per sida
     * @access private
     */
    private $per_page = 10;

    /**
     * @var int Antal sidor
     * @access public
     */
    public $num_pages = 1;

    /**
     * @var int Totalt antal poster
     * @access public
     */
    public $num_rows = 0;

    /**
     * @var int Nästa sida
     * @access public
     */
    public $next_page = 2;

    /**
     * @var int Föregående sida
     * @access public
     */
    public $prev_page = 0;

    /**
     * @var array Extra parametrar för länkar
     * @access public
     */
    public $params = [];

    /**
     * @var int Aktuell sida
     * @access public
     */
    public $page = 1;

    /**
     * Sätter antal poster per sida
     * 
     * @param int $per_page Antal poster per sida
     */
	function __construct($per_page)
	{
		if (is_numeric($per_page)) {
			$this->start = 0;
			$this->per_page = $per_page;
		}
	}

    /**
     * Sätter extra parametrar för paginering
     * 
     * @param array $extraParams Array med extra parametrar
     */
    function extraParams(array $extraParams)
    {
        $this->params = $extraParams;
    }

    /**
     * Skapar länk för föregående sida
     * 
     * @param string $title Länktext för föregående sida
     * @return string HTML-kod för länken
     */
    function prev($title = "Prev")
    {
        if ($this->IsPrev()) {
            return "<a href='" . $this->buildLink($this->prev_page) . "'>$title</a>";
        } else {
            return "<span>$title</span>";
        }
    }

    /**
     * Skapar länk för nästa sida
     * 
     * @param string $title Länktext för nästa sida
     * @return string HTML-kod för länken
     */
    function next($title = "Next")
    {
        if ($this->IsNext()) {
            return "<a href='" . $this->buildLink($this->next_page) . "'>$title</a>";
        } else {
            return "<span>$title</span>";
        }
    }

   /**
 * Skapar sidlänkar
 * 
 * @param int $max Antal länkar att visa
 * @param string $dot Text för att representera utelämnade länkar
 * @param bool $center Centrera länkarna kring den aktuella sidan
 * @return string HTML-kod för sidlänkar
 */
function links(int $max = 10, $dot = "...", $center = false)
{
    $links = "";

    if ($this->num_pages <= $max) {
        // Visa alla sidor om antalet sidor är mindre än eller lika med max
        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($i == $this->page) {
                $links .= "<span>$i</span> ";
            } else {
                $links .= "<a href='" . $this->buildLink($i) . "'>$i</a> ";
            }
        }
    } else {
        // Centrera länkarna kring den aktuella sidan om $center är true
        if ($center) {
            $half = floor($max / 2);
            $start = max(1, $this->page - $half);
            $end = min($this->num_pages, $this->page + $half);

            // Justera start och slut om de är i början eller slutet
            if ($this->page <= $half) {
                $end = $max;
            } elseif ($this->page >= $this->num_pages - $half) {
                $start = $this->num_pages - $max + 1;
            }

            if ($start > 1 && $dot != "") {
                $links .= "<a href='" . $this->buildLink(1) . "'>1</a> ";
                $links .= $dot . " ";
            }

            for ($i = $start; $i <= $end; $i++) {
                if ($i == $this->page) {
                    $links .= "<span>$i</span> ";
                } else {
                    $links .= "<a href='" . $this->buildLink($i) . "'>$i</a> ";
                }
            }

            if ($end < $this->num_pages && $dot != "") {
                $links .= $dot . " ";
                $links .= "<a href='" . $this->buildLink($this->num_pages) . "'>" . $this->num_pages . "</a> ";
            }
        } else {
            // Visa från början till mitten eller mitten till slutet beroende på aktuell sida
            $start = max(1, $this->page - floor($max / 2));
            $end = min($this->num_pages, $start + $max - 1);

            if ($end - $start + 1 < $max) {
                $start = max(1, $end - $max + 1);
            }

            if ($start > 1 && $dot != "") {
                $links .= "<a href='" . $this->buildLink(1) . "'>1</a> ";
                $links .= $dot . " ";
            }

            for ($i = $start; $i <= $end; $i++) {
                if ($i == $this->page) {
                    $links .= "<span>$i</span> ";
                } else {
                    $links .= "<a href='" . $this->buildLink($i) . "'>$i</a> ";
                }
            }

            if ($end < $this->num_pages && $dot != "") {
                $links .= $dot . " ";
                $links .= "<a href='" . $this->buildLink($this->num_pages) . "'>" . $this->num_pages . "</a> ";
            }
        }
    }

    return $links;
}

    /**
     * Sätter aktuell sida
     * 
     * @param int $page Aktuell sida
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
     * Kollar om nästa sida finns
     * 
     * @return bool Om nästa sida finns
     */
	function IsNext()
    {
        return $this->page < $this->num_pages;
    }

    /**
     * Kollar om föregående sida finns
     * 
     * @return bool Om föregående sida finns
     */
	 function IsPrev()
	 {
		 return $this->prev_page > 0;
	 }

    /**
     * Hämtar SQL LIMIT/OFFSET-sträng
     * 
     * @return string SQL LIMIT/OFFSET-sträng
     */

	function limit()
	{
		return sprintf('LIMIT %d, %d', $this->start, $this->per_page);
	}

    /**
     * Sätter totalt antal poster
     * 
     * @param int $data Totalt antal poster
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

	/**
     * Bygger en URL för en specifik sida
     * 
     * @param int $page Sidanummer
     * @return string URL för sidan
     */
	private function buildLink($page)
    {
        $params = array_merge($this->params, ['page' => $page]);
        return '?' . http_build_query($params);
    }
}