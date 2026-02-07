<?php
/**
 * MySQL configuration default
 * 
 * @file default.db.php
 * @author Marcus Larsson
 * @version 2013.4.1
 * @category Pool Class for MySQL configuration
 */

class default_db
{
    public $user = "root";
    public $name = "database";
    public $password = "password";
    public $hostname = "localhost";
    public $driver = "mysql";
    public $type = "as:text";
    public $charset = "utf8mb4";
    public $logpath = "";
}