<?php

/**
 * Sandstorm
 * 
 * Loading configuration and starting up all the necessary items needed.
 * Autoloader automatically loads the classes (include not required).
 * Error handler that enables better looking and more feature-rich messages.
 * Finally running the router delegating further run of the page to
 * Correct controller / action.
 * 
 * @file index.php
 * @author Marcus Larsson
 * @version 2014.1.1
 * @copyright (c) 2011, Marcus Larsson
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/lgpl-3.0.html>.
 */

try {

    include_once $_SERVER['HTTP_path_config'] . "config.class.php";
    new Start;

} catch (SystemException $e) {
}