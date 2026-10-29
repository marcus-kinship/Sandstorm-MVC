<?php

/**
 * Tests for Filesystem, FileInfo and FolderInfo (and the older *IO names).
 *
 * Works in a temporary folder that acts as the project folder, so no real
 * files are touched.
 *
 * Run: php -d extension=gettext tests/filesystem.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

if (!extension_loaded('gettext')) {
    exit("Run with the gettext extension: php -d extension=gettext tests/filesystem.php\n");
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

function throws(callable $fn, string $class): bool
{
    try {
        $fn();
    } catch (Throwable $e) {
        return $e instanceof $class;
    }
    return false;
}

echo "\nThe real project\n";
$routes = (new FolderInfoIO($_SERVER['HTTP_path_site'] ?? "$root/site"))->search('\.class\.php', true);
check('FolderInfoIO search() finds controllers, as RouterBuilder uses it', in_array("$root/site/default/Default.class.php", array_map(fn($f) => $f->pathName, $routes), true), array_map('strval', $routes));

// From here on a temporary folder is the project folder
$sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-fs-test-' . getmypid();
mkdir("$sandbox/project", 0777, true);
file_put_contents("$sandbox/outside.txt", 'secret');
$project = str_replace('\\', '/', realpath("$sandbox/project"));
$_SERVER['DOCUMENT_ROOT'] = $project;

echo "\nPaths stay in the project\n";
check('a relative path is resolved from the project', Filesystem::resolve('upload/a.txt') === "$project/upload/a.txt");
check('an absolute path inside the project is fine', Filesystem::resolve("$project/upload") === "$project/upload");
check('../ that stays inside is fine', Filesystem::resolve('upload/../site/x') === "$project/site/x");
check('../ that leads out throws', throws(fn() => Filesystem::resolve('../outside.txt'), RuntimeException::class));
check('an absolute path outside throws', throws(fn() => new FileInfo("$sandbox/outside.txt"), RuntimeException::class));
check('a folder with a similar name outside throws (project-x)', throws(fn() => Filesystem::resolve($project . '-x/file'), RuntimeException::class));
check('an empty path throws', throws(fn() => Filesystem::resolve(''), RuntimeException::class));
check('a null byte throws', throws(fn() => Filesystem::resolve("a.txt\0.jpg"), RuntimeException::class));
check('getDir() with ../ returns nothing', (new Filesystem('.'))->getDir('../*') === []);

echo "\nFileInfo\n";
$log = new FileInfo('app/log/today.txt', false);
check('a file that does not exist yet', $log->exists() === false && $log->extension === 'txt');
$log->put("one\n");
$log->put("two\n", true);
check('put() creates the folders and the file, true appends', $log->read() === "one\ntwo\n" && $log->size === 8);
check('reading() returns lines', $log->reading() === ["one\n", "two\n"]);
check('a file without extension has extension ""', (new FileInfo('README', false))->extension === '');
check('new FileInfo() throws for a missing file', throws(fn() => new FileInfo('nope.txt'), SystemException::class));
check('new FileInfo() throws for a folder', throws(fn() => new FileInfo('app'), SystemException::class));

$conf = new FileInfo('conf.php', false);
$conf->put("<?php\n\$set = array(\n);\n");
$conf->write(['    // after'])->insertAfter('$set = array(');
$conf->write(['// before'])->insertBefore('<?php');
check('insertAfter() and insertBefore() add the lines', $conf->read() === "// before\n<?php\n\$set = array(\n    // after\n);\n", $conf->read());

$new = (new FileInfo('made.txt', false))->write(['a', 'b']);
check('insert() creates the file with the lines', $new->insert() === true && $new->read() === "a\nb\n");
check('insert() does nothing when the file exists', $new->insert() === false);

check('copy() with a name copies into the same folder', $log->copy('copy.txt') && is_file("$project/app/log/copy.txt"));
check('copy() with a path copies there', $log->copy('backup/today.txt') && is_file("$project/backup/today.txt"));
check('copy() outside the project throws', throws(fn() => $log->copy('../../outside-copy.txt'), RuntimeException::class));
check('rename() renames in the folder', $log->rename('renamed.txt') && is_file("$project/app/log/renamed.txt") && $log->baseName === 'renamed.txt');
check('rename() with a folder throws', throws(fn() => $log->rename('x/y.txt'), InvalidArgumentException::class));
check('move() moves to another folder', $log->move('archive/2026/log.txt') && is_file("$project/archive/2026/log.txt"));
check('setPerms() needs four octal digits', throws(fn() => $log->setPerms('644'), SystemException::class) && throws(fn() => $log->setPerms('0999'), SystemException::class));
check('remove() deletes the file', $log->remove() && !is_file("$project/archive/2026/log.txt"));

echo "\nFolderInfo\n";
$upload = (new FolderInfo('.'))->newFolder('upload/2026/10') ? new FolderInfo('upload') : null;
check('newFolder() creates nested folders inside the folder', is_dir("$project/upload/2026/10"));
(new FileInfo('upload/a.jpg', false))->put('a');
(new FileInfo('upload/b.png', false))->put('b');
(new FileInfo('upload/2026/c.jpg', false))->put('c');
$names = array_map(fn($i) => $i->baseName . ':' . $i->type, $upload->getList());
check('getList() gives FileInfo and FolderInfo objects', $names === ['2026:dir', 'a.jpg:file', 'b.png:file'], $names);
check('search() in the folder', array_map(fn($i) => $i->baseName, $upload->search('\.jpg$')) === ['a.jpg']);
check('search() recursive finds files in subfolders', array_map(fn($i) => $i->baseName, $upload->search('\.jpg$', true)) === ['c.jpg', 'a.jpg'] || count($upload->search('\.jpg$', true)) === 2);
check('getTree() lists everything', count($upload->getTree()) === 5, $upload->getTree());

check('copy() copies the whole folder', $upload->copy('backup/upload') && is_file("$project/backup/upload/2026/c.jpg"));
check('copy() into itself throws', throws(fn() => $upload->copy('upload/inner'), SystemException::class));
$backup = new FolderInfo('backup/upload');
check('move() moves the folder', $backup->move('old/upload') && is_file("$project/old/upload/a.jpg") && !is_dir("$project/backup/upload"));
check('remove() of a folder that is not empty throws', throws(fn() => $backup->remove(), SystemException::class));
check('remove(true) deletes everything in it', $backup->remove(true) && !is_dir("$project/old/upload"));
check('new FolderInfo() outside the project throws', throws(fn() => new FolderInfo($sandbox), RuntimeException::class));

echo "\nFilesystem and the older names\n";
check('Filesystem::path() gives FolderInfo for a folder', Filesystem::path('upload') instanceof FolderInfo);
check('Filesystem::path() gives FileInfo for a file', Filesystem::path('upload/a.jpg') instanceof FileInfo);
check('getDir() with a pattern', array_map('basename', (new Filesystem('upload'))->getDir('*.jpg')) === ['a.jpg']);
$fs = new FilesystemIO('upload/b.png');
check('FilesystemIO still works (alias)', $fs instanceof Filesystem && $fs->copy('upload/b2.png') && is_file("$project/upload/b2.png"));
check('rename() to a path outside throws', throws(fn() => $fs->rename('../../outside.png'), RuntimeException::class));
check('remove() deletes only that file', (new Filesystem('upload/b2.png'))->remove() && is_file("$project/upload/b.png"));
check('FileInfoIO and FolderInfoIO are aliases', new FileInfoIO('upload/a.jpg') instanceof FileInfo && new FolderInfoIO('upload') instanceof FolderInfo);
check('the file outside the project is untouched', file_get_contents("$sandbox/outside.txt") === 'secret');

// Clean up
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($sandbox);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
