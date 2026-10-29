<?php

/**
 * Tests for Cache.
 *
 * Works in a temporary folder (Cache::$folderPath and DOCUMENT_ROOT), so the
 * project's own cache is not touched.
 *
 * Run: php -d extension=gettext tests/cache.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

if (!extension_loaded('gettext')) {
    exit("Run with the gettext extension: php -d extension=gettext tests/cache.php\n");
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/app/config/config.class.php";

$sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-cache-' . getmypid();
mkdir("$sandbox/data", 0777, true);
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath($sandbox));
Cache::$folderPath = "$sandbox/data";

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

echo "\nData\n";
$calls = 0;
$make = function () use (&$calls) {
    $calls++;
    return ['menu' => ['Home', 'About'], 'n' => 42];
};
$first = Cache::remember('menu', 60, $make);
$second = Cache::remember('menu', 60, $make);
check('remember() makes the value once and then reads it', $first === $second && $calls === 1 && $first['n'] === 42);
check('the stored file is PHP with var_export (opcache can keep it)', str_starts_with(file_get_contents(glob("$sandbox/data/*.cache.php")[0]), "<?php\nreturn array ("));
check('get() and has()', Cache::get('menu')['menu'][1] === 'About' && Cache::has('menu') && Cache::get('nope', 'default') === 'default');
Cache::set('short', 'x', 1);
sleep(2);
check('a value is gone after its time', Cache::get('short') === null && !Cache::has('short'));
Cache::set('forever', 'kept');
check('ttl 0 keeps it', Cache::get('forever') === 'kept');
$object = new ArrayObject([1, 2]);
Cache::set('object', $object);
check('objects are stored too (serialized)', Cache::get('object') instanceof ArrayObject && count(Cache::get('object')) === 2);
Cache::set('../../../evil', 'safe');
check('a key with ../ stays in the cache folder', Cache::get('../../../evil') === 'safe' && !is_file("$sandbox/evil.cache.php"));
Cache::set('code', "<?php echo 'injected'; ?>");
ob_start();
$value = Cache::get('code');
$printed = ob_get_clean();
check('a value with PHP code in it is just text, never run', $value === "<?php echo 'injected'; ?>" && $printed === '');
Cache::forget('menu');
check('forget()', !Cache::has('menu'));
check('clear() removes everything', Cache::clear() === 4 && glob("$sandbox/data/*.cache.php") === []);

echo "\nA piece of a page\n";
$made = 0;
ob_start();
(new Cache('cache/footer.html'))->save(function () use (&$made) {
    $made++;
    echo '<footer>' . htmlspecialchars("<?php echo 'x'; ?>") . '</footer>';
});
$out = ob_get_clean();
check('save() makes the piece and prints it', $made === 1 && $out === '<footer>&lt;?php echo &#039;x&#039;; ?&gt;</footer>', $out);
ob_start();
(new Cache('cache/footer.html'))->save(function () use (&$made) {
    $made++;
    return 'new';
});
check('the second time it is read from the file', $made === 1 && ob_get_clean() === '<footer>&lt;?php echo &#039;x&#039;; ?&gt;</footer>');
ob_start();
$text = (new Cache('cache/raw.html', true))->save(fn() => "<?php echo 'run'; ?>");
$printed = ob_get_clean();
check('a piece with PHP code is printed as text, not run', $printed === "<?php echo 'run'; ?>");
check('save() returns the piece, include=false prints nothing', (function () {
    ob_start();
    $r = (new Cache('cache/quiet.html', false))->save(fn() => 'quiet');
    return ob_get_clean() === '' && $r === 'quiet';
})());
touch("$sandbox/cache/footer.html", time() - 120);
ob_start();
(new Cache('cache/footer.html', true, 60))->save(fn() => 'fresh');
check('with a lifetime an old piece is made again', ob_get_clean() === 'fresh');
check('a file outside the project is refused', (function () {
    try {
        new Cache('../outside.html');
    } catch (RuntimeException $e) {
        return true;
    }
    return false;
})());

$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($sandbox);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
