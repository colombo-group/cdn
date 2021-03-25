<?php

return [
    'source' => new \League\Flysystem\Filesystem(
        new \Colombo\Cdn\HttpAdapter('https://icdn.dantri.com.vn/')
    ),
    'cache' => new \League\Flysystem\Filesystem(new \Colombo\Cdn\RedisAdapter(new \Predis\Client([
        'port' => 6380,
    ]))),
    'imageManager' => new Intervention\Image\ImageManager([
        'driver' => 'gd',
    ]),
    'manipulators' => [
        new League\Glide\Manipulators\Orientation(),
        new League\Glide\Manipulators\Crop(),
        new League\Glide\Manipulators\Size(2000*2000),
        new \Colombo\Cdn\Manipulators\Flip(),
//        new League\Glide\Manipulators\Brightness(),
//        new League\Glide\Manipulators\Contrast(),
//        new League\Glide\Manipulators\Gamma(),
//        new League\Glide\Manipulators\Sharpen(),
//        new League\Glide\Manipulators\Filter(),
//        new League\Glide\Manipulators\Blur(),
//        new League\Glide\Manipulators\Pixelate(),
//        new League\Glide\Manipulators\Background(),
//        new League\Glide\Manipulators\Border(),
//        new League\Glide\Manipulators\Encode(),
        new \Colombo\Cdn\Manipulators\Encode(),
    ],
];