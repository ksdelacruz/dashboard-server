<?php
require_once '/var/www/dashboard-server/src/MyApp/Dashboard.php';
use MyApp\Dashboard;

use PHPUnit\Framework\TestCase;
class Dashboard_server_test extends PHPUnit_Framework_TestCase {

	public function __construct() {
		$this->dashboard_server = new Dashboard();
	}

	// public function testWriteToLog() {
	// 	// ConnectionInterface $conn = new ConnectionInterface();
 //        date_default_timezone_set("Asia/Manila");
 //        $date = date_create();
 //        $date_now = date_format($date, 'Y-m-d H:i:s ');

	// 	$expected_result = "$date_now\tHello World!\r\n";
	// 	$actual_result = $this->dashboard_server->writeToLog("Hello World!");
	// 	$this->assertEquals(true, $result);
	// }

	public function testSaveAuthorizedID() {
		// ConnectionInterface $conn = new ConnectionInterface();
        date_default_timezone_set("Asia/Manila");
        $date = date_create();
        $date_now = date_format($date, 'Y-m-d H:i:s ');

		$expected_result = "Hello World!";
		$actual_result = $this->dashboard_server->saveAuthorizedID("Hello World!",'101','56');
		$this->assertEquals(true, $result);
	}

	public function testDeleteAuthorizedID() {

	}

	public function testShowAutomationMenu(){

	}

	public function testToggleAutomatedAlertRelease(){
		
	}

	public function testToggleAutomatedBulletinSending(){
		
	}

	public function testSendToAll(){
		
	}

	public function testUpdateJSON(){
		
	}

	public function testGetAlertsFromDatabase(){
		
	}

	public function testProcessALerts(){
		
	}

	public function testAutomateAlertRelease(){
		
	}

	public function testAutomateBulletinRelease(){
		
	}

	public function testGetNormalAndLockedIssues(){
		
	}

	public function testDeleteTemporaryChartFiles(){
		
	}

}

?>