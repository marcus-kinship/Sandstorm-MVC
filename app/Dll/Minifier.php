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
 * Minifying is careful on purpose: strings, url(...) and regular
 * expressions are never touched, and JavaScript keeps its line breaks
 * (code without semicolons depends on them).
 *
 * Cache: one week outside devmode; in devmode the browser asks every time,
 * so changes show at once.
 *
 * @category _("Utility class")
 */
class Minifier
{
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
     * Send the minified file, 304 when the browser has it, 404 when there is no original.
     *
     * @param string $path Path from the project folder, without extension
     * @param string $type 'css' or 'js'
     * @return void
     */
    public function serve(string $path, string $type): void
    {
        if (!in_array($type, ['css', 'js'], true) || !preg_match('#^/?res/css/[A-Za-z0-9/]{1,16}$#', $path)) {
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
            $this->build($source, $target, $type);
        }

        clearstatcache(true, $target);
        $etag = sprintf('"%x-%x"', filemtime($target), filesize($target));

        if (!headers_sent()) {
            header('Content-Type: ' . ($type === 'css' ? 'text/css' : 'application/javascript') . '; charset=utf-8');
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
     * Minify CSS.
     *
     * Removes comments (except /*! ... *\/), collapses white space and drops
     * it around { } ; , > ~ and after :, removes the last ; in a block.
     * Strings and url(...) are copied as they are.
     *
     * @param string $css
     * @return string
     */
    public static function css(string $css): string
    {
        $out = '';
        $space = false;
        $length = strlen($css);
        $noSpaceAfter = '{};,>~:(';
        $noSpaceBefore = '{};,>~)!';

        for ($i = 0; $i < $length; $i++) {
            $c = $css[$i];

            if ($c === '/' && ($css[$i + 1] ?? '') === '*') {
                $end = strpos($css, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                if (($css[$i + 2] ?? '') === '!') {
                    $out .= substr($css, $i, $end - $i);
                }
                $i = $end - 1;
                continue;
            }

            if (ctype_space($c)) {
                $space = true;
                continue;
            }

            // A space before : only matters in a selector (a :hover), not in a declaration (color : red)
            $declarationColon = false;
            if ($c === ':') {
                $rest = strcspn($css, '{;}', $i);
                $declarationColon = $i + $rest < $length && $css[$i + $rest] !== '{';
            }

            if ($space && $out !== '' && !$declarationColon && !str_ends_with($out, '*/')
                && !str_contains($noSpaceAfter, substr($out, -1)) && !str_contains($noSpaceBefore, $c)) {
                $out .= ' ';
            }
            $space = false;

            if ($c === '"' || $c === "'") {
                $end = self::stringEnd($css, $i, $c);
                $out .= substr($css, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            if (($c === 'u' || $c === 'U') && strncasecmp(substr($css, $i, 4), 'url(', 4) === 0) {
                $end = strpos($css, ')', $i);
                $end = $end === false ? $length - 1 : $end;
                $out .= substr($css, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            if ($c === '}' && str_ends_with($out, ';')) {
                $out = substr($out, 0, -1);
            }

            $out .= $c;
        }

        return trim($out);
    }

    /**
     * Minify JavaScript, carefully.
     *
     * Removes comments (except /*! ... *\/), collapses spaces, drops blank
     * lines and the spaces around { } ( ) [ ] ; , : = & | ! and line breaks where
     * no statement can end. Strings, template literals and regular
     * expressions are copied as they are, and other line breaks are kept.
     *
     * @param string $js
     * @return string
     */
    public static function js(string $js): string
    {
        $out = '';
        $space = false;
        $newline = false;
        $length = strlen($js);
        $templates = [];          // brace depth for each open ${ ... } in a template literal
        // No space needed next to these; + - / < > are left alone (a + +b, a / /re/, a < !--b)
        $tight = '{}()[];,:=&|!';

        for ($i = 0; $i < $length; $i++) {
            $c = $js[$i];
            $next = $js[$i + 1] ?? '';

            // Inside ${ ... } of a template literal: the } that closes it goes back to the template
            if ($templates !== [] && $c === '}' && end($templates) === 0) {
                array_pop($templates);
                $out .= '}';
                $i = self::templateEnd($js, $i + 1, $out, $templates);
                continue;
            }
            if ($templates !== [] && ($c === '{' || $c === '}')) {
                $templates[array_key_last($templates)] += $c === '{' ? 1 : -1;
            }

            if ($c === '/' && $next === '/') {
                $end = strpos($js, "\n", $i);
                $i = ($end === false ? $length : $end) - 1;
                continue;
            }
            if ($c === '/' && $next === '*') {
                $end = strpos($js, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                if (($js[$i + 2] ?? '') === '!') {
                    $out .= substr($js, $i, $end - $i) . "\n";
                }
                $i = $end - 1;
                $space = true;
                continue;
            }

            if ($c === "\n" || $c === "\r") {
                $newline = true;
                continue;
            }
            if (ctype_space($c)) {
                $space = true;
                continue;
            }

            $last = substr(rtrim($out, ' '), -1);
            if ($newline && $out !== '') {
                // A line break only matters where a statement could end
                if (str_contains('{;,([:=', $last) || str_contains('}),;.]', $c)) {
                    $space = false;
                } else {
                    $out = rtrim($out, ' ') . "\n";
                    $space = false;
                }
            }
            if ($space && $out !== '' && !str_ends_with($out, "\n") && !str_contains($tight, $last) && !str_contains($tight, $c)) {
                $out .= ' ';
            }
            $space = $newline = false;

            if ($c === '"' || $c === "'") {
                $end = self::stringEnd($js, $i, $c);
                $out .= substr($js, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            if ($c === '`') {
                $out .= '`';
                $i = self::templateEnd($js, $i + 1, $out, $templates);
                continue;
            }

            if ($c === '/' && self::regexCanStart($out)) {
                $end = self::regexEnd($js, $i);
                $out .= substr($js, $i, $end - $i + 1);
                $i = $end;
                continue;
            }

            $out .= $c;
        }

        return trim($out);
    }

    /**
     * Index of the quote that ends the string starting at $start.
     */
    private static function stringEnd(string $code, int $start, string $quote): int
    {
        $length = strlen($code);
        for ($i = $start + 1; $i < $length; $i++) {
            if ($code[$i] === '\\') {
                $i++;
            } elseif ($code[$i] === $quote) {
                return $i;
            }
        }
        return $length - 1;
    }

    /**
     * Copy a template literal from $start up to its closing ` or the next ${.
     *
     * @return int Index of the last character copied
     */
    private static function templateEnd(string $code, int $start, string &$out, array &$templates): int
    {
        $length = strlen($code);
        for ($i = $start; $i < $length; $i++) {
            $c = $code[$i];
            if ($c === '\\') {
                $out .= $c . ($code[$i + 1] ?? '');
                $i++;
                continue;
            }
            $out .= $c;
            if ($c === '`') {
                return $i;
            }
            if ($c === '$' && ($code[$i + 1] ?? '') === '{') {
                $out .= '{';
                $templates[] = 0;
                return $i + 1;
            }
        }
        return $length - 1;
    }

    /**
     * Whether a / here starts a regular expression (and not a division).
     */
    private static function regexCanStart(string $out): bool
    {
        $before = rtrim($out);
        if ($before === '') {
            return true;
        }
        if (str_contains('(,=:[!&|?{};+-*%<>~^', substr($before, -1))) {
            return true;
        }
        return (bool) preg_match('/(?:^|[^\w$])(?:return|typeof|instanceof|in|of|new|delete|void|throw|case|do|else|yield|await)$/', $before);
    }

    /**
     * Index of the last character of the regular expression starting at $start (its flags included).
     */
    private static function regexEnd(string $code, int $start): int
    {
        $length = strlen($code);
        $class = false;
        for ($i = $start + 1; $i < $length; $i++) {
            $c = $code[$i];
            if ($c === '\\') {
                $i++;
            } elseif ($c === '[') {
                $class = true;
            } elseif ($c === ']') {
                $class = false;
            } elseif ($c === "\n") {
                return $i - 1;
            } elseif ($c === '/' && !$class) {
                while ($i + 1 < $length && ctype_alpha($code[$i + 1])) {
                    $i++;
                }
                return $i;
            }
        }
        return $length - 1;
    }

    /**
     * Minify, then write the copy atomically (another request never reads half a file).
     */
    private function build(string $source, string $target, string $type): void
    {
        $content = file_get_contents($source);
        if ($content === false) {
            throw new SystemException(sprintf(_('Could not read %s'), $source));
        }

        $minified = $type === 'css' ? self::css($content) : self::js($content);

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
