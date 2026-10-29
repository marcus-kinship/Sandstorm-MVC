<?php

/**
 * Local development server for Sandstorm.
 *
 * Sandstorm is configured through .htaccess (Apache). PHP's built-in server
 * does not read .htaccess, so this file does the same work for local use:
 * it reads the SetEnv, SetEnvIfNoCase and [E=...] lines from .htaccess and
 * fills $_SERVER with them before handing over to index.php.
 *
 * Usage:
 *   php server.php              Start on http://localhost:8000
 *   php server.php 8080         Start on another port
 *
 * Run "php setup.php" first to write the settings and check the system.
 *
 * @file server.php
 * @author Marcus Larsson
 * @category Development server
 */

// Started from the command line: launch the built-in server with this file as router
if (PHP_SAPI === 'cli') {
	$port = isset($argv[1]) ? (int) $argv[1] : 8000;
	$options = ['-d', 'short_open_tag=On'];

	// Load gettext if it is installed but not enabled in php.ini
	if (!extension_loaded('gettext')) {
		$options[] = '-d';
		$options[] = 'extension=gettext';
	}

	$command = escapeshellarg(PHP_BINARY);
	foreach ($options as $option) {
		$command .= ' ' . escapeshellarg($option);
	}
	$command .= ' -S ' . escapeshellarg("localhost:$port") . ' -t ' . escapeshellarg(__DIR__) . ' ' . escapeshellarg(__FILE__);

	echo "Sandstorm is running on http://localhost:$port (Ctrl+C to stop)\n";
	passthru($command, $status);
	exit($status);
}

$root = str_replace('\\', '/', __DIR__);
$_SERVER['DOCUMENT_ROOT'] = $root;
$uri = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Existing files are served directly, except in the protected folders
if ($uri !== '/' && is_file($root . $uri) && !preg_match('#^/(?:app|site|upload)\b#i', $uri)) {
	return false;
}

// Apply the environment lines from .htaccess in the same order as Apache
foreach (file($root . '/.htaccess', FILE_IGNORE_NEW_LINES) as $line) {
	$line = trim($line);

	// SetEnv HTTP_name value
	if (preg_match('/^SetEnv\s+(\S+)\s+"?([^"]*)"?$/i', $line, $m)) {
		$_SERVER[$m[1]] = $m[2];

	// SetEnvIfNoCase Request_URI "regex" NAME=value
	} elseif (preg_match('/^SetEnvIfNoCase\s+Request_URI\s+"([^"]+)"\s+([^=\s]+)=(\S*)/i', $line, $m)) {
		if (preg_match('#' . str_replace('#', '\#', $m[1]) . '#i', $uri, $match)) {
			$_SERVER[$m[2]] = preg_replace_callback('/\$(\d)/', fn($n) => $match[$n[1]] ?? '', $m[3]);
		}

	// RewriteRule ^ - [E=NAME:value]
	} elseif (preg_match('/^RewriteRule\s+\^\s+-\s+\[E=([^:]+):([^\]]+)\]/i', $line, $m)) {
		$_SERVER[$m[1]] = str_replace('%{DOCUMENT_ROOT}', $root, $m[2]);
	}
}

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';

require $root . '/index.php';
