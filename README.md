# db_migration_tool
A sample stand alone tool to perform db changes/migrations.

To install and run:

1.  clone to a directory of your choice
2.  create a test database, and enter its name and connection creds in config.php
3.  create a db_change_log table in that database by running the db_change_log_table/db_change_log.sql file.
4.  move files 1-3 in the sample_sql_change_scripts directory to the sql_change_scripts/v1 directory.
5.  from a terminal, cd to the project root and run "php run.php"
