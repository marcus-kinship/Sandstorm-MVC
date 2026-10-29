<?php

/**
 * DefaultController
 * 
 * Handles requests to the main/default pages of the application.
 * Extends IController to inherit base controller functionality.
 */
class DefaultController extends IController
{

    /**
     * Start page (Homepage)
     * 
     * This action is mapped to the root URL ("/") and renders
     * the main starting view of the application.
     * 
     * @router / -> index
     */
    function index()
    {
        // Show the MySQL connection status on the start page
        $this->setData('mysql', $this->mysqlStatus());

        // Load the start view
        $this->load("default/start.php");
    }

    /**
     * Try to connect with app/config/mysql/default.db.php
     *
     * Connects through the driver directly so a failed connection is shown
     * on the page instead of as an error page. A missing database is
     * created by the driver.
     *
     * @return array host, name, user, connected (bool) and error (string)
     */
    private function mysqlStatus(): array
    {
        include_once $_SERVER['HTTP_path_mysql'] . 'default.db.php';
        $config = new default_db();

        $status = [
            'host' => $config->hostname,
            'name' => $config->name,
            'user' => $config->user,
            'connected' => false,
            'error' => '',
        ];

        if (!extension_loaded('mysqli')) {
            $status['error'] = 'The mysqli extension is not enabled in PHP.';
            return $status;
        }

        try {
            $className = "\\Sys\\DatabaseConnection\\" . ucfirst($config->driver) . "\\Driver";
            $db = new $className(
                $config->hostname,
                $config->user,
                $config->password,
                $config->name,
                $config->type,
                $config->charset
            );
            $status['connected'] = $db->check();
            $db->close();
        } catch (\mysqli_sql_exception $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }
}