<?php
declare(strict_types=1);

/* File Grim Reaper v1.6 - It will reap your files!
 * (c) 2011-2025 John Wellesz
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

const VERSION = "1";
const REVISION = "6"; // Also remember to change the version at the top of both PHP files.
const RESPITE  = 12; // hours
const FOUND_ON = 0;
const FILE_M_TIME = 1;


require_once __DIR__ . '/src/classes.php';

$pid_file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fileGrimReaper.pid';

clearstatcache();

if (! defined("PROPER_USAGE"))
    die("Incorrect usage, you cannot execute this file directly!");

define ('UNAME', php_uname('n'));
error_reporting ( E_ALL | E_STRICT );
ini_set('error_log', dirname(realpath(__FILE__)) . DIRECTORY_SEPARATOR .UNAME."_errors.log");

const DEFAULT_CONFIG_FILE = "FileGrimReaper-paths.txt";

define ( 'NOW', time() );
define ( 'ISWINDOWS',  strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

ini_set('memory_limit','26G');
//ini_set('opcache.jit','1'); // cannot be set at runtime


$old_umaks = umask(0);


function seekSplObjectStorage(SplObjectStorage $s, int $position): void
{
    if ($position < 0) {
        throw new OutOfBoundsException('Position must be non-negative');
    }

    $s->rewind();
    $currentPos = 0;

    while ($s->valid() && $currentPos < $position) {
        $s->next();
        $currentPos++;
    }

    if ($currentPos !== $position) {
        throw new OutOfBoundsException("Position $position is out of bounds");
    }
}


function sortSplObjectStorage(
    SplObjectStorage $storage,
    callable $callback
): SplObjectStorage {
    $temp = [];
    foreach ($storage as $obj) {
        $temp[] = [$obj, $storage[$obj]];
    }

    usort($temp, $callback);

    $sorted = new SplObjectStorage();
    foreach ($temp as [$obj, $value]) {
        $sorted[$obj] = $value;
    }

    return $sorted;
}

function sortByname(SplObjectStorage $storage): SplObjectStorage {
    return sortSplObjectStorage($storage, function ($a, $b) {
        return strnatcmp((string)$a[0], (string)$b[0]);
    });
}

function isProcessRunning($pid) {
    if (!is_numeric($pid)) {
        throw new InvalidArgumentException("PID must be a number");
    }

    $output = '';
    $return = null;
    $command = ISWINDOWS ? 'tasklist /FI "PID eq '.$pid.'" 2>NUL | find /I "'.$pid.'">NUL' : 'ps -p '.$pid;

    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );

    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $return = proc_close($process);
    }

   if (!empty($errorOutput)) {
        error("Process running check command '$command' failed: $errorOutput");
        return false;
    }

    return ISWINDOWS ? $return === 0 : strpos($output, "$pid") !== false;
}

function removeFile (string | Path $path)
{
    if ($path instanceOf Path)
        $path = (string)$path;

    if (! SHOW && ! @unlink($path)) {
        // Note that on window$ one needs to use rmdir on symbolic links pointing to directories... micro$oft never fails to disappoint!
        if (!(@chmod($path, 0777) && @unlink($path)) && (!ISWINDOWS || !@rmdir($path)))
            error("Couldn't remove '$path'");
        else
            return true;
    } else
        return true;

    return false;
}

function removeDirectory (string | Path $path)
{
    if (! SHOW && ! @rmdir((string)$path))
        error();
    else
        return true;

    return false;
}

function cprint ()
{
    $args = func_get_args();

    $toPrint = str_replace("\n", "\r\n", implode("", $args))."\r\n";

    addToLog($toPrint);

    return fwrite(STDOUT, $toPrint);
}

function unlogged_cprint()
{
    global $LOGFILEPATH;

    $tmp = $LOGFILEPATH;
    $LOGFILEPATH = "";

    $written = call_user_func_array('cprint', func_get_args());

    $LOGFILEPATH = $tmp;

    return $written;
}

function temp_cprint()
{
    //write something and place the cursor back where it was
    $args = func_get_args();

    $toPrint = str_replace("\n", "\r\n", implode("", $args));

    $written = fwrite(STDOUT, $toPrint);

    return fwrite(STDOUT, str_pad("", $written, chr(0x8)));
}

function printUsage ()
{
    cprint ( "Usage: ", $_SERVER['PHP_SELF'], " --reap | --show [--config=configFilePath] [--logging] [--doNotCreateDirs]\n");
}

function printHeader ()
{
    global $argc;

    if ($argc > 1)

        cprint ("\nFile Grim Reaper version ",VERSION,".",REVISION," Copyright (C) 2011-2025 John Wellesz\n",
            <<<SHORTWELCOME

    This program comes with ABSOLUTELY NO WARRANTY.
    This is free software, and you are welcome to redistribute it
    under certain conditions; see the provided GPL.txt for details.

SHORTWELCOME
    );

    else
        cprint("\nFile Grim Reaper version ",VERSION,".",REVISION," Copyright (C) 2011-2025 John Wellesz\n",

            <<<LONGWELCOME

Project home:
        https://github.com/2072/File-Grim-Reaper

Bug reports/Suggestions:
        https://github.com/2072/File-Grim-Reaper/issues

    File Grim Reaper is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    File Grim Reaper is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with File Grim Reaper. If not, see <http://www.gnu.org/licenses/>.

## Options

    -r, --reap      Removes expired files, directories and updates snapshots.

    -s, --show      Shows what would happen with the --reap command (doesn't
                    actually remove anything and doesn't update snapshots).

    -c=FilePath, --config=FilePath
                    Uses the specified paths configuration file (one path per line).

    -l, --logging   Creates log files for each configured directories. The log
                    files will be stored in a 'Logs' sub-folder in the
                    'FileGrimReaper-Datas' directory. The log file will be
                    named in the following way: COMPUTERNAME_SANITIZED-PATH.log
                    The log file will be written if and only if something
                    changed in the monitored folder. The number of new and
                    modified files is given as well as the full path of every
                    deleted file.

    -y, --daylightsavingbug
                    On Microsoft Windows platforms, on some filesystems (such
                    as FAT or network shares), there is a "feature" that makes
                    filemtime() report a different file modification time
                    wether Daylight Saving is active or not.

                    This option enables the detection of this bug to prevent
                    files from appearing modified (and thus resetting their
                    expiry) when DLS status changes.
                    There is one caveat though: if a file is replaced with a
                    file whose modification time is exactly one hour apart from
                    the original file (and older than a day), the file expiry
                    won't be reset and the file will be deleted sooner than
                    expected.

    -d, --doNotCreateDirs
                    Do not create missing directories in the configuration
                    file. The default is to create those directories if they are defined as some
                    users tend to remove them either by accident or ignorance.

LONGWELCOME
    );
}

$ERRORCOUNT = 0;
function error ()
{
    global $ERRORCOUNT;

    $ERRORCOUNT++;

    $args = func_get_args();

    $last_error = error_get_last();

    if (! empty($last_error)) {

        if (count($args))
            $args[] = "\n\tLast PHP error: ";
        else
            $args[] = "ERROR: ";

        $args[] = $last_error['message'];
    }

    $toPrint = str_replace("\n", "\r\n", implode("", $args))."\r\n";
    fwrite(STDERR, $toPrint);

    addToLog($toPrint);
}

$LOGFILEPATH = "";
$STARTHEADERPRINTED = false;


function IsStringInc($str_1, $str_2) {

    if (strlen($str_1) != strlen($str_2) || $str_1 == $str_2)
        return false;

    $diffs = array(); $d = 0; $diffOffset = 0;
    for ($i=0; $i < strlen($str_1); ++$i) {

        if ($str_1[$i] != $str_2[$i]) {

            $diffOffset = $i - $d;

            if (!isset ($diffs[$diffOffset]))
                $diffs[$diffOffset] = array (array(),array());

            $diffs[$diffOffset][0][]  =  $str_1[$i];
            $diffs[$diffOffset][1][]  =  $str_2[$i];

            ++$d;
        }
    }

    if (count($diffs) > 1)
        return false;

    $diffs[$diffOffset][0] = (int)implode($diffs[$diffOffset][0]);
    $diffs[$diffOffset][1] = (int)implode($diffs[$diffOffset][1]);

    if ($diffs[$diffOffset][0] + 1 == $diffs[$diffOffset][1])
        return true;
    else
        return false;
}


function addToLog ($toWrite)
{
    global $LOGFILEPATH, $STARTHEADERPRINTED;

    if (! (defined("LOGGING") && LOGGING && !empty($LOGFILEPATH)))
        return;

    if (! $STARTHEADERPRINTED) {
        $header = "---------------------------------\r\n" . "Started on: " . LOG_HEADER . "\r\n\r\n";
        $STARTHEADERPRINTED = true;
    } else
        $header = "";

    error_log("$header$toWrite", 3, $LOGFILEPATH);
}


function getDirectoryDepth(Path $path)
{
    return $path->getDepth();
}

function errorExit($code)
{
    $args = func_get_args();
    $args[0] = "FATAL ERROR: ";

    call_user_func_array("error", $args);

    global $old_umaks;
    umask($old_umaks);
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
        "YEAR"      => 3600 * 24 * 365,
        "MONTH"     => 3600 * 24 * 30,
        "DAY"       => 3600 * 24,
        "HOUR"      => 3600,
        "MINUTE"    => 60,
        "SECOND"    => 1,
    );

    if ( preg_match($nameFormat, $name, $matches) ) {
        if ( is_dir ($name) || (!DONOTCREATEDIRS && mkdir($name, 0777))) {
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

function isMTimeTheSame ($a, $b)
{
    if ($a == $b)
        return true;

    // if the difference is exactly 1 hour and the file is older than 1 day,
    // it's a fucking daylight saving issue (thank you Microsoft)
    if ( DAYLIGHTSAVINGBUG && (abs($a-$b) == 3600) && (NOW - $a > 86400) )
        return true;

    return false;
}

function getPathMTime($path) {
    $stats = @lstat($path);
    if (is_array($stats) && isset($stats['mtime'])) {
        return (int)$stats['mtime'];
    }
    error("Could not get modification time of '$path'");
    return false;
}

function getSplInfoSafeMTime(SplFileInfo $splinfo) {
   try {
        // Attempt to get the modification time normally.
        return !$splinfo->isLink() ? $splinfo->getMTime() : getPathMTime($splinfo->getPathname());
    } catch (Exception $e) {
        // Fallback: use lstat to get the mtime of the link itself.
        return getPathMTime($splinfo->getPathname());
    }
}

function GetAndSetOptions ()
{
    $longOptions = array (
        "config::",
        "show",
        "reap",
        "logging",
        "daylightsavingbug",
        "doNotCreateDirs"
    );

    $setOptions = getopt("c::srlyd", $longOptions);


    if (isset($setOptions['s']) || isset($setOptions['show']))
        define ('SHOW', true);
    else
        define ('SHOW', false);

    if (isset($setOptions['l']) || isset($setOptions['logging']))
        define ('LOGGING', true);
    else
        define ('LOGGING', false);

    if (isset($setOptions['r']) || isset($setOptions['reap']))
        define ('REAP', true);
    else
        define ('REAP', false);

    if (isset($setOptions['y']) || isset($setOptions['daylightsavingbug']))
        define ('DAYLIGHTSAVINGBUG', true);
    else
        define ('DAYLIGHTSAVINGBUG', false);

    if (isset($setOptions['d']) || isset($setOptions['doNotCreateDirs']))
        define ('DONOTCREATEDIRS', true);
    else
        define ('DONOTCREATEDIRS', false);

    if (SHOW && REAP)
        errorExit(1, '--reap and --show options are exclusive!');
    elseif (! (SHOW || REAP)) {

        global $argc;

        if ($argc > 1) {
            printUsage ();
            errorExit(1, "Action is missing!");
	} else {
		global $old_umaks;
		umask($old_umaks);
		exit(0);
	}
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
        $configPath = CONFIG;
    else
        $configPath = getcwd() . '/' . DEFAULT_CONFIG_FILE;

    if (file_exists($configPath))
        $config = array_map('trim', file($configPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES));
    else
        errorExit(1, 'No configuration file provided and no file named "', DEFAULT_CONFIG_FILE, '" found in ', getcwd());

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


function mySerialize($data): string {
    static $warningPrintedOnce = false;

    if (function_exists('igbinary_serialize'))
        return igbinary_serialize($data);
    else {
        if (!$warningPrintedOnce) {
            unlogged_cprint("⚠️ Warning: using standard php serialize because igbinary extension not available (igbinary is recommended)");
            $warningPrintedOnce = true;
        }
        return serialize($data);
    }
}

const CHUNK_MARKER = "\n---CHUNK---\n";

function serializeToFile(string $filename, SplObjectStorage $data): int {
    if ($data instanceOf SplObjectStorage && count($data))
        $data->rewind();

    $handle = fopen($filename, 'wb');

    if (!$handle) {
        throw new RuntimeException("Cannot open file: $filename");
    }

    $written = 0;
    foreach ($data as $dirName) {
        $dirItems = $data[$dirName];

        $singlePathStorage = new SplObjectStorage();
        $singlePathStorage[$dirName] = $dirItems;
        //        unlogged_cprint("serialized d: ", $dirName, " with ", count($data[$dirName]), " files");
        $writtenNow = fwrite($handle, mySerialize($singlePathStorage) . CHUNK_MARKER);

        if (!$writtenNow)
            return false;

        $written += $writtenNow;
    }
    fclose($handle);

    return $written;
}

function myUnSerialize(string $str) {
    static $warningPrintedOnce = false;

    if (function_exists('igbinary_unserialize')) {
        $data = igbinary_unserialize($str);
        if ($data !== false && $data !== null)
            return $data;
        else {
            unlogged_cprint("⚠️ Warning: using standard php unserialize because igbinary failed (assuming default serialization)");
            return unserialize($str);
        }

    } else {
        if (!$warningPrintedOnce) {
            unlogged_cprint("⚠️ Warning: using standard php unserialize because igbinary extension not available (igbinary is recommended)");
            $warningPrintedOnce = true;
        }
        return unserialize($str);
    }
}

function unserializeFromFile_gen(string $filename): Generator {
    $handle = fopen($filename, 'rb');
    if (!$handle) {
        throw new RuntimeException("Cannot open file: $filename");
    }

    $buffer = '';
    while (!feof($handle)) {
        $buffer .= fread($handle, 1024*1024); // Read in 1Mb chunks

        while (($pos = strpos($buffer, CHUNK_MARKER)) !== false) {
            $chunk = substr($buffer, 0, $pos);
            $buffer = substr($buffer, $pos + strlen(CHUNK_MARKER)); // Remove processed part

            if ($chunk !== '') {
                $data = myUnSerialize($chunk);
                if ($data !== false && $data !== null) {
                    yield $data;
                } else
                    yield false;
            }
        }
    }

    // Handle any remaining data
    if ($buffer !== '' && $buffer !== CHUNK_MARKER) {
        $data = myUnSerialize($buffer);
        if ($data !== false  && $data !== null) {
            yield $data;
        } else
            yield false;
    }

    fclose($handle);
}

function unserializeFromFile(string $filename) : SplObjectStorage | array | false {
    $data = new SplObjectStorage();
    $failed = false;
    foreach (unserializeFromFile_gen($filename) as $singleOrMultipPathStorage) {

        if (is_array($singleOrMultipPathStorage)) { // old format
            $data = $singleOrMultipPathStorage;
            break;
        }


        if ($singleOrMultipPathStorage !== false) {
            if (count($singleOrMultipPathStorage) > 1)
                cprint("⚠️ $filename: old non-chunked serialization found: ", count($singleOrMultipPathStorage), " paths");
            foreach ($singleOrMultipPathStorage as $dirName) {
                //$temp = $singleOrMultipPathStorage[$dirName]->current();
                //unlogged_cprint("unserialized d: ", $dirName, " - did: ", spl_object_id($dirName), " with ", count($singleOrMultipPathStorage[$dirName]), " files - first: ", $temp, " fid: ", spl_object_id($temp));
                $data[$dirName] = $singleOrMultipPathStorage[$dirName];
                $data[$dirName]->rewind();
            }
        } else {
            $failed = true;
            break;
        }
    }

    if (!$failed) {
        if ($data instanceOf SplObjectStorage && count($data))
            $data->rewind();
        return $data;
    } else
        return false;
}

function getDirectoryScannedDatas ($path, &$lastScanned=false): SplObjectStorage | false
{
    $dataFileName = preg_replace("#[\\\\/]|:\\\\#", "-", $path);

    if (LOGGING) {
        global $LOGFILEPATH, $STARTHEADERPRINTED;
        $LOGFILEPATH = LOGS_PATH . "/".UNAME."_$dataFileName.log";
        $STARTHEADERPRINTED = false;
    }

    if (! $dataFileName)
        errorExit(2, 'Impossible error #1: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/'. UNAME . '_' . $dataFileName . '.data.serialized';

    if (file_exists($dataFileName) && @filemtime($dataFileName)) { // sometimes file_exists() returns true whereas the file doesn't exist... Clearstatcache is not enough apparently (observed on OSX 10.5 on 2012-12-17 with php 5.3.8)

        $lastScanned = filemtime($dataFileName);

        $data = false;
        $data = unserializeFromFile($dataFileName);

        if ($data === false || $data === null || !($data instanceOf SplObjectStorage || is_array($data))) {
            if (function_exists('igbinary_unserialize'))
                error("Could not unserialize data.");
            else
                error("Could not unserialize data. installing igbinary might solve the problem");

            return false;
        }

        if (is_array($data)) {
            // convert very old format
            $firstElement = current($data);
            if ($firstElement !== FALSE && isset($firstElement["foundOn"]) ) {
                cprint("ℹ️ Converting snapshot from VERY old format...");
                $cData = [];
                foreach($data as $fullFilePath=>$times) {
                    $cData[dirname($fullFilePath)][basename($fullFilePath)] = [
                        FOUND_ON => $times["foundOn"],
                        FILE_M_TIME => $times["fileMTime"]
                    ];
                }
                $data = $cData;
            }

            $new = new SplObjectStorage();

            cprint("ℹ️ Converting snapshot $dataFileName from old format...");
            foreach ($data as $dir => $files) {
                $path = Path::fromString($dir);
                $new[$path] ??= new SplObjectStorage();

                foreach ($files as $file => $info) {
                    $new[$path][Name::get($file)] = $info;
                }
            }
            cprint("Done: " . count($new) );

            return $new;
        } elseif ($data instanceOf SplObjectStorage) {
            printStatus();
            unlogged_cprint("\tCreating name name cache ", number_format(Name::getPoolSize()));

            foreach ($data as $dir) {
                $files = $data[$dir];
                foreach (clone $files as $file) {
                    $canonical = Name::get((string)$file);
                    if (spl_object_id($file) != spl_object_id($canonical)) {
                        $fTimes = $files[$file];
                        unset($files[$file]);
                        $files[$canonical] = $fTimes;
                        //cprint("\t\tdebug: ", $file, " was migrated to canonical version: ", spl_object_id($canonical));
                    }

                }
            }
            return $data;
        } else {
            error('Error loading data for directory: ', $path);
            return false;
        }
    } else
        return new SplObjectStorage();
}

function saveDirectoryScannedDatas ($path, SplObjectStorage $datas)
{
    global $LOGFILEPATH;

    if (SHOW) {
        cprint("Changes not saved, showing only.");
        $LOGFILEPATH = false;
        return;
    }

    $dataFileName = preg_replace("#[\\\\/]|:\\\\#", "-", $path);

    if (! $dataFileName)
        errorExit(2, 'Impossible error #2: preg_replace() failed on: ', $path);

    $dataFileName = DATA_PATH . '/'. UNAME . '_' . $dataFileName . '.data.serialized';

    $tempFileName = $dataFileName . sprintf("_%X", crc32(microtime()));

    // Write the data in a temporary file and really check it's complete
    if ( ($written = serializeToFile($tempFileName, $datas)) === false
        || filesize($tempFileName)                           !== $written )

        error("Couldn't write scanned datas in: ", $tempFileName);
    else {
        // If all was written then replace the original file
        // This prevents to damage the original file if the disk happened to be full
        if (! rename($tempFileName, $dataFileName))
            error("Couldn't save scanned datas in: ", $dataFileName);
    }

    $LOGFILEPATH = false;
}

function printStatus(): void {
    unlogged_cprint("\t\tName cache: ", number_format(Name::getPoolSize()), " -  Path cache: p-: ", number_format(Path::getCacheSizes()[0]), " - fc-: ", Path::getCacheSizes()[1],
        " (max of ",number_format(memory_get_peak_usage() / 1024 / 1024) ," Mb of RAM used), ", "current usage: ", number_format(memory_get_usage() / 1024 / 1024), " Mb" );
}

$pathPidFile = "";
function fileGrimReaper ($dirToScan)
{
    global $pathPidFile, $pid_file;

    $opCacheState = opcache_get_status();

    if (!is_array($opCacheState) || !isset($opCacheState['jit']['on']) || !$opCacheState['jit']['on'])
        cprint("⚠️ oopcache.jit is not enabled in config");


	// sort directories so the shortest durations are scanned first
	uasort($dirToScan, function($a, $b) {$a = $a['duration']; $b=$b['duration']; return ($a < $b) * -1 + ($a > $b) * 1; });

    foreach ($dirToScan as $dirPath=>$dirParam) {
        Name::_resetPool();
        Path::_reset();

		if (!empty($pathPidFile))
			unlink ($pathPidFile);

		$start          = microtime(true);
		$lastScanned    = 0;

        // The log file is named after the directory being checked so...
        unlogged_cprint("\nℹ️ Now considering files in: ", $dirPath, '...');

		// get previous scan datas
        if (false === ($knownDatas = getDirectoryScannedDatas($dirPath, $lastScanned))) {
            cprint("🔴 Error: Unserialize failed, skipping ", $dirPath);
            continue;
        }

        printStatus();



		// Check if another instance is already scanning this directory
		$pathPidFile = $pid_file.".".crc32($dirPath);
		if (file_exists($pathPidFile)) {
			$pid = trim(file_get_contents($pathPidFile));
			if (isProcessRunning($pid)) {
				cprint( "Another instance ($pid) is already running. Skipping.");
				$pathPidFile = "";
				continue;
			} else {
				unlink($pathPidFile);
			}
		}

		file_put_contents($pathPidFile, getmypid());




		if ( (NOW - $lastScanned) < RESPITE * 60 * 60 && $dirParam['duration'] > 2 * RESPITE * 60 * 60) {
			unlogged_cprint("ℹ️ Skipping (snapshot is just ", sprintf("%0.1f", (NOW - $lastScanned) / 3600)," hours and life is ", sprintf("%0.1f", $dirParam['duration'] / 86400), " days)");
			continue;
		}

		/* ########################################
		 * # Take a new snapshot of the directory #
		 * ########################################
		 */

		unlogged_cprint("\tTaking a new snapshot...");
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);

		$NewSnapShot        = new SplObjectStorage();
		$DirHasChildren     = new SplObjectStorage();
		$FoundFilesCounter  = 0;
		$limitTripped       = false;

		if ( $iterator ) {
			foreach ($iterator as $file=>$fileinfo) {

				// initialize the per directory child counter
				if ($fileinfo->isDir() && ! $fileinfo->isLink() && ! isset($DirHasChildren[Path::fromString($fileinfo->getPathName())]) ) {
					// Mark the directory as empty the first time we see it,
					// because if there are no file in it, it's the only time
					// we'll see it.
					$DirHasChildren[Path::fromString($fileinfo->getPathname())] = 0;
				}

                $p = Path::fromString($fileinfo->getPath());
                //unlogged_cprint("found path ", $p, "with id: ", spl_object_id($p));
				// Count elements, increment the child number of the parent directory
				if (! isset($DirHasChildren[$p]))
					$DirHasChildren[$p] = 1;
				else
					$DirHasChildren[$p] = $DirHasChildren[$p] + 1;

				//If it's not a file
				if (! $fileinfo->isFile() && ! $fileinfo->isLink()) {

					// If neither file or directory, then we have a problem
					if (! $fileinfo->isDir())
						error("'$file' is immortal... It needs renaming so it can be reaped when its time comes.");

					continue;
				}

				if (false === ($fileSafeMTime = getSplInfoSafeMTime($fileinfo))) {
					error("'$file' is impervious to our time scanner! Its modification time could not be determined...");
					continue;
				}


                $NewSnapShot[$p] ??= new SplObjectStorage();
				$NewSnapShot[$p][Name::get(basename($fileinfo->getPathname()))] = [
					FOUND_ON => time(),
					FILE_M_TIME => getSplInfoSafeMTime($fileinfo),
				];
				$FoundFilesCounter++;

				if (! ($FoundFilesCounter % 100) )
                    temp_cprint(number_format($FoundFilesCounter), " files found (scanning '...", substr($fileinfo->getPath(),-20), DIRECTORY_SEPARATOR,"')");

                if (! ($FoundFilesCounter % 500_000))
                    printStatus();

				if (false && $FoundFilesCounter == 10000000) {
					$limitTripped = true;
					cprint("⚠️ Warning: 10M files limit tripped, stopping there...");
					break;
				}
			}
		} else {
			error("Impossible to take snapshot for directory '", $dirToScan, "'...");
			continue;
        }

		/* ################################################
		 * # Compare the new snapshot with the known data #
		 * ################################################
		 */

        printStatus();
		unlogged_cprint("\tComparing $FoundFilesCounter files with saved snapshot...");

		$ModifiedFilesCounter       = 0;
		$DisappearedFilesCounter    = 0;
		$filesToDelete              = new SplObjectStorage();

        foreach (clone $knownDatas as $dirName) {
            $dirItems = $knownDatas[$dirName];

            foreach (clone $dirItems as $fileName) {
                $times = $dirItems[$fileName];
				// if the file is still there
				if (isset($NewSnapShot[$dirName][$fileName])) {

					// If the file has NOT been modified since the last scan,
					// check if it's elligeable for deletion
					if (isMTimeTheSame( $NewSnapShot[$dirName][$fileName][FILE_M_TIME], $times[FILE_M_TIME])) {
                        if ($times[FOUND_ON] + $dirParam['duration'] < NOW) {
                            $filesToDelete[$dirName] ??= new SplObjectStorage();
                            $filesToDelete[$dirName]->attach($fileName);
                        }
                    } else {
                        $newTimes = [
                            FOUND_ON => NOW,
                            FILE_M_TIME => $NewSnapShot[$dirName][$fileName][FILE_M_TIME],
                        ];
						// treat as a new file
						$knownDatas[$dirName][$fileName] = $newTimes;
						$ModifiedFilesCounter++;
					}

                } elseif (!$limitTripped) { // only if the limit was not tripped
                    /*
                    unlogged_cprint("did not find ", $dirName, " - did: ", spl_object_id($dirName), " ", DIRECTORY_SEPARATOR, " : ", $fileName, " fid: ", spl_object_id($fileName), " path known? ", isset($NewSnapShot[$dirName]) );
                    if (isset($NewSnapShot[$dirName])) {
                        $t = $NewSnapShot[$dirName]->current();
                        unlogged_cprint("\t dir is known and its first file is ", $t, " with id:", spl_object_id($t), " and data: ", print_r($NewSnapShot[$dirName][$t], true));
                    }
                     */
					// the file is no longer there so delete its entry in $KnownDatas
					// this is where the list is cleaned
                    unset ($knownDatas[$dirName][$fileName]);

					$DisappearedFilesCounter++;

					if (!count($knownDatas[$dirName]))
						unset($knownDatas[$dirName]);
				}
            }
        }

		/* ################################
		 * # Add new items to $knownDatas #
		 * ################################
		 */
        printStatus();
        unlogged_cprint("\tAdding new files...");

		$NewFilesCounter        = 0;
        foreach ($NewSnapShot as $dirName) {
            $dirItems = $NewSnapShot[$dirName];

            if (! isset ($knownDatas[$dirName])) {
                $knownDatas[$dirName] = $NewSnapShot[$dirName];
                $NewFilesCounter += count($knownDatas[$dirName]);
            } else
                foreach ($dirItems as $fileName) {
                    $times = $dirItems[$fileName];

                    if (! isset ($knownDatas[$dirName][$fileName])) {
                        $knownDatas[$dirName][$fileName] = $times;
                        $NewFilesCounter++;
                    }
            }
        }
        unset($dirName, $dirItems, $fileName);
		unset($NewSnapShot);
        unlogged_cprint("$NewFilesCounter added");
        printStatus();



		/* ##########################
		 * # Reap the expired files #
		 * ##########################
		 */

        printStatus();
		unlogged_cprint("\tReaping expired files...");
		$deletedFileList        = new SplObjectStorage();
		$deletedFilesCounter    = 0;
		$reapedDirectories      = []; // used to remove empty dirs after deleting files

		// Delete the files in a sorted order so the delete log is readable
		// Dir sort:
		$filesToDelete = sortByname($filesToDelete);

        foreach($filesToDelete as $dirName) {
            $fileNames = $filesToDelete[$dirName];


			foreach (sortByname($fileNames) as $fileName) {
				if (removeFile($dirName.DIRECTORY_SEPARATOR.$fileName)) {

					unset($knownDatas[$dirName][$fileName]);
					if (!count($knownDatas[$dirName]))
						unset($knownDatas[$dirName]);

					$deletedFileList[$dirName] ??= new SplObjectStorage();
					$deletedFileList[$dirName]->attach($fileName);
					$deletedFilesCounter++;

					$reapedDirectories[crc32((string)$dirName)] = true;
					$DirHasChildren[$dirName] = ($DirHasChildren[$dirName] - 1);
				}
			}
		}


		/* ################################
		 * #  Removed orphaned directory  #
		 * ################################
		 */

        printStatus();
		unlogged_cprint("\tRemoving orphaned directories... ", count($DirHasChildren), " directories to check");

        $scannedRootPath = Path::fromString($dirPath);
		// Protect the base directory from deletion (adding a virtual child)
		if (!isset($DirHasChildren[$scannedRootPath]))
			$DirHasChildren[$scannedRootPath] = 1;
		else
            $DirHasChildren[$scannedRootPath] = $DirHasChildren[$scannedRootPath] + 1;

        unset($scannedRootPath);

		// sort the array using the path depth, the deepest first
        $DirHasChildren = sortSplObjectStorage ($DirHasChildren, function ($a, $b) {
            if (getDirectoryDepth($a[0]) == getDirectoryDepth($b[0]))
                return 0;

            if (getDirectoryDepth($a[0]) > getDirectoryDepth($b[0]))
                return -1;

            if (getDirectoryDepth($a[0]) < getDirectoryDepth($b[0]))
                return 1;
        });


		// deleted directories counters
		$reapedDeletedCounter   = 0;
		$expiredDeletedCounter  = 0;
		$deadEndDeletedCounter  = 0;
		$failedRemovalCounter   = 0;
		$DirIsDeadEnd           = array();

        foreach ($DirHasChildren as $path) {

            $parentPath = Path::fromString(dirname((string)$path));
			// the directory is empty and files were reaped inside it
            if ( !$DirHasChildren[$path] && isset($reapedDirectories[crc32((string)$path)])) {

				if (! removeDirectory($path) ) {
					$failedRemovalCounter++;
				} else {
					cprint('Removed empty (reaped) directory: ', $path);
					$reapedDeletedCounter++;

					// decrease parent children number, this will be enough to trigger a deletion (since we go from
					// the child to the parent) but not a deletion of a parent directory containing only directories
					// where no files were reaped.
					if (($DirHasChildren[$parentPath] = $DirHasChildren[$parentPath] - 1) < 0)
						error("Impossible Error #3: too many elements: ", $DirHasChildren[$parentPath]);

					// if the directory is now empty, mark it for deletion
					if (! $DirHasChildren[$parentPath])
						$DirIsDeadEnd[crc32((string)$parentPath)] = true;
				}
			} elseif (isset($DirIsDeadEnd[crc32((string)$path)])) {
				if (! removeDirectory($path)) {
					$failedRemovalCounter++;
				} else {
					$deadEndDeletedCounter++;

					if (($DirHasChildren[$parentPath] = $DirHasChildren[$parentPath] - 1) < 0)
						error("Impossible Error #5: too many elements: ", $DirHasChildren[$parentPath]);

					// if the directory is now empty, mark it for deletion
					if (! $DirHasChildren[$parentPath])
						$DirIsDeadEnd[crc32((string)$parentPath)] = true;

				}
				// the directory is empty and is older than allowed duration
			} elseif (!$DirHasChildren[$path] && (@filemtime((string)$path) + $dirParam['duration'] < NOW)) {

				if (! removeDirectory($path)) {
					$failedRemovalCounter++;
				} else {
					//cprint('Removed empty (old) directory: ', $path);
					$expiredDeletedCounter++;

					// decrease parent children number, this won't be enough to trigger a deletion since we just changed the directory modtime.
					if (($DirHasChildren[$parentPath] = $DirHasChildren[$parentPath] - 1) < 0)
						error("Impossible Error #4: too many elements: ", $DirHasChildren[$parentPath]);

					// if the directory is now empty, mark it for deletion
					if (! $DirHasChildren[$parentPath])
						$DirIsDeadEnd[crc32((string)$parentPath)] = true;

				}

            }
        }

		$end = microtime(true);
        printStatus();

		/* ################################
		 * #   Report/Log what happened   #
		 * ################################
		 */

		if ($ModifiedFilesCounter || $NewFilesCounter || $deletedFilesCounter || $reapedDeletedCounter || $DisappearedFilesCounter
			|| $expiredDeletedCounter || $deadEndDeletedCounter || $failedRemovalCounter)
		{

			$sequenceStart = false; $skippedCount = 0;
			$cprintFile = function ($file) { cprint ("\t", '"', $file, '"', " removed!"); };
			$lastIndex = 0;
			$isLast = function ($i) use (&$lastIndex) { return ($i == $lastIndex); };


            foreach($deletedFileList as $dirName) {
                $fileNames = $deletedFileList[$dirName];
				$lastIndex = count($fileNames) - 1;
				cprint("Directory: '$dirName':");
                foreach(clone $fileNames as $i => $fileName) {
                    $previous = null;
                    if ($i > 0) {
                        seekSplObjectStorage($fileNames, $i - 1);
                        $previous = $fileNames->current();
                    }

					// Are we inside a sequentially-numbered file list?
					$inSequence = $i > 0
						&& ! $isLast ($i)
						&& IsStringInc((string)$previous, (string)$fileName);

					// We are not and were not inside a file sequence
					if (! $inSequence && ! $skippedCount) {
						// let's echo the file name then...
						$cprintFile ((string)$fileName);
					} else
						++$skippedCount;

					if (! $inSequence && $skippedCount) {

						// display the number of file we skipped echoing
						if ($skippedCount - 1 - !$isLast ($i) > 1 )
							cprint ("[...] ", '(', $skippedCount - 1 - !$isLast ($i), ' files)');

						// if we haven't reached the end of the list
						if ( ! $isLast ($i) )
							// we echo the last file of the sequence
							$cprintFile ((string)$previous);

						// echo the file we're on since it wasn't displayed
						$cprintFile ((string)$fileName);

						$skippedCount = 0;
					}

				}
			}

			if ($deletedFilesCounter)
				// Add a new line for readability if we deleted files
				cprint();

			cprint("$FoundFilesCounter files considered:");

			if ($ModifiedFilesCounter)      cprint ($ModifiedFilesCounter,      " files were modified.");
			if ($NewFilesCounter)           cprint ($NewFilesCounter,           " files were new.");
			if ($DisappearedFilesCounter)   cprint ($DisappearedFilesCounter,   " files disappeared.");
			if ($deletedFilesCounter)       cprint ($deletedFilesCounter,       " files were removed.");

			if ($expiredDeletedCounter) cprint ($expiredDeletedCounter, " expired-empty directories were removed.");

			//if (! SHOW) { // Those values are not accurate in this mode
			if ($reapedDeletedCounter)  cprint ($reapedDeletedCounter,  " now-empty directories were removed.");
			if ($deadEndDeletedCounter) cprint ($deadEndDeletedCounter, " now-dead-end directories were removed.");
			//}

			if ($failedRemovalCounter)
				error ($failedRemovalCounter, " directories couldn't be removed.");

			if (SHOW)
				cprint ('💡NOTE: Nothing was actually done (--show was set)');

            cprint ("\n", sprintf("Reaping took %0.02fs", $end - $start));
		} else
			unlogged_cprint ("ℹ️ Nothing to do. $FoundFilesCounter files were found");


        printStatus();
        // clear as much ram as possible before serialization...
        unlogged_cprint("\tcleaning up memory...");
		unset($deletedFileList, $DirHasChildren, $filesToDelete);
        Name::_resetPool();
        Path::_reset();
        printStatus();
        unlogged_cprint("\tSaving updated snapshot...");
		saveDirectoryScannedDatas($dirPath, $knownDatas);
		unset($knownDatas);
        printStatus();
		unlink ($pathPidFile);
		$pathPidFile = "";

    }
}

printHeader();

GetAndSetOptions ();
checkDataPath ();
fileGrimReaper ( getConfig () );


global $old_umaks;
umask($old_umaks);


exit((int)($ERRORCOUNT > 0));

?>
