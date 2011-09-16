<?php
// (c) John Wellesz for MikrosImage - September 2011

//php cron-FileDeleter.php --show -c=testConfig.txt

error_reporting ( E_ALL | E_STRICT );

const DEFAULT_CONFIG_FILE = "cron-FileDeleter-paths.txt";
const ERRORSTR = "ERROR: ";

function cprint ()
{
    $args = func_get_args();

    fwrite(STDOUT, implode($args, "")."\n");
}

function printUsage ()
{
    cprint ( "\nUsage: ", $_SERVER['PHP_SELF'], " [--config configFilePath] --remove | --show\n");
}

function error ()
{
    $args = func_get_args();

    fwrite(STDERR, implode($args, "")."\n");
}

function errorExit($code)
{
    $args = func_get_args();
    unset($args[0]);

    fwrite(STDERR, implode($args, "")."\n");
    exit ($code);
}

function isDirValid ($name)
{
    // format should be something like BLABLABLA__TO_KEEP_XX_LENGTH
    // handle handle the following length : YEAR(S), MONTH(S), DAY(S), HOUR(S), MINUTE(S)

    $matches = array();
    $badDir = false;
    $nameFormat = '#[/\\\\](\w+)__TO_KEEP_(\d+)_(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND)S?$#';

    $timeMultiplicators = array (
	"YEAR"	    => 3600 * 24 * 365,
	"MONTH"	    => 3600 * 24 * 30,
	"DAY"	    => 3600 * 24,
	"HOUR"	    => 3600,
	"MINUTE"    => 60,
	"SECOND"    => 1,
    );

    if ( preg_match($nameFormat, $name, $matches) ) {
	if ( is_dir ($name)) {
	    return array ('name' => $matches[1], 'duration' => $matches[2] * $timeMultiplicators [ $matches[3] ]);
	} else
	    $badDir = "Directory '$name' cannot be found!";
    } else
	$badDir = "Wrong name format, should match '$nameFormat'";

    if ($badDir) {
	error('Config WARNING: ', "'$name'", " is not a valid directory:\n", $badDir);
	return false;
    }
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


    if (SHOW && REMOVE)
	errorExit(1, ERRORSTR,'--remove and --show options are exclusive!');
    elseif (! (SHOW || REMOVE)) {
	printUsage ();
	errorExit(1, ERRORSTR,"Action is missing!");
    }

    if (! empty($setOptions['c']) || ! empty($setOptions['config'])) {
	$config = ( (! empty($setOptions['c'])) ? $setOptions['c'] : $setOptions['config'] );

	if ( file_exists( $config ) )
	    define ('CONFIG', realpath($config));
	else
	    errorExit(1, ERRORSTR,"config file '$config' couldn't be found!");
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
	else
	    errorExit(1, ERRORSTR,'No configuration file provided and no file named "', DEFAULT_CONFIG_FILE, '" found in ', getcwd());
    }

    if (count($config) == 0 )
	errorExit(2, ERRORSTR,'Configuration file is empty!', ' Configuration file used : ', ( isset($configPath) ? realpath($configPath) : CONFIG ));

    $directories = array ();

    foreach ($config as $path)
	if (! ($param = isDirValid($path)))
	    unset ($config[$path]);
	else {
	    $directories [$path] = $param;
	}

    return $directories;
}


function getFilesToDelete ($dirToScan)
{
    var_dump($dirToScan);

    foreach ($dirToScan as $path=>$param)
	cprint($path);
}

cprint ("\nHello fucking world!\n");

checkOptions ();
getFilesToDelete ( getConfig () );




// use directory iterator



cprint ("");

/*  TODO :
 *
 *  add a logging option which will use ob_start() and auto_shutdown options
 *
 */

?>
