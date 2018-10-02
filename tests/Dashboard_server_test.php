<?php

	// to run : ./vendor/bin/phpunit --bootstrap vendor/autoload.php tests/Dashboard_server_test.php

require_once "/var/www/dashboard-server/src/MyApp/Dashboard.php";
use MyApp\Dashboard;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory as LoopFactory;

$host = "www.dewslandslide.com";

class Dashboard_server_test extends PHPUnit_Framework_TestCase {

	public function __construct() {
		$loop = LoopFactory::create();
		$this->dashboard_server = new Dashboard($loop);
	}

	// saveAuthorizedID - TRUE POSITIVE TESTS

	public function testSaveAuthorizedIDSuccessRicName() {
		$expected_result = true;
		$actual_result = $this->dashboard_server->saveAuthorizedID("Earl Anthony",'101','54');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDSuccessEarlAnthonyName() {
		$expected_result = true;
		$actual_result = $this->dashboard_server->saveAuthorizedID("Earl Anthony",'101','55');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDSuccessJohnName() {
		$expected_result = true;
		$actual_result = $this->dashboard_server->saveAuthorizedID("John",'101','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDSuccessIvyJeanName() {
		$expected_result = true;
		$actual_result = $this->dashboard_server->saveAuthorizedID('Ivy Jean','101','57');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDSuccessKevinDhaleName() {
		$expected_result = true;
		$actual_result = $this->dashboard_server->saveAuthorizedID('Kevin Dhale','101','58');
		$this->assertEquals($expected_result, $actual_result);
	}

	// saveAuthorizedID - TRUE NEGATIVE TESTS

	public function testSaveAuthorizedIDFailIncompleteName() {
		$expected_result = false;
		$actual_result = $this->dashboard_server->saveAuthorizedID("Earl",'101','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDFailNoName() {
		$expected_result = false;
		$actual_result = $this->dashboard_server->saveAuthorizedID("",'1067','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDInvalidName() {
		$expected_result = false;
		$actual_result = $this->dashboard_server->saveAuthorizedID('$afds','101','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDUnauthorizedStaff1() {
		$expected_result = false;
		$actual_result = $this->dashboard_server->saveAuthorizedID('Marvin','101','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testSaveAuthorizedIDUnauthorizedStaff2() {
		$expected_result = false;
		$actual_result = $this->dashboard_server->saveAuthorizedID("Carlo",'101','56');
		$this->assertEquals($expected_result, $actual_result);
	}

	public function testGetFileContents() {
		$expected_result = file_get_contents($host . '');
		$actual_result = $this->dashboard_server->getFileContents($host,'$filepath','');
		$this->assertEquals($expected_result,$actual_result);
	}
}

?>