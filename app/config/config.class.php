<?php

/**
 * Configuration and class loading.
 *
 * - Autoloader: finds classes in app/Dll, app/Sys, the controller helpers and,
 *   for namespaced classes, app/<Namespace>/<Class>.php.
 * - Settings: config::get() reads app/config/settings/<name>.ini.php once per
 *   request; config::set() adds a setting to that file.
 * - Registry: which classes were loaded and when (shown on the error page).
 * - Function files: config::loadFunction() loads app/funs/<name>.fun.php.
 *
 * @file config.class.php
 * @author Marcus Larsson
 * @copyright (c) 2011, Marcus Larsson
 * @category _("Config class")
 */
class config
{
	/**
	 * Loaded classes: name => ['type' => ..., 'time' => microtime]
	 *
	 * @var array<string, array{type: string, time: float}>
	 */
	private static array $classes = [];

	/**
	 * Settings that are already read in this request: ini class name => settings
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private static array $settings = [];

	/**
	 * Controller helper classes that live in app/controller/
	 *
	 * @var string[]
	 */
	private const HELPERS = ['ApIController', 'IController', 'Iproperty'];

	/**
	 * Register the autoloader.
	 *
	 * @return void
	 */
	public function Loader(): void
	{
		spl_autoload_register([$this, 'ClassAutoLoader']);
	}

	/**
	 * Load a class or trait the first time it is used.
	 *
	 * Namespaced:     app/<Namespace>/<Class>.php
	 * Not namespaced: app/Dll/<Class>.php, then app/Sys/<Class>.php, then the
	 *                 controller helpers in app/controller/.
	 *
	 * Returns quietly when there is no file, so class_exists() works and PHP
	 * reports the missing class itself.
	 *
	 * @param string $class Fully qualified class name
	 * @return void
	 */
	private function ClassAutoLoader(string $class): void
	{
		if (str_contains($class, '\\')) {
			$file = $_SERVER['DOCUMENT_ROOT'] . '/app/' . str_replace('\\', '/', $class) . '.php';
		} elseif (is_file($_SERVER['HTTP_path_dll'] . $class . '.php')) {
			$file = $_SERVER['HTTP_path_dll'] . $class . '.php';
		} elseif (is_file($_SERVER['HTTP_path_sys'] . $class . '.php')) {
			$file = $_SERVER['HTTP_path_sys'] . $class . '.php';
		} elseif (in_array($class, self::HELPERS, true)) {
			$file = $_SERVER['HTTP_path_controller'] . $class . '.php';
		} else {
			return;
		}

		if (!is_file($file)) {
			return;
		}

		include_once $file;
		self::registry($class, $file);
	}

	/**
	 * Add a setting to a settings file.
	 *
	 * - config::set('timezone', 'Europe/Stockholm')  adds to default.ini.php
	 * - config::set('database.host', 'localhost')     adds to database.ini.php (created if missing)
	 *
	 * A setting that already exists is left as it is.
	 *
	 * @param string $key  Setting name, optionally "file.setting"
	 * @param mixed  $data Value (string, number, bool or array)
	 * @return void
	 * @throws \InvalidArgumentException If the file name is not a plain word
	 * @throws \RuntimeException If the settings file cannot be read or written
	 */
	public static function set(string $key, mixed $data): void
	{
		[$name, $field] = self::splitKey($key);

		if (!preg_match('/^\w+$/', $name)) {
			throw new \InvalidArgumentException(sprintf(_('Invalid settings file name: %s'), $name));
		}

		if (array_key_exists($field, self::read($name, false) ?? [])) {
			return;
		}

		$file = $_SERVER['HTTP_path_config'] . "settings/$name.ini.php";

		if (!is_file($file)) {
			$template = "<?php\n\n/**\n * $name settings, written by config::set()\n */\nclass {$name}_ini\n{\n"
				. "\tpublic \$set = [];\n\n\tfunction __construct()\n\t{\n\t\t\$this->set = array(\n\t\t);\n\t}\n}\n";
			if (file_put_contents($file, $template) === false) {
				throw new \RuntimeException(sprintf(_('Could not create the settings file %s'), $file));
			}
		}

		$code = file_get_contents($file);
		$end = $code === false ? false : strpos($code, ');');
		if ($end === false) {
			throw new \RuntimeException(sprintf(_('Could not find the settings array in %s'), $file));
		}

		// Added after the settings array, so expressions in the array stay as they are
		$line = "\n\n\t\t// Setting for $field\n\t\t\$this->set[" . var_export($field, true) . '] = ' . var_export($data, true) . ';';
		$code = substr_replace($code, $line, $end + 2, 0);

		if (file_put_contents($file, $code) === false) {
			throw new \RuntimeException(sprintf(_('Could not write the settings file %s'), $file));
		}

		if (function_exists('opcache_invalidate')) {
			opcache_invalidate($file, true);
		}
		unset(self::$settings[$name . '_ini']);
	}

