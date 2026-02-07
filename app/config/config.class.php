<?php

/**
 * Configuration class for handling class loading and configuration values.
 *
 * This class manages automatic class loading through the autoload function and
 * stores configuration values as well as information about loaded classes.
 *
 * @file config.class.php
 * @author Marcus Larsson
 * @version 2014.1.1
 * @copyright (c) 2011, Marcus Larsson
 * @category Config class
 */
class config
{
	/**
	 * Static array for storing configuration values
	 *
	 * @var array
	 */
	private static $Configuration = [];

	/**
	 * Static array for storing information about loaded classes
	 *
	 * @var array
	 */
	private static $classes = [];

	/**
	 * Static array for storing class paths
	 *
	 * @var array
	 */
	private static $classPaths = [];

	/**
	 * Instance cache array
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * Constructor for LoaderClass that registers the autoload function
	 *
	 * Registers the ClassAutoLoader method with SPL autoload to enable
	 * automatic loading of classes when they are first referenced.
	 */
	public function Loader()
	{
		spl_autoload_register(array($this, 'ClassAutoLoader'));
	}

	/**
	 * Automatic class loader for handling class and trait loading
	 *
	 * This method is called automatically when a class or trait is referenced
	 * but not yet loaded. It searches for the class in multiple locations:
	 *
	 * For namespaced classes:
	 * - /app/{namespace}/{classname}.php
	 *
	 * For non-namespaced classes (in order):
	 * - Dll/{classname}.php
	 * - Sys/{classname}.php
	 * - Controller helpers (ApIController, IController, Iproperty)
	 *
	 * @param string $class The fully qualified class name to load
	 * @return void
	 * @throws SystemException If the class file cannot be found or loaded
	 */
	private function ClassAutoLoader($class)
	{
		// Check if the class has a namespace
		if (strpos($class, '\\') !== false) {
			// If the class has a namespace, build the path based on namespace and class name
			$file = $_SERVER['DOCUMENT_ROOT'] . "/app" . '/' . str_replace('\\', '/', $class) . '.php';
			try {
				// Check if the file exists, and include it if it does
				if (!file_exists($file)) {
					// If the class is not found in any of the directories, throw an exception
					print sprintf(_("Could not find class or trait in file %s."), $file);
				}

				include_once $file;

				if (!class_exists($class)) {
					// If the class does not exist, throw an exception
					if (!trait_exists($class)) {
						// If it's not a trait either, throw an exception
						print sprintf(_("Could not find class or trait in file %s."), $file);
					}
				}

				config::registry($class, $file);
				return;
			} catch (SystemException $e) {
			}
		} else {
			try {
				// First check for Dll\class.php
				$file = $_SERVER['HTTP_path_dll'] . $class . '.php';
				if (file_exists($file)) {
					include_once $file;

					if (!class_exists($class)) {
						// If the class does not exist, throw an exception
						if (!trait_exists($class)) {
							// If it's not a trait either, throw an exception
							throw new \SystemException(sprintf(_("Could not find class or trait in file %s."), $file));
						}
					}

					Config::registry($class, $file);
					return;
				}

				// Check for Sys\class.php
				$file = $_SERVER['HTTP_path_sys'] . $class . '.php';
				if (file_exists($file)) {
					include_once $file;

					if (!class_exists($class)) {
						// If the class does not exist, throw an exception
						if (!trait_exists($class)) {
							// If it's not a trait either, throw an exception
							throw new \SystemException(sprintf(_("Could not find class or trait in file %s."), $file));
						}
					}

					Config::registry($class, $file);
					return;
				}

				// Additional check and inclusion for certain helper classes
				$helper = array('ApIController', 'IController', 'Iproperty');

				if (in_array($class, $helper)) {
					config::registry($class, sprintf(_('%s class'), $class));
					include $_SERVER['HTTP_path_controller'] . $class . '.php';
					$helper = array();
					return;
				}

				throw new \SystemException(sprintf(_("Could not find file %s."), $file));
			} catch (SystemException $e) {
			}
		}
	}

