<?php
require '../src/MyApp/Dashboard.php';
require dirname(__DIR__) . 'vendor/autoload.php';

class Dashboard_server_test extends PHPUnit_Framework_TestCase {

	private $dashboard_server;

	public function setUp() {
		$this->dashboard_server = new Dashboard();
	}
	
	public function tearDown() {
		$this->dashboard_server = NULL;
		//Clean db.
	}

	public function testAddNum() {
		$result = $this->dashboard_server->addNum(1,2);
		$this->assertEquals(3, $result);
	}

	public function testSaveAuthorizedID() {
		ConnectionInterface $conn = new ConnectionInterface();
		$result = $this->dashboard_server->saveAuthorizedID("Kevin Dhale", $conn, 37);
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