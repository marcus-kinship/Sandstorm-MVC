<?php

use Dll\Validation\Password;

/**
 * Users: log in and out, the current user, passwords.
 *
 * The users table (static $table, default "users") has at least: id, username,
 * email, password, algorithm, salt, status, slug; image and online are optional.
 * status: 0 = blocked, 1 = not activated, 2 = active.
 *
 * <code>
 * $result = User::login(['identity' => $_POST['user'], 'password' => $_POST['password']]);
 * if ($result['status']) { header('Location: ' . $result['redirect']); }
 *
 * if (User::islogin()) { $me = User::getActiveUser(); echo $me->username; }
 * User::logout('/');
 *
 * $id = User::create('ada', 'ada@example.com', $password);
 * User::changePassword($id, $newPassword);
 * </code>
 *
 * Passwords are stored with password_hash() ('bcrypt'). Older md5/sha1
 * passwords still work and are upgraded to password_hash() the next time
 * the user logs in.
 *
 * @category _("User Management class")
 */
class User
{
    /**
     * Name of the users table
     *
     * @var string
     */
    public static $table = "users";

    /**
     * Database pool for the users table
     *
     * @var string
     */
    public static $pool = "default";

    /**
     * User found by getUserBySlug()
     *
     * @var Record|null
     */
    private static $cachedUser = null;

    /**
     * The logged-in user, read once per request
     *
     * @var Record|\Sys\Record\From|null
     */
    private static $user = null;

    /**
     * Check a password against the stored hash (see Password::verify()).
     *
     * @param string $plain     The password the user typed
     * @param string $algorithm 'bcrypt' or an older 'sha1salt', 'md5salt', 'sha1', 'md5', 'plain'
     * @param string $salt      Salt for the older salted algorithms
     * @param string $password  The stored hash
     * @return bool
     */
    public static function checkpassword($plain, $algorithm, $salt, $password)
    {
        return Password::verify((string) $plain, (string) $password, (string) $algorithm, (string) $salt);
    }

    /**
     * Hash a password for storing (see Password::hash()).
     *
     * @param string $plain
     * @return array{password: string, algorithm: string, salt: string}
     */
    public static function hashPassword(string $plain): array
    {
        return Password::hash($plain);
    }

    /**
     * Log in with username or email and password.
     *
     * The answer for an unknown user and for a wrong password is the same,
     * and takes the same time, so nobody can find out which accounts exist.
     *
     * @param array|string $credentials_or_user ['identity' => ..., 'password' => ..., 'redirect' => ..., 'messages' => [...]]
     *                                          or the username/email (with $password as second argument)
     * @param string|null  $password            Password (second form)
     * @param string       $wayto               Where to go after the login (second form)
     * @return array{status: bool, field: string|null, message: string|null, redirect: string|null, id?: int, slug?: string}
     */
    public static function login($credentials_or_user, $password = null, $wayto = "/account/dashboard")
    {
        if (is_array($credentials_or_user)) {
            $identity = $credentials_or_user['identity'] ?? null;
            $password = $credentials_or_user['password'] ?? null;
            $redirect = $credentials_or_user['redirect'] ?? '/account/dashboard';
            $customMessages = $credentials_or_user['messages'] ?? [];
        } else {
            $identity = $credentials_or_user;
            $redirect = $wayto;
            $customMessages = [];
        }

        $messages = array_merge([
            'missing' => _('Email/username and password are required'),
            'not_found' => _('Wrong username, email or password'),
            'wrong_password' => _('Wrong username, email or password'),
            'blocked' => _('Account is blocked'),
            'inactive' => _('Account is not activated'),
        ], $customMessages);

        $fail = fn(string $message, ?string $to = null) => ['status' => false, 'field' => 'account', 'message' => $message, 'redirect' => $to];

        if (!is_string($identity) || trim($identity) === '' || !is_string($password) || $password === '') {
            return $fail($messages['missing']);
        }

        $field = filter_var($identity, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $data = (new \Sys\Record\From(self::$table, ['pool' => self::$pool]))->searchInField($field, '=', trim($identity), 'single');

        if (!$data) {
            // Same work as a real check, so the answer takes the same time
            Password::verifyNothing($password);
            return $fail($messages['not_found']);
        }

        if (!self::checkpassword($password, $data['algorithm'] ?? 'bcrypt', $data['salt'] ?? '', $data['password'] ?? '')) {
            return $fail($messages['wrong_password']);
        }

        // Status is only told after the right password, so it says nothing to a stranger
        if ((int) $data['status'] === 0) {
            return $fail($messages['blocked']);
        }
        if ((int) $data['status'] === 1) {
            return $fail($messages['inactive'], '/help/enable');
        }

        // New session id first, so an id known before the login is worthless
        Session::regenerate();
        Session::set('userid', (int) $data['id']);
        self::$user = null;

        $update = new Record(self::$table, ['pool' => self::$pool]);
        if (Password::needsRehash((string) $data['password'], (string) ($data['algorithm'] ?? ''))) {
            foreach (Password::hash($password) as $column => $value) {
                $update->define($column, $value);
            }
        }
        $update->define('online', 1);

        try {
            $update->save((int) $data['id']);
        } catch (\Throwable $e) {
            // The login works even if the online flag or the password upgrade could not be saved
            error_log('[Sandstorm] User::login could not update user ' . (int) $data['id'] . ': ' . $e->getMessage());
        }

        return [
            'status' => true,
            'field' => null,
            'message' => null,
            'redirect' => self::safeRedirect($redirect, '/account/dashboard'),
            'id' => (int) $data['id'],
            'slug' => $data['slug'] ?? '',
        ];
    }

    /**
     * Log out: forget the user and give the session a new id.
     *
     * @param string|null $redirect Page on this site to go to afterwards (other sites are not allowed)
     * @return void
     */
    public static function logout(?string $redirect = null)
    {
        if (self::islogin()) {
            try {
                $db = new DatabaseConnection(self::$pool);
                $db->delete('DELETE FROM `online` WHERE `userid` = %d', (int) self::id());
            } catch (\Throwable $e) {
                error_log('[Sandstorm] User::logout could not remove the online row: ' . $e->getMessage());
            }
            Session::remove('userid');
            Session::regenerate();
        }
        self::$user = null;

        if ($redirect !== null && !headers_sent()) {
            header('Location: ' . self::safeRedirect($redirect, '/'));
            exit;
        }
    }

    /**
     * Whether a user is logged in.
     *
     * @return bool
     */
    public static function islogin()
    {
        return (bool) Session::get('userid');
    }

    /**
     * Id of the logged-in user, null when nobody is logged in.
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        $id = Session::get('userid', null);
        return $id ? (int) $id : null;
    }

    /**
     * Find a user by a column.
     *
     * @param string $field Column, for example 'email'
     * @param mixed  $word  Value
     * @return Record|null The user (with imagename added), or null
     */
    public static function find($field, $word)
    {
        if (empty($field) || $word === null || $word === '') {
            return null;
        }

        $user = new Record(self::$table, ['pool' => self::$pool]);
        $record = $user->searchInField($field, '=', $word, 'single', 'as:text');

        return is_array($record) ? $user->assign(self::withImageName($record), 'as:obj') : null;
    }

    /**
     * Find a user by slug (kept for the rest of the request).
     *
     * @param string $requestSlug
     * @return Record|null
     */
    public static function getUserBySlug($requestSlug)
    {
        if (self::$cachedUser === null || self::$cachedUser->slug !== $requestSlug) {
            self::$cachedUser = self::find('slug', $requestSlug);
        }
        return self::$cachedUser;
    }

    /**
     * The logged-in user, read again from the database.
     *
     * @return Record
     * @throws SystemException When nobody is logged in
     */
    public static function getActiveUserRestart()
    {
        self::$user = null;
        return self::getActiveUser();
    }

    /**
     * The logged-in user (read once per request).
     *
     * @return Record The user, with imagename added
     * @throws SystemException When nobody is logged in or the user is gone
     */
    public static function getActiveUser()
    {
        $userid = self::id();
        if ($userid === null) {
            throw new SystemException(_('No user is logged in'));
        }

        if (self::$user === null || (int) self::$user->id !== $userid) {
            $user = self::find('id', $userid);
            if ($user === null) {
                throw new SystemException(_('No active user'));
            }
            self::$user = $user;
        }

        return self::$user;
    }

    /**
     * Create a user with a hashed password.
     *
     * @param string $username
     * @param string $email
     * @param string $password Plain password
     * @param array  $extra    Other columns (slug, image, ...)
     * @param int    $status   2 = active (default), 1 = not activated
     * @return int The new id
     * @throws \InvalidArgumentException When the email is invalid or the password is not good enough (see Password::check())
     */
    public static function create(string $username, string $email, string $password, array $extra = [], int $status = 2): int
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(_('Invalid email address'));
        }
        self::requireGoodPassword($password, [$username, $email]);

