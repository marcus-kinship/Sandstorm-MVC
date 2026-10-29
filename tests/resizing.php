<?php

/**
 * Tests for Resizing (GD).
 *
 * Works in a temporary folder that acts as the project folder.
 *
 * Run: php -d extension=gettext -d extension=gd tests/resizing.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['gettext', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/resizing.php\n");
    }
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/app/config/config.class.php";

$sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-img-' . getmypid();
mkdir($sandbox, 0777, true);
$_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath($sandbox));

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

/** A 400×200 test image: red left half, blue right half, a transparent PNG corner */
function sample(string $file): string
{
    global $sandbox;
    $img = imagecreatetruecolor(400, 200);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefilledrectangle($img, 0, 0, 199, 199, imagecolorallocate($img, 255, 0, 0));
    imagefilledrectangle($img, 200, 0, 399, 199, imagecolorallocate($img, 0, 0, 255));
    imagefilledrectangle($img, 0, 0, 19, 19, imagecolorallocatealpha($img, 0, 0, 0, 127));
    imagepng($img, "$sandbox/$file");
    return "$sandbox/$file";
}

function rgba(string $file, int $x, int $y): array
{
    $img = imagecreatefromstring(file_get_contents($file));
    $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));
    return [$c['red'], $c['green'], $c['blue'], $c['alpha']];
}

$src = sample('sample.png');

echo "\nSizes\n";
$r = (new Resizing())->load($src);
check('load() reads the size and type', $r->getWidth() === 400 && $r->getHeight() === 200 && $r->getMime() === 'image/png');
check('fit(100, 100) keeps the shape (100×50)', (new Resizing())->load($src)->fit(100, 100)->getWidth() === 100 && (new Resizing())->load($src)->fit(100, 100)->getHeight() === 50);
check('fit() does not make a small image larger', (new Resizing())->load($src)->fit(1000, 1000)->getWidth() === 400);
check('fit(..., true) does', (new Resizing())->load($src)->fit(800, 800, true)->getWidth() === 800);
$cover = (new Resizing())->load($src)->cover(100, 100);
check('cover(100, 100) gives exactly 100×100', $cover->getWidth() === 100 && $cover->getHeight() === 100);
$cover->save('out/cover.png');
check('cover() crops from the middle (left red, right blue)', rgba("$sandbox/out/cover.png", 10, 50)[0] > 200 && rgba("$sandbox/out/cover.png", 90, 50)[2] > 200);
$pad = (new Resizing())->load($src)->pad(300, 300, '#00ff00');
$pad->save('out/pad.png');
check('pad(300, 300) puts it on a green background', $pad->getWidth() === 300 && rgba("$sandbox/out/pad.png", 150, 10)[1] > 200 && rgba("$sandbox/out/pad.png", 150, 150)[1] < 50);
check('smartResize() (older name) is pad()', (new Resizing())->load($src)->smartResize(120, 120, 0, 0, 0)->getHeight() === 120);
check('resizeToWidth(), resizeToHeight(), scale()', (new Resizing())->load($src)->resizeToWidth(200)->getHeight() === 100
    && (new Resizing())->load($src)->resizeToHeight(50)->getWidth() === 100 && (new Resizing())->load($src)->scale(25)->getWidth() === 100);
$crop = (new Resizing())->load($src)->crop(50, 50, 300, 0);
$crop->save('out/crop.png');
check('crop() takes exactly the part asked for', $crop->getWidth() === 50 && rgba("$sandbox/out/crop.png", 25, 25)[2] > 200);
check('rotate(90) swaps width and height', (new Resizing())->load($src)->rotate(90)->getWidth() === 200);
check('a size below 1 throws', throws(fn() => (new Resizing())->load($src)->resize(0, 10), SystemException::class));

echo "\nSaving\n";
(new Resizing())->load($src)->save('out/a.webp', null, 80);
check('save() to .webp writes WebP (the old code did nothing)', is_file("$sandbox/out/a.webp") && getimagesize("$sandbox/out/a.webp")[2] === IMAGETYPE_WEBP);
(new Resizing())->load($src)->save('out/a.jpg');
check('save() to .jpg writes JPEG', getimagesize("$sandbox/out/a.jpg")[2] === IMAGETYPE_JPEG);
check('a transparent corner becomes white in JPEG (not black)', min(array_slice(rgba("$sandbox/out/a.jpg", 5, 5), 0, 3)) > 230, rgba("$sandbox/out/a.jpg", 5, 5));
(new Resizing())->load($src)->save('out/b.png');
check('PNG keeps the transparency', rgba("$sandbox/out/b.png", 5, 5)[3] === 127);
(new Resizing())->load($src)->save('out/c.gif');
check('save() to .gif writes GIF', getimagesize("$sandbox/out/c.gif")[2] === IMAGETYPE_GIF);
if (function_exists('imageavif')) {
    (new Resizing())->load($src)->fit(100, 100)->save('out/d.avif', null, 60);
    check('save() to .avif writes AVIF', is_file("$sandbox/out/d.avif") && filesize("$sandbox/out/d.avif") > 0);
}
check('save() outside the project throws', throws(fn() => (new Resizing())->load($src)->save('../outside.png'), RuntimeException::class));
check('toDataUri() gives a PNG data URI', str_starts_with((new Resizing())->load($src)->fit(10, 10)->toDataUri(), 'data:image/png;base64,'));

echo "\nSafety\n";
file_put_contents("$sandbox/fake.jpg", '<?php echo "not an image"; ?>');
check('a file that is not an image is refused', throws(fn() => (new Resizing())->load("$sandbox/fake.jpg"), SystemException::class));
check('an image over the pixel limit is refused before it is decoded', throws(fn() => (new Resizing())->setMaxPixels(1000)->load($src), SystemException::class));
check('using it before load() throws', throws(fn() => (new Resizing())->getWidth(), SystemException::class));
$canvas = (new Resizing())->create(50, 20, '#336699');
check('create() makes a plain canvas', $canvas->getWidth() === 50 && $canvas->getHeight() === 20);
check('an invalid colour throws', throws(fn() => (new Resizing())->create(5, 5, '#zz'), InvalidArgumentException::class));
check('flip() and grayscale() work', (new Resizing())->load($src)->flip()->grayscale()->getWidth() === 400);

$items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($items as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($sandbox);

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
