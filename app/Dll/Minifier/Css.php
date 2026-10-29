<?php

namespace Dll\Minifier;

/**
 * Minify CSS.
 *
 * Removes comments (except /*! ... *\/), collapses white space and drops it
 * around { } ; , > ~ and after : (and before : in declarations), removes the
 * last ; in a block. Strings and url(...) are copied as they are, and the
 * spaces in calc() and in selectors such as "a :hover" are kept.
 *
 * Loaded by Minifier only for .css files.
 *
 * @category _("Utility class")
 */
class Css
{
    /**
     * @param string $css
     * @return string
     */
    public static function minify(string $css): string
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
}
