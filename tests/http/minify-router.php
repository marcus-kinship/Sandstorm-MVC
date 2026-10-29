<?php

/**
 * Router for tests/minifier.php: serves /res/css/<name>.minify.css|js with
 * Minifier, with a temporary folder as the project folder.
 *
 * Only for PHP's built-in server started by the test.
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));

foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = getenv('SANDSTORM_TEST_ROOT');
$_SERVER['HTTP_devmode'] = getenv('SANDSTORM_TEST_DEVMODE') ?: 'false';

include "$root/app/config/config.class.php";

// Same pattern as the minify line in .htaccess
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^(/res/css/[A-Za-z0-9/]{1,16})\.minify\.(CSS|css|js|JS)$#i', $uri, $m)) {
    new Minifier(['minify', $m[1], $m[2]]);
} else {
    http_response_code(404);
}
