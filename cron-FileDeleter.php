<?php
// (c) John Wellesz for MikrosImage - September 2011

//php cron-FileDeleter.php --show -c=testConfig.txt --dry-run

error_reporting ( E_ALL | E_STRICT );

const DEFAULT_CONFIG_FILE = "cron-FileDeleter-paths.txt";
const ERRORSTR = "ERROR: ";

define ( 'NOW', time());



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
	"remove",
	"dry-run"
    );

    $setOptions = getopt("c::srd", $longOptions);


    if (isset($setOptions['s']) || isset($setOptions['show']))
	define ('SHOW', true);
    else
	define ('SHOW', false);


    if (isset($setOptions['r']) || isset($setOptions['remove']))
	define ('REMOVE', true);
    else
	define ('REMOVE', false);

    if (isset($setOptions['d']) || isset($setOptions['dry-run']))
	define ('DRYRUN', true);
    else
	define ('DRYRUN', false);


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

function checkDataPath ()
{
    // find our config path
    if (! @realpath($_SERVER['argv'][0]) )
	errorExit(2, 'Impossible to determine script directory...');
    else
	define ('DATA_PATH', dirname(realpath($_SERVER['argv'][0])) . "/cron-FileDeleter-Datas");

    if (!is_dir(DATA_PATH)) {
	mkdir(DATA_PATH);
	cprint('Created data folder: ', DATA_PATH);
    }
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
	    $directories [realpath($path)] = $param;
	}

    return $directories;
}


function getDirectoryScannedDatas ($path)
{
    $dataFileName = preg_replace("#[\\\\/]|:\\\\#", "-", $path);

    if (! $dataFileName)
	errorExit(2, 'Impossible error #1: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/' . $dataFileName . '.data.serialised';

    if (file_exists($dataFileName)) {

	$data = unserialize (file_get_contents($dataFileName));

	if ($data)
	    return $data;
	else
	    error('Error loading data for directory: ', $path);
    } else
	return array ();
}

function saveDirectoryScannedDatas ($path, $datas)
{
    $dataFileName = preg_replace("#[\\\\/]|:\\\\#", "-", $path);

    if (! $dataFileName)
	errorExit(2, 'Impossible error #2: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/' . $dataFileName . '.data.serialised';

    if (! file_put_contents($dataFileName, serialize($datas), LOCK_EX))
	error("Couldn't save scanned datas in: ", $dataFileName);
}

function fileGrimReaper ($dirToScan)
{
    var_dump($dirToScan);

    $filesToDelete = array();


    foreach ($dirToScan as $dirPath=>$dirParam) {
	cprint("The file Grim Reaper is now considering files in: ", $dirPath);
	// get previous scan datas
	$knownDatas = getDirectoryScannedDatas($dirPath);

	// Scan existing entries
	foreach ($knownDatas as $filePath=>$knownData) {
	    // if the file is still there
	    if (file_exists($filePath)) {

		// get current file mod time
		if (! $fileMTime = filemtime($filePath)) {
		    error("Couldn't get modification time for ", $filePath);
		    continue;
		}

		// If the file has NOT been modified since the last scan,
		// check if it's elligeable for deletion
		if ($fileMTime == $knownData["fileMTime"])
		    if ($knownData["foundOn"] + $dirParam['duration'] < NOW)
			$filesToDelete[] = $filePath;
		
	    } else
		// the file is no longer there so delete its entry in $KnownDatas
		// this is where the list is cleaned
		unset ($knownDatas[$filePath]);
	}

	$reapedDirectories = array(); // used to remove empty dirs after delting files

	// Here comes the file Grim Reaper
	foreach ($filesToDelete as $file)
	    if (DRYRUN) {

		cprint('Would have (--dry-run is set) deleted file: ', $file);
		$reapedDirectories[dirname($file)] = true;

	    } elseif (unlink($file)) {

		unset($knownDatas[$file]);
		$reapedDirectories[dirname($file)] = true;
		cprint($file, " was deleted.");

	    } else
		error("Couldn't delete file: ", $file);

	// scan directory for new items
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath),
                                              RecursiveIteratorIterator::CHILD_FIRST);
	$isDirEmpty = array();

	if ( $iterator ) {
	    // XXX find a way to detect when directories are skipped due to wrong permission
	    foreach ($iterator as $fileinfo) {

		if ($fileinfo->isDir() && ! isset($isDirEmpty[$fileinfo->getPathname()]) )
		    $isDirEmpty[$fileinfo->getPath()] = true;


		if (! $fileinfo->isFile())
		    continue;

		$isDirEmpty[$fileinfo->getPath()] = false;

		// if the file is new
		if (! isset ($knownDatas[$fileinfo->getPathname()]) )
		    $knownDatas[$fileinfo->getPathname()] = array (
			"foundOn" => time(),
			"fileMTime" => $fileinfo->getMTime(),
		    );
	    }
	} else
	    errorExit(2, "Impossible to scan directory: ", $dirToScan);


	unset ($isDirEmpty[$dirPath]);

	// remove empty directories left behind after reaping their content or expired empty ones
	// XXX need to do this child first... just sort this list in a clever way (the one with the most '/' first...
	foreach ($isDirEmpty as $path=>$isEmpty)
	    // the directory is empty and files were reaped inside it
	    if ($isEmpty && isset($reapedDirectories[$path])) {

		if (DRYRUN)
		    cprint('Would have (--dry-run is set) removed (reaped) directory: ', $path);
		elseif (!rmdir($path))
		    error("Couldn't remove directory: ", $path);
		else
		    cprint('Removed empty (reaped) directory: ', $path);

		// the directory is empty and is older than allowed duration
	    } elseif ($isEmpty && (filemtime($path) + $dirParam['duration'] < NOW)) {

		if (DRYRUN)
		    cprint('Would have (--dry-run is set) removed (old) directory: ', $path);
		else
		    if (!rmdir($path))
			error("couldn't remove directory: ", $path);
		    else
			cprint('Removed emty (old) directory: ', $path);
	    }
	

	saveDirectoryScannedDatas($dirPath, $knownDatas);

    }

    return $filesToDelete;

}

cprint ("\nHello fucking world!\n");


checkDataPath ();
checkOptions ();
fileGrimReaper ( getConfig () );




// use directory iterator



cprint ("");

/*  TODO :
 *
 *  add a logging option which will use ob_start() and auto_shutdown options
 *
 */

?>
