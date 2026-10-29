<?php

/**
 * Tests for Curl against a local PHP server (tests/http/echo-router.php).
 *
 * Run: php -d extension=curl -d extension=gettext tests/curl.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['curl', 'gettext'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/curl.php\n");
    }
}

$_SERVER['DOCUMENT_ROOT'] = $root;
foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
include "$root/app/config/config.class.php";

$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", "$root/tests/http/echo-router.php"], [1 => ['file', $log = tempnam(sys_get_temp_dir(), 'curl'), 'w'], 2 => ['file', $log, 'a']], $pipes);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}
$base = "http://127.0.0.1:$port";

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

echo "\nCurl\n";
$json = fn($body) => json_decode($body, true);

$r = Curl::get("$base/echo", ['q' => 'sand dune', 'n' => 2], [], $json);
check('get() sends the data as query string', $r['method'] === 'GET' && $r['get'] === ['q' => 'sand dune', 'n' => '2'], $r);
check('status() is 200', Curl::status() === 200);

$r = Curl::get("$base/echo?a=1", ['b' => 2], [], $json);
check('get() adds to an existing query string', $r['get'] === ['a' => '1', 'b' => '2'], $r);

$r = Curl::post("$base/echo", ['name' => 'Ada', 'list' => ['x', 'y']], [], $json);
check('post() sends form fields', $r['method'] === 'POST' && $r['post'] === ['name' => 'Ada', 'list' => ['x', 'y']], $r);

$r = Curl::get("$base/echo", [], [CURLOPT_HTTPHEADER => ['X-Test: 1']], $json);
check('own CURLOPT_* options are kept', $r['method'] === 'GET');

Curl::get("$base/missing");
check('status() is 404 for a missing page', Curl::status() === 404);

$r = Curl::get("$base/to-echo", [], [], $json);
check('redirects to http are followed', ($r['get']['redirected'] ?? '') === '1', $r);

check('a redirect to file:// is refused', throws(fn() => Curl::get("$base/to-file"), RuntimeException::class));
check('a file:// URL is refused', throws(fn() => Curl::get('file:///etc/hosts'), InvalidArgumentException::class));
check('a connection error throws', throws(fn() => Curl::get('http://127.0.0.1:1/'), RuntimeException::class));

proc_terminate($server);
proc_close($server);
@unlink($log);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
