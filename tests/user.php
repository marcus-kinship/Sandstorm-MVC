<?php

/**
 * Tests for Dll\Validation\Password and User.
 *
 * Part 1 (Password) needs no database. Part 2 (User) runs against the test
 * pool in app/config/mysql/test.db.php (see tests/database.php); it is skipped
 * when that file is missing. The test database is removed at the end.
 *
 * Run: php -d extension=gettext -d extension=mysqli -d extension=mbstring tests/user.php
 */

use Dll\Validation\Password;

$root = str_replace('\\', '/', dirname(__DIR__));

foreach (['gettext', 'mbstring'] as $extension) {
    if (!extension_loaded($extension)) {
        exit("Run with the $extension extension: php -d extension=$extension tests/user.php\n");
    }
}

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/', 'mysql' => 'app/config/mysql/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
$_SERVER['HTTP_devmode'] = 'true';
include "$root/app/config/config.class.php";

// Like Start: output is buffered, so the session can still send its cookie
ob_start();

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

echo "\nPassword\n";
$hash = Password::hash('correct horse');
check('hash() uses password_hash (bcrypt)', $hash['algorithm'] === 'bcrypt' && password_get_info($hash['password'])['algoName'] === 'bcrypt');
check('verify() accepts the right password', Password::verify('correct horse', $hash['password']));
check('verify() refuses a wrong password', !Password::verify('wrong horse', $hash['password']));
check('verify() works for old md5, sha1 and salted hashes', Password::verify('abc', md5('abc'), 'md5') && Password::verify('abc', sha1('abc'), 'sha1')
    && Password::verify('abc', sha1('salt' . 'abc'), 'sha1salt', 'salt') && Password::verify('abc', md5('salt' . 'abc'), 'md5salt', 'salt'));
check('an unknown algorithm throws', throws(fn() => Password::verify('a', 'a', 'rot13'), InvalidArgumentException::class));
check('needsRehash() is true for old algorithms, false for bcrypt', Password::needsRehash(md5('a'), 'md5') && !Password::needsRehash($hash['password'], 'bcrypt'));
check('check(): too short', Password::check('short1') !== [] && str_contains(Password::check('short1')[0], '8'));
check('check(): common password', in_array('The password is too common', Password::check('password123'), true));
check('check(): only spaces', in_array('The password cannot be only spaces', Password::check('          '), true));
check('check(): longer than 72 bytes', Password::check(str_repeat('x', 73)) !== []);
check('check(): letters and digits when required', Password::check('onlyletters', 8, true) !== [] && Password::check('letters123', 8, true) === []);
check('check(): not the username or email', Password::check('adalovelace', 8, false, ['adalovelace', 'x@y.se']) !== []
    && Password::check('adalovelace', 8, false, ['bo', 'adalovelace@example.com']) !== []);
check('isStrong() for a good password', Password::isStrong('sand dune sunset'));
$generated = Password::generate();
check('generate() gives 16 random characters without look-alikes', strlen($generated) === 16 && !preg_match('/[0O1lI]/', $generated) && $generated !== Password::generate());

$form = new Validation();
check('Validation rule "password"', $form->password('sand dune sunset') && !$form->password('12345678') && !$form->password('short', 8));

