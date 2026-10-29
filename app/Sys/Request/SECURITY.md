# Security Guide

This document explains the security philosophy and best practices for using the Request library.

## Core Security Philosophy

**Explicit > Implicit**: This library does NOT automatically escape or sanitize all data. Instead, it provides explicit methods that you call when needed. This approach gives you full control and prevents issues where automatic escaping causes problems in certain contexts.

## Understanding Escaping Contexts

Different output contexts require different escaping strategies:

### 1. HTML Output
```php
// ✅ Correct: Explicit escaping for HTML context
$title = Request::post('title', 'valid')->string()->trim()->escape();
echo "<h1>$title</h1>";

// ❌ Wrong: No escaping
$title = Request::post('title', 'valid')->string()->trim();
echo "<h1>$title</h1>"; // XSS risk!
```

### 2. HTML Attributes
```php
// ✅ Correct: Escape for attribute context
$class = Request::get('theme', 'valid')->string()->escape();
echo "<div class='$class'>Content</div>";

// ⚠️ Note: For JavaScript in attributes, use json_encode
$data = Request::post('data', 'valid')->string();
echo "<div data-info='" . htmlspecialchars(json_encode($data), ENT_QUOTES) . "'>Content</div>";
```

### 3. JavaScript Context
```php
// ✅ Correct: Use json_encode for JavaScript
$userData = Request::post('name', 'valid')->string();
echo "<script>var userName = " . json_encode($userData) . ";</script>";

// ❌ Wrong: Don't use escape() in JavaScript context
$userData = Request::post('name', 'valid')->string()->escape();
echo "<script>var userName = '$userData';</script>"; // Can still be exploited!
```

### 4. SQL Context
```php
// ✅ Correct: Use prepared statements (NOT escape())
$userId = Request::get('id', 'valid')->int();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);

// ❌ Wrong: Never build SQL with concatenation
$userId = Request::get('id', 'valid')->string()->escape();
$query = "SELECT * FROM users WHERE id = '$userId'"; // SQL injection risk!
```

### 5. URL Context
```php
// ✅ Correct: Use urlencode for URL parameters
$search = Request::post('query', 'valid')->string();
$url = "search.php?q=" . urlencode($search);

// For URL validation
$website = Request::post('url', 'valid')->url();
if ($website) {
    // URL is valid format
}
```

## When to Use Each Validation Method

### `string()` + `trim()` + `escape()`
Use for text that will be displayed in HTML:
```php
$title = Request::post('title', 'valid')
    ->string()
    ->trim()
    ->escape();
echo "<h1>$title</h1>";
```

### `html()` + `clean()`
Use for rich text content (like from WYSIWYG editors):
```php
$content = Request::post('content', 'valid')
    ->html()
    ->clean(['p', 'br', 'strong', 'em', 'ul', 'ol', 'li']);
echo $content; // Safe HTML with only allowed tags
```

### `int()` / `float()`
Use for numeric data:
```php
$age = Request::post('age', 'valid')->int();
$price = Request::post('price', 'valid')->float();
// These are safe to use directly in calculations or SQL (with prepared statements)
```

### `email()`
Use for email validation:
```php
$email = Request::post('email', 'valid')->email();
if ($email) {
    // Email is valid format
}
```

### `url()`
Use for URL validation:
```php
$website = Request::post('website', 'valid')->url();
if ($website) {
    // URL is valid format
}
```

## Common Security Mistakes

### ❌ Mistake 1: Not escaping at all
```php
// DANGEROUS!
$name = Request::post('name', 'valid')->string();
echo "<h1>$name</h1>"; // XSS vulnerability
```

### ❌ Mistake 2: Escaping in the wrong context
```php
// WRONG!
$json = Request::post('data', 'valid')->string()->escape();
echo "<script>var data = '$json';</script>"; // Still vulnerable

// CORRECT!
$json = Request::post('data', 'valid')->string();
echo "<script>var data = " . json_encode($json) . ";</script>";
```

