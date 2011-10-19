<?php

/* File Grim Reaper - It will reap your files!
 * (c) 2011 John Wellesz
 *   
 *   This file is part of File Grim Reaper.
 *
 *   File Grim Reaper is free software: you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation, either version 3 of the License, or
 *   (at your option) any later version.
 *
 *   File Grim Reaper is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with File Grim Reaper. If not, see <http://www.gnu.org/licenses/>.
 */

const VERSION = 1;
const REVISION = 0;

if (! defined("PROPER_USAGE"))
    die("Incorrect usage, you cannot execute this file directly!");

define ('UNAME', php_uname('n'));
error_reporting ( E_ALL | E_STRICT );
ini_set('error_log', dirname(realpath(__FILE__)) . "/".UNAME."_errors.txt");

const DEFAULT_CONFIG_FILE = "FileGrimReaper-paths.txt";

define ( 'NOW', time() );


$LOGFILEPATH;

function removeFile ($path)
{
    if (! DRYRUN && ! SHOW && ! @unlink($path))
	error();
    else {
	//if (! SHOW)
	//    cprint($path, " removed.");

	return true;
    }

    return false;
}

function removeDirectory ($path)
{
    if (! DRYRUN && ! SHOW && ! @rmdir($path))
	error();
    else
	return true;

    return false;
}

function cprint ()
{
    $args = func_get_args();

    $toPrint = str_replace("\n", "\r\n", implode($args, ""))."\r\n";

    fwrite(STDOUT, $toPrint);

    global $LOGFILEPATH;
    if (defined("LOGGING") && LOGGING && !empty($LOGFILEPATH))
	error_log($toPrint, 3, $LOGFILEPATH);
}

function printUsage ()
{
    cprint ( "Usage: ", $_SERVER['PHP_SELF'], " [--config configFilePath] --remove | --show\n");

    
}

function printHeader ()
{
    cprint (
	"\nFile Grim Reaper version ",VERSION,".",REVISION," Copyright (C) 2011 John Wellesz\n\n",
	"\tThis program comes with ABSOLUTELY NO WARRANTY.\n",
	"\tThis is free software, and you are welcome to redistribute it\n",
	"\tunder certain conditions; see the provided GPL.txt for details.\n"
    );
}

$errorCount = 0;
function error ()
{
    global $errorCount;

    $errorCount++;

    $args = func_get_args();

    $last_error = error_get_last();

    if (! empty($last_error)) {

	if (count($args))
	    $args[] = "\n\tLast PHP error: ";
	else
	    $args[] = "ERROR: ";

	$args[] = $last_error['message'];
    }

    $toPrint = str_replace("\n", "\r\n", implode($args, ""))."\r\n\r\n";
    fwrite(STDERR, $toPrint);

    global $LOGFILEPATH;
    if (defined("LOGGING") && LOGGING && !empty($LOGFILEPATH))
	error_log($toPrint, 3, $LOGFILEPATH);
}

function getDirectoryDepth($path)
{
    return substr_count($path, '/') + substr_count($path, '\\');
}

function errorExit($code)
{
    $args = func_get_args();
    $args[0] = "FATAL ERROR: ";

    call_user_func_array("error", $args);

    exit ($code);
}

