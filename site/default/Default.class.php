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
        // Load the start view
        $this->load("default/start.php");
    }
}