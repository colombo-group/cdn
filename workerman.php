<?php

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

$options = \Colombo\Cdn\ArgvParser::parseArgs($argv);

$ip = $options['ip'] ?? '127.0.0.1';
$port = $options['port'] ?? 8888;
$worker_count = $options['w'] ?? 1;
$config = $options['c'] ?? 'config.php';

//var_dump($options);

// Create a Websocket server
$ws_worker = new Worker('http://' . $ip . ':' . $port);

// 4 processes
$ws_worker->count = $worker_count;

// setup glide

$config = include __DIR__ . "/" . $config;

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
//    echo "New connection\n" . get_class($connection);
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
        $connection->close($w_response);
    }catch (\Exception $ex){
        if($ex instanceof \GuzzleHttp\Exception\RequestException){
            $connection->close(new \Workerman\Protocols\Http\Response(404, [], 'Lỗi rồi '));
        }else{
            echo "Error " . $ex->getFile() . ":" . $ex->getLine() . "\n";
            echo "\t" . $ex->getMessage() . "\n";
            $connection->close(new \Workerman\Protocols\Http\Response(500, [], 'Lỗi rồi '));
        }
    }
};

// Emitted when connection closed
$ws_worker->onClose = function ($connection) {
//    echo "Connection closed\n";
};

// Run worker
Worker::runAll();