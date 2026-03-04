<?php

/**
 * Class DatabaseManager
 *
 * Handles and provides database connections.
 *
 * @file DatabaseManager.php
 * @author Marcus Larsson
 * @version 2014.1.1
 * @copyright (c) 2011, Marcus Larsson
 * 
 * @category _("DatabaseManager class")
 */
class DatabaseManager
{
	/**
	 * @var null|object|null Database connection pool
	 */
	private static ?object $pool = null;

	/**
	 * Retrieves a database connection.
	 *
	 * This function loads the configuration file, creates a database object
	 * based on the default file `default.db.php` in the MySQL folder,
	 * and returns a pool object with connection properties.
	 *
	 * Required object variables: $c->hostname, $c->user, $c->password, $c->name
	 *
	 * @param string $cfg Database configuration name
	 * @return object Database connection pool
	 * @throws SystemException If a required property is missing
	 */
	public static function getConnection(string $cfg): object
	{
		if (!self::$pool || self::$pool->settingsname !== $cfg) {
			// Ensure config class is loaded
			config::registry('Pool', _('Configuration class'));

			include_once $_SERVER['HTTP_path_mysql'] . $cfg . '.db.php';
			$cfgName = $cfg . '_db';
			$c = new $cfgName();

			// Validate required properties
			foreach (['user', 'name', 'password'] as $required) {
				if (!property_exists($c, $required) || !$c->$required) {
					throw new SystemException(
						_("Configuration object missing required property or is empty: %s", $required)
					);
				}
			}

			// Assign default values for optional properties if missing
			$c->hostname ??= 'localhost';
			$c->driver ??= 'mysql';
			$c->type ??= 'as:text';
			$c->charset ??= '';

			// Set pool and remember configuration name
			self::$pool = $c;
			self::$pool->settingsname = $cfg;
		}

		return self::$pool;
	}
}