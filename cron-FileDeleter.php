<?php
// (c) John Wellesz for MikrosImage - September 2011
  

error_reporting (E_ALL | E_STRICT );

const DEFAULT_CONFIG_FILE = "cron-FileDeleter-paths.txt";
const ERRORSTR = "ERROR: ";

function printUsage ()
{
    echo "\nUsage: ", $_SERVER['PHP_SELF'], " [--config configFilePath] --remove | --show\n";
}

function checkDirName ($name)
{
    // format should be something like BLABLABLA__TO_KEEP_XX_LENGTH
    // handle handle the following length : YEAR(S), MONTH(S), DAY(S), HOUR(S), MINUTE(S)
}

function checkOptions ()
{
    $longOptions = array (
	"config::",
	"show",
	"remove"
    );

    $setOptions = getopt("c::sr", $longOptions);


    if (isset($setOptions['s']) || isset($setOptions['show']))
	define ('SHOW', true);
    else
	define ('SHOW', false);


    if (isset($setOptions['r']) || isset($setOptions['remove']))
	define ('REMOVE', true);
    else
	define ('REMOVE', false);


    if (SHOW && REMOVE) {
	echo ERRORSTR,'--remove and --show options are exclusive!';
	exit (1);
    } elseif (! (SHOW || REMOVE)) {
	echo ERRORSTR,"Action is missing!\n";
	printUsage ();
	exit (1);
    }

    if (! empty($setOptions['c']) || ! empty($setOptions['config'])) {
	$config = ( (! empty($setOptions['c'])) ? $setOptions['c'] : $setOptions['config'] );

	if ( file_exists( $config ) )
	    define ('CONFIG', realpath($config));
	else {
	    echo ERRORSTR,"config file '$config' couldn't be found!";
	    exit (1);
	}
    } else
	define ('CONFIG', false);
}

function getConfig ()
{
    if (CONFIG)
	$config = file(CONFIG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    else {
	$configPath = getcwd() . '/' . DEFAULT_CONFIG_FILE;

	if (file_exists($configPath))
	    $config = file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	else {
	    echo ERRORSTR,'No configuration file provided and no file named "', DEFAULT_CONFIG_FILE, '" found in ', getcwd(); 
	    exit (1);
	}
    }

    if (count($config) == 0 ) {
	echo ERRORSTR,'Configuration file is empty!', ' Configuration file used : ', ( isset($configPath) ? realpath($configPath) : CONFIG );
	exit (2);
    }

    foreach ($config as $path)
	if (! is_dir($path)) {
	    echo 'Config WARNING: ', "'$path'", " is not a valid directory.\n";
	    unset ($config[$path]);
	}

}

echo "\nHello fucking world!\n\n";

checkOptions ();
getConfig ();




// use directory iterator



echo "\n";

/*  TODO :
 *
 *  add a logging option which will use ob_start() and auto_shutdown options
 *
 */

?>
