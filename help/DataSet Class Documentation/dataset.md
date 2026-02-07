# DataSet Class Documentation

## Overview

The `DataSet` class is a flexible data container that implements PHP's `ArrayAccess`, `Iterator`, and `Countable` interfaces. It allows you to manage data as arrays while providing additional functionality like dynamic function injection and array-like syntax.

## Features

- **Array-like Access**: Use bracket notation to access and modify data
- **Iteration Support**: Loop through data using `foreach`
- **Countable**: Use `count()` function on DataSet objects
- **Dynamic Functions**: Add custom functions (closures) that can access internal data
- **Type Flexibility**: Store any data type (strings, arrays, objects, etc.)
- **JSON Export**: Convert data to JSON format
- **Debug Helper**: Built-in debugging functionality

## Constructor

### Basic Usage

```php
// Create an empty DataSet
$data = new DataSet();

// Create with initial data
$data = new DataSet([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);
```

## Accessing Data

### Using Array Syntax (ArrayAccess)

```php
$data = new DataSet(['name' => 'John']);

// Get value
echo $data['name']; // Output: John

// Set value
$data['name'] = 'Jane';

// Check if key exists
if (isset($data['name'])) {
    echo "Name exists";
}

// Remove value
unset($data['name']);
```

### Using Property Syntax (Magic Methods)

```php
$data = new DataSet();

// Set value
$data->username = 'johndoe';

// Get value
echo $data->username; // Output: johndoe

// Check if exists
if (isset($data->username)) {
    echo "Username is set";
}
```

## Iteration

### Using foreach Loop

```php
$data = new DataSet([
    'name' => 'John',
    'email' => 'john@example.com',
    'age' => 30
]);

foreach ($data as $key => $value) {
    echo "$key: $value\n";
}
// Output:
// name: John
// email: john@example.com
// age: 30
```

### Manual Iterator Control

```php
$data->rewind();           // Go to start
while ($data->valid()) {   // Check if current position is valid
    echo $data->key();     // Get current key
    echo $data->current(); // Get current value
    $data->next();         // Move to next
}
```

## Counting

```php
$data = new DataSet(['a' => 1, 'b' => 2, 'c' => 3]);

echo count($data); // Output: 3
```

## Adding Dynamic Functions

### Adding a Closure (Data-Aware)

Closures are bound to the DataSet instance, allowing direct access to internal data:

```php
$data = new DataSet([
    'firstName' => 'John',
    'lastName' => 'Doe',
    'age' => 30
]);

// Add a function that can access internal data
$data->addFunction('getFullName', function() {
    return $this->firstName . ' ' . $this->lastName;
});

// Call the function
echo $data->getFullName(); // Output: John Doe
```

### More Complex Example

```php
$data = new DataSet([
    'scores' => [85, 90, 78, 92],
    'name' => 'Student'
]);

$data->addFunction('getAverage', function() {
    $sum = array_sum($this->scores);
    return $sum / count($this->scores);
});

$data->addFunction('printSummary', function() {
    echo $this->name . "'s average: " . round($this->getAverage(), 2);
});

$data->getAverage();   // Returns: 86.25
$data->printSummary(); // Output: Student's average: 86.25
```

### Adding Regular Callables

```php
// Add a function reference
function calculateTax($amount) {
    return $amount * 0.1;
}

$data = new DataSet(['price' => 100]);
$data->addFunction('tax', 'calculateTax');

echo $data->tax(100); // Output: 10
```

## Data Conversion

### Convert to Array

```php
$data = new DataSet(['a' => 1, 'b' => 2]);

$array = $data->toArray();
// Result: ['a' => 1, 'b' => 2]
```

### Convert to JSON

```php
$data = new DataSet([
    'user' => 'John',
    'status' => 'active',
    'score' => 95
]);

$json = $data->toJSON();
// Result: {"user":"John","status":"active","score":95}
```

## Debugging

### Print Contents

```php
$data = new DataSet([
    'name' => 'John',
    'email' => 'john@example.com'
]);

$data->debug();
// Output:
// Array
// (
//     [name] => John
//     [email] => john@example.com
// )
```

## Complete Example

```php
<?php
require 'DataSet.php';

// Create a user DataSet
$user = new DataSet([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'roles' => ['admin', 'user'],
    'active' => true
]);

// Add custom functions
$user->addFunction('getDisplayName', function() {
    return strtoupper($this->name);
});

$user->addFunction('hasRole', function($role) {
    return in_array($role, $this->roles);
});

// Access data
echo "User: " . $user['name'] . "\n";
echo "Email: " . $user->email . "\n";

// Use custom functions
echo "Display: " . $user->getDisplayName() . "\n"; // Output: JOHN DOE
echo "Is admin: " . ($user->hasRole('admin') ? 'yes' : 'no') . "\n"; // Output: yes

// Iterate through data
echo "Total items: " . count($user) . "\n"; // Output: 5

foreach ($user as $key => $value) {
    if (is_array($value)) {
        echo "$key: " . implode(', ', $value) . "\n";
    } else {
        echo "$key: " . ($value === true ? 'true' : ($value === false ? 'false' : $value)) . "\n";
    }
}

// Export to JSON
echo "JSON: " . $user->toJSON() . "\n";
?>
```

## Error Handling

### Invalid Function Call

```php
$data = new DataSet();

try {
    $data->nonExistentFunction(); // Throws Exception
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
    // Output: Error: Function nonExistentFunction does not exist
}
```

### Invalid Function Parameter

```php
$data = new DataSet();

try {
    $data->addFunction('test', 'not_a_function'); // May throw InvalidArgumentException
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Best Practices

1. **Type Checking**: Always verify data exists before using it
   ```php
   if (isset($data->username)) {
       echo $data->username;
   }
   ```

2. **Clear Function Names**: Use descriptive names for added functions
   ```php
   $data->addFunction('validateEmail', $closure);
   $data->addFunction('formatDate', $closure);
   ```

3. **Modular Functions**: Keep functions focused on a single responsibility
   ```php
   // Good
   $data->addFunction('getAge', function() {
       return date('Y') - $this->birthYear;
   });
   
   // Less ideal
   // Multiple operations in one function
   ```

4. **Error Handling**: Use try-catch when calling dynamic functions
   ```php
   try {
       $result = $data->processData();
   } catch (Exception $e) {
       // Handle error
   }
   ```

## API Reference

| Method | Parameters | Returns | Description |
|--------|-----------|---------|-------------|
| `__construct()` | `array $data = []` | void | Initialize with optional data |
| `addFunction()` | `string $name, Closure\|callable $callable` | void | Add dynamic function |
| `__get()` | `string $key` | mixed | Get value using property syntax |
| `__set()` | `string $key, mixed $value` | void | Set value using property syntax |
| `__isset()` | `string $key` | bool | Check if key exists |
| `offsetGet()` | `mixed $offset` | mixed | Get value using array syntax |
| `offsetSet()` | `mixed $offset, mixed $value` | void | Set value using array syntax |
| `offsetExists()` | `mixed $offset` | bool | Check if key exists (array syntax) |
| `offsetUnset()` | `mixed $offset` | void | Remove value |
| `count()` | none | int | Get number of elements |
| `current()` | none | mixed | Get current iteration value |
| `key()` | none | mixed | Get current iteration key |
| `next()` | none | void | Move to next element |
| `rewind()` | none | void | Reset iterator to start |
| `valid()` | none | bool | Check if current position is valid |
| `toArray()` | none | array | Convert to regular array |
| `toJSON()` | none | string | Convert to JSON string |
| `debug()` | none | void | Print contents for debugging |

