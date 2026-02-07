# Controller Documentation

## Table of Contents

- [Overview](#overview)
- [Architecture Philosophy](#architecture-philosophy)
  - [Automatic View Rendering via __destruct()](#automatic-view-rendering-via-__destruct)
- [System Architecture Diagram](#system-architecture-diagram)
- [Core Components](#core-components)
- [Basic Usage](#basic-usage)
  - [Creating a Controller](#creating-a-controller)
  - [Router Mapping](#router-mapping)
- [Methods Reference](#methods-reference)
  - [load()](#loadstring-page)
  - [setData()](#setdataargs)
  - [embed()](#embedstring-page)
  - [widget()](#widgetstring-widget-string-method-array-options--)
  - [callback()](#callbackdata)
  - [goTo()](#gotostring-url-bool-bool--true)
  - [getPartUrl()](#getparturlint-slug)
  - [back()](#backstring-default--)
  - [nav()](#nav)
- [View Files](#view-files)
- [Complete Example](#complete-example)
- [Best Practices](#best-practices)
- [Error Handling](#error-handling)
- [Developer Mode](#developer-mode)
- [Advanced Patterns](#advanced-patterns)
- [Summary](#summary)

---

## Overview

The controller system is built around the abstract `IController` class, which provides a clean and flexible way to manage views, data, and rendering in your PHP application. It uses a unique destructor-based rendering pattern that keeps your controller code clean and gives developers freedom in how they structure their actions.

## Architecture Philosophy

### Automatic View Rendering via `__destruct()`

A key design decision in this framework is that **views are automatically rendered when the controller object is destroyed** (when the script ends or the object goes out of scope). This approach offers several advantages:

- **Cleaner controller code** - No need to explicitly call `render()` or similar methods at the end of each action
- **Developer freedom** - Focus on business logic without worrying about rendering mechanics
- **Consistent behavior** - All views are rendered automatically in the order they were loaded
- **Less boilerplate** - Eliminates repetitive rendering code across controller actions

Instead of:
```php
function index() {
    $this->setData('title', 'Home');
    $this->load('header.php');
    $this->load('home.php');
    $this->load('footer.php');
    $this->render(); // Traditional approach
}
```

You simply write:
```php
function index() {
    $this->setData('title', 'Home');
    $this->load('header.php');
    $this->load('home.php');
    $this->load('footer.php');
    // Automatic rendering when controller is destroyed
}
```

---

## System Architecture Diagram

The following diagram illustrates how the controller system components work together:

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Request                      │
└────────────────────────────────┬────────────────────────────────┘
                                 │
                                 ▼
                    ┌────────────────────────┐
                    │  .htaccess and start   │
                    │  (Route Dispatcher)    │
                    └───────────┬────────────┘
                                │
                                │ routes to
                                ▼
            ┌───────────────────────────────────────┐
            │      YourController extends           │
            │         IController (abstract)        │
            ├───────────────────────────────────────┤
            │  Methods:                             │
            │  • index(), show(), etc.              │
            │  • load(view)        ─────────┐       │
            │  • setData(key, val) ────┐    │       │
            │  • embed(template)       │    │       │
            │  • widget(name, method)  │    │       │
            │  • callback(json)        │    │       │
            └──────────────┬───────────┘    │       │
                           │                │       │
                           │ uses           │       │
                           ▼                │       │
            ┌──────────────────────────┐    │       │
            │   Iproperty (static)     │    │       │
            ├──────────────────────────┤    │       │
            │ • static $data = []  ◄───┘    │       │
            │ • static $path = []  ◄────────┘       │
            │                              │        │
            │ Methods:                     │        │
            │ • setdata(key, value)        │        │
            │ • getdata() → array          │        │
            │ • setpath(filepath)          │        │
            │ • getpath() → array          │        │
            └──────────────┬───────────────┘        │
                           │                        │
         When controller   │                        │
         is destroyed      │                        │
         (__destruct)      │                        │
                           ▼                        │
            ┌──────────────────────────┐            │
            │  IController::__destruct │            │
            │                          │            │
            │  1. Get all paths from   │            │
            │     Iproperty::getpath() │            │
            │                          │            │
            │  2. Get all data from    │            │
            │     Iproperty::getdata() │            │
            │                          │            │
            │  3. extract($data)       │            │
            │                          │            │
            │  4. include each view    │            │
            │     in order             │            │
            └──────────────┬───────────┘            │
                           │                        │
                           ▼                        │
            ┌──────────────────────────┐            │
            │    View Files (.php)     │            │
            ├──────────────────────────┤            │
            │  Access to all $data     │            │
            │  variables via extract() │            │
            │                          │            │
            │  Can call:               │            │
            │  • $this->embed()   ─────┘            │
            │  • $pageTitle, $posts,                │
            │    etc. (extracted vars)              │
            └──────────────┬────────────┘           │
                           │                        │
                           ▼                        │
            ┌──────────────────────────┐            │
            │   Rendered HTML Output   │            │
            └──────────────────────────┘            │
                                                    │
┌───────────────────────────────────────────────────┘
│  Optional: Widget System
│
│  ┌────────────────────────────┐
│  │  widget(name, method, [])  │
│  └───────────┬────────────────┘
│              │
│              ▼
│  ┌────────────────────────────┐
│  │  WidgetNamewidget.php      │
│  │  (loaded dynamically)      │
│  ├────────────────────────────┤
│  │  class WidgetNamewidget {  │
│  │    function method($opts)  │
│  │    {                       │
│  │      // reusable logic     │
│  │      return $html;         │
│  │    }                       │
│  │  }                         │
│  └───────────┬────────────────┘
│              │
│              ▼
│      Returns value to controller
│      (usually HTML or data)
└──────────────────────────────────
```

### Flow Explanation

1. **Request arrives** → Router dispatches to a controller action
2. **Controller action executes** → Calls `load()`, `setData()`, `widget()`, etc.
3. **Data stored globally** → `Iproperty` holds paths and data in static arrays
4. **Controller destroyed** → When the action finishes, `__destruct()` triggers
5. **Views rendered** → All queued views are included with extracted data
6. **HTML output** → Complete page is sent to browser

This architecture separates concerns cleanly:
- **IController**: Orchestrates the request/response cycle
- **Iproperty**: Global state management (paths & data)
- **Views**: Presentation layer with access to data
- **Widgets**: Reusable components

---

## Core Components

### IController (Abstract Base Class)

All controllers extend `IController`, which provides core functionality for:
- Loading and rendering views
- Managing global and local data
- Embedding templates
- Working with widgets
- Handling redirects and callbacks

### Iproperty (Static Data Manager)

A helper class that stores:
- **Paths** - View file paths to be rendered
- **Data** - Key-value pairs accessible across all views

---

## Basic Usage

### Creating a Controller

```php
<?php

class DefaultController extends IController
{
    /**
     * Homepage action
     * @router / -> index
     */
    function index()
    {
        // Set data available to all views
        $this->setData('pageTitle', 'Welcome');
        $this->setData('userName', 'John Doe');
        
        // Load views to render
        $this->load('layouts/header.php');
        $this->load('pages/home.php');
        $this->load('layouts/footer.php');
        
        // Views render automatically when controller is destroyed
    }
}
```

### Router Mapping

Use the `@router` annotation in method docblocks to map URLs to actions:

```php
/**
 * About page
 * @router /about -> about
 */
function about()
{
    $this->setData('pageTitle', 'About Us');
    $this->load('pages/about.php');
}
```

---

## Methods Reference

### `load(string $page)`

Loads a view file to be rendered. Views are queued and rendered in order when the controller is destroyed.

```php
$this->load('header.php');
$this->load('content/article.php');
$this->load('footer.php');
```

**Developer Mode Feature**: If `$_SERVER['HTTP_devmode'] === "true"` and the view file doesn't exist, it will:
1. Automatically create missing directories
2. Generate a basic HTML template

**Throws**: `SystemException` if the file path is empty or directories cannot be created.

**See also**: 
- [Developer Mode](#developer-mode)
- [Best Practice: Organize Views by Feature](#3-organize-views-by-feature)

---

### `setData(...$args)`

Sets key-value pairs in the global data store. Data is accessible in all views as variables.

**Accepts variable arguments** in key-value pairs:

```php
// Single pair
$this->setData('title', 'My Page');

// Multiple pairs
$this->setData(
    'title', 'My Page',
    'author', 'Jane Smith',
    'date', date('Y-m-d')
);
```

**Returns**: `$this` for method chaining ([see example](#2-use-method-chaining))

```php
$this->setData('title', 'Home')
     ->setData('subtitle', 'Welcome back')
     ->load('home.php');
```

**Throws**: `SystemException` if an odd number of arguments is provided.

**See also**: [Best Practice: Set Data Before Loading Views](#1-set-data-before-loading-views)

---

### `embed(string $page)`

Loads a PHP template and returns its rendered output as a string. Useful for including partial templates within views.

```php
function dashboard()
{
    $sidebarHtml = $this->embed('widgets/sidebar.php');
    $this->setData('sidebar', $sidebarHtml);
    $this->load('layouts/dashboard.php');
}
```

**Returns**: Rendered HTML as a string

**Throws**: `SystemException` if the template file doesn't exist.

**See also**: [Advanced Pattern: Nested Templates with Embed](#nested-templates-with-embed)

---

### `widget(string $widget, string $method, array $options = [])`

Calls a widget class method with optional parameters. Widgets are reusable components stored in the widget directory.

```php
// Call the 'render' method of UserListWidget
$userList = $this->widget('UserList', 'render', ['limit' => 10]);
$this->setData('users', $userList);
```

**Parameters**:
- `$widget` - Widget name (without `.widget.php` extension)
- `$method` - Method name to call
- `$options` - Array of parameters to pass to the method

**Returns**: Return value of the called widget method

**Throws**: `SystemException` if widget file or method doesn't exist.

**See also**: 
- [Best Practice: Use Widgets for Reusable Components](#4-use-widgets-for-reusable-components)
- [Complete Example: Blog Controller](#complete-example)

---

### `callback($data)`

Outputs data as JSON, with optional gzip compression. Useful for AJAX endpoints.

```php
function apiGetUsers()
{
    $users = User::all();
    $this->callback(['success' => true, 'data' => $users]);
    // No views will render after this
}
```

**Features**:
- Sets `Content-Type: application/json`
- Automatically gzips response if client supports it
- Uses maximum compression (level 9)

**See also**: 
- [Best Practice: Handle API Responses Properly](#5-handle-api-responses-properly)
- [Complete Example: Blog search endpoint](#complete-example)

---

### `goTo(string $url, bool $bool = true)`

Redirects to another URL with a 301 status code.

```php
function loginRequired()
{
    if (!$this->userIsLoggedIn()) {
        $this->goTo('/login');
    }
}
```

**Parameters**:
- `$url` - Destination URL
- `$bool` - If `false`, redirect is skipped (useful for conditional logic)

**Note**: Calls `exit` after redirect, preventing further execution.

**See also**: [Best Practice: Validate Before Redirecting](#6-validate-before-redirecting)

---

### `getPartUrl(int $slug)`

Gets a segment of the current URL path by index.

```php
// URL: /products/electronics/laptops
$category = $this->getPartUrl(1);    // "electronics"
$subcategory = $this->getPartUrl(2); // "laptops"
```

---

### `back(string $default = "")`

Redirects to the previous page or a default URL.

```php
function cancelAction()
{
    $this->back('/dashboard');
}
```

---

### `nav()`

Checks if the visitor is using a mobile device.

```php
function index()
{
    if ($this->nav()) {
        $this->load('mobile/home.php');
    } else {
        $this->load('desktop/home.php');
    }
}
```

**Returns**: `bool` - `true` if mobile, `false` otherwise.

**See also**: [Advanced Pattern: Conditional View Loading](#conditional-view-loading)

---

## View Files

Views are standard PHP files with access to all data set via `setData()`.

**Example view file** (`views/pages/home.php`):
```php
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($pageTitle) ?></title>
</head>
<body>
    <h1>Welcome, <?= htmlspecialchars($userName) ?>!</h1>
    <p>Today is <?= date('F j, Y') ?></p>
</body>
</html>
```

**Available variables**: All data set with `setData()` is extracted and available as regular PHP variables.

---

## Complete Example

```php
<?php

class BlogController extends IController
{
    /**
     * List all blog posts
     * @router /blog -> index
     */
    function index()
    {
        // Fetch data (example)
        $posts = BlogPost::all();
        
        // Set data for views
        $this->setData(
            'pageTitle', 'Blog',
            'posts', $posts,
            'totalPosts', count($posts)
        );
        
        // Load views
        $this->load('layouts/header.php');
        $this->load('blog/list.php');
        $this->load('layouts/footer.php');
        
        // Automatic rendering on destruction
    }
    
    /**
     * Show single blog post
     * @router /blog/{id} -> show
     */
    function show()
    {
        $id = $this->getPartUrl(1);
        $post = BlogPost::find($id);
        
        if (!$post) {
            $this->goTo('/404');
        }
        
        // Load sidebar widget
        $recentPosts = $this->widget('RecentPosts', 'get', ['limit' => 5]);
        
        $this->setData(
            'pageTitle', $post->title,
            'post', $post,
            'recentPosts', $recentPosts
        );
        
        $this->load('layouts/header.php');
        $this->load('blog/single.php');
        $this->load('layouts/sidebar.php');
        $this->load('layouts/footer.php');
    }
    
    /**
     * AJAX endpoint for post search
     * @router /api/blog/search -> search
     */
    function search()
    {
        $query = $_GET['q'] ?? '';
        $results = BlogPost::search($query);
        
        // Return JSON, no view rendering
        $this->callback([
            'success' => true,
            'results' => $results
        ]);
    }
}
```

---

## Best Practices

### 1. Set Data Before Loading Views
Always call `setData()` before `load()` to ensure data is available when views render.

```php
// ✓ Good
$this->setData('title', 'Home');
$this->load('home.php');

// ✗ Bad - data may not be available in time
$this->load('home.php');
$this->setData('title', 'Home');
```

### 2. Use Method Chaining
Take advantage of fluent interface for cleaner code:

```php
$this->setData('title', 'Products')
     ->setData('category', 'Electronics')
     ->load('layouts/header.php')
     ->load('products/list.php');
```

### 3. Organize Views by Feature
Structure your view directory logically:

```
views/
  ├── layouts/
  │   ├── header.php
  │   ├── footer.php
  │   └── sidebar.php
  ├── pages/
  │   ├── home.php
  │   └── about.php
  ├── blog/
  │   ├── list.php
  │   └── single.php
  └── widgets/
      ├── recent-posts.php
      └── social-links.php
```

### 4. Use Widgets for Reusable Components
Create widgets for components used across multiple controllers:

```php
// widgets/Navigation.widget.php
class Navigationwidget
{
    function render($options = [])
    {
        $items = MenuItem::all();
        return view('widgets/nav', compact('items'));
    }
}
```

### 5. Handle API Responses Properly
For JSON endpoints, use `callback()` which prevents view rendering:

```php
function apiEndpoint()
{
    $data = $this->processRequest();
    $this->callback($data);
    // No need to load views - callback stops execution
}
```

### 6. Validate Before Redirecting
Check conditions before redirecting to prevent unnecessary processing:

```php
function adminOnly()
{
    if (!$this->isAdmin()) {
        $this->goTo('/forbidden');
        // Code after this won't execute due to exit in goTo()
    }
    
    // Admin-only code here
}
```

---

## Error Handling

The framework throws `SystemException` for various error conditions:

- Missing view files
- Odd number of arguments to `setData()`
- Widget file or method not found
- Empty path passed to `load()`

**Example error handling**:
```php
try {
    $this->load('non-existent.php');
} catch (SystemException $e) {
    error_log($e->getMessage());
    $this->goTo('/error');
}
```

---

## Developer Mode

When `$_SERVER['HTTP_devmode'] === "true"`, the framework provides helpful development features:

- **Auto-creates missing view directories**
- **Generates basic HTML templates** for new views
- Helpful for rapid prototyping

**Note**: Disable developer mode in production environments.

---

## Advanced Patterns

### Master-Detail Layout
```php
function userProfile()
{
    $this->setData('pageTitle', 'User Profile');
    
    $this->load('layouts/master-start.php');
    $this->load('users/profile-sidebar.php');
    $this->load('users/profile-content.php');
    $this->load('layouts/master-end.php');
}
```

### Conditional View Loading
```php
function dashboard()
{
    $this->load('layouts/header.php');
    
    if ($this->nav()) {
        $this->load('dashboard/mobile.php');
    } else {
        $this->load('dashboard/desktop.php');
    }
    
    $this->load('layouts/footer.php');
}
```

### Nested Templates with Embed
```php
function newsletter()
{
    $articles = Article::recent(5);
    $html = '';
    
    foreach ($articles as $article) {
        $this->setData('article', $article);
        $html .= $this->embed('email/article-block.php');
    }
    
    $this->setData('content', $html);
    $this->load('email/newsletter-template.php');
}
```

---

## Quick Reference Card

| Method | Purpose | Returns | Example Use Case |
|--------|---------|---------|------------------|
| [`load()`](#loadstring-page) | Queue view for rendering | void | Load header, content, footer |
| [`setData()`](#setdataargs) | Store data for views | `$this` | Pass variables to templates |
| [`embed()`](#embedstring-page) | Render template to string | string | Include partial in view |
| [`widget()`](#widgetstring-widget-string-method-array-options--) | Call widget component | mixed | Load user list, sidebar |
| [`callback()`](#callbackdata) | Output JSON response | void | AJAX/API endpoints |
| [`goTo()`](#gotostring-url-bool-bool--true) | Redirect to URL | void | Redirect after login |
| [`getPartUrl()`](#getparturlint-slug) | Get URL segment | string | Extract ID from URL |
| [`back()`](#backstring-default--) | Go to previous page | void | Cancel action |
| [`nav()`](#nav) | Check if mobile | bool | Load mobile/desktop view |

---

## Summary

The controller system provides a clean, developer-friendly way to build web applications by:

- **Automatically rendering views** via the destructor pattern
- **Managing global data** accessible across all views
- **Supporting widgets** for reusable components
- **Providing developer mode** for rapid prototyping
- **Enabling flexible patterns** for layouts and templates

The destructor-based rendering keeps your controller actions focused on business logic while ensuring consistent, automatic view rendering without boilerplate code.
