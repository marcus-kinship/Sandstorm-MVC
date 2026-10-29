<?php

use Sys\SystemException\ExceptionLogger;
use Sys\SystemException\ExceptionRenderer;


/**
 * Error Handling
 *
 * Error handling for PHP functions that normally don't have exceptions. Converts
 * regular error messages to exceptions where possible.
 * Error handling has its own language file that can be modified.
 *
 * Started in Start.php with:
 * $errorhandler = new SystemException();
 * $errorhandler->register();
 *
 * Throwing a SystemException has no side effects, so it can be caught.
 * An exception that is not caught reaches exceptionHandler(), which calls
 * show(): the full error page in devmode, a short 500 page otherwise.
 *
 * @category _("SystemException Management class")
 *
 */
class SystemException extends Exception
{
	// Private properties and class variables
	private $previous; // Previous exception
	private static $property = array(); // Static array to hold properties
	private $loadfile = ""; // File to be loaded to render a view
	public static $where = array(); // Static array to hold information about where exception was thrown from
	private static $_data = array(); // Changed to static
	private $load = false;
	private static ?ExceptionRenderer $renderer = null; // Static renderer
	private static ?ExceptionLogger $logger = null; // Static logger
	private static $pathExceptionLogger = __DIR__ . '/SystemException/ExceptionLogger.php';
	private $data = array(); // Error data collected in the constructor, used by show()
	private static $showing = false; // True while an error page is being rendered

    protected static function viewPath()
    {
        return dirname(__FILE__) . '/SystemException/Views/';
    }

	/**
	 * Render header via ExceptionRenderer with fluent interface
	 */
    public static function header(): void
    {
		if (self::$renderer === null) {
			self::$renderer = new ExceptionRenderer(self::$_data, self::viewPath());
		}
		self::$renderer->header();
    }
    
	/**
	 * Render view/body via ExceptionRenderer
	 */
	public static function view(): void
	{
		if (self::$renderer === null) {
			
			self::$renderer = new ExceptionRenderer(self::$_data, self::viewPath());
		}
		self::$renderer->view();
	}

	/**
	 * Render footer via ExceptionRenderer
	 */
    public static function footer(): void
    {
		if (self::$renderer === null) {
			self::$renderer = new ExceptionRenderer(self::$_data, self::viewPath());
		}
		self::$renderer->footer();
    }

	/**
	 * Get renderer instance
	 * 
	 * @return ExceptionRenderer
	 */
	public static function getRenderer(): ExceptionRenderer
	{
		if (self::$renderer === null) {
			self::$renderer = new ExceptionRenderer(self::$_data, self::viewPath());
		}
		return self::$renderer;
	}

	/**
	 * Get logger instance
	 * 
	 * @param string $pool Database connection pool name (default: 'default')
	 * @return ExceptionLogger|null
	 */
	public static function getLogger(string $pool = 'default'): ?ExceptionLogger
	{
		if (self::$logger === null) {
			include_once self::$pathExceptionLogger;
			self::$logger = new ExceptionLogger($pool);
		}
		return self::$logger;
	}

	/**
	 * Render complete error message
	 * 
	 * @return void
	 */
	public static function render(): void
	{
		self::getRenderer()->render();
	}

	public function getErrorList()
	{
		return [
			"Uncaught TypeError" => _("Uncaught Type Error"),
			"Uncaught ArgumentCountError" => _("Uncaught Argument Count Error"),
			"Uncaught Error" => _("Uncaught Error"),
			"Uncaught DivisionByZeroError" => _("Uncaught Division By Zero Error"),
			"Uncaught ArithmeticError" => _("Uncaught Arithmetic Error"),
			"Uncaught AssertionError" => _("Uncaught Assertion Error"),
			"Uncaught ParseError" => _("Uncaught Parse Error"),
			"Uncaught CompileError" => _("Uncaught Compile Error"),
			"Uncaught ValueError" => _("Uncaught Value Error"),
			"Uncaught OutOfBoundsException" => _("Uncaught Out Of Bounds Exception"),
			"Uncaught OutOfRangeException" => _("Uncaught Out Of Range Exception"),
			"Uncaught InvalidArgumentException" => _("Uncaught Invalid Argument Exception"),
			"Uncaught RuntimeException" => _("Uncaught Runtime Exception"),
			"Uncaught UnderflowException" => _("Uncaught Underflow Exception"),
			"Uncaught OverflowException" => _("Uncaught Overflow Exception"),
			"Uncaught LengthException" => _("Uncaught Length Exception")
		];
	}

