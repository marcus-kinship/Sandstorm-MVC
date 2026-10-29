<?php

namespace Sys\Record;

/**
 * Pagination: split a long list into pages, with links and sorting.
 *
 * With Record (the query, LIMIT and the total are handled for you):
 * <code>
 * $people = (new \Record\From('people'))->paging($_GET['page'] ?? 1, 20);
 * foreach ($people as $row) { ... }
 * echo $people->pagination->render();                       // <nav> with Prev, 1 2 3 … 9, Next
 * echo $people->pagination->summary();                      // "21–40 of 175"
 * </code>
 *
 * On its own:
 * <code>
 * $p = new Pagination(20);                                  // 20 per page
 * $p->currentPage($_GET['page'] ?? 1);
 * $p->num_rows($total);                                     // total number of rows
 * $sql = "SELECT ... " . $p->limit();                       // LIMIT 20, 20
 * </code>
 *
 * Sorting by a column the visitor chooses (only columns you allow):
 * <code>
 * $p->setOrder(['name' => 't.name', 'age' => 't.age']);     // key in the URL => column
 * $sql .= $p->getOrder('', 't.id DESC');                    // ?desc=name -> ORDER BY t.name DESC
 * echo '<a href="?' . $p->printOrder('t.name') . '">Name</a>';
 * </code>
 *
 * Links keep the other query string parameters of the page (filters,
 * search), and every value in the HTML is escaped.
 *
 * @category _("Pagination Class")
 */
class Pagination
{
    /** @var int Current page (1 = the first) */
    public $page = 1;

    /** @var int Number of pages (at least 1) */
    public $num_pages = 1;

    /** @var int Total number of rows */
    public $num_rows = 0;

    /** @var int Page after the current one */
    public $next_page = 2;

    /** @var int Page before the current one (0 = none) */
    public $prev_page = 0;

    /** @var array Extra query string parameters for the links */
    public $params = [];

    /** @var array<string, string> Sortable columns: key in the URL => column */
    public $order = [];

    /** @var int First row of the page (for LIMIT) */
    private int $start = 0;

    /** @var int Rows per page */
    private int $per_page = 10;

    /** @var string Name of the page parameter in the URL */
    private string $pageParam = 'page';

    /** @var bool Keep the other $_GET parameters in the links */
    private bool $keepQuery = true;

    /**
     * @param int $per_page Rows per page (at least 1)
     */
    public function __construct($per_page = 10)
    {
        $this->per_page = max(1, (int) $per_page);
    }

