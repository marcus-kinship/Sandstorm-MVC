<?php

/**
 * Serves minified CSS and JavaScript from res/css/.
 *
 * .htaccess sends /res/css/<name>.minify.css (or .js) here; the original is
 * res/css/<name>.css. The minified copy is kept in res/css/minify/ and only
 * made again when the original changes, so a request costs two stat() calls
 * and a readfile(). The browser gets an ETag: when it already has the file
 * it gets "304 Not Modified" and no body.
 *
 * Each file type has its own class (Dll\Minifier\Css, Dll\Minifier\Js), and
 * only the one that is needed is loaded, and only when a copy must be made
 * again. A new type is a new class in app/Dll/Minifier/ plus a line in TYPES
 * (and in the minify route in .htaccess).
 *
 * Cache: one week outside devmode; in devmode the browser asks every time,
 * so changes show at once.
 *
 * @category _("Utility class")
 */
class Minifier
{
    /**
     * File types: extension => [class with static minify(string): string, Content-Type]
     */
    private const TYPES = [
        'css' => [\Dll\Minifier\Css::class, 'text/css'],
        'js' => [\Dll\Minifier\Js::class, 'application/javascript'],
    ];

    /**
     * Serve the file for a route from .htaccess.
     *
     * @param array $args [route name, path without extension ("/res/css/site"), type ("css" or "js")]
     */
    public function __construct($args)
    {
        $this->serve((string) ($args[1] ?? ''), strtolower((string) ($args[2] ?? '')));
    }

    /**
     * Minify CSS (see Dll\Minifier\Css).
     *
     * @param string $css
     * @return string
     */
    public static function css(string $css): string
    {
        return \Dll\Minifier\Css::minify($css);
    }

    /**
     * Minify JavaScript (see Dll\Minifier\Js).
     *
     * @param string $js
     * @return string
     */
    public static function js(string $js): string
    {
        return \Dll\Minifier\Js::minify($js);
    }

    /**
     * Send the minified file, 304 when the browser has it, 404 when there is no original.
     *
     * @param string $path Path from the project folder, without extension
     * @param string $type A key of TYPES, for example 'css'
     * @return void
     */
    public function serve(string $path, string $type): void
    {
        if (!isset(self::TYPES[$type]) || !preg_match('#^/?res/css/[A-Za-z0-9/]{1,16}$#', $path)) {
            $this->notFound();
            return;
        }

        $source = Filesystem::resolve(ltrim($path, '/') . '.' . $type);
        $target = dirname($source) . '/minify/' . basename($source);

        if (!is_file($source)) {
            $this->notFound();
            return;
        }

        if (!is_file($target) || filemtime($target) < filemtime($source)) {
            $this->build($source, $target, self::TYPES[$type][0]);
        }

        clearstatcache(true, $target);
        $etag = sprintf('"%x-%x"', filemtime($target), filesize($target));

        if (!headers_sent()) {
            header('Content-Type: ' . self::TYPES[$type][1] . '; charset=utf-8');
            header('ETag: ' . $etag);
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($target)) . ' GMT');
            header('Vary: Accept-Encoding');
            header(($_SERVER['HTTP_devmode'] ?? '') === 'true' ? 'Cache-Control: no-cache' : 'Cache-Control: public, max-age=604800');
        }

        if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            return;
        }

        readfile($target);
    }

    /**
     * Minify with the type's class, then write the copy atomically
     * (another request never reads half a file).
     *
     * @param string $source
     * @param string $target
     * @param string $class Class with static minify(string): string
     * @return void
     * @throws SystemException When the file cannot be read or written
     */
    private function build(string $source, string $target, string $class): void
    {
        $content = file_get_contents($source);
        if ($content === false) {
            throw new SystemException(sprintf(_('Could not read %s'), $source));
        }

        $minified = $class::minify($content);

        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            throw new SystemException(sprintf(_('Could not create the folder %s'), dirname($target)));
        }

        $temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (file_put_contents($temp, $minified) === false || !rename($temp, $target)) {
            @unlink($temp);
            throw new SystemException(sprintf(_('Could not write %s'), $target));
        }
        @chmod($target, 0644);
    }

    /**
     * Status 404 with the site's 404 page when there is one.
     *
     * @return void
     */
    private function notFound(): void
    {
        http_response_code(404);

        $page = config::get('404 page', true);
        if ($page && is_file($page)) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=utf-8');
            }
            include $page;
        }
    }
}