if (!is_file("$root/app/config/mysql/test.db.php") || !extension_loaded('mysqli')) {
    echo "\nPart 2 skipped: no app/config/mysql/test.db.php (or no mysqli).\n";
} else {
    $pool = DatabaseManager::getConnection('test');
    $admin = new mysqli($pool->hostname, $pool->user, $pool->password);
    $quotedName = '`' . str_replace('`', '``', $pool->name) . '`';
    $admin->query("DROP DATABASE IF EXISTS $quotedName");

    User::$pool = 'test';
    User::$table = 'users';

    echo "\nUser (against the test database)\n";
    $id = User::create('ada', 'ada@example.com', 'sand dune sunset');
    check('create() makes the user (the table is created in devmode)', $id === 1);
    $row = (new Sys\Record\From('users', ['pool' => 'test']))->searchInField('id', '=', $id, 'single');
    check('the password is stored with password_hash, not in plain text', ($row['algorithm'] ?? '') === 'bcrypt' && $row['password'] !== 'sand dune sunset' && $row['slug'] === 'ada');
    check('create() refuses a weak password', throws(fn() => User::create('bo', 'bo@example.com', '12345678'), InvalidArgumentException::class));
    check('create() refuses the username as password', throws(fn() => User::create('carlotta', 'c@example.com', 'carlotta'), InvalidArgumentException::class));
    check('create() refuses an invalid email', throws(fn() => User::create('dan', 'not-an-email', 'sand dune sunset'), InvalidArgumentException::class));

    $ok = User::login(['identity' => 'ada', 'password' => 'sand dune sunset']);
    check('login() with the username works', $ok['status'] === true && $ok['id'] === 1 && User::islogin() && User::id() === 1, $ok);
    check('getActiveUser() gives the user with imagename', User::getActiveUser()->username === 'ada' && User::getActiveUser()->imagename === '');
    User::logout();
    check('logout() logs out', !User::islogin() && User::id() === null);

    $byEmail = User::login(['identity' => 'ada@example.com', 'password' => 'sand dune sunset']);
    check('login() with the email works', $byEmail['status'] === true);
    User::logout();

    $wrong = User::login(['identity' => 'ada', 'password' => 'wrong password']);
    $nobody = User::login(['identity' => 'nobody', 'password' => 'wrong password']);
    check('wrong password and unknown user give the same answer', $wrong['status'] === false && $wrong['message'] === $nobody['message'] && $wrong['field'] === $nobody['field'], [$wrong, $nobody]);

    $t = hrtime(true); User::login(['identity' => 'ada', 'password' => 'wrong password']); $known = hrtime(true) - $t;
    $t = hrtime(true); User::login(['identity' => 'nobody', 'password' => 'wrong password']); $unknown = hrtime(true) - $t;
    check('and take about the same time (both hash the password)', $unknown > $known / 3 && $unknown < $known * 3, [$known / 1e6, $unknown / 1e6]);

    $evil = User::login(['identity' => 'ada', 'password' => 'sand dune sunset', 'redirect' => '//evil.example/steal']);
    check('a redirect to another site is replaced by the default', $evil['redirect'] === '/account/dashboard', $evil['redirect']);
    $good = User::login(['identity' => 'ada', 'password' => 'sand dune sunset', 'redirect' => '/account/settings']);
    check('a redirect on the site is kept', $good['redirect'] === '/account/settings');
    User::logout();

    // An old md5 password is upgraded at the next login
    $admin->query("INSERT INTO $quotedName.`users` (`username`, `email`, `password`, `algorithm`, `salt`, `status`, `slug`) VALUES ('old', 'old@example.com', '" . md5('old secret pw') . "', 'md5', '', 2, 'old')");
    $old = User::login(['identity' => 'old', 'password' => 'old secret pw']);
    $row = (new Sys\Record\From('users', ['pool' => 'test']))->searchInField('username', '=', 'old', 'single');
    check('an old md5 password still logs in, and is upgraded to bcrypt', $old['status'] === true && $row['algorithm'] === 'bcrypt' && password_verify('old secret pw', $row['password']), $row);
    User::logout();

    $admin->query("UPDATE $quotedName.`users` SET `status` = 0 WHERE `username` = 'ada'");
    check('a blocked account is refused (only with the right password)', User::login(['identity' => 'ada', 'password' => 'sand dune sunset'])['message'] === 'Account is blocked'
        && User::login(['identity' => 'ada', 'password' => 'nope nope'])['message'] !== 'Account is blocked');

    User::changePassword(1, 'new sand dune');
    $admin->query("UPDATE $quotedName.`users` SET `status` = 2 WHERE `username` = 'ada'");
    check('changePassword() works', User::login(['identity' => 'ada', 'password' => 'new sand dune'])['status'] === true);
    User::logout();

    check('find() by email', User::find('email', 'ada@example.com')?->username === 'ada');
    check('getUserBySlug()', User::getUserBySlug('ada')?->id == 1);
    check('getActiveUser() without login throws', throws(fn() => User::getActiveUser(), SystemException::class));

    echo "\nUser\\Media\n";
    $admin->query("CREATE TABLE $quotedName.`media` (`id` INT AUTO_INCREMENT PRIMARY KEY, `images` VARCHAR(255), `position` INT, `imageid` INT, `type` VARCHAR(20))");

    // Files in a temporary project folder
    $sandbox = str_replace('\\', '/', sys_get_temp_dir()) . '/sandstorm-media-' . getmypid();
    mkdir("$sandbox/upload/user/ada/images", 0777, true);
    mkdir("$sandbox/upload/user/bo/images", 0777, true);
    foreach (['a.jpg', 'a-300.jpg', 'b.jpg'] as $f) {
        file_put_contents("$sandbox/upload/user/ada/images/$f", 'x');
    }
    file_put_contents("$sandbox/upload/user/bo/images/c.jpg", 'x');
    $realRoot = $_SERVER['DOCUMENT_ROOT'];
    $_SERVER['DOCUMENT_ROOT'] = str_replace('\\', '/', realpath($sandbox));

    $media = User::media(7);
    check('User::media() gives Dll\User\Media, Media is the older name', $media instanceof Dll\User\Media && new Media(7) instanceof Dll\User\Media);
    $saved = $media->save([
        ['adress' => 'upload/user/ada/images/a.jpg', 'position' => 2],
        ['adress' => "upload/user/ada/images/b.jpg', 1, 1, 'x'); DROP TABLE media; --", 'position' => 1],
    ]);
    check('save() adds rows, and SQL in a value stays a value', $saved === 2 && (int) $admin->query("SELECT COUNT(*) FROM $quotedName.`media`")->fetch_row()[0] === 2);
    $list = $media->get();
    check('get() in position order', count($list) === 2 && (int) $list[0]->position === 1 && $list[1]->image === 'upload/user/ada/images/a.jpg');
    Dll\User\Media::position([$list[1]->id => 0]);
    check('position() changes the order', $media->get()[0]->image === 'upload/user/ada/images/a.jpg');
    check('an invalid type throws', throws(fn() => new Dll\User\Media(7, 'virus'), InvalidArgumentException::class));

    User::login(['identity' => 'ada', 'password' => 'new sand dune']);
    $aId = $media->get()[0]->id;
    check('remove() deletes the row and the files made from it', Dll\User\Media::remove(7, $aId)
        && !is_file("$sandbox/upload/user/ada/images/a.jpg") && !is_file("$sandbox/upload/user/ada/images/a-300.jpg") && is_file("$sandbox/upload/user/ada/images/b.jpg"));
    $admin->query("INSERT INTO $quotedName.`media` (`images`, `position`, `imageid`, `type`) VALUES ('upload/user/bo/images/c.jpg', 1, 7, 'image')");
    $boId = (int) $admin->insert_id;
    Dll\User\Media::remove(7, $boId);
    check("remove() never deletes another user's files", is_file("$sandbox/upload/user/bo/images/c.jpg"));
    User::logout();

    $_SERVER['DOCUMENT_ROOT'] = $realRoot;
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sandbox, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($sandbox);

    $admin->query("DROP DATABASE IF EXISTS $quotedName");
    echo "  (test database removed)\n";
}

echo $failed === 0 ? "\nAll tests passed.\n" : "\n$failed test(s) failed.\n";
exit($failed === 0 ? 0 : 1);
