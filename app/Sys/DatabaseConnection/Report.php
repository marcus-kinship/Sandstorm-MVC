<?php

namespace Sys\DatabaseConnection;

/**
 * Report class for SQL Queries
 *
 * This class represents a report for SQL queries.
 * It contains methods to manage and display SQL queries.
 *
 * @category _("Report class for SQL Query")
 */
class Report
{
    /** @var array Stores all SQL queries and their details */
    private $allSQL;

    /**
     * Constructor for the Report class.
     *
     * @param array $SQLData Array containing all SQL queries and their details
     */
    public function __construct(array $SQLData)
    {
        $this->allSQL = $SQLData;
    }

    /**
     * Display all SQL queries and their details in a view.
     *
     * This method includes a view to display all SQL queries and their details.
     */
    public function showAllSQL()
    {
        include 'View/ShowAllSQL.php';
        die();
    }

    /**
     * Display an emulated SQL query and its details in a view.
     *
     * This method includes a view to display an emulated SQL query and its details.
     */
    public function showEmulateSQL()
    {
        include 'View/ShowEmulateSQL.php';
        die();
    }
}