        $user = new Record(self::$table, ['pool' => self::$pool]);
        $columns = ['username' => trim($username), 'email' => trim($email), 'status' => $status]
            + Password::hash($password)
            + $extra
            + ['slug' => strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $username), '-'))];

        foreach ($columns as $column => $value) {
            $user->define($column, $value);
        }

        return (int) $user->save();
    }

    /**
     * Give a user a new password.
     *
     * @param int    $id
     * @param string $password Plain password
     * @return void
     * @throws \InvalidArgumentException When the password is not good enough (see Password::check())
     */
    public static function changePassword(int $id, string $password): void
    {
        self::requireGoodPassword($password);

        $user = new Record(self::$table, ['pool' => self::$pool]);
        foreach (Password::hash($password) as $column => $value) {
            $user->define($column, $value);
        }
        $user->save(['id', '=', $id]);
    }

    /**
     * Throw with Password::check()'s first message when the password is not good enough.
     *
     * @param string   $password
     * @param string[] $personal
     * @return void
     * @throws \InvalidArgumentException
     */
    private static function requireGoodPassword(string $password, array $personal = []): void
    {
        $errors = Password::check($password, Password::MIN_LENGTH, false, $personal);
        if ($errors !== []) {
            throw new \InvalidArgumentException($errors[0]);
        }
    }

    /**
     * Number of users who are online.
     *
     * @return int
     */
    public static function getUsersByOnline()
    {
        $row = (new DatabaseConnection(self::$pool))->get()->row('SELECT COUNT(*) AS `count` FROM `online`');
        return (int) ($row['count'] ?? 0);
    }

    /**
     * Add imagename (the image's file name without extension) to a user row.
     *
     * @param array $record
     * @return array
     */
    private static function withImageName(array $record): array
    {
        $record['image'] = $record['image'] ?? '';
        $record['imagename'] = $record['image'] !== '' ? pathinfo($record['image'], PATHINFO_FILENAME) : '';

        return $record;
    }

    /**
     * Only a path on this site ("/account"), never another site ("//evil.example", "https://...").
     *
     * @param mixed  $to
     * @param string $default
     * @return string
     */
    private static function safeRedirect($to, string $default): string
    {
        if (!is_string($to) || !preg_match('#^/(?![/\\\\])[^\s]*$#', $to)) {
            return $default;
        }
        return $to;
    }
}
