<?php

/**
 * Class Session
 * 
 * Handles session management in the PHP application.
 * @category _("Session Management class")
 */
class Session
{

    /**
     * @var bool Indicates whether the session has been started.
     */
    private static $_sessionStarted = false;

    /**
     * Starts the session if it has not already been started.
     */
    public static function start()
    {
        if (self::$_sessionStarted == false) {
            session_start();
            self::$_sessionStarted = true;
        }
    }

    /**
     * Retrieves the session ID.
     *
     * @return string The session ID.
     */
    static function getSessionId()
    {
        return session_id();
    }

    /**
     * Terminates the session.
     */
    public static function kill()
    {

        if (self::$_sessionStarted == true) {
            $_SESSION = array();
            session_regenerate_id();
            session_unset();
            session_destroy();
            self::$_sessionStarted = false;
        }
    }

    /**
     * Sets a variable in the session.
     *
     * @param string $key The key for the variable.
     * @param mixed $value The value for the variable.
     */
    public static function set($key, $value)
    {

        $_SESSION[$key] = $value;
    }

    /**
     * Adds a value to an array in the session.
     *
     * @param string $key The key for the array.
     * @param mixed $value The value to add.
     * @param mixed $i The index to replace an existing value (optional).
     */
    public static function add($key, $value, $i = null)
    {
        if ($i == null) {
            $_SESSION[$key][] = $value;
        } else {
            $_SESSION[$key][$i] = $value;
        }
    }

    /**
     * Retrieves the value of a variable from the session.
     *
     * @param string $key The key for the variable.
     * @return mixed The value for the variable or false if the variable does not exist.
     */

    public static function get($key, $def = false)
    {

        if (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        } else {
            return $def;
        }
    }

    /**
     * Removes a variable from the session.
     *
     * @param string $key The key for the variable.
     */
    public static function remove($key)
    {

        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        } else {
            return false;
        }
    }

    /**
     * Retrieves multiple variables from the session.
     *
     * @param array $array The keys for the variables.
     * @return mixed Session data or false if any of the variables do not exist.
     */
    private static function filter($array)
    {

        $data = "";
        $str = null;
        $obj = null;
        $i = 0;
        foreach ($array as $keys) {

            if ($i == 0) {
                if (array_key_exists($keys, $_SESSION)) {
                    $data = gettype($_SESSION[$keys]);
                    $str = $_SESSION;
                    $i++;
                } else {
                    return false;
                }
            }
            if (gettype($str) == "object") {
                $data = gettype($str->$keys);
            } else {
                $data = gettype($str[$keys]);
            }

            switch ($data) {
                case "array":


                    if ($keys == end($array)) {
                        return $str;
                    } else {
                        $str = $str[$keys];
                    }

                    break;
                case "object":


                    if ($keys != end($array)) {

                        if (gettype($str) == "object") {
                            $str = $str->$keys;
                        } else {
                            $str = $str[$keys];
                        }
                    } else {

                        return $str;
                    }

                    break;
                case "resource":
                case "string":
                case "double":
                case "NULL":
                case "integer":
                case "unknown type":
                case 'boolean':
                    if (gettype($str) == "object") {
                        return $str->$keys;
                    } else {
                        return $str[$keys];
                    }
                default:
                    return false;
            }
            $i++;
        }
    }

    /**
     * Updates the online status for the user.
     */
    public static function updateOnline()
    {

        $userid = Session::get('userid');
        if ($userid !== false) {

            $db = new DatabaseConnection();
            $count = $db->get("as:text")->Row("SELECT * FROM `online` WHERE userid='%s'", $userid);

            if (isset($count)) {
                self::typeOnline($userid);
            } else {
                $db->insertRow("INSERT INTO `online` (`userid`,`time`, `typ`) VALUES ('%d',CURRENT_TIMESTAMP , '1')", $userid);
            }
        }
    }

    /**
     * Sets the language in the session.
     *
     * @param string $language The language code.
     */
    public static function setLanguage($language)
    {
        self::set('language', $language);
    }

    /**
     * Retrieves the current language from the session.
     *
     * @return string Current language.
     */
    public static function getLanguage()
    {
        if (key_exists('language', $_SESSION)) {
            return $_SESSION['language'];
        }
        return false;
    }

    /**
     * Updates the user's online type.
     *
     * @param int $userid User ID.
     * @param int $type Online type.
     */
    public static function typeOnline($userid, $type = 1)
    {
        $db = new DatabaseConnection();
        $db->updateRow("UPDATE `online` SET `typ` =  '%d' ,`time` = '%s' WHERE userid='%d'", $type, date("Y-m-d H:i:s"), $userid);
    }

    /**
     * Removes the user's online status.
     */
    public static function removeOnline($SkipDeleteError = true)
    {
        $db = new DatabaseConnection();
        $userid = Session::get('userid');
        
        if ($userid) {
            $db->SkipDeleteError = $SkipDeleteError;
            $db->delete("DELETE FROM `online` WHERE userid='%d'", $userid);
        }
    }
    /**
     * Retrieves information about all users who are online.
     *
     * @return array Information about online users.
     */
    public static function getAllOnline()
    {
        $db = new DatabaseConnection();
        return $db->get("as:text")->Rows("SELECT * FROM `online`");
    }

    /**
     * Displays a debug output of the session.
     */
    public static function debug()
    {

        print '<pre>';
        print_r($_SESSION);
        print '</pre>';
    }
}