	private function parseErrorMessage($errorMessage, $phpErrorPatterns, $documentRoot)
	{
		$result = [];

		// 4. Match error type and apply translation
		foreach ($phpErrorPatterns as $pattern => $shortcode) {
			if (strpos($errorMessage, $pattern) === 0) {
				$result['errorType'] = $pattern;
				$result['shortcode'] = $shortcode;

				// 5. Extract error message after :
				if (preg_match('/: (.*?) in/', $errorMessage, $messageMatch)) {
					$result['message'] = $messageMatch[1];
				}

				break;
			}
		}

		// 6. Extract information from main file and line
		if (preg_match('/in (.+):(\d+)/', $errorMessage, $matches)) {
			$result['file'] = str_replace($documentRoot, '', $matches[1]);
			$result['line'] = $matches[2];
		}

		// 7. Extract stack trace and remove "Stack trace:"
		if (preg_match('/Stack trace:(.*)/s', $errorMessage, $stackTraceMatch)) {
			$stackTrace = explode("\n", trim($stackTraceMatch[1]));
			$result['stackTrace'] = [];

			foreach ($stackTrace as $traceLine) {
				if (preg_match('/#\d+ (.+?)\((\d+)\): (.+?)\((.*)\)/', trim($traceLine), $traceParts)) {
					$result['stackTrace'][] = [
						'file' => str_replace($documentRoot, '', $traceParts[1]),
						'line' => $traceParts[2],
						'function' => $traceParts[3],
						'args' => $traceParts[4],
					];
				}
			}
		}

		return $result;
	}

	// Method to add a checkpoint to track where an exception was thrown from
	public static function checkpoint($exceptionType)
	{
		self::$where = array_merge(self::$where, $exceptionType);
	}

	public static function setTitle(string $title)
	{
		self::$_data['title'] = $title;
		if (self::$renderer !== null) {
			self::$renderer->setData(['title' => $title]);
		}
	}

	public static function setMessages(array $messages = array())
	{
		self::$_data['message'] = $messages;
		if (self::$renderer !== null) {
			self::$renderer->setData(['message' => $messages]);
		}
	}

	public static function turnOffRetrieveText()
	{
		self::$_data['retrieveText'] = array();
	}

	/**
	 * Gets the registry from the configuration class.
	 *
	 * This method calls the static method `getRegistry` from the `config` class to access
	 * the registry of registered classes and configurations. This registry may contain information
	 * about the classes that have been loaded as well as any configuration settings related to these
	 * classes.
	 *
	 * @return array Registry of registered classes and configurations, returned by
	 *               `config::getRegistry()`. Details in the registry depend on the implementation of
	 *               `config::getRegistry()` and may include data about class loads and configurations.
	 */
	public function getRegistry()
	{
		return config::getRegistry();
	}

