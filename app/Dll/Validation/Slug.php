<?php

namespace Dll\Validation;

/**
 * Checking slugs (for example one a user types or one in a URL).
 *
 * <code>
 * \Dll\Validation\Slug::isValid('hej-pa-dig');       // true
 * \Dll\Validation\Slug::isValid('Hej på dig');       // false
 * $form->set('slug')->rule(_('Only a-z, 0-9 and -'), ['slug' => 100]);
 * </code>
 *
 * To make a slug, use Slug.
 *
 * @category _("Slug class")
 */
class Slug
{
    /** Slugs that would collide with the site's own pages */
    private const RESERVED = ['admin', 'api', 'app', 'login', 'logout', 'res', 'site', 'upload', 'new', 'edit'];

    /**
     * Whether the text is a valid slug: lower case a–z and 0–9 in groups with
     * one separator between them, nothing at the start or end.
     *
     * @param string $slug
     * @param string $separator
     * @param int    $maxLength 0 = no limit
     * @return bool
     */
    public static function isValid(string $slug, string $separator = '-', int $maxLength = 0): bool
    {
        if ($slug === '' || ($maxLength > 0 && strlen($slug) > $maxLength)) {
            return false;
        }

        $s = preg_quote($separator, '/');
        return (bool) preg_match('/^[a-z0-9]+(?:' . $s . '[a-z0-9]+)*$/', $slug);
    }

    /**
     * Whether the slug is one of the words the site uses itself (admin, api, login, ...).
     *
     * @param string   $slug
     * @param string[] $more Your own reserved words
     * @return bool
     */
    public static function isReserved(string $slug, array $more = []): bool
    {
        return in_array(strtolower($slug), array_merge(self::RESERVED, array_map('strtolower', $more)), true);
    }
}
