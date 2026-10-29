<?php

namespace Dll\Validation;

/**
 * Passwords: hash, verify, check strength and generate.
 *
 * Used by User (login and storing) and Validation (the 'password' rule in forms).
 * In other code: use Dll\Validation\Password;
 *
 * <code>
 * $columns = Password::hash($plain);                 // ['password' => ..., 'algorithm' => 'bcrypt', 'salt' => '']
 * Password::verify($plain, $row['password'], $row['algorithm'], $row['salt']);
 *
 * $errors = Password::check($plain);                 // [] when the password is good enough
 * $temp = Password::generate();                      // 16 random characters
 * </code>
 *
 * New passwords use password_hash() ('bcrypt'). Older 'sha1salt', 'md5salt',
 * 'sha1', 'md5' and 'plain' hashes can still be verified (compared in
 * constant time); needsRehash() tells when to replace them.
 *
 * @category _("Password class")
 */
class Password
{
    /** Algorithm name stored with new passwords */
    public const ALGORITHM = 'bcrypt';

    /** Shortest password check() accepts by default */
    public const MIN_LENGTH = 8;

    /** bcrypt only uses the first 72 bytes */
    public const MAX_BYTES = 72;

    /** Names for password_hash() hashes in the algorithm column */
    private const MODERN = ['bcrypt', 'argon2', 'password_hash'];

    /** A valid hash to verify against when there is no user (same time as a real check) */
    private const DUMMY_HASH = '$2y$12$Vf3TXdSD.mV17vTLqre7V.5gbBYCm1HRXJ/4Dk1EFLS3G5dbSpIY2';

    /** Passwords that are refused because everyone tries them first */
    private const COMMON = [
        'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789', '1234567890',
        'qwertyuiop', 'qwerty123', 'iloveyou', 'sunshine', 'princess', 'football', 'baseball',
        'welcome1', 'admin123', 'abc12345', '11111111', '00000000', 'letmein1', 'losenord', 'lösenord',
    ];

    /**
     * Hash a password for storing.
     *
     * @param string $plain
     * @return array{password: string, algorithm: string, salt: string} Columns for the users table
     */
    public static function hash(string $plain): array
    {
        return ['password' => password_hash($plain, PASSWORD_DEFAULT), 'algorithm' => self::ALGORITHM, 'salt' => ''];
    }

    /**
     * Check a password against a stored hash.
     *
     * @param string $plain     The password the user typed
     * @param string $hash      The stored hash
     * @param string $algorithm 'bcrypt' or an older 'sha1salt', 'md5salt', 'sha1', 'md5', 'plain'
     * @param string $salt      Salt for the older salted algorithms
     * @return bool
     * @throws \InvalidArgumentException For an unknown algorithm
     */
    public static function verify(string $plain, string $hash, string $algorithm = self::ALGORITHM, string $salt = ''): bool
    {
        if (in_array($algorithm, self::MODERN, true)) {
            return password_verify($plain, $hash);
        }

        return match ($algorithm) {
            'sha1salt' => hash_equals($hash, sha1($salt . $plain)),
            'md5salt' => hash_equals($hash, md5($salt . $plain)),
            'sha1' => hash_equals($hash, sha1($plain)),
            'md5' => hash_equals($hash, md5($plain)),
            'plain' => hash_equals($hash, $plain),
            default => throw new \InvalidArgumentException(sprintf(_('Unknown password algorithm: %s'), $algorithm)),
        };
    }

    /**
     * Spend the same time as verify() when there is nothing to check against,
     * so an unknown user cannot be told apart from a wrong password.
     *
     * @param string $plain
     * @return void
     */
    public static function verifyNothing(string $plain): void
    {
        password_verify($plain, self::DUMMY_HASH);
    }

    /**
     * Whether a stored hash should be replaced with a new hash().
     *
     * True for the older algorithms and for password_hash() hashes made with older settings.
     *
     * @param string $hash
     * @param string $algorithm
     * @return bool
     */
    public static function needsRehash(string $hash, string $algorithm = self::ALGORITHM): bool
    {
        return !in_array($algorithm, self::MODERN, true) || password_needs_rehash($hash, PASSWORD_DEFAULT);
    }

    /**
     * What is wrong with a new password ([] when it is good enough).
     *
     * Rules: at least $minLength characters, at most 72 bytes (bcrypt ignores
     * the rest), not only spaces, not a common password, not the user's own
     * name or email; with $requireMix also letters and digits.
     *
     * @param string   $plain
     * @param int      $minLength
     * @param bool     $requireMix Require both letters and digits
     * @param string[] $personal   Things the password must not be (username, email, ...)
     * @return string[] Messages (translated with gettext)
     */
    public static function check(string $plain, int $minLength = self::MIN_LENGTH, bool $requireMix = false, array $personal = []): array
    {
        $errors = [];
        $lower = mb_strtolower($plain);

        if (mb_strlen($plain) < $minLength) {
            $errors[] = sprintf(_('The password must be at least %d characters'), $minLength);
        }
        if (strlen($plain) > self::MAX_BYTES) {
            $errors[] = sprintf(_('The password can be at most %d bytes'), self::MAX_BYTES);
        }
        if (trim($plain) === '') {
            $errors[] = _('The password cannot be only spaces');
        }
        if (in_array($lower, self::COMMON, true)) {
            $errors[] = _('The password is too common');
        }
        if ($requireMix && (!preg_match('/\p{L}/u', $plain) || !preg_match('/\d/', $plain))) {
            $errors[] = _('The password must contain both letters and digits');
        }
        foreach ($personal as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value !== '' && ($lower === $value || $lower === strstr($value, '@', true))) {
                $errors[] = _('The password cannot be your name or email');
                break;
            }
        }

        return $errors;
    }

    /**
     * Whether a new password is good enough (check() without messages).
     *
     * @param string $plain
     * @param int    $minLength
     * @param bool   $requireMix
     * @return bool
     */
    public static function isStrong(string $plain, int $minLength = self::MIN_LENGTH, bool $requireMix = false): bool
    {
        return self::check($plain, $minLength, $requireMix) === [];
    }

    /**
     * A random password (without characters that look alike: 0/O, 1/l/I).
     *
     * @param int $length At least 8
     * @return string
     */
    public static function generate(int $length = 16): string
    {
        $chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $password = '';
        for ($i = 0, $length = max(8, $length); $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }
}