	/**
	 * Constructor to handle an exception and display error information
	 *
	 * @param string|array|false $message Error message
	 * @param int $code Error code
	 * @param string|false $file File where error occurred
	 * @param int|false $line Line where error occurred
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct($message = false, $code = 0, $file = false, $line = false, $previous = null)
	{
		if ($message) {

			// Check types and swap if needed
			if (is_int($message) && is_numeric($message) && is_string($code)) {
				// If $code is numeric (including string representation of an integer), swap $message and $code
				list($message, $code) = array($code, $message);
			}

			// Without arguments: they can be large and are not shown
			$f = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);
			if ($file == false) {
				$line = $f[0]['line'] ?? 0;
				$file = $f[0]['file'] ?? 'unknown';
			}

			// Ensure code is int and previous is Throwable or null
			$code = is_numeric($code) ? (int)$code : 0;
			$previous = ($previous instanceof \Throwable) ? $previous : null;
			$line = is_numeric($line) ? (int)$line : 0;
			$file = is_string($file) ? $file : 'unknown';

			$data = array(
				'message' => $message,
				'title' => "SystemException",
				'code' => $code,
				'line' => $line,
				'file' => $file,
				'_file' => $file,
				'previous' => $previous,
				'debug' => $f
			);
			unset($f);

			// Check if $message is an array
			if (is_array($message)) {
				// Use the first value in the array as error message, if it's a string
				$firstValue = reset($message);
				if (is_string($firstValue)) {
					parent::__construct($firstValue, $code, $previous);
				} else {
					// If the first value is not a string, use a default string representation of the array
					parent::__construct(print_r($message, true), $code, $previous);
				}
			} else {
				// Ensure parameters have correct type
				$messageStr = is_string($message) ? $message : (is_scalar($message) ? (string)$message : print_r($message, true));
				parent::__construct($messageStr, $code, $previous);
			}

			// Complete data with values from Exception if missing
			if (!$data['line'] || $data['line'] === 0) {
				$data['line'] = $this->getLine();
			}

			if (!$data['file'] || $data['file'] === 'unknown') {
				$data['file'] = $this->getFile();
			}

			if (!$data['previous']) {
				$data['previous'] = $this->getPrevious();
			}

			// Rendering happens in show(), so the exception can be caught
			$this->data = $data;
		}
	}

	/**
	 * Show the error page for this exception and stop the request
	 *
	 * Devmode: the full error page (source, stack, POST data, classes).
	 * Otherwise: a short 500 page with an error id; details only go to the log.
	 *
	 * @return never
	 */
	public function show(): never
	{
		// An error while the error page is rendered: plain text, no new page
		if (self::$showing) {
			error_log('[Sandstorm] Error while showing an error: ' . $this->getMessage() . ' in ' . $this->getFile() . ':' . $this->getLine());
			exit(1);
		}
		self::$showing = true;

		// One log line; a wrapped TypeError etc. is logged as itself
		$errorId = bin2hex(random_bytes(4));
		$source = $this->getPrevious() ?? $this;
		error_log(sprintf(
			'[Sandstorm %s] %s: %s in %s:%d',
			$errorId,
			get_class($source),
			$source->getMessage(),
			$source === $this ? ($this->data['file'] ?? $this->getFile()) : $source->getFile(),
			$source === $this ? ($this->data['line'] ?? $this->getLine()) : $source->getLine()
		));

		// Remove everything the page had written so far (keep gzip)
		while (ob_get_level() > 0) {
			$handlers = ob_list_handlers();
			if (end($handlers) === 'ob_gzhandler') {
				break;
			}
			ob_end_clean();
		}
		if (ob_get_level() > 0) {
			ob_clean();
		}

		if (!headers_sent()) {
			http_response_code(500);
		}

		if (($_SERVER['HTTP_devmode'] ?? '') !== 'true') {
			self::showProductionPage($errorId);
		}

		self::$_data = array_merge($this->data, self::$_data);

		// Initialize renderer with initial data
		self::$renderer = new ExceptionRenderer(self::$_data, self::viewPath());

		$result = [];
		$messageValue = self::$_data['message'] ?? null;

		if (is_string($messageValue)) {
			$listOfError = $this->getErrorList();
			$documentRoot = $_SERVER["DOCUMENT_ROOT"] ?? '';
			$result = $this->parseErrorMessage($messageValue, $listOfError, $documentRoot . '/');
		}

		if ($result && !empty($result['errorType'])) {
			self::$_data['parseErrorMessage'] = $result;
			self::$renderer->setData(['parseErrorMessage' => $result]);
			
			$classname = str_replace("Uncaught ", '', $result['errorType']);
			$classname .= "Handle";

			$fullClassName = "Sys\\SystemException\\Handles\\{$classname}";

			// Check that class exists before instantiation
			if (class_exists($fullClassName)) {
				if (in_array($classname, ['TypeErrorHandle', 'ErrorHandle', 'ArgumentCountErrorHandle'])) {
					new $fullClassName($this, self::$_data);
				} else {
					// Fallback to DefaultHandle if class not in list
					$this->createDefaultHandle();
				}
			} else {
				// If class doesn't exist, use DefaultHandle
				$this->createDefaultHandle();
			}
			die();
		}

		// Handle different error types with class existence check
		if (is_array(self::$_data['message'])) {
			$this->createDefaultHandle();
		} else if (is_string(self::$_data['message'])) {
			if (strpos(self::$_data['message'], 'Uncaught ArgumentCountError:') !== false) {
				$this->createHandleIfExists('Sys\SystemException\Handles\ArgumentCountErrorHandle');
			} else if (strpos(self::$_data['message'], 'Uncaught Error:') !== false) {
				$this->createHandleIfExists('Sys\SystemException\Handles\ErrorHandle');
			} else {
				$this->createDefaultHandle();
			}
		} else {
			$this->createDefaultHandle();
		}

		exit();
	}

