<?php


namespace Colombo\Cdn;

use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\StreamedTrait as StreamPolyfill;
use League\Flysystem\Config;
use League\Flysystem\Util;
use Predis\Client;
use Predis\Response\Status;

class RedisAdapter extends AbstractAdapter
{
    use StreamPolyfill;

    /**
     * @type Predis\Client
     */
    protected $redis;
    protected $ttl = 5000000000;

    /**
     * @var string
     */
    protected $pathSeparator = ':';

    /**
     * @param Client $redis
     * @param string $prefix
     * @param int $ttl default 5000000 ~ 2 month
     */
    public function __construct(Client $redis, $prefix = 'flysystem:', $ttl = 5000000)
    {
        $this->redis = $redis;
        $this->ttl = $ttl * 1000;
        $this->setPathPrefix($prefix);
//        $this->redis->hsetnx($this->applyPathPrefix('/'), '.', json_encode(['path' => '', 'type' => 'dir', 'visibility' => 'public', 'timestamp' => time()]));
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        echo $path . "\n";
        $info = $this->getPathInfo($path);

//        if ($this->ensurePathExists($info['dirname'], $config)) {
            $fileData = [
                'path' => $info['path'],
                'type' => 'file',
                'contents' => base64_encode($contents),
                'visibility' => $config->get('visibility', 'public'),
                'timestamp' => time(),
            ];
            /** @var Status $result */
            $result = $this->redis->set($this->applyPathPrefix($info['dirname']) . "/" . $info['basename'],
                json_encode($fileData), $this->ttl);
            if ($result == "OK") {
                $fileData['contents'] = $contents;
                return $fileData;
            } else {
                return false;
            }
//        } else {
//            return false;
//        }
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        $info = $this->getPathInfo($path);

        if ($this->redis->exists($this->applyPathPrefix($info['path']))) {
            // Is Directory...
            return true;
        } elseif ($this->redis->exists($this->applyPathPrefix($info['dirname']) ."/" . $info['basename'])) {
            // Is File...
            return true;
        } else {
            // Doesn't Exist...
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $info = $this->getPathInfo($path);

        if (!$this->has($path)) {
            throw new \League\Flysystem\FileNotFoundException("File not found at path: {$info['path']}");
        }

        $data = json_decode($this->redis->get($this->applyPathPrefix($info['dirname']) . "/" . $info['basename']), TRUE);

        if ($data['type'] === 'file') {
            $data['contents'] = base64_decode($data['contents']);
            $data['size'] = Util::contentSize($data['contents']);

            return $data;
        } else {
            return '';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $info = $this->getPathInfo($path);

        if (!$this->has($path)) {
            throw new \League\Flysystem\FileNotFoundException("File not found at path: {$info['path']}");
        }

        $metadata = json_decode($this->redis->get($this->applyPathPrefix($info['dirname']) ."/" . $info['basename']), TRUE);

        if ($metadata && $metadata['type'] === 'file') {
            $metadata['contents'] = base64_decode($metadata['contents']);
            $metadata += [
                'size' => Util::contentSize($metadata['contents']),
                'mimetype' => Util::guessMimeType($metadata['path'], $metadata['contents']),
            ];

            unset ($metadata['contents']);
        }

        return $metadata;
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        $metadata = $this->getMetadata($path);

        return isset($metadata['size']) ? Util::map($metadata, ['size' => 'size']) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        $metadata = $this->getMetadata($path);

        return isset($metadata['mimetype']) ? Util::map($metadata, ['mimetype' => 'mimetype']) : ['mimetype' => 'directory'];
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        $metadata = $this->getMetadata($path);

        return isset($metadata['timestamp']) ? Util::map($metadata, ['timestamp' => 'timestamp']) : false;
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        if (!$this->has($path)) {
            return false;
        } else {
            return $this->write($path, $contents, $config);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        return $this->copy($path, $newpath) && $this->delete($path);
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newpath)
    {
        return $this->write($newpath, $this->read($path)['contents'], new Config()) !== FALSE;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        $info = $this->getPathInfo($path);

        if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'file') {
            return $this->redis->hdel($this->applyPathPrefix($info['dirname']), $info['basename']) > 0;
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($dirname, Config $config)
    {
        $info = $this->getPathInfo($dirname);
        $status = [
            'path' => $info['path'],
            'type' => 'dir',
            'visibility' => $config->get('visibility', 'public'),
            'timestamp' => time(),
        ];

        if (!$this->has($info['path'])) {
            if (!$this->ensurePathExists($info['dirname'], $config)
                || !($this->redis->hsetnx($this->applyPathPrefix($info['path']), '.', json_encode($status))
                    && $this->redis->hsetnx($this->applyPathPrefix($info['dirname']), $info['basename'], json_encode($status)))) {
                $status = false;
            }
        } elseif ($this->getMetadata($info['path'])['type'] === 'file') {
            $status = false;
        }

        return $status;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $info = $this->getPathInfo($directory);

        $result = [];

        if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'dir') {
            $files = $this->redis->hgetall($this->applyPathPrefix($info['path']));
            ksort($files);

            foreach ($files as $name => $data) {
                if ($name === '.') {
                    continue;
                }

                $data = json_decode($data, TRUE);

                $result[] = Util::map($data, ['type' => 'type', 'path' => 'path', 'timestamp' => 'timestamp', 'size' => 'size']);

                if ($recursive === TRUE && $data['type'] === 'dir') {
                    $result = array_merge($result, $this->listContents($data['path'], TRUE));
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($dirname)
    {
        $info = $this->getPathInfo($dirname);

        if ($this->has($info['path']) && $this->getMetadata($info['path'])['type'] === 'dir') {
            $status = TRUE;

            foreach (array_reverse($this->listContents($info['path'], true)) as $file) {
                if ($file['type'] === 'dir') {
                    $status = $status && $this->deleteDir($file['path']);
                } else {
                    $status = $status && $this->delete($file['path']);
                }
            }

            if ($info['path'] !== '/') {
                $status = $status && ($this->redis->hdel($this->applyPathPrefix($info['dirname']), $info['basename']) > 0);
            }

            return $status && ($this->redis->del($this->applyPathPrefix($info['path'])) > 0);
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setVisibility($path, $visibility)
    {
        $info = $this->getPathInfo($path);

        if ($this->has($info['path'])) {
            $data = json_decode($this->redis->hget($this->applyPathPrefix($info['dirname']), $info['basename']), TRUE);
            $data['visibility'] = $visibility;

            if (in_array($this->redis->hset($this->applyPathPrefix($info['dirname']), $info['basename'], json_encode($data)), [0, 1])) {
                return $data;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getVisibility($path)
    {
        $metadata = $this->getMetadata($path);

        return isset($metadata['visibility']) ? Util::map($metadata, ['visibility' => 'visibility']) : ['visibility' => 'public'];
    }

    protected function getPathInfo($path)
    {
        $info = Util::pathinfo('/' . Util::normalizePath($path));
        $info['path'] = ltrim($info['path'], '/');
        $info['dirname'] = ltrim($info['dirname'], '/');
        if (empty($info['basename'])) {
            $info['basename'] = '.';
        }

        return $info;
    }

    protected function ensurePathExists($path, Config $config)
    {
        if ($this->has($path)) {
            if ($this->getMetadata($path)['type'] === 'dir') {
                return true;
            } else {
                return false;
            }
        } else {
            $info = $this->getPathInfo($path);

            return is_array($this->createDir($info['path'], $config));
        }
    }
}
