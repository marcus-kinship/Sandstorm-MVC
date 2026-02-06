# Sandstorm MVC Framework

**A simple, lightweight MVC framework for building web applications in PHP**

Sandstorm is a free and open-source MVC framework built from the ground up for PHP developers at all levels. This framework is built with a focus on simplicity and clear MVC architecture.

**Note:** This project is currently in progress. Some features are not yet complete, but this does not affect the execution or core functionality of the framework.

---

## 📋 Table of Contents

- [Description](#description)
- [Features](#features)
- [System Requirements](#system-requirements)
- [Installation](#installation)
- [Project Structure](#project-structure)
- [Quick Start](#quick-start)
- [Usage](#usage)
  - [Controllers](#controllers)
  - [Router](#router)
  - [Views](#views)
  - [Models](#models)
- [Examples](#examples)
- [Configuration](#configuration)
- [Development Mode](#development-mode)
- [License](#license)
- [Contact](#contact)

---

## 🎯 Description

Sandstorm is an MVC (Model-View-Controller) framework for PHP built from the ground up with a focus on:

- **Simplicity**: Easy to understand and get started with
- **Flexibility**: You decide how your project structure should look
- **Automation**: Automatic generation of routes and views
- **Developer-friendly**: Perfect for both beginners and experienced developers

The framework follows classic MVC architecture and makes it easy to separate logic, data, and presentation in your web applications.

---

## ✨ Features

- ✅ **MVC Architecture**: Clear separation between Model, View, and Controller
- ✅ **Automatic Routing**: Define routes directly in PHP comments
- ✅ **Auto-generation**: Automatic creation of views and .htaccess on first run
- ✅ **Flexible Routing**: Support for dynamic URL parameters with type validation
- ✅ **Database Connection**: Simple MySQL integration with pool management
- ✅ **Development Mode**: Automatic configuration for rapid development
- ✅ **Lightweight**: Minimal framework without unnecessary overhead

---

## 💻 System Requirements

- **PHP**: 7.0 or higher
- **MySQL**: 5.6 or higher (if database is used)
- **Web Server**: Apache with mod_rewrite enabled
- **PHP Short Tags**: Must be enabled

---

## 🚀 Installation

### Step 1: Install Prerequisites

```bash
# Install PHP and MySQL on your development environment
# For Ubuntu/Debian:
sudo apt-get update
sudo apt-get install php mysql-server apache2

# For macOS (with Homebrew):
brew install php mysql
```

### Step 2: Configure PHP

Open `php.ini` and enable short tags:

```ini
short_open_tag = On
```

Restart the web server after the change.

### Step 3: Download Sandstorm

Download or clone Sandstorm to your web server's root directory:

```bash
cd /var/www/html  # or your web server's root
git clone https://github.com/yourusername/sandstorm.git
cd sandstorm
```

### Step 4: First Run

When you visit your URL for the first time, Sandstorm will automatically:
- Generate necessary `.htaccess` settings
- Create basic routing
- Display a welcome page

### Step 5: Configure Database Connection (Optional)

If you want to use MySQL, configure the pool file:

```bash
# Navigate to the config folder
cd app/config/mysql/

# Edit default.db.php
nano default.db.php
```

Fill in your database details:

```php
<?php
class DefaultDB {
    private $host = 'localhost';
    private $username = 'your_username';
    private $password = 'your_password';
    private $database = 'your_database';
    
    // ... rest of the configuration
}
```

**Tip**: You can create multiple database pools by copying the file and changing the class name.

---

## 📁 Project Structure

```
sandstorm/
│
├── app/                          # Application folder
│   ├── config/                   # Configuration files
│   │   └── mysql/               # MySQL configuration
│   │       └── default.db.php   # Default database pool
│   ├── dll/                     # Dynamic-link library (Models)
│   │   └── myModel.php          # Example model
│   └── core/                    # Core framework files
│
├── res/                         # Resources
│   ├── css/                     # Stylesheets
│   ├── js/                      # JavaScript files
│   └── images/                  # Images
│       └── logo.jpg
│
├── site/                        # Website structure
│   └── default/                 # Example controller folder
│       ├── Default.class.php    # Controller class
│       └── start.php            # View file
│
├── upload/                      # Upload folder
│
├── .htaccess                    # Apache configuration
├── index.php                    # Main file (Entry point)
├── README.md                    # This file
└── LICENSE                      # GNU LGPL 3.0 license
```

### Folder Description

| Folder/File | Description |
|-------------|-------------|
| `app/` | Contains the application's core logic and configuration |
| `res/` | Static resources like CSS, JavaScript, and images |
| `site/` | Your website's controllers and views |
| `upload/` | Folder for user uploads |
| `.htaccess` | URL rewriting and server configuration |
| `index.php` | Entry point for the entire application |

---

## 🏃 Quick Start

### 1. Create Your First Controller

```bash
# Create a folder in the site directory
mkdir site/default

# Create a controller file
touch site/default/Default.class.php
```

### 2. Define Your Controller

```php
<?php

class DefaultController extends IController {
    
    /**
     * Start page
     * 
     * @router / -> index
     */
    function index() {

        // Load view 
        $this->load("default/start.php");
    }
}
```

### 3. Visit Your Page

Open your browser and navigate to:
```
http://localhost/sandstorm/
```

Sandstorm will automatically:
- Create routing in `.htaccess`
- Generate the view file `default/start.php`
- Display your page

---

## 📖 Usage

### Controllers

Controllers are the heart of your application. They handle user requests and coordinate between models and views.

#### Creating a Controller

```php
<?php

class UserController extends IController {
    
    /**
     * User profile
     * 
     * @router user/profile/{number(1,11):id} -> profile
     */
    function profile($id) {
       
        // Fetch data from model
        $userModel = new UserModel();
        $user = $userModel->getUserById($id);
        
        // Send data to view
        $this->setData('user', $user)->load("user/profile.php");
    }
}
```

### Router

Routing in Sandstorm is defined with PHP comments directly in controller methods. When you visit a URL for the first time, Sandstorm reads the `@router` comment and automatically generates the corresponding rule in `.htaccess`.

#### Basic Routing

```php
<?php

class DefaultController extends IController {
    
    /**
     * Start page
     * 
     * @router / -> index
     */
    function index() {
        // Handles the route: /
        $this->load("default/start.php");
    }
}
```

**What happens:**
1. You write `@router / -> index` in the comment
2. You visit `/` in the browser
3. Sandstorm automatically generates in `.htaccess`:
   ```apache
   SetEnvIfNoCase Request_URI "^[/]{0,1}$" HTTP_CONTROLLER=default/default.class.php|default|index|
   ```
4. The view file `default/start.php` is created if it doesn't exist

#### Dynamic Parameters

URL parameters are retrieved with `$this->getPartUrl(position)` where position is the index in the URL:

**Method: With getPartUrl() (correct)**

```php
/**
 * @router user/blog/{string:slug}/{number(1-11):id} -> blog
 */
function blog() {
    // URL: /user/blog/my-article/123
    // Position: 0=user, 1=blog, 2=my-article, 3=123
    
    $slug = $this->getPartUrl(2);  // "my-article"
    $id = $this->getPartUrl(3);    // 123
    
    echo "Slug: $slug, ID: $id";
}
```

**Example with database call:**

```php
/**
 * @router article/{number:id} -> show
 */
function show() {
    // URL: /article/123
    // Position: 0=article, 1=123
    
    $id = $this->getPartUrl(1);
    
    $db = new DatabaseConnection();
    $article = $db->get()->Row(
        "SELECT * FROM articles WHERE id = %d", 
        $id
    );
    
    $this->setData('article', $article)->load("article/show.php");
}
```

**More examples:**

```php
/**
 * @router user/{string:username}/gallery/{string:album} -> gallery
 */
function gallery() {
    // URL: /user/john/gallery/summer2024
    // Position: 0=user, 1=john, 2=gallery, 3=summer2024
    
    $username = $this->getPartUrl(1);  // "john"
    $album = $this->getPartUrl(3);     // "summer2024"
}
```

#### Parameter Types

| Type | Description | Example |
|------|-------------|---------|
| `{string:name}` | Text string | `foo`, `hello-world` |
| `{number:name}` | Integer | `123`, `42` |
| `{number(1-11):name}` | Integer with length limit | `1` to `12345678901` |

### Views

Views are the presentation layer that displays data to the user.

#### Loading a View

```php
// Simple loading
$this->load("default/start.php");

// With data
$this->setData('title', 'My Page')->load("default/start.php");

// Multiple data variables
$this->setData(
    'title', 'My Page',
    'user', $userData,
    'posts', $posts
)->load("default/start.php");
```

#### Creating a View File

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
</head>
<body>
    <h1>Welcome <?php echo $user['name']; ?></h1>
    
    <?php foreach($posts as $post): ?>
        <article>
            <h2><?php echo $post['title']; ?></h2>
            <p><?php echo $post['content']; ?></p>
        </article>
    <?php endforeach; ?>
</body>
</html>
```

### Models

Models handle data logic and database communication.

#### Database Connection

Sandstorm uses the `DatabaseConnection` class to connect to databases. By default, `default_db` is used, but you can specify a different pool:

```php
// Use default pool (default_db)
$db = new DatabaseConnection();

// Use specific pool (analytics_db)
$db = new DatabaseConnection("analytics_db");
```

#### Creating a Model

Create a file in `app/dll/`:

```php
<?php
// app/dll/UserModel.php

class UserModel {
    
    private $db;
    
    public function __construct() {
        // Connect to default database
        $this->db = new DatabaseConnection();
    }
    
    /**
     * Fetch user by ID
     */
    public function getUserById($id) {
        return $this->db->get()->Row(
            "SELECT * FROM users WHERE id = %d", 
            $id
        );
    }
    
    /**
     * Fetch all users
     */
    public function getAllUsers() {
        return $this->db->get()->Rows(
            "SELECT * FROM users ORDER BY created_at DESC"
        );
    }
    
    /**
     * Complex query with subquery
     */
    public function getUserPosts($userId) {
        return $this->db->get()->Rows(
            "SELECT *, 
                    (SELECT COUNT(*) FROM post_likes WHERE postid = posts.id) AS like_count  
             FROM posts 
             WHERE userid = %d 
             LIMIT 0, 15", 
            $userId
        );
    }
    
    /**
     * Create new user
     */
    public function createUser($name, $email) {
        return $this->db->insertRow(
            "INSERT INTO users (name, email) VALUES ('%s', '%s')",
            $name,
            $email
        );
    }
}
```

#### Using Different Database Pools

```php
<?php
// app/dll/AnalyticsModel.php

class AnalyticsModel {
    
    private $db;
    
    public function __construct() {
        // Use analytics_db instead of default
        $this->db = new DatabaseConnection("analytics_db");
    }
    
    public function getStatistics() {
        return $this->db->get()->Rows(
            "SELECT * FROM statistics WHERE date >= '%s'",
            date('Y-m-d', strtotime('-30 days'))
        );
    }
}
```

#### Using Models in Controllers

```php
<?php

class UserController extends IController {
    
    /**
     * @router user/list -> listUsers
     */
    function listUsers() {
        $userModel = new UserModel();
        $users = $userModel->getAllUsers();
        
        $this->setData('users', $users)->load("user/list.php");
    }
}
```

---

### Helper Classes

Sandstorm includes several helper classes for common tasks:

#### Request - Handle POST/GET Data

```php
<?php

class BlogController extends IController {
    
    /**
     * @router blog/create -> create
     */
    function create() {
        // Fetch POST data
        $title = Request::post('title');
        $content = Request::post('content');
        
        // Fetch GET parameter
        $category = Request::get('category');
        
        if ($title && $content) {
            $blogModel = new BlogModel();
            $blogModel->createPost($title, $content, $category);
        }
        
        $this->load("blog/create.php");
    }
}
```

#### Wall - Handle User Feeds

```php
<?php

class UserController extends IController {
    
    /**
     * @router user/{string:username} -> profile
     */
    function profile() {
        $username = $this->getPartUrl(1);
        
        $db = new DatabaseConnection();
        
        // Fetch user posts with likes
        $posts = $db->get()->Rows(
            "SELECT *, 
                    (SELECT COUNT(*) FROM wzup_check WHERE wzupid = wzup.id) AS hit_count  
             FROM wzup 
             WHERE userid = %d 
             LIMIT 0, 15", 
            $userId
        );
        
        // Create wall feed
        $wall = new Wall();
        $wall->setPosts($posts);
        
        $this->setData(
            'username', $username,
            'wall', $wall
        )->load("user/profile.php");
    }
}
```

#### Record (ORM) - Simple Database Management

Sandstorm has a built-in ORM class called `Record` for simple CRUD operations:

```php
<?php

class ForumController extends IController {
    
    /**
     * @router forum/footer/save -> saveFooter
     */
    function saveFooter() {
        // Fetch POST data
        $footerText = Request::post('foot');
        
        // Create new post in forumfooters table
        $foot = new Record('forumfooters');
        $foot->define('foot', strip_tags($footerText));
        $foot->save();
        
        $this->callback(['success' => true]);
    }
    
    /**
     * @router forum/post/update -> updatePost
     */
    function updatePost() {
        $postId = Request::post('id');
        $title = Request::post('title');
        $content = Request::post('content');
        
        // Update existing post
        $post = new Record('forum_posts');
        $post->load($postId);  // Load existing row
        $post->define('title', $title);
        $post->define('content', $content);
        $post->define('updated_at', date('Y-m-d H:i:s'));
        $post->save();
        
        $this->callback(['success' => true]);
    }
}
```

**Record Methods:**

```php
// Create new post
$record = new Record('table_name');
$record->define('column', 'value');
$record->save();  // INSERT

// Load and update
$record = new Record('table_name');
$record->load($id);  // SELECT WHERE id = $id
$record->define('column', 'new_value');
$record->save();  // UPDATE

// Delete
$record = new Record('table_name');
$record->load($id);
$record->delete();  // DELETE
```

#### Database Methods

Sandstorm supports several database methods via `DatabaseConnection`:

```php
// Fetch one row
$user = $db->get()->Row("SELECT * FROM users WHERE id = %d", $id);

// Fetch multiple rows
$users = $db->get()->Rows("SELECT * FROM users WHERE active = %d", 1);

// Insert data
$this->db->insertRow("INSERT INTO logs (user_id, message) VALUES (%d, '%s')", $userId, $message);
```

**Formatting:**
- `%d` - Integer (number)
- `%s` - String (text)
- `%f` - Float (decimal)

---

## 💡 Examples

### Example 1: Blog Application

#### Controller: `site/blog/Blog.class.php`

```php
<?php

class BlogController extends IController {
    
    /**
     * List all blog posts
     * @router blog -> index
     */
    function index() {
        $blogModel = new BlogModel();
        $posts = $blogModel->getAllPosts();
        
        $this->setData('posts', $posts)->load("blog/index.php");
    }
    
    /**
     * Show individual blog post
     * @router blog/{string:slug} -> show
     */
    function show() {
        $slug = $this->getPartUrl(1);
        
        $blogModel = new BlogModel();
        $post = $blogModel->getPostBySlug($slug);
        
        $this->setData('post', $post)->load("blog/show.php");
    }
}
```

#### Model: `app/dll/BlogModel.php`

```php
<?php

class BlogModel {
    
    private $db;
    
    public function __construct() {
        $this->db = new DatabaseConnection();
    }
    
    public function getAllPosts() {
        return $this->db->get()->Rows(
            "SELECT * FROM posts ORDER BY created_at DESC"
        );
    }
    
    public function getPostBySlug($slug) {
        return $this->db->get()->Row(
            "SELECT * FROM posts WHERE slug = '%s'",
            $slug
        );
    }
    
    public function createPost($title, $content, $slug) {
        return $this->db->insertRow(
            "INSERT INTO posts (title, content, slug, created_at) VALUES ('%s', '%s', '%s', NOW())",
            $title,
            $content,
            $slug
        );
    }
}
```

#### View: `site/blog/index.php`

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Blog</title>
</head>
<body>
    <h1>Blog Posts</h1>
    
    <?php foreach($posts as $post): ?>
        <article>
            <h2>
                <a href="/blog/<?php echo $post['slug']; ?>">
                    <?php echo htmlspecialchars($post['title']); ?>
                </a>
            </h2>
            <p><?php echo htmlspecialchars($post['excerpt']); ?></p>
            <time><?php echo $post['created_at']; ?></time>
        </article>
    <?php endforeach; ?>
</body>
</html>
```

### Example 2: API Endpoint with JSON Callback

API endpoints can use `$this->callback()` to return JSON data:

```php
<?php

class ApiController extends IController {
    
    /**
     * Fetch user data as JSON
     * @router api/users/{number(1-11):id} -> getUser
     */
    function getUser() {
        // URL: /api/users/123
        // Position: 0=api, 1=users, 2=123
        $id = $this->getPartUrl(2);
        
        $userModel = new UserModel();
        $user = $userModel->getUserById($id);
        
        // Return JSON data
        $this->callback($user);
    }
    
    /**
     * Fetch user posts
     * @router api/users/{number:userId}/posts -> getUserPosts
     */
    function getUserPosts() {
        // URL: /api/users/123/posts
        // Position: 0=api, 1=users, 2=123, 3=posts
        $userId = $this->getPartUrl(2);
        
        $userModel = new UserModel();
        $posts = $userModel->getUserPosts($userId);
        
        // Callback automatically returns JSON with correct headers
        $this->callback([
            'success' => true,
            'data' => $posts,
            'count' => count($posts)
        ]);
    }
}
```

**Usage:**
- `GET /api/users/123` → Returns JSON with user data
- `GET /api/users/123/posts` → Returns JSON with user's posts

---

## ⚙️ Configuration

### Database Configuration

#### Default Database Pool: `app/config/mysql/default.db.php`

```php
<?php
/**
 * MySQL configuration default
 * 
 * @file default.db.php
 * @author Marcus Larsson
 * @version 2013.4.1
 */

class default_db {
    public $user = "root";
    public $name = "database";
    public $password = "password";
    public $hostname = "localhost";
    public $driver = "mysql";
    public $type = "as:text";
    public $charset = "utf8mb4";
    public $logpath = "";
}
```

**Parameters:**
- `user` - Database user
- `name` - Database name
- `password` - Password
- `hostname` - Host (usually localhost)
- `driver` - Database driver (mysql)
- `type` - Data format returned from queries:
  - `as:text` - Array with text/strings
  - `as:number` - Array with numbers/integers
  - `as:both` - Array with both text and numbers (mixed)
  - `as:obj` - Array with objects (properties)
- `charset` - Character encoding (utf8mb4 recommended)
- `logpath` - Path for log files

**Data format examples:**

```php
// type = "as:text"
// Returns: array with strings
[
    ['id' => '123', 'name' => 'John', 'age' => '25'],
    ['id' => '456', 'name' => 'Jane', 'age' => '30']
]

// type = "as:number"  
// Returns: array with numbers
[
    ['id' => 123, 'name' => 0, 'age' => 25],
    ['id' => 456, 'name' => 0, 'age' => 30]
]

// type = "as:both"
// Returns: array with both text and numbers (mixed)
[
    ['id' => 123, 'name' => 'John', 'age' => 25],
    ['id' => 456, 'name' => 'Jane', 'age' => 30]
]

// type = "as:obj"
// Returns: array with objects
[
    stdClass Object ( [id] => 123, [name] => 'John', [age] => 25 ),
    stdClass Object ( [id] => 456, [name] => 'Jane', [age] => 30 )
]
```

#### Creating Multiple Database Pools

You can have multiple database pools by copying and renaming the configuration file:

```bash
# Copy the default pool
cp app/config/mysql/default.db.php app/config/mysql/analytics.db.php
```

Change the class name in the new file to match the filename:

```php
<?php
// File: app/config/mysql/analytics.db.php

class analytics_db {
    public $user = "analytics_user";
    public $name = "analytics_database";
    public $password = "secure_password";
    public $hostname = "localhost";
    public $driver = "mysql";
    public $type = "as:text";
    public $charset = "utf8mb4";
    public $logpath = "";
}
```

Use in your code by referencing the pool name when connecting to the database.

#### Using Different Data Formats (type)

Depending on which `type` you set in the database configuration, you get different data formats:

**Example with `as:text` (strings):**
```php
// Configuration: $type = "as:text"
$db = new DatabaseConnection();
$users = $db->get()->Rows("SELECT id, name, age FROM users");

// Result:
// [['id' => '1', 'name' => 'John', 'age' => '25']]
// Everything is strings - good for direct output
echo $users[0]['age'];  // "25" (string)
```

**Example with `as:number` (numbers):**
```php
// Configuration: $type = "as:number"
$db = new DatabaseConnection();
$users = $db->get()->Rows("SELECT id, name, age FROM users");

// Result:
// [['id' => 1, 'name' => 0, 'age' => 25]]
// Numbers become integers, text becomes 0 - good for calculations
$totalAge = $users[0]['age'] + 5;  // 30 (integer)
```

**Example with `as:both` (mixed - recommended):**
```php
// Configuration: $type = "as:both"
$db = new DatabaseConnection();
$users = $db->get()->Rows("SELECT id, name, age FROM users");

// Result:
// [['id' => 1, 'name' => 'John', 'age' => 25]]
// Numbers become integers, text remains text - best of both
echo $users[0]['name'];  // "John" (string)
$totalAge = $users[0]['age'] + 5;  // 30 (integer)
```

**Example with `as:obj` (objects):**
```php
// Configuration: $type = "as:obj"
$db = new DatabaseConnection();
$users = $db->get()->Rows("SELECT id, name, age FROM users");

// Result: Array of stdClass objects
// [stdClass Object ( [id] => 1, [name] => 'John', [age] => 25 )]
// Access via object syntax
echo $users[0]->name;  // "John"
echo $users[0]->age;   // 25
```

### .htaccess Configuration

Sandstorm automatically generates `.htaccess` on first run. The file contains:

#### Basic Settings

```apache
# Enable URL Rewriting
RewriteEngine On

# HTTPS and WWW redirect
RewriteCond %{HTTP_HOST} !^www\. [NC]
RewriteRule ^ https://www.%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Protect app, site, and upload folders
RewriteRule ^(?:app|site|upload)\b.* index.php/$0 [L]

# Allow existing files to be displayed directly
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Send all requests to index.php
RewriteBase /  
RewriteRule ^(.*)$ index.php?/$1 [L]
```

#### Automatic Router Generation

Sandstorm reads the `@router` comments in your controllers and automatically generates corresponding routes in `.htaccess`.

**Example controller:**

```php
<?php

class DefaultController extends IController {
    
    /**
     * Start View
     *
     * @router / -> index
     */
    function index() {
        $this->load("default/start.php");
    }
}
```

**Automatically generates in .htaccess:**

```apache
SetEnvIfNoCase Request_URI "^[/]{0,1}$" HTTP_CONTROLLER=default/default.class.php|default|index|
```

**More examples:**

```php
/**
 * @router user/{string:username} -> profile
 */
```
↓ Generates:
```apache
SetEnvIfNoCase Request_URI "^/user/([A-Za-z-0-9_]{2,20})[/]{0,1}$" HTTP_CONTROLLER=user/user.class.php|user|profile|
```

```php
/**
 * @router blog/read/{string:slug} -> read
 */
```
↓ Generates:
```apache
SetEnvIfNoCase Request_URI "^/blog/read/([A-Za-z-0-9_]+)[/]{0,1}$" HTTP_CONTROLLER=blog/blog.class.php|blog|read|
```

**Important**: The first time you visit a URL with a new route, Sandstorm automatically creates the routing rule in the `.htaccess` file.

#### Environment Variables

Sandstorm uses environment variables for configuration:

```apache
# Paths
RewriteRule ^ - [E=HTTP_path_config:%{DOCUMENT_ROOT}/app/config/]
RewriteRule ^ - [E=HTTP_path_site:%{DOCUMENT_ROOT}/site/]
RewriteRule ^ - [E=HTTP_path_dll:%{DOCUMENT_ROOT}/app/Dll/]

# Settings
SetEnv HTTP_mysql_log "false"        # MySQL logging
SetEnv HTTP_errmode_silent "false"   # Show error messages
SetEnv HTTP_devmode "true"           # Development mode
SetEnv HTTP_language "en_US"         # Default language
```

**Tip**: Change `HTTP_devmode` to `"false"` in production environment.

---

## 🔧 Development Mode

Sandstorm has a special development mode that is activated automatically the first time you run the application (controlled by `HTTP_devmode` in `.htaccess`).

### Features in Development Mode:

- ✅ **Auto-generation of routes**: Reads `@router` comments and creates rules in `.htaccess`
- ✅ **Auto-generation of views**: View files are created with basic HTML structure
- ✅ **Error messages**: Detailed error messages are displayed
- ✅ **Blank canvas**: Empty pages generated for rapid development

### How it Works:

```php
<?php

class UserController extends IController {
    
    /**
     * @router user/{string:username} -> profile
     */
    function profile() {
        $username = $this->getParam('username');
        $this->setData('username', $username)->load("user/profile.php");
    }
}
```

**When you visit `/user/john` for the first time:**

1. Sandstorm reads `@router user/{string:username} -> profile`
2. Generates in `.htaccess`:
   ```apache
   SetEnvIfNoCase Request_URI "^/user/([A-Za-z-0-9_]{2,20})[/]{0,1}$" HTTP_CONTROLLER=user/user.class.php|user|profile|
   ```
3. Creates `site/user/profile.php` with basic HTML if it doesn't exist
4. Displays the page with `$username` available in the view

### Development Tips:

1. **Write controller first**: Define routes and methods
2. **Visit the URL**: Sandstorm automatically creates what's needed
3. **Fill in content**: Customize the generated view file
4. **Test and iterate**: Update code and test directly

---

## 📜 License

Sandstorm MVC Framework is licensed under the **GNU Lesser General Public License v3.0 (LGPL-3.0)**.

### What Does This Mean?

- ✅ **Commercial use**: You can use Sandstorm in commercial projects
- ✅ **Modification**: You can change and adapt the framework
- ✅ **Distribution**: You can distribute Sandstorm further
- ✅ **Patent use**: Express patent license
- ⚠️ **Same license**: Changes to Sandstorm code must be licensed under LGPL-3.0
- ⚠️ **State changes**: Changes must be documented
- ⚠️ **Disclose source**: Source code for changes must be made available

**Important**: Your application that *uses* Sandstorm does NOT need to be open source or under LGPL. Only changes to the framework itself must be shared.

### Full License Text

```
GNU LESSER GENERAL PUBLIC LICENSE
Version 3, 29 June 2007

Copyright (C) 2024 Sandstorm MVC Framework

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program. If not, see <https://www.gnu.org/licenses/>.
```

The full license text is in the `LICENSE` file in this repository.

For more information about LGPL-3.0, visit: https://www.gnu.org/licenses/lgpl-3.0.html

---

## 🤝 Contributing

Contributions to Sandstorm are welcome! If you want to contribute:

1. Fork the project
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Contribution Guidelines

- Follow existing code style
- Comment complex logic
- Update documentation as needed
- Test your changes thoroughly

---

## 📞 Contact

**Project Owner**: Mac3  
**Website**: [mac3.se](https://mac3.se)  
**Project**: [mac3.se/sandstorm](https://mac3.se/sandstorm)  
**Demo**: [mylunar.se](https://mylunar.se)

For support or questions, use the following channels:

- 🐛 **Bugs**: Open an issue on GitHub
- 💡 **Feature requests**: Open an issue with "enhancement" tag

---

## 🙏 Thank You

Thank you for using Sandstorm MVC Framework! We hope it makes your PHP development easier and more enjoyable.

### Resources

- 📚 [Documentation](https://mac3.se/sandstorm)
- 🎓 [Tutorials](https://mac3.se/sandstorm)
- 💬 [Community](https://mac3.se)

---

## 🗺️ Roadmap

Future features and improvements:

- [ no ] CLI tool for generating controllers, models, and views
- [ yes ] Integrated ORM for easier database management
- [ no ] Middleware support
- [ yes ] Session and authentication management
- [ yes ] Template engine (optional)
- [ yes ] REST API generator
- [ no ] Unit testing
- [ no ] Documentation generator

---

## 📊 Status

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)
![License](https://img.shields.io/badge/license-LGPL--3.0-green.svg)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.0-purple.svg)

---

**Built with ❤️ by PHP developers for PHP developers**