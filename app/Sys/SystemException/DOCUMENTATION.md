# SystemException - Comprehensive Documentation

## Overview

**SystemException** is an advanced error handling and exception management system for the Sandstorm MVC framework. It converts standard PHP errors and exceptions into a unified exception handling system with support for both CLI and web environments, providing detailed error information, stack traces, and developer-friendly error rendering.

## Architecture

The SystemException system consists of several key components:

```
SystemException/
├── SystemException.php           # Main exception class
├── ExceptionRenderer.php         # Renders exceptions for CLI/Web
├── OutputData.php               # Handles output and view loading
├── Handles/                     # Exception type handlers
│   ├── DefaultHandle.php        # Default error handler
│   ├── ArgumentCountErrorHandle.php  # Handles argument count errors
│   ├── ErrorHandle.php          # Handles general errors
│   ├── TypeErrorHandle.php      # Handles type errors
│   └── Views/                   # View templates
│       ├── argumentCountError.php
│       ├── default.php
│       ├── typeError.php
│       ├── uncaughtError.php
│       └── include/
│           ├── header.php
│           └── footer.php
```

## Core Components

### 1. SystemException Class

**Location:** [SystemException.php](SystemException.php)

**Purpose:** Main exception handler that catches and processes all PHP exceptions and errors.

#### Key Features:

- **Exception Registration:** Registers as the global error and shutdown handler
- **Error Type Detection:** Automatically detects error types (TypeError, ArgumentCountError, etc.)
- **Error Message Parsing:** Parses error messages to extract file paths, line numbers, and stack traces
- **Dynamic Handler Loading:** Loads appropriate handler classes based on error type
- **Singleton Renderer:** Manages a static ExceptionRenderer instance for consistent rendering

#### Key Methods:

```php
// Register the error handler
$handler = new SystemException();
$handler->register();

// Static rendering methods
SystemException::render();      // Render complete error
SystemException::header();      // Render header only
SystemException::view();        // Render body only
SystemException::footer();      // Render footer only

// Data manipulation
SystemException::setTitle(string $title);           // Set error title
SystemException::setMessages(array $messages);      // Set error messages
SystemException::turnOffRetrieveText();             // Disable code snippet display

// Get renderer instance
$renderer = SystemException::getRenderer();

// Error code translation
$longText = $handler->ErrorCodeToLongText($code);   // Detailed error message
$shortText = $handler->ErrorCodeToShortText($code); // Short error message
```

#### Error Code Translations:

The system supports translation of PHP error constants:

| Code | Short Text | Long Text |
|------|-----------|-----------|
| 0 | Exception Error | EXCEPTIONS: Exception Error |
| E_ERROR | Serious Error | E_ERROR: A serious error |
| E_PARSE | Syntax Error | E_PARSE: Syntax error in source code |
| E_WARNING | Warning | E_WARNING: A warning |
| E_NOTICE | Notice | E_NOTICE: A potentially problematic notice |
| E_CORE_ERROR | Core Error | E_CORE_ERROR: Serious error in PHP core |
| E_RECOVERABLE_ERROR | Recoverable Error | E_RECOVERABLE_ERROR: Recoverable error, often results in handleable exception |
| E_DEPRECATED | Deprecated | E_DEPRECATED: Warning about code not working in future versions |

### 2. ExceptionRenderer Class

**Location:** [ExceptionRenderer.php](ExceptionRenderer.php)

**Purpose:** Handles the presentation and rendering of exceptions in both CLI and web environments.

#### Key Features:

- **Environment Detection:** Automatically detects CLI vs web environment
- **Fluent Interface:** Supports method chaining for flexible rendering
- **Separation of Concerns:** Isolates presentation logic from error handling
- **Fallback Rendering:** Provides default HTML output if view files are missing
- **Scope Isolation:** Uses anonymous functions to isolate variable scope in views

#### Key Methods:

```php
// Constructor
$renderer = new ExceptionRenderer(array $data, string $viewPath);

// Fluent interface methods (return $this for chaining)
$renderer->header();            // Render header
$renderer->view();              // Render body/main content
$renderer->footer();            // Render footer
$renderer->render();            // Render complete error (header + view + footer)

// Data management
$renderer->setData(array $data);    // Set/merge data
$renderer->getData();               // Get all current data

// Environment detection
$renderer->isCli();                 // Check if CLI mode
$renderer->setCliMode(bool $isCli); // Manually set CLI mode

// Factory method
$renderer = ExceptionRenderer::create(array $data, string $viewPath);
```

