<?php
namespace MyApp;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use React\EventLoop\StreamSelectLoop as Loop;

class Dashboard implements MessageComponentInterface 
{
	protected $clients;

	public function __construct(Loop $loop) {
	    $this->clients = new \SplObjectStorage;
	    echo "Congratulations! the server is now running\n";

        $loop->addPeriodicTimer(60, function() {
            // Loop every 1 minute to get if latest JSON exists
            foreach ($this->clients as $client) {
               $this->getJSON($client);
            }
            echo "Looping every 1 minute...\n";
        });
	}

    public function getJSON($client) {
        $host = $client->WebSocket->request->getHeader('Origin');
        if(strpos($host, "swatqa") == true) $host = "http://www.dewslandslide.com";
        $json = file_get_contents($host . '/temp/data/PublicAlert.json');
        $data = array(
            'code' => 'getJSONandLastRelease',
            'alert_json' => json_decode($json),
        );
        if($json === FALSE) $data['is_bad'] = "ERROR: Problem with opening JSON";

        $client->send(json_encode($data));
    }

    public function onOpen(ConnectionInterface $conn) {
    	// Store the new connection to send messages to later
	    $this->clients->attach($conn);

        // Send JSON after connecting
        $this->getJSON($conn);

	    echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
    	$numRecv = count($this->clients) - 1;
    	echo sprintf('Connection %d sending message "%s" to %d other connection%s' . "\n", $from->resourceId, $msg, $numRecv, $numRecv == 1 ? '' : 's');

        $host = $from->WebSocket->request->getHeader('Origin');
        if(strpos($host, "swatqa") == true) $host = "192.168.1.106";

        if($msg == "getOnGoingAndExtended") {
            $ongoing = file_get_contents($host . '/monitoring/getOnGoingAndExtended');
            $data = array(
                'code' => 'getOnGoingAndExtended',
                'ongoing' => json_decode($ongoing)
            );

            foreach ($this->clients as $client) {
                $client->send(json_encode($data));
            }
        }

    	// foreach ($this->clients as $client) {
	    //     if ($from !== $client) {
	    //         // The sender is not the receiver, send to each client connected
	    //         $client->send($msg);
	    //     }
	    // }
    }

    public function onClose(ConnectionInterface $conn) {
    	// The connection is closed, remove it, as we can no longer send it messages
    	$this->clients->detach($conn);

    	echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
    	echo "An error has occurred: {$e->getMessage()}\n";

    	$conn->close();
    }
}