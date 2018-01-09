<?php
#
# Any bootstrap code would go here (including autoloader, config, etc.)
#
require_once('./config.php');


#
# ... then just load the db updater and call doUpdates()
#
require('./db_updater.php');
$dbUpdater = new \DbUpdater();
$dbUpdater->updateDb();