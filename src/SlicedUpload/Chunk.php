<?php

namespace SlicedUpload\SlicedUpload;

use RuntimeException;
use SlicedUpload\SlicedUpload;

class Chunk
{
    private $hash;
    private $chunk;
    private $size;

    /**
     * Create a chunk
     *
     * @param string $chunk 
     * @param string $hash 
     */
    protected function __construct($chunk, $hash)
    {
        $this->chunk = $chunk;
        $this->hash  = $hash;
        $this->size  = filesize($chunk);
    }

    /**
     * Read a chunk from the request
     *
     * @param string $key 
     * @param string $hash 
     *
     * @return static 
     */
    public static function read($key, $hash)
    {
        if (!isset($_FILES[$key])) {
            throw new \RuntimeException(sprintf(
                "Missing chunk: %s",
                $key
            ), 415);
        }

        if ($_FILES[$key]['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(sprintf(
                "Chunk error: %s",
                $_FILES[$key]['error']
            ), 415);
        }

        $instance = new static(
            $_FILES[$key]['tmp_name'],
            $hash
        );
        
        if (!$instance->verify()) {
            throw new \RuntimeException(sprintf(
                "Invalid chunk: %s",
                $key
            ), 422);
        }

        return $instance;
    }

    /**
     * Verify a chunk
     *
     * @return bool 
     */
    public function verify()
    {
        if (!file_exists($this->chunk)) {

            return false;

        }

        if ($this->size === 0) {

            return false;

        }

        if (!SlicedUpload::verifyFile($this->chunk, $this->hash)) {

            return false;

        }
        
        return true;
    }

    /**
     * Delete temporary file
     *
     * @return void 
     */
    public function delete()
    {
        unlink($this->chunk);
    }

    /**
     * Get chunk content
     *
     * @return string 
     */
    public function getContent()
    {
        return file_get_contents($this->chunk);
    }
}