<?php

namespace SlicedUpload\Datastore;

use SlicedUpload\IDatastore;

class Redis implements IDatastore
{
    const KEY = 'slup';

    /**
     * @var \Redis
     */
    private $redis;

    /**
     * Constructor
     *
     * @param \Redis $redis 
     */
    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * Generate a UUID
     *
     * @return string 
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a nonce
     *
     * @return string 
     */
    public function generateNonce(): string
    {
        return hash('crc32b', random_bytes(16));
    }

    protected function getKey(string $uuid): string
    {
        return sprintf('%s:%s', static::KEY, $uuid);
    }

    /**
     * See if a row exists by given keys
     *
     * @param array $keys 
     *
     * @return bool 
     */
    public function exists(array $keys): bool
    {
        return $this->redis->exists($this->getKey($keys['uuid']));
    }

    /**
     * Load a row by given keys
     *
     * @param array $keysAndValues 
     *
     * @return array 
     */
    public function load(array $keysAndValues, bool $throws = true): array|false
    {
        $row = $this->redis->get($this->getKey($keysAndValues['uuid']));

        if ($row === false && $throws) {

            throw new \RuntimeException(
                'No record found',
                404
            );

        }

        return json_decode($row, true);
    }

    /**
     * Delete a row by given keys
     *
     * @param array $keys 
     *
     * @return bool 
     */
    public function delete(array $keys): bool
    {
        return $this->redis->del($this->getKey($keys['uuid']));
    }

    /**
     * Save an upload
     *
     * @param array $data 
     * @param array $keys 
     *
     * @return SlicedUpload\Datastore\Upload 
     */
    public function save(array $data, array $keys = []): array
    {
        $this->redis->set($this->getKey($keys['uuid']), json_encode($data));

        return $this->load($keys);
    }

}