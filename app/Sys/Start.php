<?php

/**
 * Initializes and manages the system startup process.
 *
 * This class handles:
 * - Error handling registration
 * - Output buffering and compression
 * - Default language initialization
 * - Controller routing and execution
 * - 404 and 500 page handling
 * - Minification requests
 *
 * @file Start.php
 * @author Marcus Larsson
 * @copyright (c) 2011, Marcus Larsson
 * @category System Startup Class
 */
class Start
{
	/**
	 * System-wide error handler instance
	 *
	 * @var SystemException
	 */
	private static $errorHandler;

	/**
	 * Indicates whether the system has already been initialized
	 *
	 * @var bool
	 */
	private static $isInitialized = false;

	/**
	 * Constructor
	 *
	 * Registers the global error handler if not already initialized.
	 */
	public function __construct()
	{
		if (!self::$isInitialized) {
			self::$errorHandler = new SystemException();
			self::$errorHandler->register();
			self::$isInitialized = true;
		}
	}

	/**
	 * Destructor
	 *
	 * Handles system startup tasks, including:
	 * - Starting the session
	 * - Enabling output buffering and optional GZIP compression
	 * - Initializing the default language
	 * - Resolving the requested controller and method
	 * - Handling minification requests
	 * - Loading the requested file
	 * - Executing the controller method with router arguments
	 */
	public function __destruct()
	{
		// Start session
		Session::start();

		// Enable output buffering
		if (function_exists('ob_start')) {
			if (function_exists('ob_gzhandler')) {
				ob_start('ob_gzhandler');
			} else {
				ob_start();
			}
		} else {
			die("Please enable Output buffering and zlib compression to be supported on this server.");
		}

		// Initialize language
		$this->initializeDefaultLanguage();

		// Determine controller and method from HTTP headers
		if (isset($_SERVER['REDIRECT_HTTP_CONTROLLER'])) {
			$f = explode('|', $_SERVER['REDIRECT_HTTP_CONTROLLER']);
		} elseif (isset($_SERVER['HTTP_CONTROLLER'])) {
			$f = explode('|', $_SERVER['HTTP_CONTROLLER']);
		} else {
			$f = ['', 'default', 'index'];
		}

		// Guarantee array structure
		$f[0] = $f[0] ?? '';
		$f[1] = $f[1] ?? 'default';
		$f[2] = $f[2] ?? 'index';

		// Handle minify requests
		if ($f[0] === 'minify') {
			new Minifier($f);

			if (ob_get_level() > 0) {
				$data = ob_get_contents();
				ob_end_clean();
				echo $data;
			}

			exit;
		}

		// Determine file path
		$file = $_SERVER['HTTP_path_site'] . $f[0];

		// Developer mode router builder
		if (isset($_SERVER['HTTP_devmode']) && $_SERVER['HTTP_devmode'] === 'true' && !file_exists($file)) {
			$route = new RouterBuilder();
			$route->applyRules();
		}

		// 404 page handling
		$page404 = config::get('404 page');

		if ($f[0] === '') {
			if (file_exists($page404)) {
				$this->load($page404);
			} else {
				throw new SystemException(_('Could not load 404 page because the file is missing.'));
			}
		} else {
			// Load requested file
			$this->load($file);

			// Determine controller class
			$cname = $f[1] . 'Controller';

			if (!class_exists($cname)) {
				throw new SystemException(sprintf(_('Could not find controller with the name "%s" in %s'), $f[1], $file));
			}

			// Register controller in config
			config::registry($cname, _($file));

			// Instantiate controller
			$c = new $cname();

			// Resolve router arguments
			$args = $this->resolveRouterArguments($c, $f[2]);

			// Call requested method or fallback to index
			if (!method_exists($c, $f[2])) {
				if (method_exists($c, 'index')) {
					SystemException::checkpoint(['ControllerException' => sprintf(_('In controller index'))]);
					call_user_func_array([$c, 'index'], $args);
				} else {
					throw new SystemException(sprintf(_('Could not find method with the name "%s" or index in %s'), $f[2], $cname));
				}
			} else {
				SystemException::checkpoint(['ControllerException' => sprintf(_('In controller %s'), $cname)]);
				call_user_func_array([$c, $f[2]], $args);
			}
		}

		// Output buffered content
		$data = ob_get_contents();
		ob_end_clean();
		echo $data;
	}

