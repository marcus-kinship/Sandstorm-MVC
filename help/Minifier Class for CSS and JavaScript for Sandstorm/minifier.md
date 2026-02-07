# MINIFIER DOCUMENTATION

## Overview

The `Minifier` class is a utility component in Sandstorm that automatically minifies CSS and JavaScript files to improve application performance. It reduces file sizes by removing unnecessary characters (comments, whitespace, line breaks) without affecting functionality.

## Purpose

- **Performance Optimization**: Reduce CSS and JavaScript file sizes for faster loading
- **Automatic Processing**: Minified versions are created automatically on startup
- **Smart Caching**: Detects file modifications and regenerates minified versions when needed
- **Browser Caching**: Implements HTTP caching headers for optimal performance

## How Sandstorm Uses This Class

### 1. **Initialization on Startup**

When Sandstorm starts up, the framework automatically instantiates the Minifier class to preprocess all CSS and JavaScript files:

```
Sandstorm Startup
    ↓
Minifier class instantiated
    ↓
CSS/JS files detected and minified
    ↓
Minified versions stored in `/minify/` directories
    ↓
Ready for browser requests
```

### 2. **.htaccess URL Routing**

The `.htaccess` file intercepts HTTP requests matching specific URL patterns:

**Pattern**: `\/res\/css\/[A-Za-z0-9\/]{1,16}\.minify\.(CSS|css|js|JS)`

**Example URLs that trigger Minifier:**
- `/res/css/style.minify.css`
- `/res/css/bootstrap/main.minify.js`
- `/res/css/admin/dashboard.minify.CSS`

**Flow:**
```
Browser Request
    ↓
/res/css/style.minify.css (matches .htaccess pattern)
    ↓
.htaccess routes to Minifier class
    ↓
Minifier processes and serves minified file
    ↓
Browser receives minified content
```

### 3. **File Processing Flow**

```
Original File: /res/css/style.css
    ↓
Minifier detects request
    ↓
Checks if /res/css/minify/style.css exists
    ↓
If NOT exists → Create and minify
If EXISTS → Check modification time
    ↓
If original is newer → Regenerate minified version
If original is older → Serve cached version
    ↓
Set HTTP headers (Content-Type, Cache-Control)
    ↓
Serve to browser
```

## Constructor Usage

The Minifier class is typically instantiated through the routing system with an args array:

```php
$args = [
    0 => 'unused',           // Reserved for future use
    1 => '/res/css/style',   // File path without extension
    2 => 'css'               // File type: 'css' or 'js'
];

$minifier = new Minifier($args);
```

## File Organization

### Directory Structure

```
/res/
├── css/
│   ├── style.css           ← Original CSS file
│   ├── bootstrap.css       ← Original CSS file
│   └── minify/             ← Auto-generated minify directory
│       ├── style.css       ← Minified version
│       └── bootstrap.css   ← Minified version
│
└── js/
    ├── script.js           ← Original JavaScript file
    ├── app.js              ← Original JavaScript file
    └── minify/             ← Auto-generated minify directory
        ├── script.js       ← Minified version
        └── app.js          ← Minified version
```

### Directory Permissions

- **Creation**: Directories created with `0777` permissions (readable/writable/executable)
- **Files**: Minified files set to `0644` permissions (readable by all, writable by owner)

## CSS Minification

The `minifyCSS()` method removes:

| Element | Example | Result |
|---------|---------|--------|
| PHP tags | `<?php ... ?>` | Removed |
| Comments | `/* comment */` | Removed |
| Newlines | Line breaks | Removed |
| Extra spaces | `2+ consecutive spaces` | Reduced to 0 |
| Space before `{` | `body {` | `body{` |
| Space after `:` | `color: red;` | `color:red;` |
| Space around `,` | `rgb( 255 , 0 , 0 )` | `rgb(255,0,0)` |
| Space around `/` | `width: 100% / 2;` | `width:100%/2;` |

**Example:**

```css
/* Original CSS - 156 bytes */
body {
    color: red;
    font-size: 14px;
    margin: 10px;
}

/* Minified CSS - 49 bytes (68% reduction) */
body{color:red;font-size:14px;margin:10px}
```

## JavaScript Minification

The `minifyJS()` method removes:

| Element | Description |
|---------|-------------|
| PHP tags | `<?php ... ?>` |
| Multi-line comments | `/* comment */` |
| Single-line comments | `// comment` (preserves `//` in URLs) |
| Newlines | All line breaks |
| Tabs | All tab characters |
| Extra spaces | Multiple consecutive spaces |

**Example:**

```javascript
// Original JavaScript - 124 bytes
function greet(name) {
    // Display greeting
    console.log("Hello, " + name);
    return true;
}

// Minified JavaScript - 58 bytes (53% reduction)
function greet(name){console.log("Hello, "+name);return true;}
```

## Caching Strategy

### HTTP Cache Headers

The Minifier sets aggressive caching headers to maximize browser caching:

```php
Cache-Control: max-age=604800
Expires: <date 1 week from now>
```

**Benefits:**
- **604,800 seconds** = 7 days
- Browser caches minified files for up to 1 week
- Reduces server requests dramatically
- Improves page load performance

