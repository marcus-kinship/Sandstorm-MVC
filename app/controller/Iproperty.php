<?php

/**
 * Abstract class for handling global properties and data.
 *
 * Provides methods for managing paths and data that can be used
 * globally in the application.
 *
 * @file iproperty.php
 * @author Marcus Larsson
 * @version 2013.4.1
 * @category _("IProperty class")
 */

abstract class Iproperty
{
    // Array to store paths
    private static $path = [];

    // Array to store data
    private static $data = [];

    /**
     * Add a path to the list of paths.
     *
     * @param string $path Path to add.
     */
    public static function setpath(string $path): void
    {
        self::$path[] = $path;
    }

    /**
     * Get the list of paths.
     *
     * @return array Array of paths.
     */
    public static function getpath(): array
    {
        return self::$path;
    }

    /**
     * Set data with a specific name.
     *
     * @param string $name Name of the data.
     * @param mixed $value Value to assign.
     */
    public static function setdata(string $name, mixed $value): void
    {
        self::$data[$name] = $value;
    }

    /**
     * Get all stored data.
     *
     * @return array Array of all data. Always returns an array.
     */
    public static function getdata(): array
    {
        return self::$data ?: [];
    }
}