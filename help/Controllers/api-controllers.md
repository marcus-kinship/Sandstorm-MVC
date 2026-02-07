# API Controllers Documentation

How API Controllers work in the Sandstorm MVC framework.

## Table of Contents

1. [Introduction](#introduction)
2. [Naming Conventions](#naming-conventions)
3. [Basic Structure](#basic-structure)
4. [HTTP Methods](#http-methods)
5. [CORS (Cross-Origin Resource Sharing)](#cors-cross-origin-resource-sharing)
6. [JSONP Support](#jsonp-support)
7. [Response Handling](#response-handling)
8. [Examples](#examples)
9. [Best Practices](#best-practices)

## Introduction

`ApiController` is an abstract base class in Sandstorm MVC that handles JSON and XML API responses. It provides automatic CORS handling, JSONP support, HTTP method detection, and response formatting.

### Key Features

- Automatic Content-Type handling (JSON/XML)
- Built-in CORS support with domain whitelisting
- JSONP support with XSS protection
- Automatic HTTP method detection
- Flexible header management
- HTTP status code management

## Naming Conventions

### Critical Naming Rules

API Controllers must follow strict naming conventions to function correctly:

#### Class Names
```php
// Pattern: ...{js|xml}ApiController
UserJsApiController      // ✓ Correct - JSON API
ProductXmlApiController  // ✓ Correct - XML API
OrderJsApiController     // ✓ Correct
DatajsApiController      // ✗ Wrong - must have capital J
UserApiController        // ✗ Wrong - missing js/xml
```

#### File Names
```php
// Pattern: ...{js|xml}api.class
userjsapi.class          // ✓ Correct for UserJsApiController
productxmlapi.class      // ✓ Correct for ProductXmlApiController
orderjsapi.class         // ✓ Correct
userapi.class            // ✗ Wrong - missing js/xml
```

### Why These Conventions?

The framework uses regex matching to:
1. Validate that the class is a valid API controller
2. Automatically set the correct Content-Type header
3. Return HTTP 501 if the convention is not followed

## Basic Structure

### Minimal Implementation

```php
<?php

/**
 * User API Controller - Returns JSON responses
 */
class UserJsApiController extends ApiController
{
    /**
     * Get user information
     * Function name starts with 'get' → Allows GET requests
     */
    public function getUser($id)
    {
        // Fetch user data
        $user = User::findById($id);
        
        if (!$user) {
            $this->httpResponseCode(404);
            $this->callback(['error' => 'User not found']);
            return;
        }
        
        // Return user data
        $this->callback([
            'status' => 'success',
            'data' => $user
        ]);
    }
    
    /**
     * Create new user
     * Function name starts with 'post' → Allows POST requests
     */
    public function postUser()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate and create user
        $user = User::create($data);
        
        $this->httpResponseCode(201);
        $this->callback([
            'status' => 'success',
            'data' => $user
        ]);
    }
}
```

### File Structure

```
site/
└── userjsapi.class    # Contains UserJsApiController
```

## HTTP Methods

### Automatic Method Detection

API Controller automatically detects allowed HTTP methods based on the function name prefix:

| Function Prefix | Allowed Methods | Usage |
|----------------|------------------|-------|
| `delete`, `remove` | DELETE | Delete resources |
| `get`, `read` | GET | Read data |
| `post`, `add` | POST | Create new resources |
| `put`, `update` | PUT | Update entire resources |
| `fix`, `patch` | GET, POST, PATCH | Partial updates |
| `head` | GET, POST, HEAD | Metadata requests |
| `search`, `request`, `find`, `lookup`, `ask`, `set`, `save` | GET, POST | Search and query operations |
| `options`, `opt` | GET, POST, PUT, DELETE, OPTIONS | Preflight requests |

### Method Usage Examples

```php
class ProductJsApiController extends ApiController
{
    // Allows GET only
    public function getProducts() { }
    public function readProduct($id) { }
    
    // Allows POST only
    public function postProduct() { }
    public function addProduct() { }
    
    // Allows PUT only
    public function putProduct($id) { }
    public function updateProduct($id) { }
    
    // Allows DELETE only
    public function deleteProduct($id) { }
    public function removeProduct($id) { }
    
    // Allows GET and POST
    public function searchProducts() { }
    public function findProducts() { }
    public function saveProduct() { }
    
    // Allows GET, POST and PATCH
    public function patchProduct($id) { }
    public function fixProduct($id) { }
    
    // Allows all methods
    public function optionsProducts() { }
}
```

### Access-Control-Allow-Methods Header

The header is automatically set in the constructor:

```php
// Example: For function getUser()
Access-Control-Allow-Methods: GET

// Example: For function searchProducts()
Access-Control-Allow-Methods: GET, POST

// Example: For function optionsProducts()
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS
```

## CORS (Cross-Origin Resource Sharing)

### Basic CORS Configuration

```php
class UserJsApiController extends ApiController
{
    public function getUser($id)
    {
        // Allow all domains
        $this->allowDomains("*");
        
        // Or allow specific domain
        $this->allowDomains("https://example.com");
        
        // Or allow multiple domains
        $this->allowDomains([
            "https://example.com",
            "https://app.example.com",
            "https://admin.example.com"
        ]);
        
        $this->callback(['data' => 'user data']);
    }
}
```

### CORS Domain Matching Logic

When `allowDomains()` is called, the following happens:

1. **Wildcard (`*`)**: Allows all domains
   ```php
   $this->allowDomains("*");
   // Sets: Access-Control-Allow-Origin: *
   ```

2. **Array of domains**: Matches against `HTTP_ORIGIN`
   ```php
   $this->allowDomains([
       "https://example.com",
       "https://app.example.com"
   ]);
   // If HTTP_ORIGIN is https://example.com:
   // Sets: Access-Control-Allow-Origin: https://example.com
   ```

3. **Single string**: Exact matching
   ```php
   $this->allowDomains("https://example.com");
   // Matches only this exact domain
   ```

4. **Fallback**: If no match, use first domain in array
   ```php
   // If HTTP_ORIGIN is missing or doesn't match:
   // Uses first value in domains array
   ```

### Credentials and Caching

```php
class SecureApiController extends ApiController
{
    public function getData()
    {
        // Allow credentials (cookies, authorization headers)
        $this->credentials(true);
        
        // Set max-age for preflight requests (1 hour)
        $this->maxAge(3600);
        
        // Configure allowed domains
        $this->allowDomains([
            "https://trusted-app.com"
        ]);
        
        $this->callback(['secure' => 'data']);
    }
}
```

### Complete CORS Example

```php
class ApiJsApiController extends ApiController
{
    public function optionsEndpoint()
    {
        // Handle preflight request
        $this->allowDomains("*");
        $this->credentials(true);
        $this->maxAge(86400); // 24 hours
        
        // Add custom headers
        $this->setHeader(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Requested-With'
        );
        
        $this->httpResponseCode(204);
    }
    
    public function getData()
    {
        // Handle actual request
        $this->allowDomains([
            "https://app.example.com",
            "https://mobile.example.com"
        ]);
        
        $this->credentials(true);
        
        $this->callback([
            'data' => 'sensitive information'
        ]);
    }
}
```

## JSONP Support

### Basic JSONP

```php
class UserJsApiController extends ApiController
{
    public function getUser($id)
    {
        $user = User::findById($id);
        
        // Get callback function name from query parameter
        $callback = $_GET['callback'] ?? '';
        
        // Send data with JSONP callback
        $this->callback(['user' => $user], $callback);
    }
}
```

### XSS Protection in JSONP

API Controller automatically validates callback function names:

```php
// Validation regex: /^[a-zA-Z_$][a-zA-Z0-9_$]*$/

// Valid callback names
myCallback        // ✓
_privateCallback  // ✓
$jqueryCallback   // ✓
callback123       // ✓

// Invalid callback names (XSS attempts)
alert(1)          // ✗
<script>          // ✗
../../etc/passwd  // ✗
my-callback       // ✗
123callback       // ✗
```

### JSONP Request Example

```javascript
// Frontend JavaScript
function loadUser(userId) {
    const script = document.createElement('script');
    script.src = `https://api.example.com/user/get/${userId}?callback=handleUser`;
    document.body.appendChild(script);
}

function handleUser(response) {
    console.log('User data:', response.user);
}
```

```php
// Backend PHP
class UserJsApiController extends ApiController
{
    public function getUser($id)
    {
        $user = User::findById($id);
        
        // Returns: handleUser({"user":{"id":1,"name":"John"}});
        $this->callback(
            ['user' => $user],
            $_GET['callback'] ?? ''
        );
    }
}
```

## Response Handling

### callback() Method

The main method for setting response data:

```php
// Simple JSON response
$this->callback(['status' => 'success']);

// JSON response with JSONP
$this->callback(['status' => 'success'], 'myCallback');

// Complex data structure
$this->callback([
    'status' => 'success',
    'data' => [
        'users' => $users,
        'total' => count($users),
        'page' => $page
    ],
    'meta' => [
        'timestamp' => time(),
        'version' => '1.0'
    ]
]);
```

### HTTP Status Codes

```php
class ProductJsApiController extends ApiController
{
    public function getProduct($id)
    {
        $product = Product::findById($id);
        
        if (!$product) {
            // 404 Not Found
            $this->httpResponseCode(404);
            $this->callback([
                'error' => 'Product not found',
                'code' => 404
            ]);
            return;
        }
        
        // 200 OK (default)
        $this->callback(['product' => $product]);
    }
    
    public function postProduct()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$this->validateProduct($data)) {
            // 400 Bad Request
            $this->httpResponseCode(400);
            $this->callback([
                'error' => 'Invalid product data',
                'code' => 400
            ]);
            return;
        }
        
        $product = Product::create($data);
        
        // 201 Created
        $this->httpResponseCode(201);
        $this->callback([
            'status' => 'created',
            'product' => $product
        ]);
    }
    
    public function deleteProduct($id)
    {
        $product = Product::findById($id);
        
        if (!$product) {
            // 404 Not Found
            $this->httpResponseCode(404);
            $this->callback(['error' => 'Product not found']);
            return;
        }
        
        $product->delete();
        
        // 204 No Content (no data returned)
        $this->httpResponseCode(204);
    }
}
```

### Supported HTTP Status Codes

| Code | Text | Usage |
|------|------|-------|
| 200 | OK | Standard successful response |
| 201 | Created | Resource created |
| 204 | No Content | Success without response body |
| 400 | Bad Request | Invalid request data |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource doesn't exist |
| 405 | Method Not Allowed | HTTP method not allowed |
| 409 | Conflict | Resource conflict |
| 500 | Internal Server Error | Server error |
| 501 | Not Implemented | Functionality not implemented |
| 503 | Service Unavailable | Service temporarily unavailable |

### Custom Headers

```php
class CustomHeaderApiController extends ApiController
{
    public function getData()
    {
        // Set custom headers
        $this->setHeader('X-API-Version', '1.0');
        $this->setHeader('X-Rate-Limit', '1000');
        $this->setHeader('X-Rate-Limit-Remaining', '999');
        
        // Read header value
        $version = $this->getHeader('X-API-Version');
        
        $this->callback(['data' => 'response']);
    }
}
```

### 204 No Content Automatic

If no data is set via `callback()`, the destructor automatically returns 204:

```php
public function deleteUser($id)
{
    User::delete($id);
    
    // Does NOT call callback()
    // Destructor automatically returns:
    // HTTP/1.1 204 No Content
}
```

## Examples

### Complete CRUD API

```php
<?php

/**
 * Product API - Full CRUD implementation
 */
class ProductJsApiController extends ApiController
{
    /**
     * LIST - Get all products
     * GET /product/list
     */
    public function getList()
    {
        $this->allowDomains("*");
        
        $products = Product::all();
        
        $this->callback([
            'status' => 'success',
            'count' => count($products),
            'data' => $products
        ]);
    }
    
    /**
     * READ - Get single product
     * GET /product/read/{id}
     */
    public function getProduct($id)
    {
        $this->allowDomains("*");
        
        $product = Product::findById($id);
        
        if (!$product) {
            $this->httpResponseCode(404);
            $this->callback([
                'status' => 'error',
                'message' => 'Product not found'
            ]);
            return;
        }
        
        $this->callback([
            'status' => 'success',
            'data' => $product
        ]);
    }
    
    /**
     * CREATE - Create new product
     * POST /product/add
     */
    public function postProduct()
    {
        $this->allowDomains([
            'https://admin.example.com'
        ]);
        $this->credentials(true);
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate
        if (empty($data['name']) || empty($data['price'])) {
            $this->httpResponseCode(400);
            $this->callback([
                'status' => 'error',
                'message' => 'Name and price are required'
            ]);
            return;
        }
        
        // Create
        $product = Product::create($data);
        
        $this->httpResponseCode(201);
        $this->callback([
            'status' => 'success',
            'message' => 'Product created',
            'data' => $product
        ]);
    }
    
    /**
     * UPDATE - Update product
     * PUT /product/update/{id}
     */
    public function putProduct($id)
    {
        $this->allowDomains([
            'https://admin.example.com'
        ]);
        $this->credentials(true);
        
        $product = Product::findById($id);
        
        if (!$product) {
            $this->httpResponseCode(404);
            $this->callback([
                'status' => 'error',
                'message' => 'Product not found'
            ]);
            return;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $product->update($data);
        
        $this->callback([
            'status' => 'success',
            'message' => 'Product updated',
            'data' => $product
        ]);
    }
    
    /**
     * DELETE - Delete product
     * DELETE /product/remove/{id}
     */
    public function deleteProduct($id)
    {
        $this->allowDomains([
            'https://admin.example.com'
        ]);
        $this->credentials(true);
        
        $product = Product::findById($id);
        
        if (!$product) {
            $this->httpResponseCode(404);
            $this->callback([
                'status' => 'error',
                'message' => 'Product not found'
            ]);
            return;
        }
        
        $product->delete();
        
        $this->httpResponseCode(204);
        // No callback - 204 returned automatically
    }
    
    /**
     * SEARCH - Search products
     * GET /product/search?q=keyword
     */
    public function searchProducts()
    {
        $this->allowDomains("*");
        
        $query = $_GET['q'] ?? '';
        
        if (empty($query)) {
            $this->httpResponseCode(400);
            $this->callback([
                'status' => 'error',
                'message' => 'Search query required'
            ]);
            return;
        }
        
        $products = Product::search($query);
        
        $this->callback([
            'status' => 'success',
            'query' => $query,
            'count' => count($products),
            'data' => $products
        ]);
    }
}
```

### Secure API with Authentication

```php
<?php

/**
 * Secure API with authentication
 */
class SecureJsApiController extends ApiController
{
    private function authenticate()
    {
        $headers = getallheaders();
        $token = $headers['Authorization'] ?? '';
        
        if (!$this->validateToken($token)) {
            $this->httpResponseCode(401);
            $this->callback([
                'status' => 'error',
                'message' => 'Unauthorized'
            ]);
            return false;
        }
        
        return true;
    }
    
    private function validateToken($token)
    {
        // Implement your token validation logic
        return !empty($token) && $token === 'Bearer valid-token';
    }
    
    public function getData()
    {
        $this->allowDomains([
            'https://app.example.com'
        ]);
        $this->credentials(true);
        
        // Check authentication
        if (!$this->authenticate()) {
            return;
        }
        
        // Return protected data
        $this->callback([
            'status' => 'success',
            'data' => [
                'sensitive' => 'information'
            ]
        ]);
    }
}
```

### API with Rate Limiting

```php
<?php

/**
 * API with rate limiting
 */
class RateLimitedJsApiController extends ApiController
{
    private function checkRateLimit($ip)
    {
        $key = "api_rate_limit:$ip";
        $limit = 100; // requests per hour
        $window = 3600; // 1 hour in seconds
        
        // Get current count (implement with Redis, Memcached, or database)
        $current = Cache::get($key, 0);
        
        if ($current >= $limit) {
            return false;
        }
        
        // Increment counter
        Cache::increment($key);
        Cache::expire($key, $window);
        
        return [
            'limit' => $limit,
            'remaining' => $limit - $current - 1,
            'reset' => time() + $window
        ];
    }
    
    public function getData()
    {
        $this->allowDomains("*");
        
        $ip = $_SERVER['REMOTE_ADDR'];
        $rateLimit = $this->checkRateLimit($ip);
        
        if ($rateLimit === false) {
            $this->httpResponseCode(429); // Too Many Requests
            $this->setHeader('Retry-After', '3600');
            $this->callback([
                'status' => 'error',
                'message' => 'Rate limit exceeded'
            ]);
            return;
        }
        
        // Set rate limit headers
        $this->setHeader('X-RateLimit-Limit', $rateLimit['limit']);
        $this->setHeader('X-RateLimit-Remaining', $rateLimit['remaining']);
        $this->setHeader('X-RateLimit-Reset', $rateLimit['reset']);
        
        $this->callback([
            'status' => 'success',
            'data' => 'your data'
        ]);
    }
}
```

### Paginated API

```php
<?php

/**
 * Paginated API response
 */
class PaginatedJsApiController extends ApiController
{
    public function getList()
    {
        $this->allowDomains("*");
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $perPage = min($perPage, 100); // Max 100 items per page
        
        // Calculate offset
        $offset = ($page - 1) * $perPage;
        
        // Fetch data
        $total = Product::count();
        $products = Product::limit($perPage)->offset($offset)->get();
        
        // Calculate pagination meta
        $totalPages = ceil($total / $perPage);
        
        $this->callback([
            'status' => 'success',
            'data' => $products,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'links' => [
                'first' => "/api/list?page=1&per_page=$perPage",
                'last' => "/api/list?page=$totalPages&per_page=$perPage",
                'prev' => $page > 1 ? "/api/list?page=" . ($page - 1) . "&per_page=$perPage" : null,
                'next' => $page < $totalPages ? "/api/list?page=" . ($page + 1) . "&per_page=$perPage" : null
            ]
        ]);
    }
}
```

## Best Practices

### 1. Consistent Error Handling

```php
class UserJsApiController extends ApiController
{
    private function errorResponse($code, $message, $details = null)
    {
        $this->httpResponseCode($code);
        
        $response = [
            'status' => 'error',
            'message' => $message,
            'code' => $code
        ];
        
        if ($details !== null) {
            $response['details'] = $details;
        }
        
        $this->callback($response);
    }
    
    public function getUser($id)
    {
        if (!is_numeric($id)) {
            $this->errorResponse(400, 'Invalid user ID');
            return;
        }
        
        $user = User::findById($id);
        
        if (!$user) {
            $this->errorResponse(404, 'User not found');
            return;
        }
        
        $this->callback([
            'status' => 'success',
            'data' => $user
        ]);
    }
}
```

### 2. Input Validation

```php
class ProductJsApiController extends ApiController
{
    private function validateProductData($data)
    {
        $errors = [];
        
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($data['price']) || !is_numeric($data['price'])) {
            $errors['price'] = 'Valid price is required';
        }
        
        if ($data['price'] < 0) {
            $errors['price'] = 'Price must be positive';
        }
        
        return $errors;
    }
    
    public function postProduct()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            $this->httpResponseCode(400);
            $this->callback([
                'status' => 'error',
                'message' => 'Invalid JSON'
            ]);
            return;
        }
        
        $errors = $this->validateProductData($data);
        
        if (!empty($errors)) {
            $this->httpResponseCode(422); // Unprocessable Entity
            $this->callback([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
            return;
        }
        
        $product = Product::create($data);
        
        $this->httpResponseCode(201);
        $this->callback([
            'status' => 'success',
            'data' => $product
        ]);
    }
}
```

### 3. API Versioning

```php
// v1/userjsapi.class
class V1UserJsApiController extends ApiController
{
    public function getUser($id)
    {
        $this->callback([
            'version' => '1.0',
            'data' => User::findById($id)
        ]);
    }
}

// v2/userjsapi.class
class V2UserJsApiController extends ApiController
{
    public function getUser($id)
    {
        $user = User::findById($id);
        
        $this->callback([
            'version' => '2.0',
            'data' => [
                'user' => $user,
                'profile' => $user->profile,
                'metadata' => [
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at
                ]
            ]
        ]);
    }
}
```

### 4. Logging and Debugging

```php
class LoggedJsApiController extends ApiController
{
    private function logRequest($endpoint, $data = [])
    {
        $log = [
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoint' => $endpoint,
            'method' => $_SERVER['REQUEST_METHOD'],
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'data' => $data
        ];
        
        // Log to file, database, or logging service
        error_log(json_encode($log), 3, '/var/log/api.log');
    }
    
    public function postData()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $this->logRequest('postData', $data);
        
        try {
            $result = $this->processData($data);
            
            $this->callback([
                'status' => 'success',
                'data' => $result
            ]);
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            
            $this->httpResponseCode(500);
            $this->callback([
                'status' => 'error',
                'message' => 'Internal server error'
            ]);
        }
    }
}
```

### 5. CORS Best Practices

```php
class SecureCorsApiController extends ApiController
{
    private $allowedDomains = [
        'https://app.example.com',
        'https://mobile.example.com'
    ];
    
    public function __construct()
    {
        parent::__construct();
        
        // Set CORS headers for all methods
        $this->allowDomains($this->allowedDomains);
        $this->credentials(true);
        $this->maxAge(3600);
        
        // Set allowed headers
        $this->setHeader(
            'Access-Control-Allow-Headers',
            'Content-Type, Authorization, X-Requested-With'
        );
    }
    
    public function optionsData()
    {
        // Handle preflight request
        $this->httpResponseCode(204);
    }
    
    public function getData()
    {
        // Actual data endpoint
        $this->callback(['data' => 'response']);
    }
}
```

### 6. Caching Headers

```php
class CachedJsApiController extends ApiController
{
    public function getPublicData()
    {
        // Public data that can be cached
        $this->setHeader('Cache-Control', 'public, max-age=3600');
        $this->setHeader('ETag', md5(json_encode($data)));
        
        $this->callback(['data' => 'cacheable data']);
    }
    
    public function getPrivateData()
    {
        // Private data that should not be cached
        $this->setHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate');
        $this->setHeader('Pragma', 'no-cache');
        $this->setHeader('Expires', '0');
        
        $this->callback(['data' => 'private data']);
    }
}
```

### 7. Documentation Headers

```php
class DocumentedJsApiController extends ApiController
{
    public function getData()
    {
        // Add API documentation headers
        $this->setHeader('X-API-Version', '1.0');
        $this->setHeader('X-API-Deprecation', 'false');
        $this->setHeader('X-API-Docs', 'https://docs.example.com/api');
        
        $this->callback(['data' => 'response']);
    }
    
    public function getDeprecatedData()
    {
        // Mark as deprecated
        $this->setHeader('X-API-Deprecation', 'true');
        $this->setHeader('X-API-Sunset', '2024-12-31');
        $this->setHeader('X-API-Replacement', 'https://api.example.com/v2/data');
        
        $this->callback(['data' => 'deprecated response']);
    }
}
```

