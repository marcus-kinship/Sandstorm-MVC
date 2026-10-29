<?php

/**
 * Database pool settings.
 *
 * Reads app/config/mysql/<pool>.db.php (class <pool>_db) once per request
 * and keeps it, so switching between pools does not read the file again.
 *
 * @file DatabaseManager.php
 * @author Marcus Larsson
 * @copyright (c) 2011, Marcus Larsson
 * @category _("DatabaseManager class")
 */
class DatabaseManager
{
	/**
	 * Settings that are already read: pool name => settings object
	 *
	 * @var array<string, object>
	 */
	private static array $pools = [];

	/**
	 * Settings for a pool.
	 *
	 * Required: $user and $name (the password may be empty, for example for
	 * a local root without password). Missing optional settings get defaults:
	 * hostname localhost, driver mysql, type as:text, charset utf8mb4,
	 * lengthmax 0 (no limit).
	 *
	 * @param string $cfg Pool name, for example 'default'
	 * @return object The pool settings
	 * @throws SystemException If the file or a required setting is missing
	 */
	public static function getConnection(string $cfg): object
	{
		if (isset(self::$pools[$cfg])) {
			return self::$pools[$cfg];
		}

		if (!preg_match('/^\w+$/', $cfg)) {
			throw new SystemException(sprintf(_('Invalid database pool name: %s'), $cfg));
		}

		$file = $_SERVER['HTTP_path_mysql'] . $cfg . '.db.php';
		if (!is_file($file)) {
			throw new SystemException(sprintf(_('Could not find the database settings %s'), $file));
		}

		include_once $file;
		config::registry($cfg . '_db', _('Configuration class'));

		// A plain object: defaults can be added without touching the pool class
		// (no dynamic-property deprecations for older pool files)
		$class = $cfg . '_db';
		$c = (object) get_object_vars(new $class());

		foreach (['user', 'name'] as $required) {
			if (empty($c->$required)) {
				throw new SystemException(sprintf(_('Database setting $%s is missing or empty in %s'), $required, $file));
			}
		}

		$c->password ??= '';
		$c->hostname ??= 'localhost';
		$c->driver ??= 'mysql';
		$c->type ??= 'as:text';
		$c->charset = !empty($c->charset) ? $c->charset : 'utf8mb4';
		$c->lengthmax ??= 0;
		$c->settingsname = $cfg;

		return self::$pools[$cfg] = $c;
	}
}