### ❌ Mistake 3: Using escape() instead of prepared statements
```php
// DANGEROUS!
$userId = Request::get('id', 'valid')->string()->escape();
$query = "DELETE FROM users WHERE id = '$userId'"; // SQL injection!

// CORRECT!
$userId = Request::get('id', 'valid')->int();
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$userId]);
```

### ❌ Mistake 4: Trusting client-side validation
```php
// INSUFFICIENT!
// Just because JavaScript validates on client doesn't mean server should trust it
$age = Request::post('age'); // No validation!

// CORRECT!
$age = Request::post('age', 'valid')->int(0);
if ($age < 18 || $age > 120) {
    die("Invalid age");
}
```

## Security Checklist

When processing user input, ask yourself:

1. ✅ **Where will this data be output?**
   - HTML content → `escape()`
   - Rich text → `clean()` with allowed tags
   - JavaScript → `json_encode()`
   - SQL → prepared statements
   - URL → `urlencode()`

2. ✅ **What type should this data be?**
   - Number → `int()` or `float()`
   - Email → `email()`
   - URL → `url()`
   - Text → `string()`

3. ✅ **What are the valid values?**
   - Use `minLength()` / `maxLength()` for strings
   - Use `regex()` for patterns
   - Check against whitelist of allowed values

4. ✅ **Is there a default/fallback value?**
   - Always provide defaults: `int(0)`, `string('')`, `email(false)`

## Request Method Detection Security

```php
// ✅ Correct: Check request method before processing
if (Request::isPost()) {
    $data = Request::post('data', 'valid')->string()->escape();
    // Process POST data
} else {
    // Reject non-POST requests
    http_response_code(405);
    die("Method not allowed");
}

// ✅ For AJAX endpoints
if (!Request::isAjax()) {
    http_response_code(400);
    die("AJAX requests only");
}
```

## HTMLPurifier Configuration

For advanced HTML cleaning, install HTMLPurifier:

```bash
composer require ezyang/htmlpurifier
```

Example usage:
```php
// This automatically uses HTMLPurifier if available
$content = Request::post('content', 'valid')
    ->html()
    ->clean(['p', 'br', 'strong', 'em', 'a', 'ul', 'ol', 'li']);
```

## CSRF Protection

This library does NOT provide CSRF protection. Implement it separately:

```php
// Generate token
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// In your form
echo '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">';

// Validate on submission
if (Request::isPost()) {
    $submittedToken = Request::post('csrf_token', 'valid')->string();
    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        die("CSRF token validation failed");
    }
    // Process form...
}
```

## Rate Limiting

Consider implementing rate limiting for forms:

```php
session_start();

$maxAttempts = 5;
$timeWindow = 300; // 5 minutes

if (!isset($_SESSION['form_attempts'])) {
    $_SESSION['form_attempts'] = [];
}

$now = time();
$_SESSION['form_attempts'] = array_filter(
    $_SESSION['form_attempts'],
    fn($timestamp) => ($now - $timestamp) < $timeWindow
);

if (count($_SESSION['form_attempts']) >= $maxAttempts) {
    http_response_code(429);
    die("Too many attempts. Please try again later.");
}

if (Request::isPost()) {
    $_SESSION['form_attempts'][] = $now;
    // Process form...
}
```

## File Upload Security

When handling file uploads:

```php
$file = Request::files('upload');

if ($file && $file['error'] === UPLOAD_ERR_OK) {
    // ✅ Check file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        die("Invalid file type");
    }
    
    // ✅ Check file size
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($file['size'] > $maxSize) {
        die("File too large");
    }
    
    // ✅ Generate safe filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;
    
    // ✅ Move to safe directory
    $uploadDir = '/var/www/uploads/';
    move_uploaded_file($file['tmp_name'], $uploadDir . $safeFilename);
}
```

## Summary

**Remember**: This library provides the tools for security, but YOU must use them correctly. Always:

1. **Escape output** in the correct context
2. **Validate input** with appropriate type checks
3. **Use prepared statements** for SQL
4. **Implement CSRF protection** for forms
5. **Check request methods** before processing
6. **Never trust client-side validation** alone

Security is not automatic - it requires conscious, context-aware decisions.
