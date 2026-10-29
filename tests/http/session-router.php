<?php

/**
 * Router for tests/session.php: runs one Session or CookieManager action per
 * request (?a=...), with the framework loaded but without .htaccess routes.
 *
 * Only for PHP's built-in server started by the test.
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));

$_SERVER['DOCUMENT_ROOT'] = $root;
foreach (['controller' => 'app/controller/', 'sys' => 'app/Sys/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/',
    'site' => 'site/', 'mysql' => 'app/config/mysql/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
// Settings from the test's own folder (cookie secret), not from app/config
$_SERVER['HTTP_path_config'] = getenv('SANDSTORM_TEST_CONFIG') . '/';
$_SERVER['HTTP_devmode'] = 'true';

// The test asks for HTTPS this way, to check the Secure flag
if (($_SERVER['HTTP_X_TEST_HTTPS'] ?? '') === 'on') {
    $_SERVER['HTTPS'] = 'on';
}

include "$root/app/config/config.class.php";
(new SystemException())->register();

ob_start();

switch ($_GET['a'] ?? '') {
    case 'nothing':
        echo 'nothing';
        break;
    case 'get':
        echo json_encode(Session::get('n', 'none'));
        break;
    case 'set':
        Session::set('n', (int) Session::get('n', 0) + 1);
        echo json_encode(Session::get('n'));
        break;
    case 'id':
        Session::start();
        echo Session::getSessionId();
        break;
    case 'regenerate':
        Session::start();
        $old = Session::getSessionId();
        Session::regenerate();
        echo $old . '|' . Session::getSessionId() . '|' . json_encode(Session::get('n', 'none'));
        break;
    case 'kill':
        Session::kill();
        echo 'killed';
        break;
    case 'add':
        Session::add('list', 'x');
        Session::add('list', 'y', 'key');
        echo json_encode(Session::get('list'));
        break;
    case 'remove':
        Session::remove('n');
        echo json_encode(Session::get('n', 'none'));
        break;
    case 'language':
        Session::setLanguage('sv_SE');
        echo Session::getLanguage();
        break;
    case 'exists':
        echo Session::exists() ? 'yes' : 'no';
        break;
    case 'cookie-set':
        CookieManager::set('theme', 'dark', 3600);
        CookieManager::set('prefs', ['size' => 2, 'lang' => 'sv']);
        echo 'set';
        break;
    case 'cookie-get':
        echo json_encode([CookieManager::get('theme'), CookieManager::get('prefs'), CookieManager::get('missing', 'default')]);
        break;
    case 'cookie-signed-set':
        CookieManager::setSigned('plan', ['level' => 'pro']);
        echo 'set';
        break;
    case 'cookie-signed-get':
        echo json_encode(CookieManager::getSigned('plan'));
        break;
    case 'cookie-delete':
        CookieManager::delete('theme');
        echo json_encode(CookieManager::has('theme'));
        break;
    case 'cookie-bad-name':
        try {
            CookieManager::set("bad name;", 'x');
            echo 'accepted';
        } catch (InvalidArgumentException $e) {
            echo 'rejected';
        }
        break;
    case 'cookie-none':
        CookieManager::set('cross', '1', 0, ['samesite' => 'None']);
        echo 'set';
        break;
    case 'cookie-signed-old':
        // Signed with an expiry time that has already passed
        CookieManager::setSigned('old', 'x', 0, [], -10);
        echo 'set';
        break;
    case 'cookie-signed-old-get':
        echo json_encode(CookieManager::getSigned('old'));
        break;
    case 'cookie-host':
        CookieManager::set('__Host-id', '1', 0, ['path' => '/admin', 'domain' => 'example.com', 'secure' => false]);
        echo 'set';
        break;
    case 'cookie-large':
        try {
            CookieManager::set('big', str_repeat('x', 5000));
            echo 'accepted';
        } catch (InvalidArgumentException $e) {
            echo 'rejected';
        }
        break;
}

ob_end_flush();
