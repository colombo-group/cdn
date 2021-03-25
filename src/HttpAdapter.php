<?php


namespace Colombo\Cdn;


use GuzzleHttp\Client;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait as StreamPolyfill;
use League\Flysystem\Config;

class HttpAdapter extends AbstractAdapter
{
    use StreamPolyfill;
    protected $base_url;

    /**
     * HttpAdapter constructor.
     * @param $base_url
     */
    public function __construct($base_url)
    {
        $this->base_url = rtrim($base_url,"/") . "/";
    }


    public function rename($path, $newpath)
    {
        return true;
    }

    public function copy($path, $newpath)
    {
        return true;
    }

    public function delete($path)
    {
        return false;
    }

    public function deleteDir($dirname)
    {
        return false;
    }

    public function createDir($dirname, Config $config)
    {
        return false;
    }

    public function setVisibility($path, $visibility)
    {
        return true;
    }

    public function has($path)
    {
        return true;
    }

    public function listContents($directory = '', $recursive = false)
    {
        return [];
    }

    public function getMetadata($path)
    {
        return false;
    }

    public function getSize($path)
    {
        return false;
    }

    public function getMimetype($path)
    {
        return false;
    }

    public function getTimestamp($path)
    {
        return false;
    }

    public function getVisibility($path)
    {
        return false;
    }

    public function read($path)
    {
        $url = $this->base_url . ltrim($path, "/");
        $response = (new Client([
            'verify' => false,
            'redirect' => true,
        ]))->get($url);
        $contents = $response->getBody()->getContents();
        return [
            'path' => $path,
            'visibility' => 'public',
            'timestamp' => time(),
            'type' => 'file',
            'size' => strlen($contents),
            'contents' => $contents,
        ];
    }

    public function write($pash, $contents, Config $config)
    {
        return false;
    }

    public function update($pash, $contents, Config $config)
    {
        return false;
    }
}