# Request Handling Documentation

## Overview

Sandstorm-MVC provides a comprehensive request handling system with built-in validation, sanitization, and type conversion. The system consists of four main classes:

- **`Request`** - Main request wrapper
- **`Get`** - Modern GET request handler
- **`Post`** - Modern POST request handler  
- **`Validator`** - Chainable validation and transformation

## Table of Contents

1. [Quick Start](#quick-start)
2. [Modern Approach (Recommended)](#modern-approach-recommended)
3. [Request Class](#request-class)
4. [Validation & Type Conversion](#validation--type-conversion)
5. [Security Features](#security-features)
6. [API Reference](#api-reference)

---

## Quick Start

### Basic Usage

```php
use Sys\Request\Get;
use Sys\Request\Post;

// GET request
$userId = Get::get('id', 'valid')->int();
$search = Get::get('q', null, 'default value');

// POST request
$username = Post::get('username');
$email = Post::get('email', 'valid')->email();

// Check if data exists
if (Post::hasData()) {
    $data = Post::all();
}
```

---

## Modern Approach (Recommended)

### GET Requests - `Sys\Request\Get`

The `Get` class provides secure handling of GET parameters with automatic HTML entity encoding.

#### Basic Retrieval

```php
use Sys\Request\Get;

// Get single value
$id = Get::get('id');

// Get with default value
$page = Get::get('page', null, 1);

// Get all GET data
$allParams = Get::all();
```

#### Advanced Methods

```php
// Check if key exists
if (Get::has('filter')) {
    $filter = Get::get('filter');
}

// Check if any GET data exists
if (Get::hasData()) {
    // Process query parameters
}

// Get only specific keys
$filters = Get::only(['category', 'price', 'brand']);
// Returns: ['category' => 'electronics', 'price' => '100']

// Get all except certain keys
$safeParams = Get::except(['token', 'secret']);

// Get raw query string
$queryString = Get::queryString();
```

#### With Validation

```php
// Integer validation
$page = Get::get('page', 'valid')->int(1);

// Email validation
$email = Get::get('email', 'valid')->email();

// String with sanitization
$search = Get::get('q', 'valid')
    ->string()
    ->trim()
    ->escape();
```

### POST Requests - `Sys\Request\Post`

The `Post` class handles POST data with validation support.

#### Basic Retrieval

```php
use Sys\Request\Post;

// Get single value
$username = Post::get('username');

// Get with default
$remember = Post::get('remember', null, false);

// Get all POST data
$formData = Post::all();
```

#### Advanced Methods

```php
// Check if key exists
if (Post::has('password')) {
    $password = Post::get('password');
}

// Check if form was submitted
if (Post::hasData()) {
    // Process form
}

// Get only specific fields
$credentials = Post::only(['username', 'password']);

// Get all except sensitive fields
$profileData = Post::except(['password', 'token']);
```

#### Form Processing Example

```php
use Sys\Request\Post;

if (Post::hasData()) {
    // Get validated data
    $userId = Post::get('user_id', 'valid')->int();
    $email = Post::get('email', 'valid')->email();
    $bio = Post::get('bio', 'valid')
        ->string()
        ->trim()
        ->clean(['p', 'br', 'strong', 'em']);
    
    // Process the form
    if ($email && $userId > 0) {
        // Save to database
    }
}
```

---

## Request Class

The `Request` class provides methods for existing code.

#### Request Type Detection

```php
// Check request method
if (Request::isPost()) {
    // Handle POST
}

if (Request::isGet()) {
    // Handle GET
}

if (Request::isAjax()) {
    // Handle AJAX request
}

// Get request method
$method = Request::method(); // Returns: GET, POST, PUT, DELETE, etc.
```

#### Data Retrieval

```php
// POST data
$username = Request::post('username');
$allPost = Request::post('*');

// GET data (with htmlentities applied)
$id = Request::get('id');
$allGet = Request::get('*');

// Combined GET + POST
$value = Request::req('key');
$allRequest = Request::req('*');

// File uploads
$file = Request::files('avatar');
$allFiles = Request::files('*');
```

#### With Validation

```php
// POST with validation
$age = Request::post('age', 'valid')->int(18);

// GET with validation
$email = Request::get('email', 'valid')->email();

// Request with validation
$price = Request::req('price', 'valid')->float();
```

#### URL Parsing

```php
// Parse URL parameters manually
$params = Request::parse_url('page.php?id=5&name=test');
// Returns: ['id' => '5', 'name' => 'test', 0 => '5', 1 => 'test']
```

---

## Validation & Type Conversion

### Validator Class - `Sys\Request\Validator`

The `Validator` class provides chainable validation and transformation methods.

#### Type Conversion

```php
use Sys\Request\Post;

// Integer conversion
$age = Post::get('age', 'valid')->int(0);
// Returns integer or default (0)

// Float conversion
$price = Post::get('price', 'valid')->float(0.0);
// Returns float or default (0.0)

// String conversion
$name = Post::get('name', 'valid')->string('Anonymous');
// Returns string or default
```

#### String Manipulation

```php
// Trim whitespace
$username = Post::get('username', 'valid')
    ->string()
    ->trim();

// Escape HTML entities
$comment = Post::get('comment', 'valid')
    ->string()
    ->escape();
// Returns: HTML-safe string

// Clean HTML (strip tags)
$bio = Post::get('bio', 'valid')
    ->string()
    ->clean();
// All tags removed

// Clean with allowed tags
$content = Post::get('content', 'valid')
    ->string()
    ->clean(['p', 'br', 'strong', 'em', 'a']);
// Only specified tags allowed
```

#### Built-in Validators

```php
// Email validation
$email = Post::get('email', 'valid')->email();
// Returns valid email or false

$email = Post::get('email', 'valid')->email('default@example.com');
// Returns valid email or default

// URL validation
$website = Post::get('website', 'valid')->url();
// Returns valid URL or false

// Regex pattern matching
$zipCode = Post::get('zip', 'valid')
    ->regex('/^\d{5}$/', '00000');
// Returns value if matches pattern, otherwise default
```

#### Length Validation

```php
// Minimum length
$password = Post::get('password', 'valid')
    ->string()
    ->minLength(8, '');
// Returns string if >= 8 chars, otherwise default

// Maximum length
$title = Post::get('title', 'valid')
    ->string()
    ->maxLength(100);
// Fails if > 100 chars

// Maximum length with truncation
$excerpt = Post::get('excerpt', 'valid')
    ->string()
    ->maxLength(200, true);
// Truncates to 200 chars if longer
```

#### Advanced Usage

```php
// Check validation status
$validator = Post::get('email', 'valid');
$email = $validator->email();

if ($validator->hasFailed()) {
    // Validation failed
    echo "Invalid email";
} else {
    // Email is valid
    echo "Email: " . $email;
}

// Get current value
$currentValue = $validator->getValue();

// Chaining multiple validations
$cleanInput = Post::get('comment', 'valid')
    ->string()
    ->trim()
    ->minLength(10)
    ->maxLength(500, true)
    ->clean(['br', 'p'])
    ->getValue();
```

---

## Security Features

### Automatic Protection

#### HTML Entity Encoding

All GET parameters automatically have `htmlentities()` applied:

```php
// URL: page.php?name=<script>alert('xss')</script>
$name = Get::get('name');
// Returns: &lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;
```

#### POST Data Validation

```php
// Escape user input before output
$comment = Post::get('comment', 'valid')
    ->string()
    ->escape();

// Clean HTML input
$richText = Post::get('content', 'valid')
    ->string()
    ->clean(['p', 'br', 'strong', 'em']);
```

### Best Practices

```php
// ✅ GOOD - Validate and sanitize
$userId = Get::get('id', 'valid')->int();
$email = Post::get('email', 'valid')->email();
$content = Post::get('text', 'valid')
    ->string()
    ->trim()
    ->clean();

// ❌ BAD - Direct usage without validation
$userId = Get::get('id');  // Could be non-numeric
$email = Post::get('email');  // Could be invalid
$content = Post::get('text');  // Could contain XSS
```

---

## API Reference

### Get Class

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `get()` | `string $key, string $mode = null, mixed $default = null` | `mixed` | Get GET parameter |
| `all()` | - | `array` | Get all GET data |
| `has()` | `string $key` | `bool` | Check if key exists |
| `hasData()` | - | `bool` | Check if GET data exists |
| `only()` | `array $keys` | `array` | Get specific keys only |
| `except()` | `array $keys` | `array` | Get all except specified keys |
| `queryString()` | - | `string` | Get raw query string |

### Post Class

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `get()` | `string $key, string $mode = null, mixed $default = null` | `mixed` | Get POST parameter |
| `all()` | - | `array` | Get all POST data |
| `has()` | `string $key` | `bool` | Check if key exists |
| `hasData()` | - | `bool` | Check if POST data exists |
| `only()` | `array $keys` | `array` | Get specific keys only |
| `except()` | `array $keys` | `array` | Get all except specified keys |

### Validator Class

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `int()` | `int $default = null` | `int` | Convert to integer |
| `float()` | `float $default = null` | `float` | Convert to float |
| `string()` | `string $default = null` | `self` | Convert to string |
| `trim()` | - | `self` | Trim whitespace |
| `escape()` | - | `string` | Escape HTML entities |
| `clean()` | `array $allowedTags = []` | `string` | Clean HTML |
| `email()` | `string $default = null` | `string\|false` | Validate email |
| `url()` | `string $default = null` | `string\|false` | Validate URL |
| `regex()` | `string $pattern, mixed $default = null` | `mixed` | Match regex pattern |
| `minLength()` | `int $min, mixed $default = null` | `self` | Minimum string length |
| `maxLength()` | `int $max, bool $truncate = false` | `self` | Maximum string length |
| `getValue()` | - | `mixed` | Get current value |
| `hasFailed()` | - | `bool` | Check if validation failed |

### Request Class (Legacy)

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `isPost()` | - | `bool` | Check if POST request |
| `isGet()` | - | `bool` | Check if GET request |
| `isAjax()` | - | `bool` | Check if AJAX request |
| `method()` | - | `string` | Get request method |
| `post()` | `string $key = "*", string $mode = null` | `mixed` | Get POST data |
| `get()` | `string $key = "*", string $mode = null` | `mixed` | Get GET data |
| `req()` | `string $key = "*", string $mode = null` | `mixed` | Get REQUEST data |
| `files()` | `string $key = "*"` | `mixed` | Get FILES data |
| `parse_url()` | `string $url` | `array\|false` | Parse URL parameters |

---

## Complete Examples

### User Registration Form

```php
use Sys\Request\Post;

if (Post::hasData()) {
    // Get and validate all fields
    $username = Post::get('username', 'valid')
        ->string()
        ->trim()
        ->minLength(3)
        ->maxLength(20)
        ->getValue();
    
    $email = Post::get('email', 'valid')->email();
    
    $age = Post::get('age', 'valid')->int(0);
    
    $bio = Post::get('bio', 'valid')
        ->string()
        ->trim()
        ->maxLength(500, true)
        ->clean(['p', 'br'])
        ->getValue();
    
    // Check validation
    if ($username && $email && $age >= 18) {
        // Register user
        echo "Registration successful!";
    } else {
        echo "Please check your input.";
    }
}
```

### Search with Filters

```php
use Sys\Request\Get;

if (Get::hasData()) {
    // Get search parameters
    $query = Get::get('q', 'valid')
        ->string()
        ->trim()
        ->getValue();
    
    $page = Get::get('page', 'valid')->int(1);
    $perPage = Get::get('per_page', 'valid')->int(10);
    
    // Get all filters
    $filters = Get::only(['category', 'price_min', 'price_max', 'brand']);
    
    // Perform search
    $results = searchProducts($query, $page, $perPage, $filters);
}
```

### API Endpoint

```php
use Sys\Request\Post;

if (Request::isPost() && Request::isAjax()) {
    // Get JSON payload or form data
    $action = Post::get('action');
    
    switch ($action) {
        case 'create':
            $title = Post::get('title', 'valid')
                ->string()
                ->trim()
                ->escape();
            
            $content = Post::get('content', 'valid')
                ->string()
                ->clean(['p', 'br', 'strong', 'em', 'a'])
                ->getValue();
            
            // Process creation
            break;
            
        case 'update':
            $id = Post::get('id', 'valid')->int();
            $data = Post::except(['action', 'id']);
            
            // Process update
            break;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
}
```

### File Upload with Validation

```php
use Sys\Request\Post;

if (Request::isPost()) {
    $file = Request::files('avatar');
    $userId = Post::get('user_id', 'valid')->int();
    
    if ($file && $userId > 0) {
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            // Upload file
            move_uploaded_file($file['tmp_name'], "uploads/{$userId}_avatar.jpg");
        }
    }
}
```

---

## Security Best Practices

### Always Validate User Input

```php
use Sys\Request\Get;
use Sys\Request\Post;

// ✅ GOOD - Validate and sanitize
$userId = Get::get('id', 'valid')->int();
$email = Post::get('email', 'valid')->email();
$content = Post::get('text', 'valid')
    ->string()
    ->trim()
    ->clean();

// ❌ BAD - Direct usage without validation
$userId = Get::get('id');  // Could be non-numeric
$email = Post::get('email');  // Could be invalid
$content = Post::get('text');  // Could contain XSS
```

### Migrating from Request Class

**Before:**
```php
$id = Request::get('id');
$email = Request::post('email', 'valid')->email();
```

**After:**
```php
use Sys\Request\Get;
use Sys\Request\Post;

$id = Get::get('id', 'valid')->int();
$email = Post::get('email', 'valid')->email();
```

**Benefits:**
- Clearer separation of GET and POST
- Better namespace organization
- More consistent API
- Improved type safety

### XSS Prevention

```php
// For display in HTML
$userInput = Post::get('comment', 'valid')
    ->string()
    ->escape();  // Converts <script> to &lt;script&gt;

// For rich text content
$htmlContent = Post::get('article', 'valid')
    ->string()
    ->clean(['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li']);
```

### SQL Injection Prevention

```php
// Always validate types before database queries
$userId = Get::get('user_id', 'valid')->int();
$email = Post::get('email', 'valid')->email();

if ($userId && $email) {
    // Safe to use in prepared statements
    $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
    $stmt->execute([$email, $userId]);
}
```

### File Upload Security

```php
if (Request::isPost()) {
    $file = Request::files('document');
    $userId = Post::get('user_id', 'valid')->int();
    
    if ($file && $userId > 0) {
        // Validate file type
        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($file['type'], $allowedTypes) && 
            in_array($fileExtension, $allowedExtensions) &&
            $file['size'] <= 5 * 1024 * 1024) {  // 5MB max
            
            // Generate safe filename
            $safeFilename = $userId . '_' . uniqid() . '.' . $fileExtension;
            move_uploaded_file($file['tmp_name'], "uploads/{$safeFilename}");
        }
    }
}
```

### CSRF Protection Example

```php
use Sys\Request\Post;

if (Request::isPost()) {
    // Verify CSRF token
    $token = Post::get('csrf_token', 'valid')
        ->string()
        ->trim()
        ->getValue();
    
    if ($token && hash_equals($_SESSION['csrf_token'], $token)) {
        // Process form safely
        $data = Post::only(['username', 'email', 'bio']);
        // ... process data
    } else {
        die('Invalid CSRF token');
    }
}
```

### Input Length Limits

```php
// Prevent buffer overflow and DoS attacks
$username = Post::get('username', 'valid')
    ->string()
    ->trim()
    ->minLength(3)
    ->maxLength(50)
    ->getValue();

$bio = Post::get('bio', 'valid')
    ->string()
    ->trim()
    ->maxLength(1000, true)  // Truncate if too long
    ->getValue();
```

### Email & URL Validation

```php
// Prevent header injection and malicious redirects
$email = Post::get('email', 'valid')->email();
$website = Post::get('website', 'valid')->url();

if ($email && $website) {
    // Safe to use
    mail($email, "Subject", "Message");
    header("Location: " . $website);
}
```

