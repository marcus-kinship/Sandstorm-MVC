<?php

/**
 * Tests for Captcha, Encoding and Slug (with their Dll\Validation parts).
 *
 * Run: php -d extension=gettext -d extension=mbstring -d extension=gd tests/validation.php
 */

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['gettext', 'mbstring', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/validation.php\n");
    }
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTP_devmode'] = 'true';
include "$root/app/config/config.class.php";
(new SystemException())->register();

// Like Start: output is buffered, so the session can send its cookie
ob_start();

$failed = 0;
function check(string $what, bool $ok, mixed $got = null): void
{
    global $failed;
    echo ($ok ? '  [ok]   ' : '  [FAIL] ') . $what . ($ok || $got === null ? '' : '  got: ' . var_export($got, true)) . "\n";
    $failed += $ok ? 0 : 1;
}

echo "\nCaptcha\n";
$captcha = new Dll\Validation\Captcha(['length' => 6]);
$code = $captcha->generate();
check('generate() gives a code of the asked length, without look-alike characters', strlen($code) === 6 && !preg_match('/[0O1IL]/', $code));
check('only a hash is kept in the session, not the code', !str_contains(json_encode($_SESSION), $code));
check('check() accepts the right code, also in lower case', Dll\Validation\Captcha::check(strtolower($code)));
check('the code works only once', !Dll\Validation\Captcha::check($code));
$code = $captcha->generate();
check('a wrong guess uses the code up too', !Dll\Validation\Captcha::check('WRONG') && !Dll\Validation\Captcha::check($code));
$code = $captcha->generate();
Session::set('captcha', ['expires' => time() - 1] + Session::get('captcha'));
check('an expired code is refused', !Dll\Validation\Captcha::check($code));
check('nothing generated: refused', !Dll\Validation\Captcha::check('ABCDE'));
ob_start();
(new Dll\Validation\Captcha())->image();
$png = ob_get_clean();
check('image() sends a PNG (works without a TTF font)', str_starts_with($png, "\x89PNG"));
check('Validation rule "captcha" and Captcha::vaild() (older name)', (function () {
    $code = (new Dll\Validation\Captcha())->generate();
    if (!(new Validation())->captcha($code)) {
        return false;
    }
    $code = (new Dll\Validation\Captcha())->generate();
    return Captcha::vaild($code) === true;
})());

echo "\nEncoding\n";
$latin1 = "Caf\xe9 \x80 och \xe5\xe4\xf6";             // Windows-1252 bytes
check('toUTF8() turns Windows-1252 into UTF-8 (€ too)', Encoding::toUTF8($latin1) === 'Café € och åäö', Encoding::toUTF8($latin1));
check('toUTF8() leaves valid UTF-8 as it is', Encoding::toUTF8('Smörgås €') === 'Smörgås €');
check('toUTF8() fixes mixed UTF-8 and Windows-1252', Encoding::toUTF8("Smörgås \xe4r gott") === 'Smörgås är gott');
check('toUTF8() works on arrays', Encoding::toUTF8(['a' => "\xe5", 'b' => 5]) === ['a' => 'å', 'b' => 5]);
check('fixUTF8() undoes double encoding', Encoding::fixUTF8('FÃ©dÃ©ration') === 'Fédération', Encoding::fixUTF8('FÃ©dÃ©ration'));
check('toLatin1() gives Windows-1252 bytes (no utf8_decode, which is deprecated)', Encoding::toLatin1('Café €') === "Caf\xe9 \x80");
check('removeBOM()', Encoding::removeBOM("\xef\xbb\xbfhej") === 'hej');
check('UTF8FixWin1252Chars()', Encoding::UTF8FixWin1252Chars("\xc2\x80") === '€');
check('normalizeEncoding() and encode()', Encoding::normalizeEncoding('windows-1252') === 'ISO-8859-1' && Encoding::encode('utf8', "\xe5") === 'å');
check('Validation\Encoding::isUtf8()', Dll\Validation\Encoding::isUtf8('åäö') && !Dll\Validation\Encoding::isUtf8($latin1) && !Dll\Validation\Encoding::isUtf8("\xc0\xaf"));
check('isAscii(), hasBom(), hasControlChars()', Dll\Validation\Encoding::isAscii('abc') && !Dll\Validation\Encoding::isAscii('å')
    && Dll\Validation\Encoding::hasBom("\xef\xbb\xbfx") && Dll\Validation\Encoding::hasControlChars("a\x00b") && !Dll\Validation\Encoding::hasControlChars("a\tb\n"));
check('isDoubleEncoded()', Dll\Validation\Encoding::isDoubleEncoded('FÃ©dÃ©ration') && !Dll\Validation\Encoding::isDoubleEncoded('Fédération'));
check('Validation rule "utf8"', (new Validation())->utf8('åäö') && !(new Validation())->utf8($latin1));

echo "\nSlug\n";
check('a slug from a title', (string) new Slug('Hej på dig, världen!') === 'hej-pao-dig-vaerlden', (string) new Slug('Hej på dig, världen!'));
check('a slug from Windows-1252 text', (new Slug("Caf\xe9"))->getSlug() === 'cafe');
check('max_length cuts at a word', (new Slug('A long title for a post', ['max_length' => 12]))->getSlug() === 'a-long-title');
check('ArrayAccess works without deprecations', (function () {
    $slug = new Slug('x');
    $slug['prefix'] = 'p-';
    return isset($slug['prefix']) && $slug['prefix'] === 'p-' && $slug->getSlug() === 'p-x';
})());
check('increment()', Slug::increment('hej') === 'hej-1' && Slug::increment('hej-1') === 'hej-2' && Slug::increment('a.b', '.') === 'a.b.1');
$taken = ['hej', 'hej-1'];
check('unique() skips the slugs that are taken', Slug::unique('Hej', fn($s) => in_array($s, $taken, true)) === 'hej-2');
check('unique() of an empty text', Slug::unique('!!!', fn($s) => false) === 'item');
check('Validation\Slug::isValid()', Dll\Validation\Slug::isValid('hej-pa-dig') && !Dll\Validation\Slug::isValid('Hej på dig')
    && !Dll\Validation\Slug::isValid('-hej') && !Dll\Validation\Slug::isValid('hej--dig') && !Dll\Validation\Slug::isValid(''));
check('isValid() with a max length', Dll\Validation\Slug::isValid('abc', '-', 3) && !Dll\Validation\Slug::isValid('abcd', '-', 3));
check('isReserved()', Dll\Validation\Slug::isReserved('Admin') && !Dll\Validation\Slug::isReserved('ada') && Dll\Validation\Slug::isReserved('shop', ['shop']));
check('Validation rule "slug"', (new Validation())->slug('hej-1', 10) && !(new Validation())->slug('hej 1'));

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
ob_end_flush();
exit($failed === 0 ? 0 : 1);
