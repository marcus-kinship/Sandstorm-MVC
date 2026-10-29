<?php

namespace Dll\Minifier;

/**
 * Minify JavaScript, carefully.
 *
 * Removes comments (except /*! ... *\/), collapses spaces, drops blank lines,
 * the spaces around { } ( ) [ ] ; , : = & | ! and line breaks where no
 * statement can end. Strings, template literals (also nested ${ }) and
 * regular expressions are copied as they are, and other line breaks are kept,
 * because code without semicolons depends on them.
 *
 * Loaded by Minifier only for .js files.
 *
 * @category _("Utility class")
 */
class Js
{
    /**
     * @param string $js
     * @return string
     */
    public static function minify(string $js): string
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
}