	/**
	 * Short error page for visitors (devmode off): no paths, code or data
	 *
	 * Uses the '500 page' file from the configuration when it exists. The
	 * page gets $errorId, the id written to the log.
	 *
	 * @param string $errorId Id that is also written to the log
	 * @return never
	 */
	private static function showProductionPage(string $errorId): never
	{
		$ajax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
			|| str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

		if ($ajax) {
			if (!headers_sent()) {
				header('Content-Type: application/json; charset=utf-8');
			}
			echo json_encode(['error' => 'Internal Server Error', 'id' => $errorId]);
			exit();
		}

		$page = config::get('500 page', true);
		if ($page && is_file($page)) {
			include $page;
			exit();
		}

		echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Something went wrong</title></head>'
			. '<body style="font-family:system-ui,sans-serif;margin:4rem auto;max-width:32rem;padding:0 1rem">'
			. '<h1>Something went wrong</h1><p>Please try again later.</p>'
			. '<p style="color:#777">Error id: ' . $errorId . '</p></body></html>';
		exit();
	}

	/**
	 * Helper method to create a handle class if it exists
	 */
	private function createHandleIfExists($className)
	{
		if (class_exists($className)) {
			new $className($this, self::$_data);
		} else {
			$this->createDefaultHandle();
		}
	}

	/**
	 * Helper method to create DefaultHandle
	 */
	private function createDefaultHandle()
	{
		$defaultClassName = 'Sys\SystemException\Handles\DefaultHandle';
		if (class_exists($defaultClassName)) {
			new $defaultClassName($this, self::$_data);
		} else {
			// If not even DefaultHandle exists, use renderer directly
			error_log("SystemException: No handle class could be loaded for error handling");
			self::render();
			die("A system error occurred");
		}
	}

	/**
	 * Gets an array with stack information for current execution
	 *
	 * @return array An array with stack information
	 */
	function debug_backtrace_array()
	{
		$stack = [];
		$i = 1;

		// The exception's own trace: where it was thrown, not where it is shown
		foreach ($this->getTrace() as $node) {
			if (array_key_exists('file', $node)) {
				$stack[] = [
					'stack' => "$i " . basename($node['file']) . (isset($node['class']) ? ' ' . $node['class'] . "->" : '') . ' ' . $node['function'] . "()",
					'row' => $node['line']
				];
				$i++;
			}
		}

		return $stack;
	}

	/**
	 * Gets previous exception
	 *
	 * @return mixed Previous exception or null if none exists
	 */
	public function getsPrevious()
	{
		return $this->previous;
	}

