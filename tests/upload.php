<?php

/**
 * Tests for Dll\Validation\UploadedFiles with real uploads (multipart POST to
 * tests/http/upload-router.php on PHP's built-in server).
 *
 * The files land in a temporary folder that is removed afterwards.
 *
 * Run: php -d extension=curl -d extension=gettext -d extension=fileinfo tests/upload.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['curl', 'gettext', 'fileinfo'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/upload.php\n");
    }
}

$sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-upload-' . getmypid();
mkdir("$sandbox/in", 0777, true);
$sandbox = str_replace('\\', '/', realpath($sandbox));

$socket = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
fclose($socket);
$ini = [];
foreach (['gettext', 'fileinfo', 'mbstring'] as $extension) {
    $ini[] = '-d';
    $ini[] = "extension=$extension";
}
$env = getenv() + ['SANDSTORM_SANDBOX' => $sandbox];
$env['SANDSTORM_SANDBOX'] = $sandbox;
$log = tempnam(sys_get_temp_dir(), 'upload');
$server = proc_open(array_merge([PHP_BINARY], $ini, ['-S', "127.0.0.1:$port", "$root/tests/http/upload-router.php"]), [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, null, $env);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
    usleep(100000);
}

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

/**
 * Post files: [[local file, name shown to the server], ...].
 */
function upload(array $files, array $query = [], bool $submit = true): array
{
    global $port;
    $fields = $submit ? ['send' => '1'] : [];
    foreach (array_values($files) as $i => [$file, $name]) {
        $fields["photos[$i]"] = new CURLFile($file, '', $name);
    }
    $curl = curl_init("http://127.0.0.1:$port/?" . http_build_query($query));
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $fields, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = (string) curl_exec($curl);
    return json_decode($body, true) ?? ['raw' => $body];
}

// Test files
$png = "$sandbox/in/dot.png";
$image = imagecreatetruecolor(4, 4);
imagepng($image, $png);
$php = "$sandbox/in/shell.jpg";
file_put_contents($php, "<?php echo 'not an image'; ?>\n"); // harmless: antivirus removes real web shells at once
$fake = "$sandbox/in/fake.png";
file_put_contents($fake, "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32));
$big = "$sandbox/in/big.png";
copy($png, $big);
file_put_contents($big, str_repeat("\0", 5000), FILE_APPEND);

echo "\nUpload\n";
$r = upload([[$png, 'My Photo.PNG']]);
$stored = $r['files'][0]['name'] ?? '';
check('a real image is stored', $r['valid'] === true && $r['errors'] === [] && is_file("$sandbox/$stored"), $r);
check('it gets a new random name, not the visitor\'s', preg_match('#^upload/[0-9a-f]{32}\.png$#', $stored) === 1 && $r['files'][0]['tmp_name'] === 'My Photo.PNG', $stored);
check('the real type is reported', ($r['files'][0]['type'] ?? '') === 'image/png');
check('a success message is set', is_string($r['success']) && $r['success'] !== '');

$r = upload([[$png, 'a.png'], [$png, 'b.png']]);
check('several files at once', count($r['files']) === 2 && $r['valid'] === true, $r);

$r = upload([[$php, 'holiday.jpg']]);
check('PHP code called .jpg is refused', $r['valid'] === false && $r['files'] === [] && str_contains($r['errors'][0] ?? '', 'not a real jpg'), $r);

$r = upload([[$php, 'shell.php']], ['extension' => 'php,jpg']);
check('.php is refused even when allowed by mistake', $r['valid'] === false && $r['files'] === [], $r);

$r = upload([[$png, 'dot.png.php']]);
check('a double extension ending in .php is refused', $r['valid'] === false && $r['files'] === [], $r);

$r = upload([[$fake, 'broken.png']]);
check('a file with only a PNG header is not an image', $r['valid'] === false && $r['files'] === [], $r);

$r = upload([[$big, 'big.png']], ['maxsize' => 1000]);
check('a file over maxsize is refused', $r['valid'] === false && str_contains($r['errors'][0] ?? '', 'larger than'), $r);

$r = upload([[$png, 'a.png'], [$png, 'b.png'], [$png, 'c.png']], ['maxfiles' => 2]);
check('more than maxfiles is refused and nothing stored', $r['valid'] === false && $r['files'] === [], $r);

$r = upload([[$png, 'a.png']], [], false);
check('without the submit button nothing happens', $r['valid'] === false && $r['files'] === [] && $r['errors'] === [], $r);

$r = upload([[$png, 'a.png']], ['path' => '../outside']);
check('a folder outside the project is refused', $r['valid'] === false && str_contains($r['errors'][0] ?? '', 'outside'), $r);

$r = upload([[$png, '../../evil.png']]);
check('../ in the file name does not move the file', $r['valid'] === true && str_starts_with($r['files'][0]['name'] ?? '', 'upload/') && !is_file(dirname($sandbox) . '/evil.png'), $r);

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
