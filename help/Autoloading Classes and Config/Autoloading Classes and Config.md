# Autoloading Classes and Config

## Overview

This configuration system provides automatic class loading and lightweight configuration management for PHP applications. It is designed for **small-scale data storage** and should be used responsibly with security considerations in mind.

## Design Philosophy

**Note on PSR Standards:** This implementation intentionally does not follow PSR-x autoloading standards. The custom autoloader eliminates the need for developers to manually specify file paths, providing a more streamlined approach for this specific use case. While PSR standards offer excellent interoperability, this implementation prioritizes simplicity and automatic path resolution for smaller projects.

**Bug Prevention with Use Statements:** A key advantage of this autoloader is that it resolves classes when `use` statements are written at the top of files, before the code executes. This means classes are already loaded when you write your code, resulting in:
- **Fewer errors** - Missing class errors are caught immediately
- **Cleaner code** - Clear visibility of all dependencies at file start
- **More precise** - Explicit declarations make code intent clearer
- **Faster development** - No need to hunt down file paths or require statements
- **Better IDE support** - Autocompletion works correctly with loaded classes

## Features

- **Automatic Class Loading** - No manual `require` or `include` statements needed
- **Namespace Support** - Full support for namespaced classes
- **Configuration Management** - Simple key-value configuration storage
- **Function Loading** - Dynamic loading of utility functions
- **Class Registry** - Track loaded classes for debugging
- **Clean Code Architecture** - Organized file structure with clear separation of concerns

---

## Installation & Setup

### Basic Setup

```php
<?php
// Include the config class
require_once 'config.class.php';

// The autoloader is now registered automatically
```

The `config.class.php` file automatically:
1. Registers itself in the class registry
2. Creates a config instance
3. Registers the SPL autoloader

### Required Server Variables

Ensure these are defined in your bootstrap or configuration:

```php
$_SERVER['DOCUMENT_ROOT']         // Web root directory
$_SERVER['HTTP_path_dll']         // Path to DLL classes
$_SERVER['HTTP_path_sys']         // Path to System classes
$_SERVER['HTTP_path_controller']  // Path to Controller helpers
$_SERVER['HTTP_path_config']      // Path to configuration files
$_SERVER['HTTP_path_funs']        // Path to function files
```

---

## Autoloading Classes

### How It Works

The autoloader searches for classes in the following order:

#### 1. Namespaced Classes
```
/app/{namespace}/{classname}.php
```

**Example:**
```php
// Using a namespaced class
$user = new \Models\User();

// Looks for: /app/Models/User.php
```

#### 2. Non-Namespaced Classes

Searched in this order:
1. `Dll/{classname}.php`
2. `Sys/{classname}.php`
3. Controller helpers: `ApIController`, `IController`, `Iproperty`

**Example:**
```php
// Using a DLL class
$db = new Database();

// Looks for:
// 1. {HTTP_path_dll}Database.php
// 2. {HTTP_path_sys}Database.php
```

### Supported File Types

The autoloader handles both:
- **Classes** - Standard PHP classes
- **Traits** - PHP traits for code reuse

### Usage Examples

```php
<?php
// Namespaced class - automatically loaded
$validator = new \Validators\EmailValidator();

// Non-namespaced class - automatically loaded
$logger = new Logger();

// Trait - automatically loaded when used
class MyClass {
    use LoggerTrait;
}
```

---

## Configuration Management

### ⚠️ Important Security Notice

The configuration system is designed for **small, non-sensitive data only**. 

**DO NOT store:**
- Passwords or API keys
- Sensitive user data
- Large datasets
- Frequently changing values

**Use environment variables or dedicated secret management for sensitive data.**

### Configuration File Structure

Configuration is stored in `.ini.php` files in the `settings/` directory:
- `default.ini.php` - Default configuration values
- `{classname}.ini.php` - Class-specific configuration

### Setting Configuration Values

#### Simple Keys (Default Configuration)

```php
<?php
// Saves to default.ini.php
config::set('timezone', 'Europe/Stockholm');
config::set('app_name', 'MyApplication');
config::set('debug_mode', true);
```

#### Namespaced Keys (Class-Specific Configuration)