	/**
	 * Translate error code to explanatory long text
	 *
	 * @param int $code Error code
	 * @return string Explanatory text for error code
	 */
	public function ErrorCodeToLongText($code)
	{
		$translations = array(
			0 => _('EXCEPTIONS: Exception error'),
			E_ERROR => _('E_ERROR: A fatal error'),
			E_PARSE => _('E_PARSE: Syntax error in source code.'),
			E_WARNING => _('E_WARNING: A warning'),
			E_NOTICE => _('E_NOTICE: A notice that may be problematic'),
			E_CORE_ERROR => _('E_CORE_ERROR: Fatal error occurring in PHP core.'),
			E_CORE_WARNING => _('E_CORE_WARNING: Warning occurring in PHP core.'),
			E_COMPILE_ERROR => _('E_COMPILE_ERROR: Error during code compilation.'),
			E_COMPILE_WARNING => _('E_COMPILE_WARNING: Warning during code compilation.'),
			E_RECOVERABLE_ERROR => _('E_RECOVERABLE_ERROR: A slightly recoverable error, often resulting in an exception that can be handled.'),
			E_DEPRECATED => _('E_DEPRECATED: A warning about code that will not work in future versions.'),
			// Add more translations here as needed
		);

		return isset($translations[$code]) ? $translations[$code] : _('Unknown error code: ') . $code;
	}

	/**
	 * Translate error code to explanatory short text
	 *
	 * @param int $code Error code
	 * @return string Explanatory text for error code
	 */
	public function ErrorCodeToShortText($code)
	{
		$translations = array(
			0 => _('Exception error'),
			E_ERROR => _('Fatal error'),
			E_PARSE => _('Syntax error'),
			E_WARNING => _('Warning'),
			E_NOTICE => _('Notice'),
			E_CORE_ERROR => _('Fatal error occurring in PHP core.'),
			E_CORE_WARNING => _('Warning occurring in PHP core.'),
			E_COMPILE_ERROR => _('Syntax error'),
			E_COMPILE_WARNING => _('Warning'),
			E_RECOVERABLE_ERROR => _('Notice'),
			E_DEPRECATED => _('Incompatibility warning'),
			// Add more translations here as needed
		);

		return isset($translations[$code]) ? $translations[$code] : _('Unknown error code: ') . $code;
	}

	/**
	 * Common error handler for PHP errors and script shutdown
	 *
	 * Devmode: every reported error becomes a SystemException.
	 * Otherwise: warnings, notices and deprecations are logged and the page
	 * continues; other errors become a SystemException.
	 */
	public function errorHandlerAndShutdown($code = null, $message = null, $file = null, $line = null)
	{
		if ($code !== null) {
			// Check if error should be reported (not for @-suppressed errors)
			if (!(error_reporting() & $code)) {
				return false;
			}

			$minor = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED;
			if (($code & $minor) && ($_SERVER['HTTP_devmode'] ?? '') !== 'true') {
				error_log(sprintf('[Sandstorm] %s: %s in %s:%d', $this->ErrorCodeToShortText($code), $message, $file, $line));
				return true;
			}

			throw new SystemException($message, $code, $file, $line);
		}

		// Script shutdown: only fatal errors are left here, the rest went through the handlers
		$error = error_get_last();
		$fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
		if ($error !== null && ($error['type'] & $fatal)) {
			(new SystemException($error['message'], (int) $error['type'], $error['file'], (int) $error['line']))->show();
		}
	}

	/**
	 * Handler for exceptions that nobody caught
	 *
	 * A SystemException is shown as it is. Other exceptions and errors
	 * (TypeError, ValueError, ...) are wrapped in PHP's "Uncaught ..." text,
	 * which the Handles classes read.
	 *
	 * @param \Throwable $e
	 * @return never
	 */
	public function exceptionHandler(\Throwable $e): never
	{
		if ($e instanceof SystemException) {
			$e->show();
		}

		(new SystemException('Uncaught ' . $e, 0, $e->getFile(), $e->getLine(), $e))->show();
	}

	/**
	 * Start/Register the error handler
	 */
	public function register()
	{
		// Set common error handler for PHP errors and script shutdown
		register_shutdown_function(array($this, 'errorHandlerAndShutdown'));
		set_error_handler(array($this, 'errorHandlerAndShutdown'), E_ALL);
		set_exception_handler(array($this, 'exceptionHandler'));
	}
}