#### CLI Output Format:

The CLI renderer produces formatted output with:
- Separator lines (80 characters)
- Error type and message
- File and line number information
- Error code and translations
- Stack trace with file locations and function calls
- Debug backtrace with line numbers

#### Web Output Format:

The web renderer produces HTML with:
- Styled error container
- Error title and type
- Error message with details
- File path and line number
- Stack trace in formatted list
- Fallback HTML if view files are missing

### 3. OutputData Class

**Location:** [OutputData.php](OutputData.php)

**Purpose:** Abstract base class for handling output data and view loading logic.

#### Key Features:

- **Data Property Management:** Stores data for use in views
- **AJAX Detection:** Identifies AJAX requests
- **View Loading:** Dynamically loads view files with error handling
- **Code Snippet Retrieval:** Extracts code context around error lines
- **Memory Size Conversion:** Converts bytes to human-readable format
- **Recursive Error Protection:** Prevents infinite loops in error handling

#### Key Methods:

```php
// Data management
$this->setData('key1', $value1, 'key2', $value2, ...);  // Set variables for views
$this->getData('key1');                                  // Get specific data

// View loading
$this->load('viewname.php');                            // Load a view file

// AJAX detection
if ($this->isAjax()) {
    // Handle AJAX request
}

// Code retrieval
$lines = $this->retrieveText($file, $minLine, $maxLine); // Get lines from file
```

#### Data Property Storage:

Stores key-value pairs as static properties accessible in views:

```php
// Set data
$this->setData('username', 'John', 'message', 'Error occurred');

// In view file
echo $username;  // Outputs: John
echo $message;   // Outputs: Error occurred
```

### 4. Exception Handler Classes

Each handler class processes specific exception types and prepares data for display.

#### ArgumentCountErrorHandle

**Location:** [Handles/ArgumentCountErrorHandle.php](Handles/ArgumentCountErrorHandle.php)

Handles `ArgumentCountError` exceptions when incorrect number of arguments is passed to functions.

**Processing:**
- Parses error message with regex patterns
- Extracts file path and line numbers
- Identifies function where error originated
- Prepares data for view rendering

#### ErrorHandle

**Location:** [Handles/ErrorHandle.php](Handles/ErrorHandle.php)

Handles general `Error` exceptions.

**Processing:**
- Analyzes error messages
- Extracts diagnostic information
- Formats file paths relative to site root
- Handles both development and production modes

#### TypeErrorHandle

**Location:** [Handles/TypeErrorHandle.php](Handles/TypeErrorHandle.php)

Handles `TypeError` exceptions from incorrect data types.

**Processing:**
- Parses type error messages
- Extracts file location and line number
- Provides error code translations
- Prepares debug information

#### DefaultHandle

**Location:** [Handles/DefaultHandle.php](Handles/DefaultHandle.php)

Fallback handler for unspecified error types.

**Processing:**
- Generic error processing
- Handles all error types not specifically handled
- Provides basic error information

## Usage Guide

### Installation and Initialization

In your application's initialization file (typically `Start.php` or `config.php`):

```php
use Sys\SystemException;

// Create instance
$errorhandler = new SystemException();

// Register as global error and shutdown handler
$errorhandler->register();
```

This single call:
- Sets up the error handler for all PHP errors
- Registers shutdown function to catch fatal errors
- Configures error reporting with custom exception handling

### Throwing Custom Exceptions

```php
// Simple exception
throw new SystemException("Something went wrong");

// With error code
throw new SystemException("Database connection failed", E_ERROR);

// With file and line information (auto-detected if not provided)
throw new SystemException("Invalid input", 1001, __FILE__, __LINE__);

// With previous exception (exception chaining)
try {
    // Some code
} catch (Exception $e) {
    throw new SystemException("Failed to process data", 0, null, null, $e);
}
```

### Setting Error Titles and Messages