```php
<?php
// Saves to database.ini.php
config::set('database.host', 'localhost');
config::set('database.port', '3306');
config::set('database.charset', 'utf8mb4');
```

#### Array Values

```php
<?php
// Store array data
config::set('allowed_extensions', ['jpg', 'png', 'gif', 'pdf']);
config::set('email.smtp', [
    'host' => 'smtp.example.com',
    'port' => 587,
    'encryption' => 'tls'
]);
```

### Retrieving Configuration Values

#### Get Single Value

```php
<?php
// From default configuration
$timezone = config::get('timezone');

// From class-specific configuration
$dbHost = config::get('database.host');
```

#### Get All Values from a Configuration Class

```php
<?php
// Get all default configuration values
$allDefaults = config::get('.*');

// Get all database configuration values
$allDbConfig = config::get('database.*');
```

#### Safe Retrieval (No Exception)

```php
<?php
// Returns false if key doesn't exist (instead of throwing exception)
$value = config::get('optional_setting', true);

if ($value === false) {
    // Key doesn't exist, use default
    $value = 'default_value';
}
```

### Auto-Generated Configuration Files

When setting a namespaced key for the first time, the system automatically creates the configuration file:

```php
<?php
/**
* database_ini data 
*/
class database_ini {
    function __construct() {
        $this->set = array();
        
        // Setting for host 
        $this->set['host'] = 'localhost';
    }
}
?>
```

---

## Function Loading

### Overview

Load and execute functions from `.fun.php` files dynamically.

### File Structure

Functions should be stored as:
```
{HTTP_path_funs}/{functionName}.fun.php
```

### Creating Function Files

**Example:** `calculateTax.fun.php`
```php
<?php
function calculateTax($amount, $taxRate) {
    return $amount * $taxRate;
}
?>
```

### Loading and Calling Functions

```php
<?php
// Load and call the function
$tax = config::loadFunction('calculateTax', 100, 0.25);
// Returns: 25

// Additional arguments are passed through
$result = config::loadFunction('processData', $data, $options, $callback);
```

### Function Naming Rules

- Only alphanumeric characters and underscores
- Maximum 40 characters
- Example valid names: `calculate_total`, `sendEmail`, `format_date_2024`

### Caching

Functions are cached after first load - subsequent calls don't re-include the file.

---

## Class Registry

### Overview

The registry tracks all loaded classes with timestamps for debugging and performance analysis.

### Registering Classes

```php
<?php
// Automatically called by autoloader
config::registry('ClassName', 'class type');

// Manual registration
config::registry('MyUtility', 'utility class');
```

### Retrieving Registry Data

```php
<?php
$registry = config::getRegistry();

/* Returns:
[
    'Config' => [
        'type' => 'Configuration class',
        'time' => 1234567890.1234
    ],
    'Database' => [
        'type' => 'Dll/Database.php',
        'time' => 1234567890.5678
    ]
]
*/

// Use for debugging
foreach ($registry as $class => $info) {
    echo "$class loaded at {$info['time']} ({$info['type']})\n";
}
```

---

## Best Practices

### Configuration Management

1. **Keep Data Small** - Only store lightweight configuration values
2. **Use Namespacing** - Group related settings: `email.smtp_host`, `email.from_address`
3. **Validate Input** - Always validate data before storing
4. **Document Settings** - Comment your configuration keys
5. **Version Control** - Consider `.gitignore` for environment-specific configs

### Security Considerations

1. **Never store sensitive data** in configuration files
2. **Validate all input** before using `config::set()`
3. **Restrict file permissions** on configuration directories
4. **Use environment variables** for secrets (database passwords, API keys)
5. **Sanitize output** when displaying configuration values

### Code Organization

```
/app
  /Models
    User.php
    Product.php
  /Validators
    EmailValidator.php
  /Services
    PaymentService.php

/Dll
  Database.php
  Logger.php

/Sys
  Cache.php
  Session.php

/Controller
  IController.php
  ApIController.php

/config/settings
  default.ini.php
  database.ini.php
  email.ini.php

/functions
  calculateTax.fun.php
  formatCurrency.fun.php
```

### Performance Tips

