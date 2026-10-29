<?php

/**
 * Tests for the Record ORM.
 *
 * Part 1 needs no database: SQL building and column types.
 * Part 2 runs against the test pool in app/config/mysql/test.db.php (see
 * tests/database.php); it is skipped when that file is missing. The database
 * in the pool is removed at the end, so never point it at real data.
 *
 * Run: php -d extension=mysqli -d extension=gettext -d extension=mbstring tests/record.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['gettext', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/record.php\n");
    }
}

$_SERVER['DOCUMENT_ROOT'] = $root;
foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/',
    'funs' => 'app/funs/', 'site' => 'site/', 'mysql' => 'app/config/mysql/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['HTTP_devmode'] = 'true';

include "$root/app/config/config.class.php";

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

function throws(callable $fn, string $class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

/** Opens up the protected helpers for the tests */
class TestRecord extends Record
{
    public static function where_(array $c): array { return self::buildWhere($c); }
}

echo "\nWHERE\n";
check('one condition', TestRecord::where_(['name', '=', 'Ada']) === ['`name` = %s', ['Ada']], TestRecord::where_(['name', '=', 'Ada']));
check('int value uses %d', TestRecord::where_(['age', '>', 30]) === ['`age` > %d', [30]]);
check('AND by default, OR when given', TestRecord::where_(['a', '=', 1, 'b', '=', 2, 'OR', 'c', '=', 3])[0] === '`a` = %d AND `b` = %d OR `c` = %d');
check('IN with a list', TestRecord::where_(['id', 'IN', [1, 2]]) === ['`id` IN (%s, %s)', [1, 2]]);
check('IN with an empty list matches nothing', TestRecord::where_(['id', 'IN', []])[0] === '0 = 1');
check('null becomes IS NULL', TestRecord::where_(['deleted', '=', null]) === ['`deleted` IS NULL', []]);
check('associative array', TestRecord::where_(['name' => 'Ada', 'age' => 36]) === ['`name` = %s AND `age` = %d', ['Ada', 36]]);
check('t.name is quoted', TestRecord::where_(['t.name', 'LIKE', 'A%'])[0] === '`t`.`name` LIKE %s');
check('unknown operator throws', throws(fn() => TestRecord::where_(['a', '; DROP', 1]), InvalidArgumentException::class));
check('bad column name throws', throws(fn() => TestRecord::where_(['a`; --', '=', 1]), InvalidArgumentException::class));

echo "\nColumn types\n";
$r = new Record('people');
$r->define('name', 'Ada')->define('age', 36)->define('flag', true)->define('score', '4.5')
    ->define('born', '1990-12-10')->define('at', '2026-10-29 12:00:00')->define('zip', '01234')->define('tags', ['a', 'b']);
$types = array_map(fn($c) => $c['sql'], $r->properties);
check('text -> VARCHAR(255)', $types['name'] === 'VARCHAR(255)');
check('int -> INT, %d', $types['age'] === 'INT' && $r->properties['age']['placeholder'] === '%d');
check('bool -> TINYINT(1)', $types['flag'] === 'TINYINT(1)' && $r->data['flag'] === 1);
check('decimal -> DOUBLE', $types['score'] === 'DOUBLE');
check('date -> DATE, datetime -> DATETIME', $types['born'] === 'DATE' && $types['at'] === 'DATETIME');
check('leading zero stays text (01234)', $types['zip'] === 'VARCHAR(255)');
check('array -> JSON text', $types['tags'] === 'VARCHAR(255)' && $r->data['tags'] === '["a","b"]');

$r->define('age', 3000000000);
check('a larger number widens INT -> BIGINT', $r->properties['age']['sql'] === 'BIGINT');
$r->define('age', 'forty');
check('text in a number column -> VARCHAR', $r->properties['age']['sql'] === 'VARCHAR(255)');
$r->define('name', str_repeat('x', 300));
check('long text widens VARCHAR -> TEXT', $r->properties['name']['sql'] === 'TEXT');

$c = new Record('people');
check('text(5)->max() throws for longer values', throws(fn() => $c->text(5)->max()->define('nick', 'toolong'), SystemException::class));
$c->text(3)->cut()->define('nick', 'abcdef');
check('text(3)->cut() cuts', $c->data['nick'] === 'abc');
check('text(4)->min() throws for shorter values', throws(fn() => $c->text(4)->min()->define('code', 'ab'), SystemException::class));
check('a bad column name throws', throws(fn() => $c->define('na me', 1), InvalidArgumentException::class));

$m = (new Record('people'))->multi();
$m->define('name', 'Ada')->define('age', 36)->define('name', 'Bo')->define('age', 41);
check('multi(): a repeated name starts a new row', $m->get('name', 1) === 'Bo' && $m->get('age', [0])[0]['age'] === 36);

