<?php

/* File Grim Reaper v1.0 - It will reap your files!
 * (c) 2011-2013 John Wellesz
 *   
 *  This file is part of File Grim Reaper.
 *
 *  Project home:
 *      https://github.com/2072/File-Grim-Reaper
 *
 *  Bug reports/Suggestions:
 *      https://github.com/2072/File-Grim-Reaper/issues
 *
 *   File Grim Reaper is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   File Grim Reaper is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with File Grim Reaper. If not, see <http://www.gnu.org/licenses/>.
 */

const PROPER_USAGE = true;

if (! version_compare(PHP_VERSION, '5.3.8', '>='))
    die ("PHP 5.3.8 at least is required to run this program, you are using ".PHP_VERSION);

include (dirname(__FILE__) . "/core.php");

?>
