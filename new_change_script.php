<?php
#
# Any bootstrap code would go here (including autoloader, config, etc.)
#
require('./config.php');
require('./db_updater.php');

$dbUpdater = new DbUpdater();

#
# The following creates a new file named as: 
# 
# [new change number].[timestamp].[change description].sql
# 
# E.g.: 1.2018-01-12.10:16:32.some-change-description.sql
#

$latestChangeNumber = $dbUpdater->getLatestChangeNumber();
$newFileNumber = $latestChangeNumber + 1;

$filename = CHANGE_SCRIPT_DIRECTORY.'/'.CHANGE_SET.'/'.$newFileNumber.'.'.date('Y-m-d.H:i:s');

if(!empty($argv[1]))
{
    $filename.= '.'.$argv[1];
}

$filename.= '.sql';

fopen($filename, 'w+');