<?php
require_once('../config.php');

#
# For backwards compat w/PHPUnit's older naming convention.
#
if (!class_exists('\PHPUnit\Framework\TestCase') &&
    class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

use PHPUnit\Framework\TestCase;

#
# Test that db_change_log table exists
#
class GeneralTest extends TestCase
{
    private $pathToSampleScripts = '../sample_sql_change_scripts';
    private $pathToChangeScripts = '../sql_change_scripts/'.CHANGE_SET;
    
    /**
     * @var $gitPlaceholderFile A placeholder file in an empty dir, b/c git 
     * won't check-in empty directories.
     */
    private $gitPlaceholderFile = 'for_git.txt';
    
    private $changeScripts = [
        '1.2018-01-12.10:16:32.add_user_table.sql',
        '2.2018-01-12.10:17:22.add_last_name_to_user_table',
        '3.2018-01-12.10:19:01.seed_users',
        '4.2018-01-12.10:26:32.this_should_error_on_purose'
    ];
    
    public function __construct()
    {
        parent::__construct();
        $this->db = new PDO('mysql:dbname='.DB_NAME.';host='.DB_HOST, DB_USERNAME, DB_PASSWORD);        
    }
    
    public function setUp()
    {        
        parent::setUp();
    }  
    
    public function tearDown()
    {
        #
        # clear out any sql files we moved to the sql change script diretory, 
        # then assert the directory is empty.
        #
        $existingFilesBefore = scandir($this->pathToChangeScripts);
		foreach($existingFilesBefore as $existingFile)
		{
            #
            # Skip over the relative path entries ...
            #
            if($existingFile == '.' || $existingFile == '..')
			{
                continue;
            }
            
            if($existingFile != $this->gitPlaceholderFile)
            {
                unlink($this->pathToChangeScripts.'/'.$existingFile);    
            }
		}
        
        $existingFilesAfter = scandir($this->pathToChangeScripts);        
        foreach($existingFilesAfter as $existingFile)
		{
            $this->assertTrue(in_array($existingFile, 
                array('.', '..', $this->gitPlaceholderFile)), 'Test for empty '
                . 'directory failed, file name '.$existingFile.' was found.');
        }
        
        #
        # Also truncate the db change log table and verify it emptied.
        #
        $result = $this->db->exec("truncate table db_change_log");
        
        if($result === false)
        {
            throw new Exception('truncate failed: '.print_r($this->db->errorInfo(), true));
        }
        
		$sql = "select * from db_change_log";        
        $stmt = $this->db->query($sql);        
        $this->assertTrue($stmt->rowCount() === 0);        
        
        #
        # drop the test users table we created
        #
        $this->db->exec("drop table if exists test_users_table");
        $this->assertTrue($this->tableExists('test_users_table') === false);
        
        parent::tearDown();
    }
    
    public function testChangeDeltaSetDirectoryExists()
    {		
        $this->assertTrue(file_exists($this->pathToChangeScripts));
    }    
    
    public function testChangeLogTableExists()
    {
		$this->assertTrue($this->tableExists('db_change_log'));
    }
    
    /*
     * Moves a sql file that creates a test user table to the sql_change_scripts 
     * dir and then runs the db updater.  Afterward we test that the table 
     * updated and that the change was recorded in the db_change_log table.     
     */
    public function testValidFileRuns()
    {
        #
        # We currently have no sql change scripts to run, so we'll create done by 
        # copying the first sample change script to the change script directory.
        #                
        copy($this->pathToSampleScripts.'/'.$this->changeScripts[0], $this->pathToChangeScripts.'/'.$this->changeScripts[0]);
        $this->assertTrue(file_exists($this->pathToChangeScripts.'/'.$this->changeScripts[0]), 
            'First sample change script did not copy properly!');
        
        #
        # Before running the db updater, assert that the test table does not exist.
        #        
        $this->assertTrue($this->tableExists('test_users_table') === false);
        
        #
        # now run the db updater ...
        #
        exec('cd .. && php run.php');        
        
        #
        # Now test that that the user table exists
        #
        $this->assertTrue($this->tableExists('test_users_table'));
    }    
    
    /*
     * Moves a sql file that creates a test user table to the sql_change_scripts 
     * dir and then runs the db updater.  Afterward we test that the table 
     * updated and that the change was recorded in the db_change_log table.     
     */
    public function testValidFileExecutionIsRecorded()
    {
        #
        # Before we run anything we'll record the state of the db_change_log
        # table for before-and-after comparison.  Here, we expect it to be empty.
        #
        $sql = "select * from db_change_log";
        $stmt = $this->db->query($sql);        
        $this->assertTrue($stmt->rowCount() === 0);
        
        #
        # Now copy our sample sql file and run the db updater ...
        #
        copy($this->pathToSampleScripts.'/'.$this->changeScripts[0], $this->pathToChangeScripts.'/'.$this->changeScripts[0]);        
        $this->assertTrue(file_exists($this->pathToChangeScripts.'/'.$this->changeScripts[0]), 
            'First sample change script did not copy properly!');        
        exec('cd .. && php run.php');
        
        #
        # Now test the 'after' state of the db_change_log table; it should have
        # 1, and only 1, record with the change num being 1.
        #
		$sql = "select * from db_change_log";
        $stmt = $this->db->query($sql);        
        $this->assertTrue($stmt->rowCount() === 1);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertTrue((string)$row['change_number'] === '1');
    }      
    
    private function tableExists($tableName)
    {
        $sql = "show tables";
        
        $stmt = $this->db->prepare($sql);        
        $result = $stmt->execute();
        
        if(!$result)
        {
            throw new Exception('show tables failed: '.print_r($stmt->errorInfo(), true));
        }
        
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        return array_search($tableName, $rows) !== false;
    }
}

#
# Test that the users table is added properly
#

#
# Test that the users last_name column is added properly
#

#
# Test that the users seed data is added properly (count = 3, etc.)
#