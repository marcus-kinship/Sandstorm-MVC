<?php

/**
 * Router for tests/curl.php: answers with what it received, or redirects.
 *
 * Only for PHP's built-in server started by the test.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/missing') {
    http_response_code(404);
    echo 'not found';
    return;
}

if ($path === '/to-file') {
    header('Location: file:///etc/hosts', true, 302);
    return;
}

if ($path === '/to-echo') {
    header('Location: /echo?redirected=1', true, 302);
    return;
}

header('Content-Type: application/json');
echo json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'get' => $_GET, 'post' => $_POST]);