	/**
	 * Initializes the default language for the system
	 *
	 * Priority order for determining the language:
	 * 1. Session language (if already set)
	 * 2. GET parameter 'setLang' (allows user to change language)
	 * 3. HTTP_LANGUAGE header (from server configuration)
	 *
	 * If no language is specified in the HTTP_LANGUAGE header, the method
	 * returns early without initializing any language.
	 *
	 * @return void
	 */
	protected function initializeDefaultLanguage()
	{
		$defaultLanguage = $_SERVER['HTTP_language'] ?? null;

		if (!$defaultLanguage)
			return;

		$userLanguage = Session::getLanguage();

		if ($userLanguage !== false) {
			$defaultLanguage = $userLanguage;
		}

		if (isset($_GET['setLang'])) {
			$defaultLanguage = $_GET['setLang'];
		}

		$lang = new Language($defaultLanguage);
		$lang->initialize();
	}

	/**
	 * Loads a PHP file with correct HTTP headers
	 *
	 * Sets the following headers before including the file:
	 * - Vary: Accept-Encoding (for proper caching with compression)
	 * - Content-Type: text/html; charset=utf-8 (UTF-8 encoded HTML)
	 *
	 * Headers are only set if they haven't been sent already.
	 *
	 * @param string $file Absolute path to the PHP file to include
	 * @return void
	 */
	protected function load($file)
	{
		if (!headers_sent()) {
			header('Vary: Accept-Encoding');
			header('Content-Type: text/html; charset=utf-8');
		}

		include_once $file;
	}

	/**
	 * Resolves arguments for a controller method using the @router annotation
	 *
	 * Parses the @router annotation in the method's docblock to extract URL
	 * parameters and pass them as arguments to the controller method.
	 *
	 * Supported router pattern formats:
	 * - {number(1-11):id} - Matches numeric values, captured as 'id'
	 * - {string(...):name} - Matches string values, captured as 'name'
	 * - {param} - Generic parameter, matches any non-slash characters
	 *
	 * Example annotation:
	 * @router user/{number(1-11):id}/profile -> userProfile
	 *
	 * The pattern is matched against the current REQUEST_URI and extracted
	 * parameters are returned as an array in the order they appear.
	 *
	 * @param object $controller The controller instance
	 * @param string $method The method name to check for router annotations
	 * @return array An array of extracted URL parameters, or empty array if no match
	 */
	protected function resolveRouterArguments(object $controller, string $method)
	{
		$ref = new ReflectionMethod($controller, $method);
		$doc = $ref->getDocComment();

		if (!$doc) {
			return [];
		}

		// Extract @router annotation
		if (!preg_match('/@router\s+(.+)/', $doc, $m)) {
			return [];
		}

		$router = trim($m[1]);

		// Must contain '->' separator
		if (!str_contains($router, '->')) {
			return [];
		}

		[$pattern] = array_map('trim', explode('->', $router));

		// Get current URI path
		$uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');

		// Build regex from router pattern
		$regex = preg_replace_callback(
			'/\{(number|string)\([^)]+\):([^}]+)\}/', // {number(1-11):id} or {string(...):name}
			function ($matches) {
				$type = $matches[1];   // number|string
				$name = $matches[2];   // parameter name
	
				// Return the regex pattern for each type
				return $type === 'number' ? '(\d+)' : '([^\/]+)';
			},
			$pattern
		);

		// Also replace generic {param} placeholders with string pattern
		$regex = preg_replace('/\{[^}]+\}/', '([^\/]+)', $regex);

		$regex = '#^' . $regex . '$#';

		if (!preg_match($regex, $uri, $matches)) {
			return [];
		}

		array_shift($matches); // Remove full match
		return $matches;
	}
}