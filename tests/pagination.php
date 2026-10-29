<?php

/**
 * Tests for Pagination (Sys\Record\Pagination and the global name Pagination).
 *
 * Run: php -d extension=gettext tests/pagination.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

if (!extension_loaded('gettext')) {
    exit("Run with the gettext extension: php -d extension=gettext tests/pagination.php\n");
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/app/config/config.class.php";

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

echo "\nPages\n";
$p = (new Pagination(20))->currentPage(3)->num_rows(175);
check('the global name Pagination is Sys\Record\Pagination', $p instanceof Sys\Record\Pagination);
check('limit() for page 3 of 20 per page', $p->limit() === 'LIMIT 40, 20');
check('pages() and total()', $p->pages() === 9 && $p->total() === 175);
check('firstItem(), lastItem() and summary()', $p->firstItem() === 41 && $p->lastItem() === 60 && $p->summary() === '41–60 of 175', $p->summary());
check('hasPrev(), hasNext(), isFirst(), isLast()', $p->hasPrev() && $p->hasNext() && !$p->isFirst() && !$p->isLast());

$last = (new Pagination(20))->currentPage(9)->num_rows(175);
check('last page: lastItem() is the total, no next', $last->lastItem() === 175 && !$last->IsNext() && $last->isLast());
$too = (new Pagination(20))->currentPage(50)->num_rows(175);
check('a page after the last becomes the last page', $too->page === 9 && $too->limit() === 'LIMIT 160, 20');
foreach (['-3', 'abc', '2.5', '', null, "1 OR 1=1"] as $bad) {
    $q = (new Pagination(10))->currentPage($bad);
    if ($q->page !== 1) {
        check("a bad page value gives page 1 (" . var_export($bad, true) . ")", false, $q->page);
    }
}
check('bad page values (-3, abc, 2.5, "", null, "1 OR 1=1") give page 1', true);
$empty = (new Pagination(10))->currentPage(1)->num_rows(0);
check('no rows: one page, 0–0 of 0', $empty->pages() === 1 && $empty->summary() === '0–0 of 0' && !$empty->IsNext());
check('per page below 1 becomes 1', (new Pagination(0))->perPage() === 1);

echo "\nLinks\n";
$_GET = ['q' => 'sand <dune>', 'page' => '3'];
$p = (new Pagination(20))->currentPage(3)->num_rows(175);
check('url() keeps the other GET parameters and escapes them', $p->url(4) === '?q=sand+%3Cdune%3E&page=4', $p->url(4));
check('url() is kept inside the pages', $p->url(99) === '?q=sand+%3Cdune%3E&page=9');
check('prev()/next() link with rel', str_contains($p->prev(), 'href="?q=sand+%3Cdune%3E&amp;page=2" rel="prev"') && str_contains($p->next(), 'rel="next"'), $p->prev());
check('window() around page 3 of 9', (new Pagination(20))->currentPage(3)->num_rows(175)->window(5) === [1, 2, 3, 4, 5, null, 9], (new Pagination(20))->currentPage(3)->num_rows(175)->window(5));
check('window() in the middle has gaps on both sides', (new Pagination(10))->currentPage(50)->num_rows(1000)->window(5) === [1, null, 48, 49, 50, 51, 52, null, 100]);
check('window() with few pages shows all', (new Pagination(10))->currentPage(2)->num_rows(30)->window(5) === [1, 2, 3]);
check('links(): current page has aria-current', str_contains($p->links(5), '<span aria-current="page">3</span>'), $p->links(5));
check('render() gives <nav> with aria-label', str_starts_with($p->render(), '<nav class="pagination" aria-label="Pagination">'));
check('render() is empty with one page', (new Pagination(10))->currentPage(1)->num_rows(5)->render() === '');
check('keepQuery(false) and setPageParam()', (new Pagination(10))->keepQuery(false)->setPageParam('p')->currentPage(1)->num_rows(50)->url(2) === '?p=2');
check('extraParams() are added', (new Pagination(10))->keepQuery(false)->extraParams(['tag' => 'x'])->currentPage(1)->num_rows(50)->url(2) === '?tag=x&page=2');
check('toArray() for an API', $p->toArray() === ['page' => 3, 'per_page' => 20, 'total' => 175, 'pages' => 9, 'prev' => 2, 'next' => 4, 'from' => 41, 'to' => 60]);

echo "\nSorting\n";
$_GET = ['desc' => 'name'];
$s = (new Pagination(10))->setOrder(['name' => 't.name', 'age' => 'age']);
check('getOrder() from ?desc=name', $s->getOrder() === 'ORDER BY `t`.`name` DESC ', $s->getOrder());
$_GET = ['asc' => 'age'];
check('getOrder() from ?asc=age', $s->getOrder() === 'ORDER BY `age` ASC ');
$_GET = ['desc' => 'password'];
check('a column that is not allowed gives the default', $s->getOrder('', 't.id DESC') === 'ORDER BY t.id DESC');
$_GET = ['desc' => "name`; DROP TABLE x; --"];
check('an injected value gives nothing', $s->getOrder() === '');
$_GET = [];
check('printOrder(): first click sorts descending', $s->printOrder('t.name') === 'desc=name');
$_GET = ['desc' => 'name'];
check('printOrder(): second click sorts ascending', $s->printOrder('t.name') === 'asc=name');
check('setOrder() with a list uses the column as key', (new Pagination(10))->setOrder('t.name', 'age')->order === ['t.name' => 't.name', 'age' => 'age']);
check('a bad column in setOrder() throws when used', (function () {
    $_GET = ['asc' => 'bad'];
    try {
        (new Pagination(10))->setOrder(['bad' => 'name; DROP'])->getOrder();
    } catch (InvalidArgumentException $e) {
        return true;
    }
    return false;
})());

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