1. **Lazy Loading** - Classes load only when needed
2. **Function Caching** - Functions are cached after first load
3. **Registry Monitoring** - Use registry to identify performance bottlenecks
4. **Minimize Config Calls** - Cache frequently used config values in variables

---

## Error Handling

### Common Exceptions

```php
<?php
try {
    // Missing configuration key
    $value = config::get('nonexistent.key');
} catch (SystemException $e) {
    // Handle error
}

try {
    // Invalid function name
    config::loadFunction('invalid-name!');
} catch (SystemException $e) {
    // Handle error
}
```

### Safe Operations

```php
<?php
// Check if key exists before retrieving
$value = config::get('optional.setting', true);
if ($value !== false) {
    // Key exists, use it
}

// Check if function file exists
if (function_exists('myFunction') || 
    file_exists($_SERVER['HTTP_path_funs'] . 'myFunction.fun.php')) {
    $result = config::loadFunction('myFunction', $args);
}
```

---

## Examples

### Complete Application Setup

```php
<?php
// bootstrap.php

// Define paths
$_SERVER['HTTP_path_dll'] = __DIR__ . '/Dll/';
$_SERVER['HTTP_path_sys'] = __DIR__ . '/Sys/';
$_SERVER['HTTP_path_controller'] = __DIR__ . '/Controller/';
$_SERVER['HTTP_path_config'] = __DIR__ . '/config/';
$_SERVER['HTTP_path_funs'] = __DIR__ . '/functions/';

// Load config class (autoloader activates automatically)
require_once __DIR__ . '/config.class.php';

// Set application configuration
config::set('app.name', 'MyApp');
config::set('app.version', '1.0.0');
config::set('app.environment', 'production');

// Use namespaced classes (auto-loaded)
$user = new \Models\User();
$validator = new \Validators\EmailValidator();

// Use functions
$formatted = config::loadFunction('formatCurrency', 1234.56, 'USD');

// Get configuration
$appName = config::get('app.name');
```

### Configuration Management Example

```php
<?php
// Setup database configuration
config::set('database.host', 'localhost');
config::set('database.name', 'myapp_db');
config::set('database.charset', 'utf8mb4');

// Setup email configuration
config::set('email.from_name', 'MyApp');
config::set('email.from_address', 'noreply@myapp.com');
config::set('email.smtp', [
    'host' => 'smtp.example.com',
    'port' => 587
]);

// Retrieve configuration
$dbConfig = config::get('database.*');
$smtpSettings = config::get('email.smtp');

// Use in application
$db = new Database(
    $dbConfig['host'],
    $dbConfig['name'],
    $dbConfig['charset']
);
```

---

## Troubleshooting

### Class Not Found

**Problem:** Class fails to load

**Solutions:**
1. Verify file exists in correct directory
2. Check namespace matches directory structure
3. Ensure file naming matches class name exactly (case-sensitive)
4. Verify server path variables are set correctly

### Configuration Not Persisting

**Problem:** Settings not saved to file

**Solutions:**
1. Check file permissions on `/config/settings/` directory
2. Verify `HTTP_path_config` is set correctly
3. Ensure PHP has write permissions

### Function Loading Fails

**Problem:** Function file not found

**Solutions:**
1. Verify function name contains only valid characters
2. Check file exists: `{functionName}.fun.php`
3. Verify `HTTP_path_funs` is set correctly
4. Ensure function is defined in the file after inclusion

---

## Limitations

1. **Not PSR-Compliant** - Custom autoloading may conflict with PSR-based libraries
2. **File-Based Config** - Not suitable for high-frequency writes
3. **No Config Encryption** - Sensitive data should use other methods
4. **Single-File Functions** - Each function requires its own file
5. **No Config Validation** - Manual validation required

---

## Migration Notes

If migrating from PSR-4 autoloading:

1. Reorganize files to match this autoloader's structure
2. Update namespace declarations if needed
3. Move configuration to `.ini.php` files
4. Update any hardcoded `require`/`include` statements
5. Test thoroughly - autoloading behavior differs from PSR-4

---

*Remember: Use this configuration system wisely and with respect to security best practices. It is designed for convenience with small datasets, not as a replacement for proper secret management or database storage.*
