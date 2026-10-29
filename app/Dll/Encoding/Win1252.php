<?php

namespace Dll\Encoding;

/**
 * Turning UTF-8 into Windows-1252 (Latin-1 with € and the typographic
 * quotes), for Encoding.
 *
 * @category _("Encoding class")
 */
class Win1252
{
    /** Characters that do not exist in Windows-1252 become the closest ones (iconv) */
    public const TRANSLIT = 'TRANSLIT';

    /** Characters that do not exist in Windows-1252 are left out (iconv) */
    public const IGNORE = 'IGNORE';

    /**
     * Turn UTF-8 (or every string in an array) into Windows-1252.
     *
     * Without an option, characters that do not exist in Windows-1252 become "?".
     *
     * @param mixed  $text
     * @param string $option self::TRANSLIT, self::IGNORE or ''
     * @return mixed
     */
    public static function fromUtf8($text, string $option = '')
    {
        if (is_array($text)) {
            return array_map(fn($t) => self::fromUtf8($t, $option), $text);
        }
        if (!is_string($text)) {
            return $text;
        }

        $text = Utf8::toUtf8($text);

        if ($option !== '' && function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//' . $option, $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
    }
}