```php
// Set custom title
SystemException::setTitle("Database Error");

// Set multiple messages
SystemException::setMessages([
    "Failed to connect to database",
    "Connection timeout after 30 seconds",
    "Please check database configuration"
]);

// Turn off code snippet display
SystemException::turnOffRetrieveText();
```

### Custom Rendering

```php
// Get the renderer instance
$renderer = SystemException::getRenderer();

// Use fluent interface for custom rendering
$renderer
    ->setData(['customKey' => 'customValue'])
    ->header()
    ->view()
    ->footer();

// Or render everything at once
SystemException::render();
```

### AJAX Request Handling

The system automatically detects AJAX requests by checking:
- `HTTP_X_REQUESTED_WITH` header (XMLHttpRequest)
- Request URI starting with `api/js`

```php
// In an exception handler
if ($this->isAjax()) {
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An error occurred']);
} else {
    // Render HTML page
    SystemException::render();
}
```

### Development Mode Features

When development mode is enabled (`$_SERVER['HTTP_devmode'] == "true"`):

- **Extended Information:** Shows detailed stack traces and file context
- **Code Snippets:** Displays 5 lines before and after the error line
- **Debug Information:** Includes full backtrace with variable information
- **Stack Trace Details:** Shows function arguments and return values

For production mode, the system:
- Shows generic error messages
- Loads custom 500 error page (configured via `config::get('500 page')`)
- Restricts sensitive information display
- Logs errors for debugging

## Error Processing Flow

### Exception Lifecycle:

1. **Error Occurrence:** PHP error or exception is triggered
2. **Handler Invocation:** SystemException error handler is called
3. **Error Detection:** System determines error type
4. **Message Parsing:** Error message is parsed for details
5. **Handler Selection:** Appropriate handler class is instantiated
6. **Data Preparation:** Handler extracts and formats error data
7. **Rendering:** ExceptionRenderer outputs error in appropriate format
8. **Termination:** Script execution is halted

### Error Detection Process:

```
Error Message Received
    ↓
Match Against Known Error Types (TypeError, ArgumentCountError, etc.)
    ↓
Extract: File Path, Line Number, Stack Trace
    ↓
Load Specific Handler Class
    ↓
Handler Processes Data
    ↓
Determine Output Format (CLI vs Web)
    ↓
ExceptionRenderer Outputs Error
    ↓
Script Terminates
```

## View Templates

### Location: `SystemException/Views/`

View files receive data via static properties set by handlers:

```php
// Example view file structure
?>
<h1><?php echo $title ?? 'Error'; ?></h1>
<p><?php echo htmlspecialchars($message ?? ''); ?></p>
```

### Available View Files:

- **default.php** - Generic error display
- **argumentCountError.php** - Argument count error specific view
- **typeError.php** - Type error specific view
- **uncaughtError.php** - Uncaught exception view
- **include/header.php** - Common header
- **include/footer.php** - Common footer

### Accessing Data in Views:

All data set via `setData()` is available as variables in views:

```php
// In handler
$this->setData('username', 'John', 'errorCode', 404);

// In view
echo $username;    // Outputs: John
echo $errorCode;   // Outputs: 404
```

## Configuration

### Server Variables Used:

```php
$_SERVER['HTTP_devmode']      // Enable/disable development mode ("true"/"false")
$_SERVER['HTTP_path_site']    // Path to site root
$_SERVER['HTTP_path_sys']     // Path to system directory
$_SERVER['DOCUMENT_ROOT']     // Server document root
$_SERVER['REQUEST_URI']       // Current request URI
```

### Configuration File Integration:

```php
// In config
config::get('500 page')  // Path to custom 500 error page
```

## Performance Considerations

### Memory Management:

- **Static Properties:** Error data stored statically to minimize memory overhead
- **Lazy Initialization:** Renderer created only when needed
- **Stream Processing:** Code retrieval reads files line-by-line to conserve memory

### Error Handling Protection:

- **Recursive Error Guard:** `$isHandlingError` flag prevents infinite loops
- **File Validation:** Validates file paths before attempting to read
- **Safe File Operations:** Uses error suppression (@) for file operations to prevent cascading errors

## Supported Error Types

### Uncaught Exceptions:

