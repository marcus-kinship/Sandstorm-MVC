<?php
/**
 * Default Application Configuration
 * 
 * This class provides the default configuration settings for the application.
 * It defines paths, pagination settings, default pages, and language settings.
 * 
 * @file default.ini.php
 * @package Config
 * @version 2013.4.1
 * @author Marcus Larsson
 * @category Configuration Class
 */
class default_ini
{
	function __construct()
	{
		$start = $_SERVER['DOCUMENT_ROOT'];

		/**
		 * Configuration settings array
		 *
		 * @var array
		 */
		$this->set = array(

			// DEFAULT SETTINGS

			// Set the current language for the page
			'set current language' => 'sv_SE',

			// Path to language files
			'path_lang' => $start . '/lang/',

			// Name of the default class for the start page
			'default_page' => 'default',

			// Page shown when there is no active user
			'no active user page' => '/',

			// 404 error page path
			'404 page' => $start . '/site/errorpages/404.php',

			// 500 error page path
			'500 page' => $start . '/site/errorpages/500.php',

			// Number of items per page for live pagination
			'pagination_live' => 10,

			// Default limit for archives
			'limit def archives' => '12',

			// Number of items per page for search results
			'pagination_search' => 10,

			// Number of items per page for article archive
			'pagination_articlearchive' => 30,

			// Number of items per page for wzup archive
			'pagination_archive' => 30,

		);

	}

}