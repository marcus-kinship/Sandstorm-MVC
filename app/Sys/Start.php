<?php

/**
 * Runs one request: error handling, output buffer, language, controller.
 *
 * Kept small on purpose, since it runs on every request:
 * - the session is not started here (Session starts on first use), and the
 *   stored language is only read for visitors who already have a session
 * - settings are read through config::get(), which reads each file once
 * - the @router docblock is only parsed for methods that have one
 *
 * index.php creates the object; the constructor registers the error
 * handler and the destructor runs the request.
 *
 * @file Start.php
 * @author Marcus Larsson
 * @copyright (c) 2011, Marcus Larsson
 * @category _(System Startup Class)
 */
class Start
{
	/**
	 * System-wide error handler instance
	 *
	 * @var SystemException|null
	 */
	private static ?SystemException $errorHandler = null;

	/**
	 * Register the error handler (once).
	 */
	public function __construct()
	{
		if (self::$errorHandler === null) {
			self::$errorHandler = new SystemException();
			self::$errorHandler->register();
		}
	}

	/**
	 * Run the request.
	 */
	public function __destruct()
	{
		$this->run();
	}

	/**
	 * Resolve the controller from the route in .htaccess and call it.
	 *
	 * HTTP_CONTROLLER (set by .htaccess) is "file|controller|method|":
	 * an empty file means no route matched (404), "minify" serves CSS/JS.
	 *
	 * @return void
	 * @throws SystemException When the controller, the method or the 404 page is missing
	 */
	public function run(): void
	{
		// One output buffer, compressed once when the page is done
		ob_start(function_exists('ob_gzhandler') ? 'ob_gzhandler' : null);

		$this->initializeDefaultLanguage();

		$route = explode('|', $_SERVER['REDIRECT_HTTP_CONTROLLER'] ?? $_SERVER['HTTP_CONTROLLER'] ?? '|default|index');
		$path = $route[0] ?? '';
		$controller = ($route[1] ?? '') !== '' ? $route[1] : 'default';
		$method = ($route[2] ?? '') !== '' ? $route[2] : 'index';

		if ($path === 'minify') {
			new Minifier($route);
			ob_end_flush();
			exit;
		}

		$file = $_SERVER['HTTP_path_site'] . $path;

		// Devmode: write routes for new controllers into .htaccess
		if ($path !== '' && ($_SERVER['HTTP_devmode'] ?? '') === 'true' && !is_file($file)) {
			(new RouterBuilder())->applyRules();
		}

		if ($path === '') {
			$this->notFound();
		} else {
			$this->load($file);

			$class = $controller . 'Controller';
			if (!class_exists($class, false)) {
				throw new SystemException(sprintf(_('Could not find controller with the name "%s" in %s'), $controller, $file));
			}
			config::registry($class, $file);

			$instance = new $class();

			if (!method_exists($instance, $method)) {
				if (!method_exists($instance, 'index')) {
					throw new SystemException(sprintf(_('Could not find method with the name "%s" or index in %s'), $method, $class));
				}
				$method = 'index';
			}

			SystemException::checkpoint(['ControllerException' => sprintf(_('In controller %s'), $class)]);
			$instance->$method(...$this->resolveRouterArguments($instance, $method));

			// The controller renders its views when it is destroyed: do it now,
			// inside the buffer, so the views are compressed and an error in a
			// view still reaches the error page
			unset($instance);
		}

		ob_end_flush();
	}

	/**
	 * Show the 404 page with status 404.
	 *
	 * @return void
	 * @throws SystemException When the 404 page file is missing
	 */
	protected function notFound(): void
	{
		$page404 = config::get('404 page', true);

		if (!$page404 || !is_file($page404)) {
			throw new SystemException(_('Could not load 404 page because the file is missing.'));
		}

		http_response_code(404);
		$this->load($page404);
	}

	/**
	 * Set up the language for this request.
	 *
	 * Order: ?setLang=xx_XX (if that locale exists), then the language stored
	 * in the visitor's session, then HTTP_language from .htaccess.
	 *
	 * @return void
	 */
	protected function initializeDefaultLanguage(): void
	{
		$language = $_SERVER['HTTP_language'] ?? '';

		if ($language === '') {
			return;
		}

		$userLanguage = Language::getFromSession();
		if ($userLanguage !== false) {
			$language = $userLanguage;
		}

		// Only a locale name that has a folder in app/locales
		$requested = $_GET['setLang'] ?? '';
		if (is_string($requested) && preg_match('/^[a-z]{2,3}_[A-Z]{2}$/', $requested)
			&& is_dir($_SERVER['DOCUMENT_ROOT'] . '/app/locales/' . $requested)) {
			$language = $requested;
		}

		(new Language($language))->initialize();
	}

	/**
	 * Include a page with UTF-8 HTML headers.
	 *
	 * @param string $file Absolute path to the PHP file to include
	 * @return void
	 */
	protected function load(string $file): void
	{
		if (!headers_sent()) {
			header('Vary: Accept-Encoding');
			header('Content-Type: text/html; charset=utf-8');
		}

		include_once $file;
	}

	/**
	 * Read URL arguments for a controller method from its @router docblock.
	 *
	 * Supported patterns:
	 * - {number(1-11):id}  digits, passed as 'id'
	 * - {string(...):name} anything but a slash, passed as 'name'
	 * - {param}            anything but a slash
	 *
	 * Example: @router user/{number(1-11):id}/profile -> userProfile
	 *
	 * @param object $controller The controller instance
	 * @param string $method     The method that will be called
	 * @return array<int, string> The URL parts in order, or [] when nothing matches
	 */
	protected function resolveRouterArguments(object $controller, string $method): array
	{
		$doc = (new ReflectionMethod($controller, $method))->getDocComment();

		if (!$doc || !str_contains($doc, '@router') || !preg_match('/@router\s+(.+?)\s*->/', $doc, $m)) {
			return [];
		}

		$regex = preg_replace('/\{(number)\([^)]+\):[^}]+\}/', '(\d+)', trim($m[1]));
		$regex = preg_replace('/\{[^}]+\}/', '([^\/]+)', $regex);

		$uri = trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '', '/');

		if (!preg_match('#^' . $regex . '$#', $uri, $matches)) {
			return [];
		}

		array_shift($matches);
		return $matches;
	}
}
