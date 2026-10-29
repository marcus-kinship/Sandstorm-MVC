<?php

/**
 * Manages gettext-based translations and locale configuration.
 *
 * Responsible for:
 * - Resolving and validating the path to locale files
 * - Configuring environment variables for gettext (LANG, LANGUAGE, LC_ALL)
 * - Binding the translation domain and codeset
 * - Persisting and retrieving the user's language preference via the session
 *
 * Usage:
 * ```php
 * $lang = new Language('sv_SE');
 * $lang->initialize();
 * ```
 *
 * @file     Language.php
 * @author   Marcus Larsson
 * @category _("Language Management class")
 */
class Language
{
    /**
     * The locale code used for translations (e.g. 'sv_SE', 'en_US').
     *
     * @var string
     */
    private string $locale = "";

    /**
     * The gettext translation domain (e.g. 'messages', 'app').
     *
     * Must match the name of the .mo file inside the locale directory.
     *
     * @var string
     */
    private string $domain = "";

    /**
     * Absolute path to the directory containing locale subdirectories.
     *
     * Expected structure:
     * ```
     * locales/
     *   sv_SE/
     *     LC_MESSAGES/
     *       messages.mo
     * ```
     *
     * @var string
     */
    private string $localeDir = "";

    /**
     * Character encoding used for gettext output (e.g. 'UTF-8').
     *
     * @var string
     */
    private string $codeset = "";

    /**
     * Constructs a Language instance and resolves the locale file path.
     *
     * If no $path is provided, defaults to {DOCUMENT_ROOT}/app/locales.
     * In developer mode, a missing default path throws a SystemException
     * instead of failing silently.
     *
     * @param string $defaultLanguage Locale code to activate (e.g. 'sv_SE').
     * @param string $path            Optional absolute path to locale directory.
     * @param string $domain          Gettext domain name. Defaults to 'messages'.
     * @param string $codeset         Character encoding. Defaults to 'UTF-8'.
     *
     * @throws \InvalidArgumentException If a custom $path is provided but does not exist.
     * @throws SystemException           If the default locale directory is missing in dev mode.
     */
    public function __construct(
        string $defaultLanguage,
        string $path = "",
        string $domain = "messages",
        string $codeset = "UTF-8"
    ) {
        $this->domain = $domain;
        $this->locale = $defaultLanguage;
        $this->codeset = $codeset;

        if ($path === "") {
            $defpath = $_SERVER["DOCUMENT_ROOT"] . "/app/locales";

            if (file_exists($defpath)) {
                $this->localeDir = $defpath;
            } elseif (($_SERVER['HTTP_devmode'] ?? '') === 'true') {
                throw new SystemException(_('Locale directory not found: ' . $defpath));
            }
        } else {
            if (is_dir($path)) {
                $this->localeDir = $path;
            } else {
                throw new \InvalidArgumentException(_('Invalid locale path provided: ' . $path));
            }
        }
    }

    /**
     * Applies locale settings and activates the gettext translation domain.
     *
     * Sets the LANG, LANGUAGE, and LC_ALL environment variables, calls
     * setlocale(), binds the translation domain to the locale directory,
     * and configures the character encoding.
     *
     * Returns early without throwing if no locale has been set.
     *
     * @return void
     *
     * @throws SystemException If putenv(), setlocale(), or bind_textdomain_codeset() fails.
     */
    public function initialize(): void
    {
        if ($this->locale === "") {
            return;
        }

        foreach (["LANG", "LANGUAGE", "LC_ALL"] as $var) {
            if (!putenv("$var={$this->locale}")) {
                throw new SystemException(_("putenv failed for $var"));
            }
        }

        // Linux uses names like sv_SE.UTF-8, Windows uses sv-SE
        $result = setlocale(LC_ALL, [
            $this->locale . '.' . $this->codeset,
            $this->locale . '.utf8',
            $this->locale,
            str_replace('_', '-', $this->locale),
        ]);

        if ($result === false) {
            throw new SystemException(_(
                'setlocale() failed — the locale may not be installed on this system: '
                . $this->locale . '.' . $this->codeset
            ));
        }

        bindtextdomain($this->domain, $this->localeDir);
        textdomain($this->domain);

        if (!bind_textdomain_codeset($this->domain, $this->codeset)) {
            throw new SystemException(_(
                'bind_textdomain_codeset() failed for domain "' . $this->domain . '"'
            ));
        }
    }

    /**
     * Retrieves the user's language preference from the session.
     *
     * Returns false if no language has been stored yet.
     *
     * @return string|false Locale code (e.g. 'sv_SE'), or false if not set.
     */
    public static function getFromSession(): string|false
    {
        return Session::get('language', false);
    }

    /**
     * Stores the user's language preference in the session.
     *
     * Should be called whenever the user explicitly changes their language,
     * for example via a ?setLang=sv_SE query parameter.
     *
     * @param string $lang Locale code to persist (e.g. 'sv_SE').
     *
     * @return void
     */
    public static function setInSession(string $lang): void
    {
        Session::set('language', $lang);
    }
}