- **TypeError** - Invalid function argument types
- **ArgumentCountError** - Wrong number of arguments
- **Error** - Generic errors
- **DivisionByZeroError** - Division by zero
- **ArithmeticError** - Arithmetic operation errors
- **AssertionError** - Assertion failures
- **ParseError** - Code parsing errors
- **CompileError** - Compilation errors
- **ValueError** - Invalid value operations
- **OutOfBoundsException** - Array/collection bounds exceeded
- **OutOfRangeException** - Numeric range exceeded
- **InvalidArgumentException** - Invalid function arguments
- **RuntimeException** - Runtime errors
- **UnderflowException** - Stack/queue underflow
- **OverflowException** - Stack/queue overflow
- **LengthException** - String/array length errors

### PHP Error Types (E_* constants):

- E_ERROR - Serious runtime errors
- E_PARSE - Parse-time errors
- E_WARNING - Warnings
- E_NOTICE - Notices
- E_CORE_ERROR - Core errors
- E_CORE_WARNING - Core warnings
- E_COMPILE_ERROR - Compile errors
- E_COMPILE_WARNING - Compile warnings
- E_RECOVERABLE_ERROR - Recoverable errors

## Advanced Features

### Exception Chaining

Chain exceptions to track error cause:

```php
try {
    // Original error
    $db->connect();
} catch (Exception $e) {
    throw new SystemException("Failed to connect", 0, null, null, $e);
}
```

Access previous exception:

```php
$previous = $exception->getPrevious();
```

### Error Code Translation

Manual translation of error codes:

```php
$exception = new SystemException();
$longText = $exception->ErrorCodeToLongText(E_ERROR);
$shortText = $exception->ErrorCodeToShortText(E_ERROR);
```

### Stack Trace Analysis

Automatic backtrace collection:

```php
$exception = new SystemException("Error");
$debug = $exception->getMessage();  // Contains backtrace
```

### Custom Error Messages

Array of error messages:

```php
throw new SystemException([
    "Primary error message",
    "Additional context",
    "Remediation suggestion"
], E_WARNING);
```

## Troubleshooting

### Common Issues:

#### Issue: View files not found

**Cause:** Incorrect view path or missing files
**Solution:** Verify `SystemException/Views/` directory exists and contains required files

#### Issue: Infinite error loops

**Cause:** Error handler triggering errors
**Solution:** System uses recursive error guard (`$isHandlingError`), but check for errors in custom code

#### Issue: Missing error details

**Cause:** Not in development mode
**Solution:** Set `$_SERVER['HTTP_devmode'] = 'true'` for detailed error information

#### Issue: Code snippets not showing

**Cause:** Development mode off or file not readable
**Solution:** Enable dev mode and ensure files have read permissions

## Best Practices

1. **Initialize Early:** Register handler in application bootstrap
2. **Use Type Declarations:** Leverage TypeError detection with function type hints
3. **Provide Context:** Use custom titles and messages for better debugging
4. **Development Mode:** Enable for development, disable for production
5. **Custom Error Pages:** Configure 500 error page for production environments
6. **Monitor Logs:** Log errors in error_log for monitoring
7. **Exception Chaining:** Chain exceptions to preserve error context
8. **Handle Gracefully:** Catch specific exceptions and respond appropriately

## Related Files

- [SystemException.php](SystemException.php) - Main exception class
- [ExceptionRenderer.php](ExceptionRenderer.php) - Rendering engine
- [OutputData.php](OutputData.php) - Base output class
- [Handles/ArgumentCountErrorHandle.php](Handles/ArgumentCountErrorHandle.php)
- [Handles/ErrorHandle.php](Handles/ErrorHandle.php)
- [Handles/TypeErrorHandle.php](Handles/TypeErrorHandle.php)
- [Handles/DefaultHandle.php](Handles/DefaultHandle.php)

## Summary

SystemException provides a comprehensive, extensible error handling system that:

- ✅ Catches all PHP errors and exceptions
- ✅ Provides detailed error information and stack traces
- ✅ Supports CLI and web environments
- ✅ Automatically selects appropriate error handlers
- ✅ Isolates presentation logic from error handling
- ✅ Prevents recursive error loops
- ✅ Supports custom error pages and messages
- ✅ Integrates with Sandstorm MVC framework
- ✅ Provides developer-friendly error output
- ✅ Handles AJAX requests gracefully

This makes it an essential component for robust application error handling and debugging.
