<?php

/**
 * Tests for Session and CookieManager, over real HTTP requests.
 *
 * Starts PHP's built-in server with tests/http/session-router.php and checks
 * security (cookie flags, session fixation, regenerate, logout) and that the
 * session and cookies work.
 *
 * Run: php -d extension=gettext tests/session.php
 */

$root = dirname(__DIR__);

if (!extension_loaded('gettext')) {
    exit("Run with the gettext extension: php -d extension=gettext tests/session.php\n");
}

// Own settings folder with a cookie secret, so app/config is not touched
$config = sys_get_temp_dir() . '/sandstorm-session-test-' . getmypid();
@mkdir("$config/settings", 0777, true);
copy("$root/app/config/config.class.php", "$config/config.class.php");
copy("$root/app/config/settings/default.ini.php", "$config/settings/default.ini.php");
file_put_contents("$config/settings/cookie.ini.php", "<?php\nclass cookie_ini\n{\n    public \$set = [];\n    function __construct()\n    {\n        \$this->set = ['secret' => '" . bin2hex(random_bytes(24)) . "'];\n    }\n}\n");

// Free port
$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);

$sessionDir = "$config/sessions";
@mkdir($sessionDir);
$command = [PHP_BINARY, '-d', 'extension=gettext', '-d', 'session.save_path=' . $sessionDir,
    '-S', "127.0.0.1:$port", "$root/tests/http/session-router.php"];
$env = ['SANDSTORM_TEST_CONFIG' => $config] + getenv();
$server = proc_open($command, [1 => ['file', "$config/server.log", 'w'], 2 => ['file', "$config/server.log", 'a']], $pipes, $root, $env);

for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

/**
 * One request. Returns [body, Set-Cookie headers].
 */
function request(string $action, array $cookies = [], array $headers = []): array
{
    global $port;
    $lines = $headers;
    if ($cookies) {
        $lines[] = 'Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=" . rawurlencode($v), array_keys($cookies), $cookies));
    }
    $context = stream_context_create(['http' => ['header' => implode("\r\n", $lines), 'ignore_errors' => true]]);
    $body = file_get_contents("http://127.0.0.1:$port/?a=$action", false, $context);
    $setCookies = array_values(array_filter($http_response_header, fn($h) => stripos($h, 'Set-Cookie:') === 0));

    return [$body, $setCookies];
}

/**
 * The value of a cookie in Set-Cookie headers, or null.
 */
function cookieValue(array $setCookies, string $name): ?string
{
    foreach ($setCookies as $header) {
        if (preg_match('/^Set-Cookie:\s*' . preg_quote($name, '/') . '=([^;]*)/i', $header, $m)) {
            return rawurldecode($m[1]);
        }
    }
    return null;
}

function cookieHeader(array $setCookies, string $name): string
{
    foreach ($setCookies as $header) {
        if (stripos($header, "Set-Cookie: $name=") === 0) {
            return $header;
        }
    }
    return '';
}

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

echo "\nSession: security\n";
[, $c] = request('nothing');
check('a page that does not use the session sends no cookie', $c === []);
[$body, $c] = request('get');
check('reading the session without a session starts none (no cookie)', $c === [] && $body === '"none"', [$body, $c]);

[$body, $c] = request('set');
$sid = cookieValue($c, 'PHPSESSID');
$header = cookieHeader($c, 'PHPSESSID');
check('the session cookie is HttpOnly', stripos($header, 'HttpOnly') !== false, $header);
check('the session cookie is SameSite=Strict', stripos($header, 'SameSite=Strict') !== false, $header);
check('the session cookie is not Secure over HTTP', stripos($header, 'secure') === false, $header);
[, $c] = request('set', [], ['X-Test-Https: on']);
check('the session cookie is Secure over HTTPS', stripos(cookieHeader($c, 'PHPSESSID'), 'secure') !== false);

[$body, $c] = request('id', ['PHPSESSID' => 'attackerchosenid1234567890']);
check('a made-up session id is not accepted (session fixation)', $body !== 'attackerchosenid1234567890' && cookieValue($c, 'PHPSESSID') !== null, $body);

[$body] = request('regenerate', ['PHPSESSID' => $sid]);
[$old, $new, $kept] = explode('|', $body);
check('regenerate() gives a new id', $old === $sid && $new !== $sid && $new !== '');
check('regenerate() keeps the data', $kept === '1', $kept);
[$body] = request('get', ['PHPSESSID' => $sid]);
check('the old id no longer works after regenerate()', $body === '"none"', $body);

