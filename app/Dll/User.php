<?php

/**
 * The User class handles user-related operations such as login, password verification, and user session management.
 * 
 * @category _("User Management class")
 *
 */
class User
{
  /**
   * @var string $table The name of the database table for users.
   */
  public static $table = "users";

  private static $cachedUser = null;

  /**
   * @var string $user The current user information.
   */
  private static $user = null;

  /**
   * Checks if the provided password matches the stored password based on the specific hash algorithm.
   *
   * @param string $plain The plain (non-hashed) password.
   * @param string $algorithm The hash algorithm used ('sha1salt', 'md5salt', 'sha1', 'md5', 'plain').
   * @param string $salt The salt used for hashing (only relevant for certain algorithms).
   * @param string $password The stored (hashed) password.
   * 
   * @return bool Returns true if the password matches, otherwise false.
   * @throws Exception If an unknown hash algorithm is specified.
   */
  public static function checkpassword($plain, $algorithm, $salt, $password)
  {
    switch ($algorithm) {
      case 'sha1salt':
        return $password === sha1($salt . $plain);
        break;
      case 'md5salt':
        return $password === md5($salt . $plain);
        break;
      case 'sha1':
        return $password === sha1($plain);
        break;
      case 'md5':
        return $password === md5($plain);
        break;
      case 'plain':
        return $password === $plain;
        break;
      default:
        throw new Exception('Unknown hash algorithm');
    }
  }

