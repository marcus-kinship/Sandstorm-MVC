<?php

namespace Dll\Encoding;

/**
 * Turning text into clean UTF-8, for Encoding.
 *
 * toUtf8() also fixes text that mixes UTF-8 with Windows-1252/Latin-1
 * (for example old database rows or files from Windows); text that is
 * already valid UTF-8 is returned at once, without going through it.
 *
 * @category _("Encoding class")
 */
class Utf8
{
    /** @var array<int, string> Windows-1252 bytes 128–159 => their UTF-8 */
    public const FROM_WIN1252 = [
        128 => "\xe2\x82\xac",
        130 => "\xe2\x80\x9a",
        131 => "\xc6\x92",
        132 => "\xe2\x80\x9e",
        133 => "\xe2\x80\xa6",
        134 => "\xe2\x80\xa0",
        135 => "\xe2\x80\xa1",
        136 => "\xcb\x86",
        137 => "\xe2\x80\xb0",
        138 => "\xc5\xa0",
        139 => "\xe2\x80\xb9",
        140 => "\xc5\x92",
        142 => "\xc5\xbd",
        145 => "\xe2\x80\x98",
        146 => "\xe2\x80\x99",
        147 => "\xe2\x80\x9c",
        148 => "\xe2\x80\x9d",
        149 => "\xe2\x80\xa2",
        150 => "\xe2\x80\x93",
        151 => "\xe2\x80\x94",
        152 => "\xcb\x9c",
        153 => "\xe2\x84\xa2",
        154 => "\xc5\xa1",
        155 => "\xe2\x80\xba",
        156 => "\xc5\x93",
        158 => "\xc5\xbe",
        159 => "\xc5\xb8"
    ];

    /** @var array<string, string> UTF-8 that was made from Windows-1252 as if it were Latin-1 => the right UTF-8 */
    private const BROKEN = [
        "\xc2\x80" => "\xe2\x82\xac",
        "\xc2\x82" => "\xe2\x80\x9a",
        "\xc2\x83" => "\xc6\x92",
        "\xc2\x84" => "\xe2\x80\x9e",
        "\xc2\x85" => "\xe2\x80\xa6",
        "\xc2\x86" => "\xe2\x80\xa0",
        "\xc2\x87" => "\xe2\x80\xa1",
        "\xc2\x88" => "\xcb\x86",
        "\xc2\x89" => "\xe2\x80\xb0",
        "\xc2\x8a" => "\xc5\xa0",
        "\xc2\x8b" => "\xe2\x80\xb9",
        "\xc2\x8c" => "\xc5\x92",
        "\xc2\x8e" => "\xc5\xbd",
        "\xc2\x91" => "\xe2\x80\x98",
        "\xc2\x92" => "\xe2\x80\x99",
        "\xc2\x93" => "\xe2\x80\x9c",
        "\xc2\x94" => "\xe2\x80\x9d",
        "\xc2\x95" => "\xe2\x80\xa2",
        "\xc2\x96" => "\xe2\x80\x93",
        "\xc2\x97" => "\xe2\x80\x94",
        "\xc2\x98" => "\xcb\x9c",
        "\xc2\x99" => "\xe2\x84\xa2",
        "\xc2\x9a" => "\xc5\xa1",
        "\xc2\x9b" => "\xe2\x80\xba",
        "\xc2\x9c" => "\xc5\x93",
        "\xc2\x9e" => "\xc5\xbe",
        "\xc2\x9f" => "\xc5\xb8"
    ];

    /**
     * Turn text (or every string in an array) into UTF-8.
     *
     * @param mixed $text
     * @return mixed
     */
    public static function toUtf8($text)
    {
        if (is_array($text)) {
            return array_map([self::class, 'toUtf8'], $text);
        }
        if (!is_string($text) || mb_check_encoding($text, 'UTF-8')) {
            return $text;
        }

        $max = strlen($text);
        $buf = '';

        for ($i = 0; $i < $max; $i++) {
            $c1 = $text[$i];

            if ($c1 >= "\xc0") {
                $c2 = $i + 1 >= $max ? "\x00" : $text[$i + 1];
                $c3 = $i + 2 >= $max ? "\x00" : $text[$i + 2];
                $c4 = $i + 3 >= $max ? "\x00" : $text[$i + 3];

                if ($c1 >= "\xc0" && $c1 <= "\xdf" && $c2 >= "\x80" && $c2 <= "\xbf") {
                    $buf .= $c1 . $c2;
                    $i++;
                } elseif ($c1 >= "\xe0" && $c1 <= "\xef" && $c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf") {
                    $buf .= $c1 . $c2 . $c3;
                    $i += 2;
                } elseif ($c1 >= "\xf0" && $c1 <= "\xf7" && $c2 >= "\x80" && $c2 <= "\xbf" && $c3 >= "\x80" && $c3 <= "\xbf" && $c4 >= "\x80" && $c4 <= "\xbf") {
                    $buf .= $c1 . $c2 . $c3 . $c4;
                    $i += 3;
                } else {
                    $buf .= self::latin1Char($c1);
                }
            } elseif (($c1 & "\xc0") === "\x80") {
                $buf .= self::FROM_WIN1252[ord($c1)] ?? self::latin1Char($c1);
            } else {
                $buf .= $c1;
            }
        }

        return $buf;
    }

    /**
     * Fix text that was turned into UTF-8 more than once ("Ã¤" back to "ä").
     *
     * @param mixed  $text
     * @param string $option Win1252::TRANSLIT, Win1252::IGNORE or '' (see Win1252::fromUtf8())
     * @return mixed
     */
    public static function fix($text, string $option = '')
    {
        if (is_array($text)) {
            return array_map(fn($t) => self::fix($t, $option), $text);
        }
        if (!is_string($text)) {
            return $text;
        }

        // Undo one layer at a time until nothing changes (at most 10 layers)
        for ($i = 0, $last = null; $i < 10 && $last !== $text; $i++) {
            $last = $text;
            $text = self::toUtf8(Win1252::fromUtf8($text, $option));
        }

        return $text;
    }

    /**
     * Fix Windows-1252 characters that were read as Latin-1 (for example "\xc2\x80" to "€").
     *
     * @param string $text
     * @return string
     */
    public static function fixWin1252Chars(string $text): string
    {
        return strtr($text, self::BROKEN);
    }

    /**
     * Remove a UTF-8 byte order mark at the start.
     *
     * @param string $text
     * @return string
     */
    public static function removeBom(string $text = ''): string
    {
        return str_starts_with($text, "\xef\xbb\xbf") ? substr($text, 3) : $text;
    }

    /**
     * One Latin-1 byte as UTF-8.
     */
    private static function latin1Char(string $c): string
    {
        return (chr(intdiv(ord($c), 64)) | "\xc0") . (($c & "\x3f") | "\x80");
    }
}