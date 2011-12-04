# File Grim Reaper

## Purpose

File Grim Reaper is a temporary file manager. It monitors a configured set of
directories with independent expiry. The expiry of each folder is contained in
the very name of each folder.

File Grim Reaper is meant to be run as a cron job. Each time the program is run
it scans the configured directories and takes a snapshot of their content
comparing the result with the previous snapshot.

Files get deleted when they have been in the snapshot without being modified for
longer than the folder expiry. This insures that if an old file is moved to one
of the monitored directory, its unchanged modification time will not trigger its
untimely deletion upon the next run.

File Grim Reaper also recursively removes directories left empty after the
files it contained are removed.

Real empty directories (a directory which never contained anything) are deleted
when their modification date expires.

## Configuration

By default, File Grim Reaper looks for a file named 'FileGrimReaper-paths.txt'
in the current working directory. You can also provide the configuration file
using the command line option --config 'file name'.

The configuration contains the directory to be monitored, one per line. Empty
lines and spaces at start or end of lines are ignored.

The folder names have to respect the following format:

    (\w+)__TO_KEEP_(\d+)_(YEAR|MONTH|DAY|HOUR|MINUTE|SECOND)S?$

**Exemple of a configuration file on a Windows machine:**

    O:\____AUTODELETE__TO_KEEP_7_DAYS
    O:\____AUTODELETE__TO_KEEP_1_MONTH

    C:\Documents and Settings\All Users\Desktop\TEMP__TO_KEEP_2_HOURS

**Exemple of a configuration file on a Unix machine:**

    /Volumes/Stripe/___AUTODELETE___TO_KEEP_1_DAY
    /Volumes/Stripe/___AUTODELETE___TO_KEEP_7_DAYS
    /Volumes/Stripe/___AUTODELETE___TO_KEEP_1_MONTH
    /Volumes/Stripe/___AUTODELETE___TO_KEEP_6_MONTHS

    /Volumes/Small/___AUTODELETE___TO_KEEP_1_MONTH

    /Users/graph_local/Desktop/___AUTODELETE___TO_KEEP_2_HOURS
    /Users/graph_local/Desktop/___AUTODELETE___TO_KEEP_1_DAY

## Data

File Grim Reaper stores the snapshots it takes from each configured path in a
directory named "FileGrimReaper-Datas" which is located in the same directory
as FileGrimReaper.php.
Each file is named after the computer name where the script is run and a
sanitized version of the path.

Example:

    HV-NODAL2_O-____AUTODELETE__TO_KEEP_7_DAYS.data.serialized
    pglmac15_-Volumes-Stripe_PGLMAC15-___AUTODELETE___TO_KEEP_1_MONTH.data.serialized

Additionally if internal errors occur, such as a PHP exception, a file "computer-name_errors.log" 
will be created in FileGrimReaper.php's directory.


## Options

    -r, --reap	    Removes expired files and directories and updates snapshots.

    -s, --show	    Shows what would happen with the --reap command (doesn't
		    actually remove anything and doesn't update snapshots).

    -c, --config    Uses the specified configuration file.

    -l, --logging   Creates log files for each configured directories. The log files
		    will be stored in a 'Logs' sub-folder in the 'FileGrimReaper-Datas' directory.
		    The log file will be named in the following way:
		    COMPUTERNAME_SANITIZED-PATH.log
		    The log file will be written if and only if something changed
		    in the monitored folder. The number of new and modified files is given as well
		    as the full path of every deleted file.

    -y, --daylightsavingbug
		    On Microsoft Windows platforms, on some filesystems (such as
		    FAT or network shares), there is a "feature" that makes filemtime() report a
		    different file modification time wether Daylight Saving is active or not.

		    This option enables the detection of this bug to prevent files
		    from appearing modified (and thus resetting their expiry) when DLS status
		    changes.
		    There is one caveat though: if a file is replaced with a file
		    whose modification time is exactly one hour apart from the
		    original file (and older than a day), the file expiry won't be
		    reset and the file will be deleted sooner than expected.

## Usage

File Grim Reaper is meant to be run as a cron job.

If you plan using it on several computers it's best to have FileGrimReaper
located on a unique place such as on a network volume so you can update it
easily and have all your log and snapshot files in one place.

PHP 5.3.8 or superior is required.

### Implementation example for Windows platforms

In this example we are on Windows XP. FileGrimReaper is located on a remote
volume and the last error is logged to a file on this remote volume (if files
or directories can't be deleted you'll find their full path in this file).

In a folder "C:\scripts" create a .bat file containing:

    C:\php\php.exe K:\Utils\Scripts\FileGrimReaper\FileGrimReaper.php --reap --logging --daylightsavingbug 2>K:\Utils\Scripts\FileGrimReaper\errors\HV-NODAL2_LastError.txt

Then create a shortcut and edit its properties to make it start in a minimized
window.

You also need to create a configuration file "FileGrimReaper-paths.txt" (see
above for an example).

Finally use Windows' task manager to create a task that will run the shortcut
every hour (depending on the expiry of your configured folders).

### Implementation example for Unix platforms

In this example we are on Mac OSX. FileGrimReaper is located on a remote
volume and the last error is logged to a file on this remote volume (if files
or directories can't be deleted you'll find their full path in this file).

In a folder "/Volumes/Data/Scrips" create a bash file containing:

    #!/bin/bash

    cd /Volumes/Small_PgLMAc15/Scripts/FileGrimReaper 

    /usr/bin/php-5.3.8/php /Volumes/Team_Virtuel/Utils/Scripts/FileGrimReaper/FileGrimReaper.php --reap --logging 2> /Volumes/Team_Virtuel/Utils/Scripts/FileGrimReaper/errors/PGLMAC15_LastError.txt 

Then make a file containing:

    # The periodic and atrun jobs have moved to launchd jobs
    # See /System/Library/LaunchDaemons
    #
    # minute	hour	mday	month	wday	who	command
    0	*/1	*	*	*	nice /Volumes/Small_PgLMAc15/Scripts/LauchFileGrimReaper.sh > /dev/null

(this will run the script each hour)

Finally load it using the command "crontab YOURFILE".

## Links

### Project home
 https://github.com/2072/File-Grim-Reaper

### Issues, bug reports and suggestions
 https://github.com/2072/File-Grim-Reaper/issues

