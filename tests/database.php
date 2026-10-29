<?php

/**
 * Tests for the database classes (DatabaseConnection, the Mysql driver and
 * its Select, Insert, Update and Delete).
 *
 * Needs a test pool in app/config/mysql/test.db.php (not committed):
 *
 *   <?php
 *   class test_db
 *   {
 *       public $user = "sandstorm_test";
 *       public $password = "...";
 *       public $name = "sandstorm_test";   // created and removed by the test
 *       public $hostname = "localhost";
 *       public $driver = "mysql";
 *       public $type = "as:text";
 *       public $charset = "utf8mb4";
 *   }
 *
 * The database in $name is removed at the end, so never point it at real data.
 *
 * Run: php tests/database.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

if (!is_file("$root/app/config/mysql/test.db.php")) {
    exit("Create app/config/mysql/test.db.php first (see the top of this file).\n");
}

foreach (['mysqli', 'gettext'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/database.php\n");
    }
}

$_SERVER['DOCUMENT_ROOT'] = $root;
foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/',
    'funs' => 'app/funs/', 'site' => 'site/', 'mysql' => 'app/config/mysql/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['HTTP_devmode'] = 'false';
$_SERVER['HTTP_mysql_log'] = 'false';

include "$root/app/config/config.class.php";

$failed = 0;
function check(string $what, bool $ok): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . "\n";
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

$pool = DatabaseManager::getConnection('test');
$name = $pool->name;

// Start from a database that does not exist, to test that it is created
$admin = new mysqli($pool->hostname, $pool->user, $pool->password);
$admin->query('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $name) . '`');
$connections = fn() => (int) $admin->query("SHOW GLOBAL STATUS LIKE 'Connections'")->fetch_row()[1];

echo "\nConnection\n";
$db = new DatabaseConnection('test');
check('the database is created when it does not exist', (bool) $admin->query("SHOW DATABASES LIKE '" . $admin->real_escape_string($name) . "'")->num_rows);
check('check() is true', DatabaseConnection::check('test'));
check('check() is false for a pool that does not exist', DatabaseConnection::check('no_such_pool') === false);

$before = $connections();
for ($i = 0; $i < 20; $i++) {
    (new DatabaseConnection('test'))->get()->row('SELECT %d AS n', $i);
}
check('20 x new DatabaseConnection share one connection', $connections() === $before);

echo "\nQueries\n";
$db->query('CREATE TABLE `people` (`id` INT AUTO_INCREMENT PRIMARY KEY, `name` VARCHAR(50) NOT NULL UNIQUE, `age` INT NOT NULL)');

$id = $db->insertRow('INSERT INTO `people` (`name`, `age`) VALUES (%s, %d)', "O'Brien", 41);
check('insertRow() returns the new id', (int) $id === 1);
$db->insertRow('INSERT INTO `people` (`name`, `age`) VALUES (%s, %d)', 'Ada', 36);

$row = $db->get()->row('SELECT * FROM `people` WHERE `id` = %d', $id);
check("row() returns the row, quotes escaped (O'Brien)", ($row['name'] ?? '') === "O'Brien");
check('row() returns null when nothing matches', $db->get()->row('SELECT * FROM `people` WHERE `id` = %d', 999) === null);

$rows = $db->get()->rows('SELECT * FROM `people` ORDER BY `id`');
check('rows() returns all rows', count($rows) === 2 && $rows[1]['name'] === 'Ada');
check('rows() returns [] when nothing matches', $db->get()->rows('SELECT * FROM `people` WHERE `age` > %d', 100) === []);

$set = $db->get('as:obj')->row('SELECT * FROM `people` WHERE `id` = %d', $id);
check("get('as:obj') returns a DataSet", $set instanceof DataSet);

check('update() is true when a row changed', $db->update()->row('UPDATE `people` SET `age` = %d WHERE `id` = %d', 42, $id) === true);
check('update() is false when nothing changed', $db->update()->row('UPDATE `people` SET `age` = %d WHERE `id` = %d', 42, $id) === false);
check('affectedRows() after update', $db->affectedRows() === 0);

check('delete() is true when a row was deleted', $db->delete('DELETE FROM `people` WHERE `name` = %s', 'Ada') === true);
check('delete() of a missing row is false (SkipDeleteError)', $db->delete('DELETE FROM `people` WHERE `name` = %s', 'Ada') === false);
$strict = new DatabaseConnection('test');
$strict->SkipDeleteError = false;
check('delete() of a missing row throws without SkipDeleteError', throws(fn() => $strict->delete('DELETE FROM `people` WHERE `name` = %s', 'Ada'), SystemException::class));

echo "\nErrors\n";
check('a bad SELECT throws DatabaseException (and can be caught)', throws(fn() => $db->get()->row('SELECT * FROM `no_such_table`'), Sys\DatabaseConnection\DatabaseException::class));
check('a duplicate insert throws DatabaseException', throws(fn() => $db->insertRow('INSERT INTO `people` (`name`, `age`) VALUES (%s, %d)', "O'Brien", 1), Sys\DatabaseConnection\DatabaseException::class));

$soft = new DatabaseConnection('test');
$soft->getError = true;
check('with getError a duplicate insert returns false', $soft->insertRow('INSERT INTO `people` (`name`, `age`) VALUES (%s, %d)', "O'Brien", 1) === false);
check('getErrorMessage() tells why', str_contains($soft->getErrorMessage(), 'Duplicate'));
check('a working query clears the error', $soft->get()->row('SELECT 1 AS one')['one'] == 1 && $soft->getErrorMessage() === '');
check('a missing argument throws DatabaseException', throws(fn() => $db->get()->row('SELECT %d, %d', 1), Sys\DatabaseConnection\DatabaseException::class));

$helper = new DatabaseConnection('test');
$rows = $helper->notSetTable(function () use ($db) {
    $db->query('CREATE TABLE `later` (`id` INT PRIMARY KEY)');
})->get()->rows('SELECT * FROM `later`');
check('notSetTable() runs the helper and tries again', $rows === []);

echo "\nMemory\n";
DatabaseConnection::$allSQL = [];
for ($i = 0; $i < 100; $i++) {
    $db->get()->row('SELECT %d AS n', $i);
}
check('queries are not collected when devmode and mysql_log are off', DatabaseConnection::$allSQL === []);
$_SERVER['HTTP_mysql_log'] = 'true';
$db->get()->row('SELECT 1');
check('queries are collected with HTTP_mysql_log true', count(DatabaseConnection::$allSQL) === 1);

$admin->query('DROP DATABASE IF EXISTS `' . str_replace('`', '``', $name) . '`');

echo $failed === 0 ? "\nAll tests passed. The test database was removed.\n" : "\n$failed test(s) failed. The test database was removed.\n";
exit($failed === 0 ? 0 : 1);
