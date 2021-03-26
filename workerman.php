<?php

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

// Create a Websocket server
$ws_worker = new Worker('http://0.0.0.0:8888');

// 4 processes
$ws_worker->count = 1;

// setup glide

$config = include __DIR__ . "/config.php";

$api = new \League\Glide\Api\Api($config['imageManager'], $config['manipulators']);

$glide_server = new \League\Glide\Server(
    $config['source'],
    $config['cache'],
    $api
);

$glide_server->setResponseFactory(new \League\Glide\Responses\PsrResponseFactory(new \GuzzleHttp\Psr7\Response(), function ($stream) {
    return new \GuzzleHttp\Psr7\Stream($stream);
}));

// Emitted when new connection come
$ws_worker->onConnect = function ($connection) {
    echo "New connection\n" . get_class($connection);
};

// Emitted when data received
$ws_worker->onMessage = function ($connection, $request) use ($glide_server) {
    /** @var \Workerman\Protocols\Http\Request $request */
    /** @var \Workerman\Connection\TcpConnection $connection */
    try{
        /** @var \Psr\Http\Message\ResponseInterface $response */
        \parse_str($request->queryString(), $query);
        $response = $glide_server->getImageResponse($request->path(),  $query);
        $w_response = new \Workerman\Protocols\Http\Response();
        $w_response->withHeaders($response->getHeaders());
        $w_response->withBody($response->getBody());
        $connection->send($w_response);
    }catch (\Exception $ex){
        echo "Error " . $ex->getFile() . ":" . $ex->getLine() . "\n";
        echo "\t" . $ex->getMessage() . "\n";

        $connection->send('Error ' . $ex->getMessage(), true);
    }
};

// Emitted when connection closed
$ws_worker->onClose = function ($connection) {
    echo "Connection closed\n";
};

// Run worker
Worker::runAll();