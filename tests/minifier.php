<?php

/**
 * Tests for Minifier: CSS and JavaScript output, and serving over HTTP.
 *
 * When Node.js is installed, every JavaScript sample is run before and after
 * minifying, and the results must be the same.
 *
 * Run: php -d extension=gettext tests/minifier.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

if (!extension_loaded('gettext')) {
    exit("Run with the gettext extension: php -d extension=gettext tests/minifier.php\n");
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

echo "\nCSS\n";
$cases = [
    'descendant selector keeps its space' => [".a  .b { color : red ; }", '.a .b{color:red}'],
    'comments are removed' => ["/* x */ a { b: c; } /* y */", 'a{b:c}'],
    'license comments are kept' => ["/*! MIT */\na{b:c}", '/*! MIT */a{b:c}'],
    'strings are not touched' => ["a::before { content: \"a  ,  b ; }\"; }", 'a::before{content:"a  ,  b ; }"}'],
    'url() is not touched' => ["a { background: url( //cdn.example.com/a  b.png ); }", 'a{background:url( //cdn.example.com/a  b.png )}'],
    'calc() keeps the spaces around + and -' => ["a { width: calc(100% - 2px + 1em); }", 'a{width:calc(100% - 2px + 1em)}'],
    'media query keeps "and ("' => ["@media screen and (max-width: 600px) { a { b: c } }", '@media screen and (max-width:600px){a{b:c}}'],
    '!important' => ["a { b: c !important; }", 'a{b:c!important}'],
    'child and sibling selectors' => ["a > b ~ c , d { e: f }", 'a>b~c,d{e:f}'],
    'pseudo class after a space stays' => ["a :hover { b: c }", 'a :hover{b:c}'],
];
foreach ($cases as $what => [$in, $expected]) {
    $got = Minifier::css($in);
    check($what, $got === $expected, $got);
}

echo "\nJavaScript\n";
$samples = [
    'no semicolons (line breaks are kept)' => "let a = 1\nlet b = 2\nconsole.log(a + b)",
    'URL in a string with //' => "const u = \"https://example.com//x\" // comment\nconsole.log(u)",
    'spaces inside strings' => "console.log('a    b', \"c\\t  d\")",
    'regular expression with // and quotes' => "const r = /\\/\\/[\"']+/g\nconsole.log('//\"x'.replace(r, '-'))",
    'division is not a regex' => "const a = 10, b = 2, c = 5\nconsole.log(a / b / c)",
    'template literal with ${} and a nested template' => 'const n = 2' . "\n" . 'console.log(`x  ${n + 1}  ${`in ${n}`}  y // not a comment`)',
    'a + +b and a - -b' => "const a = 1, b = 2\nconsole.log(a + +b, a - -b, a+ ++[b][0])",
    'return on its own line (ASI)' => "function f() {\n  return\n  42\n}\nconsole.log(f())",
    'line starting with ( after a statement' => "const x = 1;\n(function () { console.log(x) })()",
    'block comments and blank lines' => "/* head */\n\n\nfunction add(a, b) { /* inner */ return a + b }\n\nconsole.log(add(2, 3))",
    'method chaining over lines' => "console.log([3, 1, 2]\n  .sort()\n  .map(x => x * 2)\n  .join(','))",
    'object and arrow functions' => "const o = { a: 1, b: (x) => { return x * 2 } }\nconsole.log(o.b(o.a))",
    'regex after return' => "function t(s) { return /^a+$/.test(s) }\nconsole.log(t('aaa'), t('ab'))",
    'escaped quotes in strings' => "console.log('it\\'s', \"say \\\"hi\\\"\")",
];

$node = trim((string) shell_exec('node -v 2>' . (PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null')));
$run = function (string $code): ?string {
    $file = tempnam(sys_get_temp_dir(), 'js') . '.js';
    file_put_contents($file, $code);
    $out = shell_exec('node ' . escapeshellarg($file) . ' 2>&1');
    unlink($file);
    return $out === null ? null : trim($out);
};

foreach ($samples as $what => $code) {
    $min = Minifier::js($code);
    $smaller = strlen($min) <= strlen($code);
    if ($node !== '') {
        $before = $run($code);
        $after = $run($min);
        check("$what: same result", $before === $after && $smaller, ['before' => $before, 'after' => $after, 'minified' => $min]);
    } else {
        check("$what: not larger", $smaller, $min);
    }
}
if ($node === '') {
    echo "  (Node.js not found: results not compared)\n";
}

echo "\nServing over HTTP\n";
$sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-minify-' . getmypid();
mkdir("$sandbox/res/css/theme", 0777, true);
file_put_contents("$sandbox/res/css/site.css", ".a  .b { color : red ; }\n");
file_put_contents("$sandbox/res/css/theme/app.js", "let a = 1\nconsole.log(a) // hi\n");

$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$log = tempnam(sys_get_temp_dir(), 'min');
$server = proc_open([PHP_BINARY, '-d', 'extension=gettext', '-S', "127.0.0.1:$port", "$root/tests/http/minify-router.php"],
    [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, $root, ['SANDSTORM_TEST_ROOT' => $sandbox] + getenv());
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

function get(string $path, array $headers = []): array
{
    global $port;
    $context = stream_context_create(['http' => ['header' => implode("\r\n", $headers), 'ignore_errors' => true]]);
    $body = file_get_contents("http://127.0.0.1:$port$path", false, $context);
    $status = (int) explode(' ', $http_response_header[0])[1];
    $map = [];
    foreach ($http_response_header as $h) {
        if (str_contains($h, ':')) {
            [$k, $v] = explode(':', $h, 2);
            $map[strtolower($k)] = trim($v);
        }
    }
    return [$status, $body, $map];
}

[$status, $body, $h] = get('/res/css/site.minify.css');
check('a CSS file is served minified, as text/css', $status === 200 && $body === '.a .b{color:red}' && str_starts_with($h['content-type'] ?? '', 'text/css'), [$status, $body, $h['content-type'] ?? '']);
check('the minified copy is kept in res/css/minify/', is_file("$sandbox/res/css/minify/site.css"));
check('it has an ETag and is cached one week outside devmode', isset($h['etag']) && ($h['cache-control'] ?? '') === 'public, max-age=604800');

[$status, $body] = get('/res/css/site.minify.css', ['If-None-Match: ' . $h['etag']]);
check('the same ETag gives 304 without a body', $status === 304 && $body === '', [$status, $body]);

touch("$sandbox/res/css/site.css", time() + 5);
file_put_contents("$sandbox/res/css/site.css", "a { b : c }\n");
touch("$sandbox/res/css/site.css", time() + 5);
[$status, $body, $h2] = get('/res/css/site.minify.css', ['If-None-Match: ' . $h['etag']]);
check('a changed original is minified again (new ETag, 200)', $status === 200 && $body === 'a{b:c}' && ($h2['etag'] ?? '') !== $h['etag'], [$status, $body]);

[$status, $body, $h] = get('/res/css/theme/app.minify.JS');
check('upper case .JS works, as application/javascript', $status === 200 && $body === "let a=1\nconsole.log(a)" && str_starts_with($h['content-type'] ?? '', 'application/javascript'), [$status, $body]);

[$status] = get('/res/css/missing.minify.css');
check('a missing original gives 404', $status === 404);

proc_terminate($server);
proc_close($server);
@unlink($log);
$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($sandbox);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
