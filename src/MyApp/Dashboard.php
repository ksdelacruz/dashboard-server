<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\StreamSelectLoop as Loop;

class Dashboard implements MessageComponentInterface 
{
	protected $clients;
    private $authorized_staff = [];
    private $onAccomplishment = [];
    private $host = null; // Sets the host
    
    // Switch for automated sending of alert release on web
    // every four hours or if there's an onset
    private $isAutomatedAlertRelease = [
        "switch" => false,
        "staff_name" => false,
        "staff_id" => false
    ];

    private $isAutomatedBulletinSending = [
        "switch" => false,
        "staff_name" => false,
        "staff_id" => false
    ];

    private $json = null; // Variable repository of alerts json generated by MT scripts
    private $alerts = array( 'latest' => null, 'extended' => null, 'overdue' => null );
                        // Variable repository for alert entries from database
    private $sent_routine = null; // variable repository for released routine for a given date
    private $candidate_alerts = null; // variable repository for processed alerts to be sent
                                      // on clients for table building on monitoring_dashboard.js

	public function __construct(Loop $loop) {
	    $this->clients = new \SplObjectStorage;
	    $this->writeToLog("Congratulations! the server is now running\n");

        date_default_timezone_set("Asia/Manila");
        $loop->addPeriodicTimer(60, function() { // 1-minute loop
            
            $date = date_create();
            $this->writeToLog("||=> Looping every 1 minute...\n");

            $hasUpdate = false; // variable that tell if there's a new json data
            // Update json variable ONLY IF there is 1 or more connected client
            if (count($this->clients) >= 1) {
                $hasUpdate = $this->updateJSON();

                if ((int) date_format($date, 'H') % 4 == 2 && (int) date_format($date, 'i') == 30) {
                    if (count($this->onAccomplishment) == 0) {
                        $this->deleteTemporaryChartFiles();
                    }
                }
            } else {
                $this->writeToLog("No connected client.\n");
            }

            $alertReleaseSuccess = false;
            /*if( count($this->authorized_staff) > 0 ) {
                $str = implode(", ", array_map( function ($v) { return "{$v['staff_name']}"; }, array_values($this->authorized_staff)));
                $this->writeToLog("Authorized personnel connected: " . $str . "\n");

                //PUT HERE AUTOMATION OF RELEASE SHIT
                //CHECK TIME HERE IF TIME OF RELEASE AND STUFF
                // $this->isAutomatedAlertRelease = array("switch" => true, "staff" => "Kevin Dhale");
                if( $this->isAutomatedAlertRelease['switch'] && (int) date_format($date, 'H') % 4 == 3 && (int) date_format($date, 'i') == 55 )
                {
                    if( $this->automateALertRelease() ) {
                        $this->writeToLog("Automated alert release for " . date_format($date, 'H:00') . " successful; activated by {$this->isAutomatedAlertRelease['staff_name']}\n");
                        $alertReleaseSuccess = true;
                    }
                    else $this->writeToLog("ERROR: Problem automated releasing alerts...\n");
                }

                // $this->isAutomatedBulletinSending = array("switch" => true, "staff" => "Kevin Dhale");
                if( $this->isAutomatedBulletinSending['switch'] && (int) date_format($date, 'H') % 4 == 4 && (int) date_format($date, 'i') == 05 )
                {
                    if( $this->automateBulletinRelease($date) ) {
                        $this->writeToLog("Automated bulletin sending for " . date_format($date, 'H:00') . " successful; activated by {$this->isAutomatedBulletinSending['staff_name']}\n");
                        $alertReleaseSuccess = true;
                    }
                    else $this->writeToLog("ERROR: Problem automated bulletin sending...\n");
                }
            } else $this->writeToLog("No authorized personnel connected\n");

            $date = date_create("2017-04-24 20:00:00");
            $this->automateBulletinRelease($date);*/

            if($hasUpdate || $alertReleaseSuccess) {
                $data["alerts"] = $this->getAlertsFromDatabase();
                $data["processed_alerts"] = $this->processAlerts();
                $alert_json = $this->prepareSendingJSON();
                $this->sendToAll($data["alerts"]);
                $this->sendToAll($data["processed_alerts"]);
                $this->sendToAll($alert_json);
            }

        });
	}

