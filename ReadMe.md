# File Grim Reaper

## Purpose

File Grim Reaper is a temporary file manager. It monitors a configured set of
directories with independent expiry. The expiry of each folder is contained in
the very name of each folder.

File Grim Reaper is meant to be run as a cron job. Each time the program is run
it scans the configured directories and takes a snapshot of their content
comparing the result with the previous snapshot.

Files get deleted when they've been in the snapshot without being modified for
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

Example :
    HV-NODAL2_O-____AUTODELETE__TO_KEEP_7_DAYS.data.serialized
    pglmac15_-Volumes-Stripe_PGLMAC15-___AUTODELETE___TO_KEEP_1_MONTH.data.serialized

Additionally if internal errors occur, such as a PHP exception, a file

    "computer-name_errors.log" will be created in FileGrimReaper.php's directory.

## Usage

File Grim Reaper is meant to be run as a cron job.
If you plan using it on several computers its best to have FileGrimReaper
located on a unique place such as on a network volume so you can update it
easily and have all your log files in one place.

### Exemple on Windows



## Options

-r, --reap	Removes expired files and directories and update snapshots.

-s, --show	Shows what would happen (doesn't actually remove anything and
		doesn't update snapshots).

-c, --config	Uses the specified configuration file.

-l, --logging	Creates log files for each configured directories. The log files
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
		There is one caveat though: If a file is replaced with a file
		whose modification time is exactly one hour apart from the
		original file (and older than a day), the file expiry won't be
		reset and the file will be deleted sooner than expected.


