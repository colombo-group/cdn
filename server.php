<?php
require __DIR__ . "/vendor/autoload.php";

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;

$loop = Factory::create();

// start redix
//$process = new React\ChildProcess\Process('cd "' . __DIR__ . '" && ./redix_darwin_amd64');
//$process->start($loop);
//
//$process->stdout->on('data', function ($chunk) {
//    echo $chunk;
//});
//
//$process->on('exit', function($exitCode, $termSignal) {
//    echo 'Process exited with code ' . $exitCode . PHP_EOL;
//});


// setup glide

$config = include __DIR__ . "/config.php";

$api = new \League\Glide\Api\Api($config['imageManager'], $config['manipulators']);

$glide_server = new \League\Glide\Server(
    $config['source'],
    $config['cache'],
    $api
);

$glide_server->setResponseFactory(new \League\Glide\Responses\PsrResponseFactory(new Response(), function ($stream) {
    return new \GuzzleHttp\Psr7\Stream($stream);
}));

$server = new Server($loop, function (ServerRequestInterface $request) use($loop, $glide_server) {

    try{
        /** @var \Psr\Http\Message\ResponseInterface $response */
        $response = $glide_server->getImageResponse($request->getUri()->getPath(), $request->getQueryParams());
        return $response;
    }catch (\Exception $ex){
//        echo "Error " . $ex->getFile() . ":" . $ex->getLine() . "\n";
//        echo "\t" . $ex->getMessage() . "\n";
    }

    $body = "Lỗi rồi ...";

    return new Response(
        200,
        array(
            'Content-Type' => 'text/plain'
        ),
        $body
    );
});

$socket = new \React\Socket\Server(isset($argv[1]) ? $argv[1] : '0.0.0.0:8888', $loop);
$server->listen($socket);

echo 'Listening on ' . str_replace('tcp:', 'http:', $socket->getAddress()) . PHP_EOL;

$loop->run();