    public function onOpen(ConnectionInterface $conn) {
    	// Store the new connection to send messages to later
		$this->clients->attach($conn);

        $this->host = $conn->WebSocket->request->getHeader('Origin');
        if(strpos($this->host, "dynaslope.phivolcs.dost") == true) $this->host = "http://192.168.150.80";

        $data = [];

        $path = $conn->WebSocket->request->getPath();

        if( $path == "/accomplishment" ) {
            $this->writeToLog("PATH: $path\n");
            // Send connection ID
            // $data = array(
            //     "code" => "sendConnectionID",
            //     "connection_id" => $conn->resourceId
            // );
            // $conn->send(json_encode($data));
            
            array_push($this->onAccomplishment, $conn->resourceId);
        }
        else {
            if( count($this->clients) == 1 ) {
                $this->updateJSON();
                $data["alerts"] = $this->getAlertsFromDatabase();
            } else {
                $data["alerts"] = $this->getAlertsFromDatabase(true);
            }

            $data["processed_alerts"] = $this->processAlerts();
            $alert_json = $this->prepareSendingJSON();

            $conn->send($data["alerts"]);
            $conn->send($data["processed_alerts"]);
            $conn->send($alert_json);

            $data = $this->getNormalAndLockedIssues($conn);
            $conn->send($data);
        }

	    $this->writeToLog("New connection! ({$conn->resourceId})\n");
		$this->writeToLog("Host: ({$this->host})\n");
    }

    public function onMessage(ConnectionInterface $from, $msg) {
    	$numRecv = count($this->clients) - 1;
    	$this->writeToLog(sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n", $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's'));

        $msg = json_decode($msg);
        $code = $msg->code;
        $vars = $msg->data;
        $data = null;

        if($code == "sendIdentification") {
            $isAuthorized = $this->saveAuthorizedID( $vars->name, $from, $vars->staff_id );
            if($isAuthorized) { $x = $this->showAutomationMenu(); $from->send($x); }
        } else if($code == "updateDashboardTables") {
            $data['alerts'] = $this->getAlertsFromDatabase();
            $data["processed_alerts"] = $this->processAlerts();
        } else if ($code == "getNormalAndLockedIssues") { // FUNCTION FOR ISSUES AND REMINDERS
            $data['issues'] = $this->getNormalAndLockedIssues();
        } else if ($code == "toggleAutomatedAlertRelease") {
            $this->toggleAutomatedAlertRelease( $vars->staff_name, $vars->staff_id, $vars->switch, $from->resourceId );
        } else if ($code == "toggleAutomatedBulletinSending") {
            $this->toggleAutomatedBulletinSending( $vars->staff_name, $vars->staff_id, $vars->switch, $from->resourceId );
        }

        if( !is_null($data) ) {
            foreach ($data as $payload) {
                $this->sendToAll($payload);
            }
        }
        
    }

    public function onClose(ConnectionInterface $conn) {
    	// The connection is closed, remove it, as we can no longer send it messages
    	$this->clients->detach($conn);

        /*$this->deleteAuthorizedID($conn->resourceId);*/

        $search = array_search($conn->resourceId, $this->onAccomplishment);
        if ( $search !== FALSE ) {
            array_splice($this->onAccomplishment, $search, 1);
        }

        /*$path = $conn->WebSocket->request->getPath();
        if( $path == "/accomplishment" ) {
            $this->deleteTemporaryChartFiles($conn->resourceId);
        }*/

    	$this->writeToLog("Connection {$conn->resourceId} has disconnected\n");
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
    	$this->writeToLog("An error has occurred: {$e->getMessage()}\n");

    	$conn->close();
    }


    /************************
     * 
     *      FUNCTIONS 
     * 
     ***********************/

    public function getPath($type)
    {
        $host = $this->host;
        if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $initial =  "C:/xampp/";
            switch ($type) {
                case "logs":
                    $path = $initial . "dashboard-server/logs/"; break;
                case "htdocs":
                    $path = $initial . "htdocs/"; break;
            }
        } else {
            $initial =  "/var/www/";
            if (strpos($this->host, "dewslqa") == true) {
                $initial = $initial . "dewslqa.com/";
            }
            switch ($type) {
                case "logs":
                    $path = $initial . "dashboard-server/logs/"; break;
                case "htdocs":
                    $path = $initial . "html/"; break;
            }
        }

        return $path;
    }

    public function writeToLog($str)
    {
        date_default_timezone_set("Asia/Manila");
        $date = date_create();
        $date_now = date_format($date, 'Y-m-d H:i:s ');
        echo $date_now . $str;

        $path = $this->getPath("logs");

        if ( !is_dir($path) ) { mkdir($path, 0777, true); }

        $file = $path . date_format($date, 'Y-m-d') . ".txt";
        file_put_contents($file, "$date_now\t$str\r\n", FILE_APPEND | LOCK_EX);
    }

    public function saveAuthorizedID($name, $conn, $staff_id)
    {
        if( in_array($name, ["Kevin Dhale", "Prado Arturo", "John", "Ivy Jean", "Earl Anthony", "Ric", "DYNASLOPE-SWAT"]) )
        {
            array_push($this->authorized_staff, array( "staff_name" => $name, "conn" => $conn, "staff_id" => $staff_id ));
            $this->writeToLog("Authorized personnel \"$name\" is online\n");
            return true;
        } else return false;
    }

