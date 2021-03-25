<?php
require __DIR__ . "/vendor/autoload.php";

use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Factory;
use React\Http\Message\Response;
use React\Http\Server;

$loop = Factory::create();

$config = include __DIR__ . "/config.php";

$glide_server = \League\Glide\ServerFactory::create([
    'source' => new \League\Flysystem\Filesystem(new \League\Flysystem\Adapter\Local(__DIR__ . "/source")),
    'cache' => $config['cache'],
    'response' => new \League\Glide\Responses\PsrResponseFactory(new Response(), function ($stream) {
        return new \GuzzleHttp\Psr7\Stream($stream);
    }),
]);

$server = new Server($loop, function (ServerRequestInterface $request) use($loop, $glide_server) {



    try{
        return $glide_server->getImageResponse($request->getUri()->getPath(), $request->getQueryParams());
    }catch (\Exception $ex){
        var_dump($ex->getMessage());
    }

    $body = "sss";//var_export($response, true);

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