    /**
     * Authenticates a user using username or email and password.
     *
     * This method validates the provided credentials, checks the account status,
     * verifies the password using the stored hashing algorithm, and initializes
     * the user session on successful login.
     *
     * Supported account states:
     * - status = 0 → Account is blocked
     * - status = 1 → Account is inactive (not activated)
     * - status = 2 → Account is active (allowed to log in)
     *
     * Custom error messages can be overridden by passing a `messages` array
     * inside the `$credentials` parameter (when using array format).
     *
     * @param array|string $credentials_or_user Either an array of credentials OR username/email string.
     *     When array: {
     *         @type string      $identity  Username or email address.
     *         @type string      $password  Plain-text password.
     *         @type string|null $redirect  Optional redirect URL on success.
     *         @type array|null  $messages  Optional custom error messages.
     *     }
     * @param string|null $password Plain-text password (when using three-parameter format).
     * @param string $wayto Redirect URL on success (default: '/account/dashboard').
     *
     * @return array {
     *     @type bool        $status    TRUE on success, FALSE on failure.
     *     @type string|null $field     Field related to the error (if any).
     *     @type string|null $message   Error message (if any).
     *     @type string|null $redirect  Redirect URL (if applicable).
     *     @type int|null    $id        User ID (on success).
     *     @type string|null $slug      User slug (on success).
     * }
     */
    public static function login($credentials_or_user, $password = null, $wayto = "/account/dashboard")
    {
        // Check if first parameter is an array (new format) or string (legacy format)
        if (is_array($credentials_or_user)) {
            // Array format
            $identity = $credentials_or_user['identity'] ?? null;
            $password = $credentials_or_user['password'] ?? null;
            $redirect = $credentials_or_user['redirect'] ?? '/account/dashboard';
            $customMessages = $credentials_or_user['messages'] ?? [];
        } else {
            // Three-parameter format (legacy)
            $identity = $credentials_or_user;
            $redirect = $wayto;
            $customMessages = [];
        }
    
        // Default messages (can be overridden by caller)
        $defaultMessages = [
            'missing'        => _('Email/username and password are required'),
            'not_found'      => _('Invalid username or email'),
            'wrong_password' => _('Incorrect password'),
            'blocked'        => _('Account is blocked'),
            'inactive'       => _('Account is not activated')
        ];
    
        $messages = array_merge(
            $defaultMessages,
            $customMessages
        );
    
        // Missing credentials
        if (!$identity || !$password) {
            return [
                'status'   => false,
                'field'    => 'account',
                'message'  => $messages['missing'],
                'redirect' => null
            ];
        }
    
        // Determine if identity is email or username
        $field = filter_var($identity, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';
    
        $userrecord = new Record(self::$table);
        $data = $userrecord->searchInField($field, '=', $identity, 'single');
    
        // User not found
        if (!$data) {
            return [
                'status'   => false,
                'field'    => $field,
                'message'  => $messages['not_found'],
                'redirect' => null
            ];
        }
    
        // Blocked account
        if ($data['status'] == 0) {
            return [
                'status'   => false,
                'field'    => 'account',
                'message'  => $messages['blocked'],
                'redirect' => null
            ];
        }
    
        // Inactive (not activated) account
        if ($data['status'] == 1) {
            return [
                'status'   => false,
                'field'    => 'account',
                'message'  => $messages['inactive'],
                'redirect' => '/help/enable'
            ];
        }
    
        // Incorrect password
        if (!self::checkpassword(
            $password,
            $data['algorithm'],
            $data['salt'],
            $data['password']
        )) {
            return [
                'status'   => false,
                'field'    => 'password',
                'message'  => $messages['wrong_password'],
                'redirect' => null
            ];
        }
    
        // Login successful
        Session::set('userid', $data['id']);
    
        $userrecord->define('online', 1);
        $userrecord->save($data['id']);
    
        return [
            'status'   => true,
            'field'    => null,
            'message'  => null,
            'redirect' => $redirect,
            'id'       => $data['id'],
            'slug'     => $data['slug']
        ];
    }


  /**
   * Logs out the current user and ends the session.
   */
    static function logout(string $redirect = null)
    {
        if (User::islogin()) {
            // Ta bort användaren från online-tabellen
            Session::removeOnline();
    
            // Radera sessiondata
            Session::remove('userid');
        }
    
        // Skicka vidare om sida är angiven
        if ($redirect !== null) {
            header('Location: ' . $redirect);
            exit;
        }
    }

  /**
   * Check if a user is logged in.
   *
   * @return bool Returns true if the user is logged in, otherwise false.
   */
  static function islogin()
  {
    return Session::get('userid') ? true : false;
  }

  /**
   * Find a user based on a specific field and value.
   *
   * @param string $field The name of the field to search in (e.g., 'email').
   * @param string $word The value to match against the field.
   * 
   * @return Record|null Returns a Record object for the user if found, otherwise null.
   */
  static function find($field, $word)
  {
    // Check that field and search term are valid
    if (empty($field) || empty($word)) {
      return null;
    }

    // Create a new Record object and search for the user in the database
    $user = new Record(self::$table);
    $record = $user->searchInField($field, "=", $word, "single", "as:text");

    // Check if a valid record was found
    if ($record && is_array($record)) {
      // Handle the user's image
      if (isset($record['image'])) {
        if (!empty($record['image'])) {
          // Extract the filename from the image path
          $imgInfo = pathinfo($record['image']);
          $record['imagename'] = isset($imgInfo['filename']) ? $imgInfo['filename'] : '';
        } else {
          $record['imagename'] = '';
        }
      } else {
        $record['image'] = '';
        $record['imagename'] = '';
      }

      // Assign the updated record to the user and return the user object
      $user->assign($record, "as:obj");
      return $user;
    }

    return null;
  }

  /**
   * Get user based on slug
   *
   * @param string $requestSlug The user slug to search for.
   * 
   * @return Record|null Returns a cached or newly retrieved user Record object.
   */
  static function getUserBySlug($requestSlug)
  {
    // Check if the user is already cached
    if (self::$cachedUser === null || self::$cachedUser->slug !== $requestSlug) {
      // If not, retrieve the user from the database
      self::$cachedUser = self::find('slug', $requestSlug);
    }
    return self::$cachedUser;
  }

  /**
   * Retrieve the current logged-in user and restart user data.
   *
   * @return Record Returns a Record object for the logged-in user.
   * @throws SystemException If no user is logged in.
   */
  static function getActiveUserRestart()
  {
    // Get user ID from the session
    $userid = Session::get('userid');

    // Check if user ID is empty
    if (empty($userid)) {
      throw new SystemException("No active user");
    }

    // If user data is not in cache, retrieve from database
    if (self::$user === null || self::$user->id != $userid) {
      $user = new Record(self::$table);
      $record = $user->searchInField('id', $userid, "single", "as:text");

      // Check if a valid user record was found
      if ($record && is_array($record)) {
        self::$user = $user->assign($record);

        // Handle user image
        if (isset($record['image'])) {
          if (!empty($record['image'])) {
            $img = pathinfo($record['image']);
            $record['imagename'] = isset($img['filename']) ? $img['filename'] : '';
          } else {
            $record['imagename'] = '';
          }
        } else {
          $record['image'] = '';
          $record['imagename'] = '';
        }

        $user->assign($record);
        return $user;
      } else {
        throw new SystemException("No active user");
      }
    }

    return self::$user;
  }


  /**
   * Retrieve the current logged-in user.
   *
   * @return Record Returns a Record object for the logged-in user.
   * @throws SystemException If no user is logged in.
   */
  static function getActiveUser()
  {
    // Get user ID from the session
    $userid = Session::get('userid', null);

    // Check if user ID is null or empty
    if ($userid == null) {
      throw new SystemException("No user is logged in");
    }

    // Check if the user is already cached
    if (empty(self::$user) || self::$user->id != $userid) {
      $userRecord = new \Sys\Record\From(self::$table);
      $record = $userRecord->searchInField('id', '=', (int) $userid, 'single', 'as:text');

      // Check if the user is found
      if (!$record) {
        throw new SystemException("No active user");
      }

      // Cache the user
      self::$user = $userRecord->assign($record, 'as:obj');
    }

    // Check if the user is valid
    if (self::$user === null) {
      throw new SystemException("No active user");
    }

    // Handle user image
    if (isset(self::$user->image)) {
      if (!empty(self::$user->image)) {
        $imgInfo = pathinfo(self::$user->image);
        self::$user->imagename = $imgInfo['filename'];
      } else {
        self::$user->imagename = self::$user->image;
      }
    }

    return self::$user;
  }

  /**
   * Get the number of users who are online.
   *
   * @return int Returns the number of users who are online.
   */
  static function getUsersByOnline()
  {
    $db = new DatabaseConnection();
    $record = $db->get()->Row('SELECT COUNT(*) FROM  `online` ');
    if ($record) {
      return $record['COUNT(*)'];
    }
  }
}