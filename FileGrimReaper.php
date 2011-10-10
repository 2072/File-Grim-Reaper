<?php

const PROPER_USAGE = true;

if (! version_compare(PHP_VERSION, '5.3.8', '>='))
    die ("PHP 5.3.8 at least is required to run this program, you are using ".PHP_VERSION);
   
include (dirname(__FILE__) . "/core.php");

?>
