<?php

class DbUpdater
{
    /**
     * @var $db 
     * 
     * A database instance.
     */
    private $db;
    
    /**
     * @var $deltaSet string
     * 
     * A folder indicating a version or grouping of change scripts.  E.g. after 
     * some point you might have 100 change scripts, and therefore it may be 
     * preferable to start a new group of change scripts.
     */
    private $deltaSet;
    
    /**
     * @var $updateFilesDirectory string
     * 
     * The relative path to  SQL change scripts, initialized in the constructor
     * b/c it relies on $deltaSet.
     */
    private $updateFilesDirectory;
    
    /**
     * @var $lastChangeNumber int
     * 
     * The latest change number that was ran, per the db_change_log table.
     */
    private $lastChangeNumber = 0;    
    
    /**
     * @var $newFiles array
     * 
     * An array, eventually key-sorted, of new change scripts to be executed.
     */
    private $newFiles = [];    
    
    /**
     * @var $gitPlaceholderFile A placeholder file in an empty dir, b/c git 
     * won't check-in empty directories.  This is currently only needed for unit
     * tests and any empty change set directories we want to check in.
     */
    private $gitPlaceholderFile = 'for_git.txt';

    
    public function __construct()
    {
        $this->db = new PDO('mysql:dbname='.DB_NAME.';host='.DB_HOST, DB_USERNAME, DB_PASSWORD); 
        
        $this->deltaSet = CHANGE_SET;
        
        $this->updateFilesDirectory = './sql_change_scripts/'.$this->deltaSet;
    }
    
    public function updateDb()
    {
        $this->setLatestChangeNumber();        
        
        $this->setNewFiles();
        
        $this->runUpdates();
        
        $this->outputResult();
    }
    
    public function getLatestChangeNumber()
    {
		$sql =
		"
		select max(change_number) as last_update_number
		from db_change_log
		where delta_set = :delta_set
		";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':delta_set', $this->deltaSet);
        $result = $stmt->execute();
        
        if(!$result)
        {
            throw new Exception('Select query failed: '.print_r($stmt->errorInfo(), true));
        }
        
        $row = $stmt->fetch();

		if(!empty($row['last_update_number']))
		{
			$this->lastChangeNumber = $row['last_update_number'];
		}
        
        return $this->lastChangeNumber;
    }

    /**
     * @description Queries the max change_number from the db_change_log table.  
     * The db updater will treat any change scripts with a number prefix greater
     * than this as new files to run.  E.g. if the last update to run was update
     * #7, any files found with a change number >= 8 will be executed.
     */
	private function setLatestChangeNumber()
	{
        $this->lastChangeNumber = $this->getLatestChangeNumber();
    }
    
    /**     
     * @description Scans the sql_update_files directory for new change scripts, 
     * comparing each file's number prefix to that of $this->lastChangeNumber. 
     * If the file prefix is >= to that, then the file is considered new and 
     * will be executed.
     */
	private function setNewFiles()
	{
		$existingFiles = scandir($this->updateFilesDirectory);

		foreach($existingFiles as $existingFile)
		{
            #
            # B/c we don't want to operate on path entries ...
            #
            if($existingFile == '.' || $existingFile == '..' || $existingFile == $this->gitPlaceholderFile)
			{
                continue;
            }
            
            $fileNamePieces = explode('.', $existingFile);
            $fileNumber = $fileNamePieces[0];
            $fileDate = $fileNamePieces[1];
            $fileTime = $fileNamePieces[2];

            if($fileNumber > $this->lastChangeNumber)
            {                    
                $this->newFiles[$fileNumber] = [
                    'change_timestamp' => $fileDate.'.'.$fileTime,
                    'filename' => $existingFile
                ];
            }		
		}
        
        #
        # If we have new files they must be sorted by numeric prefix to ensure 
        # proper order of execution.
        #
        if(!empty($this->newFiles))
        {
            ksort($this->newFiles);    
        }
	}    
    
    /**
     * @description For each new $this->newFiles, extracts the sql commands and
     * runs them 1-by-1.  If any statement within a file fails, all commands from
     * within that file are rolled back, and error output is immediately rendered.
     */
	private function runUpdates()
	{
		$sql = '';
		$sqlCommands = [];

		foreach($this->newFiles as $changeNumber => $newFile)
		{
			$sql = file_get_contents($this->updateFilesDirectory.'/'.$newFile['filename']);
			$sqlCommands = explode(';', $sql);

			$this->db->beginTransaction();

			foreach($sqlCommands as $sqlCommand)
			{
                #
                # White space is handled via trim(); if we wind up w/out a sql
                # command we continue to the next element.
                #
				$sqlCommand = trim($sqlCommand);
                if(empty($sqlCommand))
                {
                    continue;
                }                
				
                #
                # If a query within the update file fails, print the error
                # and rollback all statements from the file.  Then exit.
                #
                if($this->db->exec($sqlCommand) === false)
                {
                    print "Sql update file failed (".$newFile['filename']."): ".$this->db->errorInfo()[2].PHP_EOL.PHP_EOL;
                    $this->db->rollback();
                    print "All statements within this file have been rolled back.".PHP_EOL.PHP_EOL;
                    exit;
                }				
			}
            
            $sql =
            "
            insert db_change_log (change_number, change_timestamp, delta_set, filename)
            values (:change_number, :change_timestamp, :delta_set, :filename);
            ";
            
            #
            # To change "2018-01-12.10:16:32" to "2018-01-12 10:16:32", for database
            # insertion ...
            #
            $changeTimestamp = str_replace('.', ' ', $newFile['change_timestamp']);
                    
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':change_number', $changeNumber);
            $stmt->bindParam(':change_timestamp', $changeTimestamp);
            $stmt->bindParam(':delta_set', $this->deltaSet);            
            $stmt->bindParam(':filename', $newFile['filename']);                        
            
            if(!$stmt->execute())
            {
                throw new Exception('Select query failed: '.print_r($stmt->errorInfo(), true));
            }

			$this->db->commit();
		}
	}    
    
    /**     
     * @description Outputs the success result of what the db updater did.  Note
     * that erroneous sql statements are immediately reported in runUpdates(), so
     * this method doesn't print those out.
     */
    private function outputResult()
    {
        $numUpdateFilesRan = count($this->newFiles);

		$output = 'DB Updater ran on database '.DB_NAME.PHP_EOL;

        if($numUpdateFilesRan === 0)
        {
            $output.='No new update files found - the database is already up to date.'.PHP_EOL;
        }
        else
        {
            $output.="Database update succeeded. $numUpdateFilesRan update file(s) were executed:".PHP_EOL;

            foreach($this->newFiles as $newFile)
            {
                $output.= $newFile['filename'].PHP_EOL;
            }
        }

		print $output;
    }    
}