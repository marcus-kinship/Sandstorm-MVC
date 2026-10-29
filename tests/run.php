<?php

/**
 * Run all tests.
 *
 * Run: php tests/run.php
 *
 * The database tests need app/config/mysql/test.db.php (see tests/database.php);
 * without it they are skipped.
 */

$php = escapeshellarg(PHP_BINARY);
$flags = '';
foreach (['gettext', 'mysqli', 'mbstring', 'curl', 'gd'] as $extension) {
    if (!extension_loaded($extension)) {
        $flags .= ' -d extension=' . $extension;
    }
}

$tests = ['session.php', 'record.php', 'curl.php', 'filesystem.php', 'minifier.php', 'pagination.php', 'resizing.php', 'user.php'];
if (is_file(dirname(__DIR__) . '/app/config/mysql/test.db.php')) {
    $tests[] = 'database.php';
} else {
    echo "database.php skipped: no app/config/mysql/test.db.php\n";
}

$failed = [];
foreach ($tests as $test) {
    echo "\n=== $test\n";
    passthru("$php$flags " . escapeshellarg(__DIR__ . "/$test"), $status);
    if ($status !== 0) {
        $failed[] = $test;
    }
}

echo $failed === [] ? "\nAll test files passed.\n" : "\nFailed: " . implode(', ', $failed) . "\n";
exit($failed === [] ? 0 : 1);