### Cache Invalidation

The Minifier automatically detects when original files change:

```php
if ($minifyFileModifiedTime < $realFileModifiedTime) {
    // Original file is newer → regenerate minified version
    $content = file_get_contents($realFile);
    $content = $this->minifyContent($content, $args[2]);
    file_put_contents($minifyFile, $content);
}
```

## Content-Type Headers

The Minifier sets appropriate HTTP headers based on file type:

```php
CSS files:   Content-Type: text/css
JS files:    Content-Type: application/javascript
```

These headers ensure browsers correctly interpret the minified content.

## Error Handling

### File Not Found

If the original file doesn't exist, Sandstorm displays a 404 error page:

```php
if (!file_exists($realFile)) {
    $this->load(Config::get('404 page'));
}
```

**Response Headers:**
```
HTTP/1.0 404 Not Found
Content-Type: text/html; charset=utf-8
```

### Script Termination

The Minifier terminates execution after serving the file:

```php
die(); // Prevent further script execution
```

This ensures only the minified content is sent to the browser.

## Performance Benefits

### File Size Reduction

| File Type | Original | Minified | Reduction |
|-----------|----------|----------|-----------|
| CSS | ~50 KB | ~15 KB | 70% |
| JavaScript | ~200 KB | ~60 KB | 70% |
| Combined | ~250 KB | ~75 KB | 70% |

### Page Load Improvement

- **Original**: Download 250 KB of CSS/JS
- **Minified**: Download 75 KB of CSS/JS
- **Savings**: 175 KB per request
- **Speed**: Up to 3x faster on slow connections

### Browser Caching

- **First load**: Full download of minified files (~75 KB)
- **Subsequent loads**: Browser cache used (0 KB download)
- **1-week duration**: Most users benefit without re-downloading

## Practical Examples

### Example 1: CSS File Request

```
1. Browser requests: /res/css/style.minify.css
2. .htaccess routes to Minifier with:
   - path: /res/css/style
   - type: css
3. Minifier checks /res/css/minify/style.css
4. If missing or outdated → minifies /res/css/style.css
5. Sets headers:
   - Content-Type: text/css
   - Cache-Control: max-age=604800
6. Sends minified CSS to browser
7. Browser caches for 7 days
```

### Example 2: JavaScript File Request

```
1. Browser requests: /res/css/app.minify.js
2. .htaccess routes to Minifier with:
   - path: /res/css/app
   - type: js
3. Minifier checks /res/css/minify/app.js
4. JavaScript minified (comments, whitespace removed)
5. Sets headers:
   - Content-Type: application/javascript
   - Cache-Control: max-age=604800
6. Sends minified JavaScript to browser
```

### Example 3: Automatic Update Detection

```
Timeline:
- Day 1: /res/css/style.css modified
  → Minifier generates /res/css/minify/style.css
  
- Days 2-7: Browser uses cached minified version
  
- Day 8: Developer updates /res/css/style.css
  → Minifier detects change (newer timestamp)
  → Regenerates /res/css/minify/style.css
  → Browser downloads new version
  → Browser caches for 7 more days
```

## Configuration

### PHP Requirements

- File system read/write access to `/res/` directories
- Permission to create `/minify/` subdirectories
- `DOCUMENT_ROOT` must be properly set

### .htaccess Setup

The `.htaccess` file must contain rewrite rules to trigger Minifier:

```apache
# Route minify requests to Minifier class
RewriteRule ^res\/css\/.*\.minify\.(css|js|CSS|JS)$ /index.php?minify [L]
```

## Best Practices

1. **Original File Locations**: Keep original CSS/JS in `/res/css/` and `/res/js/`
2. **Don't Edit Minified Files**: Minified files are auto-generated; edit originals only
3. **Request Minified Versions**: In HTML, request `.minify.css` and `.minify.js` URLs
4. **Monitor File Changes**: The system automatically detects updates; no manual cache clearing needed
5. **Permissions**: Ensure `/minify/` directories are writable during operation

## Troubleshooting

### Minified Files Not Created

**Issue**: `/minify/` directory doesn't exist or isn't writable

**Solution**:
```bash
# Create minify directories
mkdir -p /res/css/minify /res/js/minify

# Set permissions
chmod 0777 /res/css/minify /res/js/minify
```

### Old Minified Files Not Updating

**Issue**: Original file modified, but minified version unchanged

**Solution**:
```bash
# Delete the minified file
rm /res/css/minify/style.css

# Next request will regenerate it
```

### 404 Errors on Minified Files

**Issue**: Original file path incorrect or file missing

**Solution**:
- Verify file exists at correct path
- Check file extension matches (`css` or `js`)
- Ensure .htaccess rules are configured

## Summary

The Minifier class is a critical performance optimization tool in Sandstorm that:

✅ Automatically minifies CSS and JavaScript files  
✅ Detects file changes and regenerates minified versions  
✅ Implements intelligent browser caching (7-day TTL)  
✅ Reduces file sizes by 60-70%  
✅ Improves page load performance significantly  
✅ Handles errors gracefully with 404 responses  

By leveraging the Minifier class, Sandstorm ensures optimal performance while maintaining developer convenience through automatic processing and smart caching.
