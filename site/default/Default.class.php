<?php

class DefaultController extends IController
{

    /**
     * Start page
     * 
     * @router / -> index
     */
    function index()
    {
        // Load view
        $this->load("default/start.php");
    }
}