	/**
	 * Read a setting.
	 *
	 * <code>
	 * config::get('value');            // from default.ini.php
	 * config::get('*');                // all of default.ini.php
	 * config::get('classname.value');  // from classname.ini.php
	 * config::get('classname.*');      // all of classname.ini.php
	 * config::get('value', true);      // false instead of an exception when missing
	 * </code>
	 *
	 * @param string $key   Setting name, optionally "file.setting"
	 * @param bool   $check True: return false when the setting or file is missing
	 * @return mixed The value, all values for "*", or false (with $check)
	 * @throws SystemException When the setting or file is missing and $check is false
	 */
	public static function get(string $key = '', bool $check = false): mixed
	{
		[$name, $field] = self::splitKey($key);

		$settings = self::read($name, !$check);
		if ($settings === null) {
			return false;
		}

		if ($field === '*') {
			return $settings;
		}

		if (!array_key_exists($field, $settings)) {
			if ($check) {
				return false;
			}
			throw new SystemException(sprintf(_('Could not retrieve value %s'), $key));
		}

		return $settings[$field];
	}

	/**
	 * Split "file.setting" into [file, setting]; no dot means default.ini.php.
	 *
	 * @param string $key
	 * @return array{0: string, 1: string}
	 */
	private static function splitKey(string $key): array
	{
		$parts = explode('.', $key, 2);

		if (count($parts) === 1) {
			return ['default', $key];
		}

		return [$parts[0] === '' ? 'default' : $parts[0], $parts[1]];
	}

	/**
	 * Read a settings file once per request.
	 *
	 * @param string $name   File name without .ini.php
	 * @param bool   $strict Throw when the file is missing (otherwise return null)
	 * @return array<string, mixed>|null
	 * @throws SystemException When the file is missing and $strict is true
	 */
	private static function read(string $name, bool $strict): ?array
	{
		$class = $name . '_ini';

		if (!isset(self::$settings[$class])) {
			$file = $_SERVER['HTTP_path_config'] . "settings/$name.ini.php";

			if (!is_file($file)) {
				if ($strict) {
					throw new SystemException(sprintf(_('Could not find this file %s.ini.php.'), $name));
				}
				return null;
			}

			include_once $file;
			self::registry($class, _('Configuration class'));
			self::$settings[$class] = (new $class())->set;
		}

		return self::$settings[$class];
	}

	/**
	 * Note that a class was loaded (shown on the error page).
	 *
	 * <code>
	 * config::registry('classname', 'type of class');
	 * </code>
	 *
	 * @param string $class Class name
	 * @param string $type  Kind of class or the file it came from
	 * @return void
	 */
	public static function registry(string $class, string $type = 'dll'): void
	{
		self::$classes[$class] = ['type' => $type, 'time' => microtime(true)];
	}

	/**
	 * All classes noted with registry(), in load order.
	 *
	 * @return array<string, array{type: string, time: float}>
	 */
	public static function getRegistry(): array
	{
		return self::$classes;
	}

	/**
	 * Load app/funs/<name>.fun.php (once) and call the function.
	 *
	 * <code>
	 * $data = config::loadFunction('calculateTax', 100, 0.25);
	 * </code>
	 *
	 * @param string $functionName Function name (letters, digits and _, at most 40)
	 * @param mixed  ...$args      Arguments for the function
	 * @return mixed What the function returns
	 * @throws SystemException If the name is invalid, the file is missing or the function is not in it
	 */
	public static function loadFunction(string $functionName, mixed ...$args): mixed
	{
		if (!preg_match('/^[a-zA-Z0-9_]{1,40}$/', $functionName)) {
			throw new SystemException(_('Invalid characters in function name.'));
		}

		if (!function_exists($functionName)) {
			$filename = $_SERVER['HTTP_path_funs'] . "$functionName.fun.php";

			if (!is_file($filename)) {
				throw new SystemException(sprintf(_("File '%s' was not found."), $filename));
			}

			include_once $filename;
			self::registry($filename, _('Function'));

			if (!function_exists($functionName)) {
				throw new SystemException(sprintf(_("Function '%s' was not found after including the file."), $functionName));
			}
		}

		return $functionName(...$args);
	}
}

config::registry('Config', _('Configuration class'));

$confighandler = new config();
$confighandler->Loader();