function isDirValid ($name)
{
    // format should be something like BLABLABLA__TO_KEEP_XX_LENGTH
    // handle the following length : YEAR(S), MONTH(S), DAY(S), HOUR(S), MINUTE(S)

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

function GetAndSetOptions ()
{
    $longOptions = array (
	"config::",
	"show",
	"remove",
	"logging",
	"dry-run"
    );

    $setOptions = getopt("c::srdl", $longOptions);


    if (isset($setOptions['s']) || isset($setOptions['show']))
	define ('SHOW', true);
    else
	define ('SHOW', false);

    if (isset($setOptions['l']) || isset($setOptions['logging']))
	define ('LOGGING', true);
    else
	define ('LOGGING', false);

    if (isset($setOptions['r']) || isset($setOptions['remove']))
	define ('REMOVE', true);
    else
	define ('REMOVE', false);

    if (isset($setOptions['d']) || isset($setOptions['dry-run'])) {
	define ('DRYRUN', true);
	cprint ("== DRY-RUN MODE ==");
    } else
	define ('DRYRUN', false);


    if (SHOW && REMOVE)
	errorExit(1, '--remove and --show options are exclusive!');
    elseif (! (SHOW || REMOVE)) {
	printUsage ();
	errorExit(1, "Action is missing!");
    }

    if (! empty($setOptions['c']) || ! empty($setOptions['config'])) {
	$config = ( (! empty($setOptions['c'])) ? $setOptions['c'] : $setOptions['config'] );

	if ( file_exists( $config ) )
	    define ('CONFIG', realpath($config));
	else
	    errorExit(1, "config file '$config' couldn't be found!");
    } else
	define ('CONFIG', false);
}

function checkDataPath ()
{
    // find our config path
    if (! @realpath(__FILE__) )
	errorExit(2, 'Impossible to determine script directory...');
    else
	define ('DATA_PATH', dirname(realpath(__FILE__)) . "/FileGrimReaper-Datas");

    if (!is_dir(DATA_PATH)) {
	mkdir(DATA_PATH);
	cprint('Created data folder: ', DATA_PATH);
    }

    if (LOGGING) {
	if (!is_dir(DATA_PATH."/Logs")) {
	    mkdir(DATA_PATH."/Logs");
	    cprint('Created log folder: ', DATA_PATH."/Logs");
	}

	define ('LOGS_PATH', DATA_PATH."/Logs");
	define ('LOG_HEADER', date("[Y-m-d H:i:s] "));
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
	    errorExit(1, 'No configuration file provided and no file named "', DEFAULT_CONFIG_FILE, '" found in ', getcwd());
    }

    if (count($config) == 0 )
	errorExit(2, 'Configuration file is empty!', ' Configuration file used : ', ( isset($configPath) ? realpath($configPath) : CONFIG ));

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

    if (LOGGING) {
	global $LOGFILEPATH;
	$LOGFILEPATH = LOGS_PATH . "/".UNAME."_$dataFileName.log";
    }

    if (! $dataFileName)
	errorExit(2, 'Impossible error #1: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/'. UNAME . '_' . $dataFileName . '.data.serialized';

    if (file_exists($dataFileName)) {

	$data = unserialize (file_get_contents($dataFileName));

	if (is_array($data))
	    return $data;
	else {
	    error('Error loading data for directory: ', $path);
	    return false;
	}
    } else
	return array ();
}

function saveDirectoryScannedDatas ($path, $datas)
{
    global $LOGFILEPATH;

    if (SHOW || DRYRUN) {
	cprint("Changes not saved, showing only.");
	$LOGFILEPATH = false;
	return;
    }

    $dataFileName = preg_replace("#[\\\\/]|:\\\\#", "-", $path);

    if (! $dataFileName)
	errorExit(2, 'Impossible error #2: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/'. UNAME . '_' . $dataFileName . '.data.serialized';

    $tempFileName = $dataFileName . sprintf("_%X", crc32(microtime()));
    $serializedDatas = serialize($datas);

    // Write the data in a temporary file and really check it's complete
    if ( file_put_contents($tempFileName, $serializedDatas) !== strlen($serializedDatas)
	|| filesize($tempFileName)			    !== strlen($serializedDatas) )

	error("Couldn't write scanned datas in: ", $tempFileName);
    else {
	// If all was written then replace the original file
	// This prevents to damage the original file if the disk happened to be full
	if (! rename($tempFileName, $dataFileName))
	    error("Couldn't save scanned datas in: ", $dataFileName);
    }
    


    $LOGFILEPATH = false;
}

function fileGrimReaper ($dirToScan)
{
    foreach ($dirToScan as $dirPath=>$dirParam) {

	$start = microtime(true);
	// get previous scan datas
	if (!is_array( $knownDatas = getDirectoryScannedDatas($dirPath)))
	    continue;


	// The log file is named after the directory being checked so...
	if (!LOGGING)
	    cprint("\nNow considering files in: ", $dirPath, '...', "\n");

	/* #########################
	 * # Scan existing entries #
	 * #########################
	 */

	$filesToDelete = array();
	$ModifiedFilesCounter	    = 0;
	$DisappearedFilesCounter    = 0;
	foreach ($knownDatas as $filePath=>$knownData) {
	    // if the file is still there
	    if (file_exists($filePath)) {

		// get current file mod time
		if (! $fileMTime = @filemtime($filePath)) {
		    error("Couldn't get modification time for ", $filePath);
		    continue;
		}

		// If the file has NOT been modified since the last scan,
		// check if it's elligeable for deletion
		if ($fileMTime == $knownData["fileMTime"]) {
		    if ($knownData["foundOn"] + $dirParam['duration'] < NOW)
			$filesToDelete[] = $filePath;
		} else {
		    // treat as a new file
		    $knownDatas[$filePath]["foundOn"] = NOW;
		    $knownDatas[$filePath]["fileMTime"] = $fileMTime;
		    $ModifiedFilesCounter++;
		}

	    } else {
		// the file is no longer there so delete its entry in $KnownDatas
		// this is where the list is cleaned
		unset ($knownDatas[$filePath]);
		$DisappearedFilesCounter++;
	    }
	}

	$reapedDirectories = array(); // used to remove empty dirs after deleting files

	/* ##########################
	 * # Reap the expired files #
	 * ##########################
	 */

	$deletedFileList = array();
	$deletedFilesCounter = 0;
	foreach ($filesToDelete as $file)
	    if (removeFile($file)) {
		if (REMOVE) unset($knownDatas[$file]);
		$deletedFileList[] = $file;
		$deletedFilesCounter++;
		$reapedDirectories[dirname($file)] = true;
	    }


	/* ################################
	 * # Scan directory for new items #
	 * ################################
	 */

	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath),
	    RecursiveIteratorIterator::CHILD_FIRST);
	$isDirEmpty = array();
	$DirHasChildren = array();

	$NewFilesCounter	= 0;
	if ( $iterator ) {
	    foreach ($iterator as $file=>$fileinfo) {

		if ($fileinfo->isDir() && ! isset($DirHasChildren[$fileinfo->getPathname()]) ) {
		    // Mark the directory as empty the first time we see it
		    $DirHasChildren[$fileinfo->getPathname()] = 0;

		}

		// Count elements
		if (! isset($DirHasChildren[$fileinfo->getPath()]))
		    $DirHasChildren[$fileinfo->getPath()] = 1;
		else
		    $DirHasChildren[$fileinfo->getPath()]++;

		if (! $fileinfo->isFile()) {

		    if (! $fileinfo->isDir())
			error("'$file' is immortal... It needs renaming so it can be reaped when its time comes.");

		    continue;
		}

		// Mark the parent directory as not empty
		$DirHasChildren[$fileinfo->getPath()] = 'file';

		// if the file is new
		if (! isset ($knownDatas[$fileinfo->getPathname()]) ) {
		    $knownDatas[$fileinfo->getPathname()] = array (
			"foundOn" => time(),
			"fileMTime" => $fileinfo->getMTime(),
		    );
		    $NewFilesCounter++;
		}
	    }
	} else
	    errorExit(2, "Impossible to scan directory: ", $dirToScan);

	/* ################################
	 * #  Removed orphaned directory  #
	 * ################################
	 */

	// Protect teh base directory from deletion
	$DirHasChildren[$dirPath] = 'file';


	// sort the array using the path depth, the deepest first
	if (!
	    uksort ($DirHasChildren, function ($a, $b) {
		if (getDirectoryDepth($a) == getDirectoryDepth($b))
		    return 0;

		if (getDirectoryDepth($a) > getDirectoryDepth($b))
		    return -1;

		if (getDirectoryDepth($a) < getDirectoryDepth($b))
		    return 1;
	    }
	))
	    error ("uksort() failed.");

	// deleted directories counters
	$reapedDeletedCounter	= 0;
	$expiredDeletedCounter	= 0;
	$deadEndDeletedCounter	= 0;
	$failedRemovalCounter	= 0;
	$DirIsDeadEnd		= array();

	foreach ($DirHasChildren as $path=>$_notused_)
	    // the directory is empty and files were reaped inside it
	    if ( !$DirHasChildren[$path] && isset($reapedDirectories[$path])) {

		if (! removeDirectory($path) ) {
		    $failedRemovalCounter++;
		} else {
		    //cprint('Removed empty (reaped) directory: ', $path);
		    $reapedDeletedCounter++;

		    // decrease parent children number, this will be enough to trigger a deletion (since we go from
		    // the child to the parent) but not a deletion of a parent directory containing only directories.
		    if (--$DirHasChildren[dirname($path)] < 0)
			error("Impossible Error #3: too many elements: ", $DirHasChildren[dirname($path)]);

		    // if the directory is now empty, mark it for deletion
		    if (! $DirHasChildren[dirname($path)])
			$DirIsDeadEnd[dirname($path)] = true;
		}

		// the directory is empty and is older than allowed duration
	    } elseif (!$DirHasChildren[$path] && (@filemtime($path) + $dirParam['duration'] < NOW)) {

		if (!removeDirectory($path)) {
		    $failedRemovalCounter++;
		} else {
		    //cprint('Removed emty (old) directory: ', $path);
		    $expiredDeletedCounter++;

		    // decrease parent children number, this won't be enough to trigger a deletion since we just changed the directory modtime.
		    if (--$DirHasChildren[dirname($path)] < 0)
			error("Impossible Error #4: too many elements: ", $DirHasChildren[dirname($path)]);

		    // if the directory is now empty, mark it for deletion
		    if (! $DirHasChildren[dirname($path)])
			$DirIsDeadEnd[dirname($path)] = true;

		}
	    } elseif (isset($DirIsDeadEnd[$path])) {
		if (!removeDirectory($path)) {
		    $failedRemovalCounter++;
		} else {
		    $deadEndDeletedCounter++;

		    if (--$DirHasChildren[dirname($path)] < 0)
			error("Impossible Error #5: too many elements: ", $DirHasChildren[dirname($path)]);

		    // if the directory is now empty, mark it for deletion
		    if (! $DirHasChildren[dirname($path)])
			$DirIsDeadEnd[dirname($path)] = true;

		}
	    }

	$end = microtime(true);

	/* ################################
	 * #   Report/Log what happened	  #
	 * ################################
	 */

	if ($ModifiedFilesCounter || $NewFilesCounter || $deletedFilesCounter || $reapedDeletedCounter || $DisappearedFilesCounter
	    || $expiredDeletedCounter || $deadEndDeletedCounter)
	{
	    // Print the date if writing to a log file
	    if (LOGGING)
		cprint("Started on: ", LOG_HEADER, "\n");

	    foreach ($deletedFileList as $file)
		cprint ('"', $file, '"', " removed.");

	    if ($deletedFilesCounter)
		// Add a new line for readability if we deleted files
		cprint();

	    if ($ModifiedFilesCounter)	    cprint ($ModifiedFilesCounter,	" files were modified.");
	    if ($NewFilesCounter)	    cprint ($NewFilesCounter,		" files were new.");
	    if ($DisappearedFilesCounter)   cprint ($DisappearedFilesCounter,	" files disappeared.");
	    if ($deletedFilesCounter)	    cprint ($deletedFilesCounter,	(REMOVE?" files were removed." :  " files have expired."));

	    if (! SHOW) {
		if ($reapedDeletedCounter)	cprint ($reapedDeletedCounter,  " now-empty directories were removed.");
		if ($expiredDeletedCounter)	cprint ($expiredDeletedCounter, " expired-empty directories were removed.");
		if ($deadEndDeletedCounter)	cprint ($deadEndDeletedCounter, " now-dead-end directories were removed.");
	    }

	    cprint ("\n", sprintf("Reaping took %0.02fs", $end - $start));
	    cprint ('---------------------------------');

	} elseif (! LOGGING)
	    cprint ("Nothing to do.");

	if ($failedRemovalCounter)
	    error ($failedRemovalCounter, " directory couldn't be removed.");

	saveDirectoryScannedDatas($dirPath, $knownDatas);

    }

    return $filesToDelete;

}


printHeader();

GetAndSetOptions ();
checkDataPath ();
fileGrimReaper ( getConfig () );



exit((int)($errorCount > 0));

/*
 *
 *
 */

// (c) John Wellesz for MikrosImage - September 2011
?>