    public function deleteAuthorizedID($conn_id)
    { 
        $isAuthorized = null;
        for ($i=0; $i < count($this->authorized_staff); $i++) 
        { 
            if( $this->authorized_staff[$i]['conn']->resourceId == $conn_id ) {
                $isAuthorized = $i; break;
            }
        }

        if( !is_null($isAuthorized) )
        {
            $this->writeToLog("Authorized personnel \"{$this->authorized_staff[$i]['staff_name']}\" has logged off\n");
            array_splice($this->authorized_staff, $isAuthorized, 1);
        }

        if( count($this->authorized_staff) == 0 ) {
            $this->isAutomatedAlertRelease['switch'] = false;
            $this->isAutomatedBulletinSending['switch'] = false;
            $this->writeToLog("All authorized staff has logged off... Clearing permissions on alert and bulletin automation...\n");
        }
    }

    public function showAutomationMenu()
    {
        $data = array( 
            'code' => 'showAutomationMenu',
            'alert_release' => $this->isAutomatedAlertRelease,
            'bulletin_sending' => $this->isAutomatedBulletinSending
        );

        return json_encode($data);
    }

    public function toggleAutomatedAlertRelease($staff_name, $staff_id, $switch, $from)
    {
        if($switch) {
            $this->isAutomatedAlertRelease['switch'] = true;
            $this->isAutomatedAlertRelease['staff_id'] = $staff_id;
            $this->isAutomatedAlertRelease['staff_name'] = $staff_name;
            $this->writeToLog("Automated Alert Release has been ACTIVATED by $staff_name\n");
        } else {
            $this->isAutomatedAlertRelease['switch'] = false;
            $this->writeToLog("Automated Alert Release has been DEACTIVATED by $staff_name\n");
        }

        $data = $this->showAutomationMenu();
        foreach ($this->authorized_staff as $client) {
            $client['conn']->send($data);
        }
    }

    public function toggleAutomatedBulletinSending($staff_name, $staff_id, $switch, $from)
    {
        if($switch) {
            $this->isAutomatedBulletinSending['switch'] = true;
            $this->isAutomatedBulletinSending['staff_id'] = $staff_id;
            $this->isAutomatedBulletinSending['staff_name'] = $staff_name;
            $this->writeToLog("Automated Bulletin Sending has been ACTIVATED by $staff_name\n");
        } else {
            $this->isAutomatedBulletinSending['switch'] = false;
            $this->writeToLog("Automated Bulletin Sending has been DEACTIVATED by $staff_name\n");
        }

        $data = $this->showAutomationMenu();
        foreach ($this->authorized_staff as $client) {
            $client['conn']->send($data);
        }
    }

    public function sendToAll($payload, $from = null)
    {
        foreach ($this->clients as $client) {
            // Check path if not from accomplishment server
            $path = $client->WebSocket->request->getPath();

            if ($from !== $client && $path != "/accomplishment" ) {
                // The sender is not the receiver, send to each client connected
                $client->send($payload);
            }
        }
    }

    public function updateJSON() {

        $host = $this->host;
        // if(strpos($this->host, "dewslqa.com") == true) $host = "http://www.dewslandslide.com";

        $temp_json = file_get_contents($host . '/temp/data/PublicAlertRefDB.json');

        if( $this->json !== $temp_json ) {
            $this->json = $temp_json;
            return true;
        }
        else { 
            $this->writeToLog("No new data from alert JSON\n");
            return false;
        }
    }

    public function prepareSendingJSON() {
        $data = array("code" => "updateGeneratedAlerts", "generated_alerts" => $this->json);
        return json_encode($data);
    }

    public function getAlertsFromDatabase( $getDataFromCache = false )
    {
        if( !$getDataFromCache ) {
            $host = $this->host;

            $alerts = json_decode(file_get_contents($host . '/monitoring/getOnGoingAndExtended'));

            $delete_routine = true;
            if (in_array(date("D"), ["Tue", "Wed", "Fri"])) {
                $hour = (int) date("H");
                if ($hour === 11 || ($hour === 12 && (int) date("i") < 30)) {
                    $this->sent_routine = json_decode(file_get_contents($host . '/monitoring/getAllRoutineEventsGivenDate/' . date("Y-m-d")));
                    $delete_routine = false;
                }
            }
            if ($delete_routine) $this->sent_routine = null;

            $this->alerts['latest'] = $alerts->latest;
            $this->alerts['extended'] = $alerts->extended;
            $this->alerts['overdue'] = $alerts->overdue;
        }
        else 
            $alerts = $this->alerts;

        $data = array(
            'code' => 'existingAlerts',
            'alerts' => $alerts
        );

        return json_encode($data);
    }

