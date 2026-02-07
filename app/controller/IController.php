<?php

/**
 * Abstract class for rendering templates and handling global data.
 *
 * This class is used to manage templates (usually HTML) and render them
 * as strings. It also provides methods to store and retrieve global data
 * that can be used across views and widgets.
 *
 * Typically used in controllers to generate content ready to be sent
 * to the browser.
 *
 * @file IController.php
 * @author Marcus Larsson
 * @version 2013.4.1
 * @category _("IController class")
 */

abstract class IController
{
    /**
     * Path to store the current template/view
     * @var string
     */
    private $path;

    /**
     * Data array local to the controller instance
     * @var array
     */
    private $data;

    /**
     * Widget path to include in views
     * @var string
     */
    private $widget;

    /**
     * Destructor: Renders all views in the path stack when the object is destroyed.
     *
     * It retrieves all paths from Iproperty, extracts the stored data as
     * variables, and includes the templates. Output buffering is used
     * to ensure clean rendering.
     *
     * @throws SystemException If a view file cannot be loaded.
     */
    function __destruct()
    {
        // Always set the root directory
        $this->setData('start', $_SERVER['DOCUMENT_ROOT']);

        // Get all registered paths and data
        $paths = Iproperty::getpath();
        $data = Iproperty::getdata(); // Always returns array

        if (!empty($paths)) {
            // Extract all data as local variables
            extract($data, EXTR_SKIP);

            // Start output buffering
            ob_start();

            // Checkpoint for view rendering exceptions
            SystemException::checkpoint(["ViewException" => _("In the view.")]);

            // Include all view files
            foreach ($paths as $dir) {
                if (file_exists($dir)) {
                    include $dir;
                } else {
                    ob_end_clean(); // Clear buffer on error
                    throw new SystemException(sprintf(_("Could not load this view: %s"), $dir));
                }
            }

            ob_end_flush(); // Output the buffered content
        }
    }

    /**
     * Set key-value pairs in global Iproperty data.
     *
     * Accepts an arbitrary number of arguments, alternating key-value.
     *
     * @param mixed ...$args Keys and values
     * @return $this Fluent interface for chaining
     * @throws SystemException If an odd number of arguments is provided.
     */
    final function setData(...$args)
    {
        $numArgs = count($args);
        if ($numArgs % 2 !== 0) {
            throw new SystemException(_('Odd number of arguments. Expecting key-value pairs.'));
        }

        for ($i = 0; $i < $numArgs; $i += 2) {
            Iproperty::setdata($args[$i], $args[$i + 1]);
        }

        return $this;
    }

    /**
     * Load a PHP page/template and return its output as a string.
     *
     * Extracts variables from global data and includes the file.
     *
     * @param string $page Path to PHP template
     * @return string Rendered content
     * @throws SystemException If the file does not exist.
     */
    final function embed(string $page)
    {
        $this->widget = $_SERVER['HTTP_path_site'] . $page;
        $data = Iproperty::getdata();

        if (!file_exists($this->widget)) {
            throw new SystemException(sprintf(_('Could not load this view: %s'), $page));
        }

        extract($data, EXTR_SKIP);

        if (!ob_get_level())
            ob_start();
        include $this->widget;
        $content = ob_get_contents();
        if (ob_get_level())
            ob_end_clean();

        return $content;
    }

    /**
     * Call a widget class and method with optional parameters.
     *
     * @param string $widget Widget name (without ".widget.php")
     * @param string $method Method to call in the widget class
     * @param array $options Optional parameters to pass to the method
     * @return mixed Return value of the called method
     * @throws SystemException If the widget or method does not exist.
     */
    final function widget(string $widget, string $method, array $options = [])
    {
        $start = $_SERVER['HTTP_path_widget'];
        $file = $start . $widget . '.widget.php';

        if (!file_exists($file)) {
            throw new SystemException('Could not load this widget: ' . $file);
        }

        include $file;

        $widgetClass = $widget . 'widget';
        if (!class_exists($widgetClass)) {
            throw new SystemException(_('Could not find widget with this name'));
        }

        config::registry($widgetClass, 'widget');

        $instance = new $widgetClass;
        if (!is_callable([$instance, $method])) {
            throw new SystemException("Could not find function with this name: $method");
        }

        return call_user_func_array([$instance, $method], $options);
    }

    /**
     * Load a view file, automatically creating directories and basic HTML
     * if developer mode is enabled.
     *
     * @param string $page Path to the view file
     * @throws SystemException If directories cannot be created
     */
    final function load(string $page)
    {
        if ($page === "") {
            throw new SystemException(sprintf(_('Could not load the view because no address has been specified: %s'), $page));
        }

        $filePath = $_SERVER['HTTP_path_site'] . $page;

        if ($_SERVER['HTTP_devmode'] === "true" && !file_exists($filePath)) {
            $path = explode("/", $page);
            array_pop($path);
            $structure = rtrim($_SERVER['HTTP_path_site'], "/");

            foreach ($path as $dir) {
                $structure .= "/" . $dir;
                if (!is_dir($structure) && !mkdir($structure, 0755, true)) {
                    throw new SystemException(sprintf(_("Failed to create folder: %s"), $structure));
                }
            }

            $extensions = [".php", ".html", ".htm"];
            $extension = '.' . pathinfo($page, PATHINFO_EXTENSION);

            if (in_array($extension, $extensions, true)) {
                file_put_contents($filePath, '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Welcome to the Sandstorm</title>
</head>
<body>
<h1>Hello, World!</h1>
</body>
</html>');
            }
        }

        Iproperty::setpath($filePath);
    }

    /**
     * Get a part of the URL path by index.
     *
     * @param int $slug Index of URL segment
     * @return string URL segment
     */
    public function getPartUrl(int $slug)
    {
        return SiteRouter::getsPartUrl($slug);
    }

    /**
     * Go back to a previous page or a default URL.
     *
     * @param string $default Default URL if no previous page exists
     */
    public function back(string $default = "")
    {
        SiteRouter::back($default);
    }

    /**
     * Check if the visitor is using a mobile device.
     *
     * @return bool True if mobile, false otherwise
     */
    public function nav(): bool
    {
        $nav = new SiteRouter();
        return $nav->nav();
    }

    /**
     * Render data in JSON format, optionally gzipped.
     *
     * @param mixed $data Data to encode as JSON
     */
    final function callback($data)
    {
        header('Content-type:application/json; charset=utf-8');

        if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
            header('Content-Encoding: gzip');
            echo gzencode(json_encode($data), 9); // Max compression
        } else {
            echo json_encode($data);
        }

        return;
    }

    /**
     * Redirect to a URL.
     *
     * @param string $url Destination URL
     * @param bool $bool True for redirect, false to skip
     */
    final function goTo(string $url, bool $bool = true)
    {
        if (!headers_sent() && $bool) {
            header('Location: ' . $url, true, 301);
            exit;
        }
    }
}