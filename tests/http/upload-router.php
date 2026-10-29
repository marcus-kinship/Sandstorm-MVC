<?php

/**
 * Router for tests/upload.php: runs UploadedFiles on the posted files and
 * answers with the result as JSON.
 *
 * Only for PHP's built-in server started by the test. The sandbox folder
 * (DOCUMENT_ROOT for Filesystem::resolve) comes from the SANDSTORM_SANDBOX
 * environment variable.
 */

$root = str_replace('\\', '/', dirname(__DIR__, 2));
foreach (['config' => 'app/config/', 'sys' => 'app/Sys/', 'controller' => 'app/controller/', 'dll' => 'app/Dll/', 'funs' => 'app/funs/'] as $key => $path) {
    $_SERVER['HTTP_path_' . $key] = "$root/$path";
}
$_SERVER['DOCUMENT_ROOT'] = $root;
include "$root/app/config/config.class.php";
$_SERVER['DOCUMENT_ROOT'] = (string) getenv('SANDSTORM_SANDBOX');

$upload = new \Dll\Validation\UploadedFiles('send');
$upload->field = 'photos';
$upload->path = $_GET['path'] ?? 'upload';
$upload->maxsize = (int) ($_GET['maxsize'] ?? 2097152);
$upload->maxfiles = $_GET['maxfiles'] ?? '';
if (isset($_GET['extension'])) {
    $upload->extension = explode(',', $_GET['extension']);
}

header('Content-Type: application/json');
echo json_encode(['valid' => $upload->valid(), 'files' => $upload->getFiles(), 'errors' => $upload->errors, 'success' => $upload->success]);