    /**
     * Set the current page. Anything that is not a positive number gives page 1;
     * a page after the last one gives the last page once num_rows() is known.
     *
     * @param mixed $page For example $_GET['page'] ?? 1
     * @return $this
     */
    public function currentPage($page)
    {
        $page = filter_var($page, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $this->page = $page === false ? 1 : $page;
        $this->update();

        return $this;
    }

    /**
     * Set the total number of rows (and so the number of pages).
     *
     * @param int $data Total number of rows
     * @return $this
     */
    public function num_rows($data)
    {
        $this->num_rows = max(0, (int) $data);
        $this->num_pages = max(1, (int) ceil($this->num_rows / $this->per_page));

        if ($this->page > $this->num_pages) {
            $this->page = $this->num_pages;
        }
        $this->update();

        return $this;
    }

    /**
     * Same as num_rows().
     *
     * @param int $total
     * @return $this
     */
    public function setTotal(int $total)
    {
        return $this->num_rows($total);
    }

    /**
     * Extra query string parameters for the links (for example a search).
     *
     * @param array $extraParams
     * @return $this
     */
    public function extraParams(array $extraParams)
    {
        $this->params = $extraParams;
        return $this;
    }

    /**
     * Name of the page parameter in the URL (default "page").
     *
     * @param string $name
     * @return $this
     */
    public function setPageParam(string $name)
    {
        $this->pageParam = $name;
        return $this;
    }

    /**
     * Whether the links keep the page's other $_GET parameters (default true).
     *
     * @param bool $keep
     * @return $this
     */
    public function keepQuery(bool $keep = true)
    {
        $this->keepQuery = $keep;
        return $this;
    }

    /** @return int Rows per page */
    public function perPage(): int
    {
        return $this->per_page;
    }

    /** @return int First row of the page, from 0 (for LIMIT) */
    public function offset(): int
    {
        return $this->start;
    }

    /** @return int Total number of rows */
    public function total(): int
    {
        return $this->num_rows;
    }

    /** @return int Number of pages */
    public function pages(): int
    {
        return $this->num_pages;
    }

    /** @return int Number shown for the first row on the page, from 1 (0 when empty) */
    public function firstItem(): int
    {
        return $this->num_rows === 0 ? 0 : $this->start + 1;
    }

    /** @return int Number shown for the last row on the page */
    public function lastItem(): int
    {
        return min($this->start + $this->per_page, $this->num_rows);
    }

    /** @return bool Whether there is a next page */
    public function IsNext()
    {
        return $this->page < $this->num_pages;
    }

    /** @return bool Whether there is a previous page */
    public function IsPrev()
    {
        return $this->page > 1;
    }

    /** @return bool Same as IsNext() */
    public function hasNext(): bool
    {
        return $this->IsNext();
    }

    /** @return bool Same as IsPrev() */
    public function hasPrev(): bool
    {
        return $this->IsPrev();
    }

    /** @return bool Whether this is the first page */
    public function isFirst(): bool
    {
        return $this->page === 1;
    }

    /** @return bool Whether this is the last page */
    public function isLast(): bool
    {
        return $this->page >= $this->num_pages;
    }

    /**
     * LIMIT for the current page.
     *
     * @return string "LIMIT start, per page"
     */
    public function limit()
    {
        return sprintf('LIMIT %d, %d', $this->start, $this->per_page);
    }

    /**
     * URL (query string) for a page.
     *
     * @param int $page
     * @return string For example "?q=sand&page=3"
     */
    public function url(int $page): string
    {
        $params = $this->keepQuery ? $_GET : [];
        $params = array_merge($params, $this->params, [$this->pageParam => max(1, min($page, $this->num_pages))]);

        return '?' . http_build_query($params);
    }

    /**
     * Link to the previous page, or a <span> when there is none.
     *
     * @param string $title HTML for the link text
     * @return string
     */
    public function prev($title = "Prev")
    {
        return $this->IsPrev()
            ? '<a href="' . $this->escape($this->url($this->prev_page)) . '" rel="prev">' . $title . '</a>'
            : '<span>' . $title . '</span>';
    }

    /**
     * Link to the next page, or a <span> when there is none.
     *
     * @param string $title HTML for the link text
     * @return string
     */
    public function next($title = "Next")
    {
        return $this->IsNext()
            ? '<a href="' . $this->escape($this->url($this->next_page)) . '" rel="next">' . $title . '</a>'
            : '<span>' . $title . '</span>';
    }

    /**
     * Link to the first page, or a <span> on the first page.
     *
     * @param string $title HTML for the link text
     * @return string
     */
    public function first($title = "First")
    {
        return $this->isFirst() ? '<span>' . $title . '</span>' : '<a href="' . $this->escape($this->url(1)) . '">' . $title . '</a>';
    }

    /**
     * Link to the last page, or a <span> on the last page.
     *
     * @param string $title HTML for the link text
     * @return string
     */
    public function last($title = "Last")
    {
        return $this->isLast() ? '<span>' . $title . '</span>' : '<a href="' . $this->escape($this->url($this->num_pages)) . '">' . $title . '</a>';
    }

    /**
     * Page numbers around the current page: 1 … 4 5 [6] 7 8 … 20.
     *
     * @param int    $max    Most page numbers to show (first and last not counted)
     * @param string $dot    Text between gaps ('' = no first/last link and no gap)
     * @param bool   $center Kept for older code; the current page is always in the middle
     * @return string The current page as <span aria-current="page">, the others as <a>
     */
    public function links(int $max = 10, $dot = "...", $center = false)
    {
        $html = '';
        foreach ($this->window($max) as $page) {
            if ($page === null) {
                $html .= '<span class="gap">' . $dot . '</span> ';
            } elseif ($page === $this->page) {
                $html .= '<span aria-current="page">' . $page . '</span> ';
            } else {
                $html .= '<a href="' . $this->escape($this->url($page)) . '">' . $page . '</a> ';
            }
        }

        return rtrim($html);
    }

    /**
     * The page numbers to show, with null for a gap.
     *
     * @param int  $max  Most page numbers around the current page
     * @param bool $ends Also the first and last page, with gaps
     * @return array<int, int|null>
     */
    public function window(int $max = 10, bool $ends = true): array
    {
        $max = max(1, $max);

        if ($this->num_pages <= $max) {
            return range(1, $this->num_pages);
        }

        $start = max(1, $this->page - intdiv($max, 2));
        $end = min($this->num_pages, $start + $max - 1);
        $start = max(1, $end - $max + 1);

        $pages = range($start, $end);
        if ($ends && $start > 1) {
            array_unshift($pages, ...($start > 2 ? [1, null] : [1]));
        }
        if ($ends && $end < $this->num_pages) {
            array_push($pages, ...($end < $this->num_pages - 1 ? [null, $this->num_pages] : [$this->num_pages]));
        }

        return $pages;
    }

    /**
     * Complete navigation: <nav class="pagination"> with Prev, the page numbers and Next.
     *
     * Nothing when there is only one page.
     *
     * @param int    $max   Most page numbers to show
     * @param string $prev  HTML for "previous"
     * @param string $next  HTML for "next"
     * @param string $label aria-label for screen readers
     * @return string
     */
    public function render(int $max = 7, string $prev = '&laquo;', string $next = '&raquo;', string $label = 'Pagination'): string
    {
        if ($this->num_pages <= 1) {
            return '';
        }

        return '<nav class="pagination" aria-label="' . $this->escape($label) . '">'
            . $this->prev($prev) . ' ' . $this->links($max, '&hellip;') . ' ' . $this->next($next) . '</nav>';
    }

    /**
     * "21–40 of 175" (numbers only, so the words can be translated around it).
     *
     * @param string $format sprintf format with first, last and total
     * @return string
     */
    public function summary(string $format = '%d–%d of %d'): string
    {
        return sprintf($format, $this->firstItem(), $this->lastItem(), $this->num_rows);
    }

    /**
     * Everything about the page, for an API response.
     *
     * @return array{page: int, per_page: int, total: int, pages: int, prev: int|null, next: int|null, from: int, to: int}
     */
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'per_page' => $this->per_page,
            'total' => $this->num_rows,
            'pages' => $this->num_pages,
            'prev' => $this->IsPrev() ? $this->prev_page : null,
            'next' => $this->IsNext() ? $this->next_page : null,
            'from' => $this->firstItem(),
            'to' => $this->lastItem(),
        ];
    }

    /**
     * Columns the visitor may sort by: key in the URL => column.
     *
     * <code>
     * $p->setOrder(['name' => 't.name', 'age' => 't.age']);
     * $p->setOrder('t.name', 't.age');        // the key is the column
     * </code>
     *
     * @param mixed ...$columns
     * @return $this
     */
    public function setOrder(...$columns)
    {
        $columns = isset($columns[0]) && is_array($columns[0]) ? $columns[0] : $columns;

        $this->order = [];
        foreach ($columns as $key => $column) {
            $this->order[is_int($key) ? (string) $column : (string) $key] = (string) $column;
        }

        return $this;
    }

    /**
     * ORDER BY from ?desc=<key> or ?asc=<key>, only for columns set with setOrder().
     *
     * @param string $value Key to sort by (instead of reading ?desc= / ?asc=)
     * @param string $def   ORDER BY when nothing (valid) was asked for, without "ORDER BY"
     * @return string "ORDER BY `column` DESC " or "" when there is nothing to sort by
     */
    public function getOrder($value = "", $def = "")
    {
        foreach (['desc' => 'DESC', 'asc' => 'ASC'] as $param => $direction) {
            $key = $value !== '' ? $value : ($_GET[$param] ?? '');
            if (is_string($key) && $key !== '' && isset($this->order[$key])) {
                return 'ORDER BY ' . self::quoteColumn($this->order[$key]) . ' ' . $direction . ' ';
            }
            if ($value !== '') {
                break;
            }
        }

        return $def === '' ? '' : 'ORDER BY ' . $def;
    }

    /**
     * Query string for a sort link: the column sorts descending the first
     * time, and switches to ascending when it already sorts descending.
     *
     * @param string $value The column (a value given to setOrder())
     * @return string For example "desc=name"
     */
    public function printOrder($value)
    {
        $key = array_search($value, $this->order, true);
        if ($key === false) {
            return '';
        }

        return ($_GET['desc'] ?? null) === $key ? 'asc=' . rawurlencode($key) : 'desc=' . rawurlencode($key);
    }

    /**
     * Start row and previous/next page from the current page.
     *
     * @return void
     */
    private function update(): void
    {
        $this->start = ($this->page - 1) * $this->per_page;
        $this->prev_page = $this->page - 1;
        $this->next_page = $this->page + 1;
    }

    /**
     * `t`.`name` for t.name; anything that is not a plain column name is refused.
     *
     * @param string $column
     * @return string
     * @throws \InvalidArgumentException
     */
    private static function quoteColumn(string $column): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            throw new \InvalidArgumentException(sprintf(_('Invalid sort column: %s'), $column));
        }

        return '`' . str_replace('.', '`.`', $column) . '`';
    }

    /**
     * Escape for an HTML attribute.
     */
    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
