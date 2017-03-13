<?php

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\Socket\Server as Reactor;
use MyApp\Dashboard;
use React\EventLoop\Factory as LoopFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

// $loop = LoopFactory::create();
// $server = IoServer::factory( new HttpServer(new WsServer(new Dashboard($loop) ) ), 8080);

// // $server->loop->addPeriodicTimer(5, function () use ($server) {        
// //     echo "LOOPING";
// // });

// $server->run();

$loop = LoopFactory::create();
$socket = new Reactor($loop);
$socket->listen(5070, '0.0.0.0');
$server = new IoServer(new HttpServer(new WsServer(new Dashboard($loop))), $socket, $loop);
$server->run();

?>