[$body] = request('set', ['PHPSESSID' => $new]);
check('the new id works', $body === '2', $body);
[, $c] = request('kill', ['PHPSESSID' => $new]);
check('kill() deletes the session cookie', ($v = cookieHeader($c, 'PHPSESSID')) !== '' && (cookieValue($c, 'PHPSESSID') === '' || stripos($v, 'deleted') !== false), $c);
[$body] = request('get', ['PHPSESSID' => $new]);
check('after kill() the session data is gone', $body === '"none"', $body);

echo "\nSession: function\n";
[$body, $c] = request('set');
$sid = cookieValue($c, 'PHPSESSID');
request('set', ['PHPSESSID' => $sid]);
[$body] = request('set', ['PHPSESSID' => $sid]);
check('set()/get() keeps a value over requests', $body === '3', $body);
[$body] = request('add', ['PHPSESSID' => $sid]);
check('add() appends and sets keys', $body === '{"0":"x","key":"y"}', $body);
[$body] = request('remove', ['PHPSESSID' => $sid]);
check('remove() removes a value', $body === '"none"', $body);
[$body] = request('language', ['PHPSESSID' => $sid]);
check('setLanguage()/getLanguage()', $body === 'sv_SE', $body);
[$body] = request('exists');
check('exists() is false without a session cookie', $body === 'no', $body);
[$body] = request('exists', ['PHPSESSID' => $sid]);
check('exists() is true with one', $body === 'yes', $body);

echo "\nCookieManager\n";
[, $c] = request('cookie-set');
$theme = cookieHeader($c, 'theme');
check('set() sends HttpOnly, SameSite=Lax and Path=/', stripos($theme, 'HttpOnly') !== false && stripos($theme, 'SameSite=Lax') !== false && stripos($theme, 'path=/') !== false, $theme);
check('set() with a lifetime sends an expiry', stripos($theme, 'expires=') !== false, $theme);
[$body] = request('cookie-get', ['theme' => 'dark', 'prefs' => cookieValue($c, 'prefs')]);
check('get() returns the value, arrays come back as arrays, default when missing', $body === '["dark",{"size":2,"lang":"sv"},"default"]', $body);

[, $c] = request('cookie-signed-set');
$signed = cookieValue($c, 'plan');
[$body] = request('cookie-signed-get', ['plan' => $signed]);
check('getSigned() returns a signed value', $body === '{"level":"pro"}', $body);
[$payload, $signature] = explode('.', $signed);
$forged = base64_encode('{"level":"admin"}') . '.' . $signature;
[$body] = request('cookie-signed-get', ['plan' => $forged]);
check('getSigned() returns null for a changed value', $body === 'null', $body);

[$body, $c] = request('cookie-delete', ['theme' => 'dark']);
check('delete() expires the cookie', $body === 'false' && stripos(cookieHeader($c, 'theme'), 'expires=') !== false, [$body, $c]);
[$body] = request('cookie-bad-name');
check('an invalid cookie name is rejected', $body === 'rejected', $body);
[, $c] = request('cookie-none');
check('SameSite=None is always Secure', stripos(cookieHeader($c, 'cross'), 'secure') !== false, $c);
[, $c] = request('cookie-signed-old');
[$body] = request('cookie-signed-old-get', ['old' => cookieValue($c, 'old')]);
check('getSigned() returns null after the signed expiry time', $body === 'null', $body);
[, $c] = request('cookie-host');
$host = cookieHeader($c, '__Host-id');
check('__Host- cookies are Secure, Path=/ and have no Domain', stripos($host, 'secure') !== false && stripos($host, 'path=/;') !== false && stripos($host, 'domain') === false, $host);
[$body] = request('cookie-large');
check('a cookie over 4 KB is rejected', $body === 'rejected', $body);
[, $c] = request('set', [], ['X-Forwarded-Proto: https']);
check('behind a proxy (X-Forwarded-Proto: https) the session cookie is Secure', stripos(cookieHeader($c, 'PHPSESSID'), 'secure') !== false);

proc_terminate($server);
proc_close($server);
array_map('unlink', glob("$sessionDir/*") ?: []);
@rmdir($sessionDir);
array_map('unlink', glob("$config/settings/*") ?: []);
@rmdir("$config/settings");
@unlink("$config/config.class.php");
$log = @file_get_contents("$config/server.log");
@unlink("$config/server.log");
@rmdir($config);

if ($failed > 0 && $log) {
    echo "\nServer log:\n" . implode("\n", array_slice(array_filter(explode("\n", $log), fn($l) => !preg_match('/Accepted|Closing|started/', $l)), 0, 20)) . "\n";
}

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