	/**
	 * Static method for setting configuration data
	 *
	 * Sets a configuration value and persists it to the appropriate .ini.php file.
	 * Supports both simple keys (saved to default.ini.php) and namespaced keys
	 * in the format "classname.key" (saved to classname.ini.php).
	 *
	 * Examples:
	 * - config::set('timezone', 'Europe/Stockholm') - saves to default.ini.php
	 * - config::set('database.host', 'localhost') - saves to database.ini.php
	 *
	 * @param string $key The key for the configuration data (supports dot notation)
	 * @param mixed $data The data to store (string or array)
	 * @return void
	 */
	public static function set($key, $data)
	{
		$parts = explode('.', $key);

		if (sizeof($parts) <= 1) {
			// Load the default configuration file
			include_once $_SERVER['HTTP_path_config'] . 'settings/default.ini.php';
			$config = new default_ini();

			// Check if the key exists in the configuration
			if (!isset($config->set[$key])) {
				$f = new \Dll\Filesystem($_SERVER['HTTP_path_config'] . 'settings/');
				$f->open('default.ini.php');

				// Insert the new configuration data after the closing parenthesis
				if (is_array($data)) {
					$f->insertAfter(');', "\n // Setting for $key " . "\n " . '$this->set[' . "'$key'] = " . var_export($data) . ";");
				} else {
					$f->insertAfter(');', "\n // Setting for $key " . "\n " . '$this->set[' . "'$key'] = '$data';");
				}
			}
		} else {
			// Generate a new configuration file for the specified class if it doesn't already exist
			$class = $parts[0] . '_ini';
			if (!file_exists($_SERVER['HTTP_path_config'] . 'settings/' . $parts[0] . '.ini.php')) {
				$f = new \Dll\Filesystem($_SERVER['HTTP_path_config'] . 'settings/');
				$f->write(
					[
						0 => '<?',
						1 => '/**',
						2 => '* ' . $class . ' data ',
						3 => '*/',
						4 => ' ',
						5 => 'class ' . $class . ' {',
						6 => '    function __construct() {',
						7 => '',
						8 => '        $this->set = array();',
						9 => '        ',
						11 => '',
						12 => '    }',
						13 => '}',
						14 => '',
						15 => '?>'
					]
				)->theme($parts[0] . '.ini.php');

				$f->open($parts[0] . '.ini.php');

				// Insert the new configuration data after the closing parenthesis
				if (is_array($data)) {
					$f->insertAfter(');', "\n // Setting for $parts[1] " . "\n " . '$this->set[' . "'$parts[1]'] = " . var_export($data) . ";");
				} else {
					$f->insertAfter(');', "\n // Setting for $parts[1] " . "\n " . '$this->set[' . "'$parts[1]'] = '$data';");
				}
			}
		}
	}

	/**
	 * Retrieves configuration values from configuration classes
	 *
	 * Supports multiple formats for retrieving configuration data:
	 *
	 * Examples:
	 * <code>
	 * // Get a value from a named configuration class
	 * config::get('classname.value');
	 *
	 * // Get all values from a named configuration class
	 * config::get('classname.*');
	 *
	 * // Get all values from the default configuration class
	 * config::get('.*');
	 *
	 * // Get a value from the default configuration class
	 * config::get('value');
	 *
	 * // Get a value from the default configuration class, return false if not found
	 * config::get('value', true);
	 * </code>
	 *
	 * @param string|null $key The configuration key to retrieve (supports dot notation)
	 * @param bool $check If true, returns false instead of throwing exception when key is not found
	 * @return mixed The configuration value, array of values, or false if not found and $check is true
	 * @throws SystemException If the key is not found and $check is false
	 */
	public static function get($key = null, $check = false)
	{
		$parts = explode('.', $key);

		if (sizeof($parts) <= 1) {
			config::registry('Default_ini', _('Configuration class'));
			include_once $_SERVER['HTTP_path_config'] . 'settings/default.ini.php';
			$config = new default_ini();
			if ($parts[0] == "*") {
				$val = $config->set;
				unset($config);
				return $val;
			} else {
				$config = new default_ini();
				if (!isset($config->set[$key])) {
					if ($check) {
						return false;
					} else {
						throw new \SystemException(sprintf(_("Could not retrieve value %s"), $key));
					}
				} else {
					$val = $config->set[$key];
					unset($config);
					return $val;
				}
			}
		} else {
			$class = $parts['0'] . '_ini';
			if (!file_exists($_SERVER['HTTP_path_config'] . 'settings/' . $parts['0'] . '.ini.php')) {
				if ($check) {
					return false;
				} else {
					throw new \SystemException(sprintf(_("Could not find this file %s.ini.php."), $parts[1]));
				}
			}
			config::registry(sprintf(_('%s_ini '), $parts['0']), _('Configuration class'));
			include_once $_SERVER['HTTP_path_config'] . 'settings/' . $parts['0'] . '.ini.php';
			$config = new $class();

			if ($parts[1] == "*") {
				$val = $config->set;
				unset($config);
				return $val;
			} else {
				if (!isset($config->set[$parts[1]])) {
					if ($check) {
						return false;
					} else {
						throw new \SystemException(sprintf(_("Could not retrieve value %s."), $parts[1]));
					}
				} else {
					$val = $config->set[$parts['1']];
					unset($config);
					return $val;
				}
			}
		}
	}

