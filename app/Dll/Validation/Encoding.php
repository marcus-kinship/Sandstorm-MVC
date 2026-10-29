<?php

namespace Dll\Validation;

/**
 * Checking text encodings (input from forms, uploaded files, imports).
 *
 * <code>
 * if (!\Dll\Validation\Encoding::isUtf8($_POST['text'])) { ... }
 * $form->set('text')->rule(_('The text is not UTF-8'), ['utf8' => '']);
 * </code>
 *
 * To change text, use Encoding (Encoding::toUTF8(), fixUTF8(), ...).
 *
 * @category _("Encoding class")
 */
class Encoding
{
    /**
     * Whether the text is valid UTF-8 (overlong forms and surrogates are not).
     *
     * @param string $text
     * @return bool
     */
    public static function isUtf8(string $text): bool
    {
        return mb_check_encoding($text, 'UTF-8');
    }

    /**
     * Whether the text is only 7-bit ASCII.
     *
     * @param string $text
     * @return bool
     */
    public static function isAscii(string $text): bool
    {
        return !preg_match('/[^\x00-\x7f]/', $text);
    }

    /**
     * Whether the text starts with a UTF-8 byte order mark.
     *
     * @param string $text
     * @return bool
     */
    public static function hasBom(string $text): bool
    {
        return str_starts_with($text, "\xef\xbb\xbf");
    }

    /**
     * Whether the text looks like UTF-8 that was turned into UTF-8 twice ("Ã©" for "é").
     *
     * @param string $text
     * @return bool
     */
    public static function isDoubleEncoded(string $text): bool
    {
        return self::isUtf8($text) && (bool) preg_match('/\xc3[\x82-\x83][\xc2-\xc3][\x80-\xbf]/', $text);
    }

    /**
     * Whether the text has control characters that do not belong in normal
     * text (everything below space except tab, newline and carriage return).
     *
     * @param string $text
     * @return bool
     */
    public static function hasControlChars(string $text): bool
    {
        return (bool) preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/', $text);
    }
}
