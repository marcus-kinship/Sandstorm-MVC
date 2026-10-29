# Request Library

A modular, backward-compatible PHP library for handling HTTP requests with chainable validation.

## Features

- **Backward Compatible**: Works with existing code using the original `Request` class
- **Chainable Validation**: Fluent interface for data validation and transformation
- **Type Safety**: Built-in type conversion with defaults
- **XSS-Safe Output**: Explicit context-aware escaping via `escape()` method
- **Modular Structure**: Separated concerns for better maintainability
- **Request Detection**: Methods to detect POST, GET, and AJAX requests



## Usage

### Basic Usage 

```php


// Traditional usage still works
$age = Request::post('age');
$price = Request::get('price');
$title = Request::req('title');

// Check if POST request
if (Request::isPost()) {
    // Handle POST
}

// Check if GET request
if (Request::isGet()) {
    // Handle GET
}

// Check if AJAX request
if (Request::isAjax()) {
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['status' => 'success']);
    exit;
}

// Get request method
$method = Request::method(); // Returns: GET, POST, PUT, DELETE, etc.

// Get all POST data
$allPost = Request::post('*');

// Get all GET data
$allGet = Request::get('*');
```

### New Chainable Validation

```php


// Validate and convert to integer
$age = Request::post('age', 'valid')->int();

// Validate and convert to float
$price = Request::get('price', 'valid')->float();

// String validation with trim and escape
$title = Request::post('title', 'valid')
    ->string()
    ->trim()
    ->escape();

// HTML content cleaning
$content = Request::post('content', 'valid')
    ->html()
    ->clean();

// Email validation
$email = Request::post('email', 'valid')->email();

// URL validation
$website = Request::get('url', 'valid')->url();

// String length validation
$username = Request::post('username', 'valid')
    ->string()
    ->minLength(3)
    ->maxLength(20)
    ->escape();
```

### Using Specialized Classes

```php
use Sys\Request\Post;
use Sys\Request\Get;

// POST operations
$age = Post::get('age', 'valid')->int();
$allPost = Post::all();
$hasAge = Post::has('age');
$selected = Post::only(['name', 'email', 'age']);

// GET operations
$price = Get::get('price', 'valid')->float();
$allGet = Get::all();
$hasPrice = Get::has('price');
$filtered = Get::except(['debug', 'token']);
```

## Validation Methods

### Type Conversion

- **`int($default = null)`** - Convert to integer
- **`float($default = null)`** - Convert to float
- **`string($default = null)`** - Convert to string

### String Manipulation

- **`trim()`** - Remove whitespace
- **`escape()`** - Apply HTML entity encoding
- **`minLength(int $min, $default = null)`** - Enforce minimum length
- **`maxLength(int $max, bool $truncate = false)`** - Enforce maximum length

### HTML & Security

- **`html()`** - Prepare for HTML output (returns self for chaining)
- **`clean(array $allowedTags = [])`** - Clean HTML content (uses HTMLPurifier if available, falls back to strip_tags)

**Important**: Escaping is explicit and context-aware. Use `escape()` when you need HTML entity encoding. The library does NOT automatically escape all values - you control when and where escaping happens.

### Specialized Validation

- **`email($default = null)`** - Validate email address
- **`url($default = null)`** - Validate URL
- **`regex(string $pattern, $default = null)`** - Match regex pattern

### Utility Methods

- **`getValue()`** - Get current value
- **`hasFailed()`** - Check if validation failed

## Examples

### Form Processing

```php


if (Request::isPost()) {
    $name = Request::post('name', 'valid')
        ->string()
        ->trim()
        ->minLength(2)
        ->escape();
    
    $email = Request::post('email', 'valid')->email();
    
    $age = Request::post('age', 'valid')->int(18);
    
    $bio = Request::post('bio', 'valid')
        ->html()
        ->clean(['p', 'br', 'strong', 'em']);
    
    if ($email && $name) {
        // Process form
    }
}
```

### Query String Parameters

```php
use Sys\Request\Get;

$page = Get::get('page', 'valid')->int(1);
$sort = Get::get('sort', 'valid')->string('date');
$search = Get::get('q', 'valid')->string()->trim()->escape();

$filters = Get::only(['category', 'price_min', 'price_max']);
```

### API Endpoint

```php


// GET /api/users?limit=10&offset=0
$limit = Request::get('limit', 'valid')->int(20);
$offset = Request::get('offset', 'valid')->int(0);

// POST /api/users with JSON body
// ⚠️ Note: This is a deliberate workaround for JSON request bodies
// The Request library expects data in $_POST, so we parse JSON and populate it
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
    $_POST = $data; // Make JSON data available to Request library
}

$username = Request::post('username', 'valid')
    ->string()
    ->trim()
    ->minLength(3)
    ->maxLength(20)
    ->escape();

// Alternative: Create a dedicated method for JSON requests
class JsonRequest {
    public static function json(string $key, string $mode = null) {
        static $data = null;
        if ($data === null) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true) ?: [];
        }
        $_POST = $data;
        return Request::post($key, $mode);
    }
}
```

## Directory Structure

```
Sys/
├── Request.php              # Main class (backward compatible)
└── Request/
    ├── Validator.php        # Validation and transformation
    ├── Post.php             # POST-specific operations
    └── Get.php              # GET-specific operations
```

## Namespace Usage

```php
// Full namespace
$age = \Sys\Request::post('age', 'valid')->int();

// With use statement
use Sys\Request;
$age = Request::post('age', 'valid')->int();

// Specialized classes
use Sys\Request\Post;
use Sys\Request\Get;
use Sys\Request\Validator;

$age = Post::get('age', 'valid')->int();
$price = Get::get('price', 'valid')->float();
```

## Migration Guide

### Old Code

```php
$age = $_POST['age'];
$title = htmlentities($_GET['title'], ENT_QUOTES, 'UTF-8');
```

### New Code 

```php


$age = Request::post('age');
$title = Request::get('title');
```

### New Code (With Validation)

```php


$age = Request::post('age', 'valid')->int(0);
$title = Request::get('title', 'valid')->string()->trim()->escape();
```

## Security Features

- **Explicit HTML entity encoding**: XSS prevention through `escape()` method - escaping happens when you explicitly call it, giving you full control
- **HTMLPurifier integration**: Advanced HTML cleaning for rich text content when HTMLPurifier is installed
- **Type validation**: Prevents unexpected data types with strict type conversion
- **Default values**: Prevents null reference errors with sensible defaults
- **URL and Email validation**: Built-in format validation for common data types

**Security Philosophy**: This library provides tools for secure output (like `escape()`) but doesn't automatically escape everything. This gives you control over when and how data is escaped, which is essential for different output contexts (HTML, JavaScript, SQL, etc.).