	/**
	 * Registers a class in the system registry
	 *
	 * Stores information about when a class was loaded and its type.
	 * Used for debugging and tracking which classes have been loaded.
	 *
	 * Example:
	 * <code>
	 * config::registry('classname', 'type of class');
	 * </code>
	 *
	 * @param string $class The name of the class to register
	 * @param string $type The type or category of the class (default: 'dll')
	 * @return void
	 */
	public static function registry($class, $type = 'dll')
	{
		// Get current timestamp before registration
		$startTime = microtime(true);
		self::$classes[$class]['type'] = $type;
		self::$classes[$class]['time'] = $startTime;
	}

	/**
	 * Retrieves the registry of loaded classes and configuration classes
	 *
	 * Returns an array containing all registered classes with their types
	 * and load times. Useful for debugging and performance analysis.
	 *
	 * Example:
	 * <code>
	 * config::getRegistry();
	 * </code>
	 *
	 * @return array An associative array of all registered classes with metadata
	 */
	public static function getRegistry()
	{
		return self::$classes;
	}

	/**
	 * Loads and calls a function from a .fun.php file
	 *
	 * This function dynamically loads a function file and executes it with
	 * the provided arguments. Function files should be located in the
	 * HTTP_path_funs directory and follow the naming convention:
	 * {functionName}.fun.php
	 *
	 * The function caches loaded functions so subsequent calls don't require
	 * re-including the file.
	 *
	 * Example:
	 * <code>
	 * $data = config::loadFunction('calculateTax', 100, 0.25);
	 * </code>
	 *
	 * @param string $functionName The name of the function to load and call
	 * @param mixed ...$args Additional arguments to pass to the function
	 * @return mixed The result of the executed function
	 * @throws SystemException If no function name is provided, if the function name
	 *                         contains invalid characters, if the file is not found,
	 *                         or if the function doesn't exist after file inclusion
	 */
	public static function loadFunction($functionName)
	{
		// Get all arguments
		$args = func_get_args();

		// Check if no arguments were provided
		if (empty($args)) {
			throw new SystemException(_("Specify the function name."));
		}

		// Get the function name from the first argument
		$functionName = $args[0];

		// Remove the first argument from the array
		unset($args[0]);

		// Validate that the function name contains only valid characters (a-z, A-Z, 0-9, and underscore)
		// Maximum length of 40 characters
		if (!preg_match('/^[a-zA-Z0-9_]{1,40}$/', $functionName)) {
			throw new SystemException(_("Invalid characters in function name."));
		}

		// Check if the function already exists
		if (function_exists($functionName)) {
			// Function already exists, call it directly
			return call_user_func_array($functionName, $args);
		} else {
			// Build the path to the file based on the function name
			$filename = $_SERVER['HTTP_path_funs'] . "{$functionName}.fun.php";

			// Check if the file exists
			if (file_exists($filename)) {
				config::registry($filename, 'function');
				// File exists, include it
				config::registry('Config', _('Function'));
				include_once $filename;

				// Check if the function now exists after inclusion
				if (function_exists($functionName)) {
					// Function has been loaded, call it
					return call_user_func_array($functionName, $args);
				} else {
					throw new SystemException(_("Function '{$functionName}' was not found after including the file."));
				}
			} else {
				throw new SystemException(_("File '{$filename}' was not found."));
			}
		}
	}
}

// Call the static registry method on the config class
config::registry('Config', _('Configuration class'));

// Create an instance of the config class
$confighandler = new config();

// Call the Loader method on the created instance
$confighandler->Loader();