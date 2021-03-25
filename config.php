<?php

return [
    'sources' => [

    ],
    'cache' => new \League\Flysystem\Filesystem(new \Colombo\Cdn\RedisAdapter(new \Predis\Client([
        'port' => 6380,
    ])))
];