<?php

use Dll\Encoding\Utf8;
use Dll\Encoding\Win1252;

/**
 * Text encodings: make text clean UTF-8, or turn it into Windows-1252/Latin-1.
 *
 * <code>
 * $clean = Encoding::toUTF8($mixed);         // also mixed UTF-8 and Windows-1252
 * $fixed = Encoding::fixUTF8('FÃ©dÃ©ration'); // "Fédération"
 * $latin = Encoding::toLatin1('Café €');      // Windows-1252 bytes
 * \Dll\Validation\Encoding::isUtf8($input);   // checking: see Dll\Validation\Encoding
 * </code>
 *
 * The work is done in Dll\Encoding\Utf8 and Dll\Encoding\Win1252, which are
 * only loaded when they are used. Text that is already valid UTF-8 is
 * returned at once by toUTF8().
 *
 * @category _("Encoding class")
 */
class Encoding
{
    const ICONV_TRANSLIT = Win1252::TRANSLIT;
    const ICONV_IGNORE = Win1252::IGNORE;
    const WITHOUT_ICONV = "";

    /**
     * Turn text (or every string in an array) into UTF-8.
     *
     * @param mixed $text
     * @return mixed
     */
    public static function toUTF8($text)
    {
        return Utf8::toUtf8($text);
    }

    /**
     * Turn UTF-8 text into Windows-1252.
     *
     * @param mixed  $text
     * @param string $option ICONV_TRANSLIT, ICONV_IGNORE or WITHOUT_ICONV
     * @return mixed
     */
    public static function toWin1252($text, $option = self::WITHOUT_ICONV)
    {
        return Win1252::fromUtf8($text, (string) $option);
    }

    /**
     * Same as toWin1252().
     *
     * @return mixed
     */
    public static function toISO8859($text, $option = self::WITHOUT_ICONV)
    {
        return self::toWin1252($text, $option);
    }

    /**
     * Same as toWin1252().
     *
     * @return mixed
     */
    public static function toLatin1($text, $option = self::WITHOUT_ICONV)
    {
        return self::toWin1252($text, $option);
    }

    /**
     * Fix text that was turned into UTF-8 more than once ("Ã©" back to "é").
     *
     * @param mixed  $text
     * @param string $option
     * @return mixed
     */
    public static function fixUTF8($text, $option = self::WITHOUT_ICONV)
    {
        return Utf8::fix($text, (string) $option);
    }

    /**
     * Fix Windows-1252 characters that were read as Latin-1.
     *
     * @param string $text
     * @return string
     */
    public static function UTF8FixWin1252Chars($text)
    {
        return Utf8::fixWin1252Chars((string) $text);
    }

    /**
     * Remove a UTF-8 byte order mark at the start.
     *
     * @param string $str
     * @return string
     */
    public static function removeBOM($str = "")
    {
        return Utf8::removeBom((string) $str);
    }

    /**
     * 'UTF-8' or 'ISO-8859-1' for a label such as 'utf8', 'Latin1' or 'Windows-1252'.
     *
     * @param string $encodingLabel
     * @return string
     */
    public static function normalizeEncoding($encodingLabel)
    {
        $label = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $encodingLabel));

        return in_array($label, ['ISO88591', 'ISO8859', 'ISO', 'LATIN1', 'LATIN', 'WIN1252', 'WINDOWS1252'], true) ? 'ISO-8859-1' : 'UTF-8';
    }

    /**
     * Turn text into the encoding a label names.
     *
     * @param string $encodingLabel
     * @param mixed  $text
     * @return mixed
     */
    public static function encode($encodingLabel, $text)
    {
        return self::normalizeEncoding($encodingLabel) === 'ISO-8859-1' ? self::toLatin1($text) : self::toUTF8($text);
    }
}