    public function processAlerts()
    {
        $path = $this->getPath("htdocs");

        $directory = "temp/alert_processing/";

        if ( !is_dir($path . $directory) ) { mkdir($path . $directory, 0777, true); }

        file_put_contents($path . $directory . "public_alert.json", $this->json);
        file_put_contents($path . $directory . "alerts_from_db.json", json_encode($this->alerts));        

        $command = "node " . $path . "js/dewslandslide/public_alert/check_candidate_triggers.js " . $path . $directory . "public_alert.json " . $path . $directory . "alerts_from_db.json " . $path . "temp/data/dynaslope_sites.json";

        if (!is_null($this->sent_routine)) {
            file_put_contents($path . $directory . "sent_routine.json", json_encode($this->sent_routine));
            $command = $command . " " . $path . $directory . "sent_routine.json";
        }
                
        $response = exec( $command . " 2>&1", $output );
        $response = json_decode($response);

        $data = array( 'code' => 'candidateAlerts', 'alerts' => $response );
        if($response == null) $data['error'] = implode("\n", $output);

        file_put_contents($path . $directory . "candidate_alerts.json", json_encode($response));

        return json_encode($data);
    }

    public function automateALertRelease()
    {
        $path = $this->getPath("htdocs");

        $directory = "temp/alert_processing/";

        $command = "node " . $path . "js/dewslandslide/public_alert/release_all_alerts.js " . $path . $directory . "candidate_alerts.json " . $path . $directory . "alerts_from_db.json " . $this->host;
        $response = exec( $command . " 2>&1", $output );
        $response = json_decode($response);

        if ($response == "success")
        {
            return true;
        }  
        else if( !is_null($output) )
        {
            $this->writeToLog(implode("\n", $output) . "\n");
            return false;
        }    
    }

    public function automateBulletinRelease($date)
    {
        $temp_date = date_format($date, 'jMY_gA');
        $temp_date = str_replace("12AM", "12MN", $temp_date);
        $temp_date = str_replace("12PM", "12NN", $temp_date);

        $path = $this->getPath("htdocs");

        $directory = "temp/alert_processing/";

        $response = null;
        $output = null;
        $releases = [];
        $narratives = [];
        foreach ($this->alerts['latest'] as $alert) 
        {
            $x = [];
            $bulletin_timestamp = date_format($date, "j F Y, h:00 A");
            $public_alert = substr($alert->internal_alert_level, 0, 2);
            $public_alert = $public_alert == "ND" ? "A1" : $public_alert;

            $location = !is_null($alert->sitio) ? "Sitio " . $alert->sitio . ", " : "";
            $x['location'] = $location = $location . $alert->barangay . ", " . $alert->municipality . ", " . $alert->province;

            $x['filename'] = strtoupper($alert->name) . "_" . $temp_date . ".pdf";
            $x['subject'] = strtoupper($alert->name) . " " . strtoupper(date("d M Y", strtotime($alert->event_start)));
            $x['body'] = "DEWS-L Bulletin for " . $bulletin_timestamp . "\n" . $public_alert . " - " . $location;
            $x['event_id'] = $alert->event_id;
            $x['release_id'] = $alert->latest_release_id;
            $x['recipients'] = $recipients = ['hyunbin_vince@yahoo.com'];
            $x['bulletin_timestamp'] = $bulletin_timestamp;
            array_push($releases, $x);
            array_push($narratives, array("event_id" => $alert->event_id, "recipients" => $recipients, "bulletin_timestamp" => $date));
        }

        file_put_contents($path . $directory . "bulletin_release.json", json_encode($releases));

        $command = "node " . $path . "js/dewslandslide/public_alert/send_all_bulletins.js " . $path . $directory . "bulletin_release.json " . $this->host;

        $response = exec( $command . " 2>&1", $output );
        $response = json_decode($response);
        
        if ($response == "Sending success")
        {
            return true;
        }  
        else if( !is_null($output) )
        {
            $this->writeToLog(implode("\n", $output) . "\n");
            return false;
        }      
    }

    public function getNormalAndLockedIssues() {
        
        $host = $this->host;

        $normal = file_get_contents($host . '/issues_and_reminders/getAllNormal');
        $locked = file_get_contents($host . '/issues_and_reminders/getAllLocked');
        $archived = file_get_contents($host . '/issues_and_reminders/getAllArchived');
        $data = array(
            'code' => 'getNormalAndLockedIssues',
            'normal' => json_decode($normal),
            'locked' => json_decode($locked),
            'archived' => json_decode($archived)
        );

        return json_encode($data);
    }

    public function deleteTemporaryChartFiles($id = null) {
        
        $host = $this->host;
        $result = file_get_contents($host . '/chart_export/deleteTemporaryChartFiles/' . $id);
        $this->writeToLog($result . "\n");
    }

}
