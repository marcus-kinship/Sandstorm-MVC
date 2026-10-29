<?php

/**
 * Minifier Class for static assets (CSS and JavaScript) for Sandstorm.
 *
 * This class minifies CSS and JavaScript files to improve performance by reducing file size.
 * It checks if the minified version of the file exists, and if not, it creates one.
 * If the original file is modified, the minified file is recreated. Sandstorm uses this class on startup.
 * The .htaccess file intercepts requests for specific URLs (\/res\/css\/[A-Za-z0-9\/]{1,16})\.minify\.(CSS|css|js|JS)
 * and routes .css and .js files to this class, which serves the minified version.
 *
 * @category __("Utility class")
 */
class Minifier
{

    /**
     * Constructor method
     *
     * Initializes the Minifier object and minifies the specified CSS or JavaScript file.
     *
     * @param array $args An array containing information about the file to minify:
     *                    - $args[0]: (unused in current implementation)
     *                    - $args[1]: File path (relative or absolute)
     *                    - $args[2]: File type ('css' or 'js')
     */
    public function __construct($args)
    {
        // Set the paths for the original and minified files
        $realFile = $_SERVER['DOCUMENT_ROOT'] . $args[1] . '.' . $args[2];
        $directoryPath = dirname($args[1]);
        $minifyFile = $_SERVER['DOCUMENT_ROOT'] . $directoryPath . '/minify/' . basename($args[1]) . '.' . $args[2];

        // Check if the original file exists and get its modified time
        if (file_exists($realFile)) {
            $realFileModifiedTime = filemtime($realFile);

            // Create the "minify" directory if it doesn't exist
            $minifyFolderPath = $_SERVER['DOCUMENT_ROOT'] . $directoryPath . '/minify';
            if (!file_exists($minifyFolderPath)) {
                mkdir($minifyFolderPath, 0777, true); // Create the directory recursively with full permissions
            }


            // Check if the minified file exists
            if (file_exists($minifyFile)) {
                $minifyFileModifiedTime = filemtime($minifyFile);

                // If the minified file is older than the original file
                if ($minifyFileModifiedTime < $realFileModifiedTime) {
                    // Minify the content and write it to the minified file
                    $content = file_get_contents($realFile);
                    $content = $this->minifyContent($content, $args[2]);
                    file_put_contents($minifyFile, $content);
                    chmod($minifyFile, 0644); // Set file permissions
                }

                // Set appropriate content type header
                $this->setContentTypeHeader($args[2]);
                // Set cache headers
                header('Cache-Control: max-age=604800'); // 1 week in seconds
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // 1 week from now
                readfile($minifyFile);
            } else {
                // Create the minified file and write the content
                $content = file_get_contents($realFile);
                $content = $this->minifyContent($content, $args[2]);
                file_put_contents($minifyFile, $content);
                chmod($minifyFile, 0644); // Set file permissions

                // Set appropriate content type header
                $this->setContentTypeHeader($args[2]);
                // Set cache headers
                header('Cache-Control: max-age=604800'); // 1 week in seconds
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 604800) . ' GMT'); // 1 week from now
                readfile($minifyFile);
            }
        } else {
            // If the original file doesn't exist, send a 404 error
            $this->load(Config::get('404 page'));
        }

        die(); // Terminate script execution
    }

    /**
     * Set Content-Type header based on file type
     *
     * @param string $type The file type ('css' or 'js')
     */
    private function setContentTypeHeader($type)
    {
        if ($type === 'css') {
            header('Content-Type: text/css');
        } elseif ($type === 'js') {
            header('Content-Type: application/javascript');
        }
    }

    /**
     * Minify CSS content
     *
     * Minifies the provided CSS content.
     *
     * @param string $css The CSS content to minify
     * @return string The minified CSS content
     */
    private function minifyCSS($css)
    {
        // Remove PHP tags
        $css = preg_replace('/<\?(php)?/i', '', $css);

        // Remove CSS comments
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Remove newlines
        $css = preg_replace('![\n\r]!', '', $css);

        // Remove extra spaces (2 or more)
        $css = preg_replace('![ ]{2,}!', '', $css);

        // Remove space before opening curly brace
        $css = preg_replace('! {!', '{', $css);

        // Remove space after colon
        $css = preg_replace('!: !', ':', $css);

        // Remove space before colon
        $css = preg_replace('! :!', ':', $css);

        // Remove space after comma
        $css = preg_replace('!, !', ',', $css);

        // Remove space before comma
        $css = preg_replace('! ,!', ',', $css);

        // Remove space around slash
        $css = preg_replace('! / !', '/', $css);

        // Remove space around greater-than sign
        $css = preg_replace('! > !', '>', $css);

        // Remove space around plus sign
        $css = preg_replace('! \+ !', '+', $css);

        // Remove space before exclamation mark
        $css = preg_replace('@ !@', '!', $css);

        return $css;
    }

    /**
     * Minify JavaScript content
     *
     * Minifies the provided JavaScript content.
     *
     * @param string $js The JavaScript content to minify
     * @return string The minified JavaScript content
     */
    private function minifyJS($js)
    {
        // Remove PHP tags
        $js = preg_replace('/<\?(php)?/i', '', $js);

        // Remove multi-line comments
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);

        // Remove single-line comments (but preserve URLs with //)
        $js = preg_replace('~//(?![\'"]).*~m', '', $js);

        // Remove newlines and whitespaces
        $js = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $js);

        return $js;
    }

    /**
     * Load a file and set appropriate headers.
     *
     * @param string $file The path to the file to load
     */
    protected function load($file)
    {
        // If no headers have been sent yet, set them
        if (!headers_sent()) {
            header("HTTP/1.0 404 Not Found");
            header('Vary: Accept-Encoding');
            header('Content-Type: text/html; charset=utf-8');
        }

        include_once $file;
    }

    /**
     * Minify Content
     *
     * Minifies the provided content based on its type (CSS or JavaScript).
     *
     * @param string $content The content to minify
     * @param string $type The type of content ('css' or 'js')
     * @return string The minified content
     */
    private function minifyContent($content, $type)
    {
        if ($type === 'css') {
            return $this->minifyCSS($content);
        } elseif ($type === 'js') {
            return $this->minifyJS($content);
        } else {
            return $content;
        }
    }
}