echo "\nSQL\n";
$q = Sys\Record\QueryBuilder\Inserts::rows('people', $r->properties, [['name' => 'Ada', 'age' => null]]);
check('INSERT writes NULL for missing and null values', str_contains($q[0], "VALUES (%s, NULL, NULL") && $q[1] === 'Ada', $q);
$u = Sys\Record\QueryBuilder\Updates::row('people', ['id' => ['placeholder' => '%d'], 'name' => ['placeholder' => '%s']], ['id' => 1, 'name' => 'Ada'], '`id` = %d', [1]);
check('UPDATE skips id and adds the WHERE arguments', $u === ['UPDATE `people` SET `name` = %s WHERE `id` = %d', 'Ada', 1], $u);
$create = Sys\Record\SQL\Scheme::createTable('people', ['name' => ['sql' => 'VARCHAR(255)']]);
check('CREATE TABLE has an id and NULL columns', $create === 'CREATE TABLE IF NOT EXISTS `people` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(255) NULL DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', $create);
check('ADD COLUMN without IF NOT EXISTS (works in MySQL)', Sys\Record\SQL\Scheme::addColumns('people', ['age' => ['sql' => 'INT']]) === 'ALTER TABLE `people` ADD COLUMN `age` INT NULL DEFAULT NULL');
check('a bad column type throws', throws(fn() => Sys\Record\SQL\Scheme::modifyColumn('people', 'a', ['sql' => 'INT; DROP']), InvalidArgumentException::class));

if (!is_file("$root/app/config/mysql/test.db.php") || !extension_loaded('mysqli')) {
    echo "\nPart 2 skipped: no app/config/mysql/test.db.php (or no mysqli).\n";
} else {
    $pool = DatabaseManager::getConnection('test');
    $admin = new mysqli($pool->hostname, $pool->user, $pool->password);
    $quotedName = '`' . str_replace('`', '``', $pool->name) . '`';
    $admin->query("DROP DATABASE IF EXISTS $quotedName");

    echo "\nDatabase (devmode: tables are repaired)\n";
    $repairs = [];
    $people = (new Record('people', ['pool' => 'test']))->repairAfterSQLRun(function ($error) use (&$repairs) {
        $repairs[] = $error;
    });
    $id = $people->define('name', "O'Brien")->define('age', 41)->save();
    check('save() creates the table and returns the id', (int) $id === 1 && count($repairs) === 1, $repairs);

    $more = (new Record('people', ['pool' => 'test']))->define('name', 'Ada')->define('age', 36)->define('city', 'Lund');
    check('a new column is added', (int) $more->save() === 2);
    check('a longer value makes the column larger', (int) (new Record('people', ['pool' => 'test']))->define('city', str_repeat('x', 400))->save() === 3);

    $read = new Sys\Record\From('people', ['pool' => 'test']);
    check('From: searchInField single', ($read->searchInField('name', '=', "O'Brien", 'single')['age'] ?? null) == 41);
    check('From: where + orderBy + limit', array_column((new Sys\Record\From('people', ['pool' => 'test']))->where(['age', '>', 0])->orderBy(['age' => 'ASC'])->limit(0, 1)->all(), 'name') === ['Ada']);
    check('From: last()', ((new Sys\Record\From('people', ['pool' => 'test']))->last()['id'] ?? null) == 3);
    check('From: foreach and count()', count(new Sys\Record\From('people', ['pool' => 'test'])) === 3);

    $paged = (new Sys\Record\From('people', ['pool' => 'test']))->paging(2, 2);
    check('paging(2, 2): second page, total from COUNT', count($paged) === 1 && $paged->pagination->num_rows === 3);

    (new Record('people', ['pool' => 'test']))->define('age', 42)->save($id);
    check('save($id) updates', ((new Sys\Record\From('people', ['pool' => 'test']))->searchInField('id', '=', (int) $id, 'single')['age'] ?? null) == 42);
    (new Record('people', ['pool' => 'test']))->define('name', 'Cy')->save(50);
    check('save($id) inserts when the row is gone', ((new Sys\Record\From('people', ['pool' => 'test']))->searchInField('id', '=', 50, 'single')['name'] ?? null) === 'Cy');
    (new Record('people', ['pool' => 'test']))->define('age', 1)->save(['name', '=', 'Cy']);
    check('save([conditions]) updates the matching rows', ((new Sys\Record\From('people', ['pool' => 'test']))->searchInField('name', '=', 'Cy', 'single')['age'] ?? null) == 1);

    check('delete($id)', (new Record('people', ['pool' => 'test']))->delete(50) === true);
    check('delete([conditions])', (new Record('people', ['pool' => 'test']))->delete(['age', '=', 36]) === true);

    $_SERVER['HTTP_devmode'] = 'false';
    check('outside devmode a missing column throws (no repair)', throws(
        fn() => (new Record('people', ['pool' => 'test']))->define('new_column', 1)->save(),
        Sys\DatabaseConnection\DatabaseException::class
    ));

    $admin->query("DROP DATABASE IF EXISTS $quotedName");
    echo "  (test database removed)\n";
}

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
