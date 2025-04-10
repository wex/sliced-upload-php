<?php

namespace SlicedUpload;

use RuntimeException;
use SlicedUpload\SlicedUpload\Chunk;
use SlicedUpload\SlicedUpload\Upload;

class SlicedUpload
{
    const DEFAULT_MAXIMUM_CHUNK_SIZE = 1_048_576;

    protected $datastore;
    protected $maximumChunkSize = self::DEFAULT_MAXIMUM_CHUNK_SIZE;
    protected $callback;

    /**
     * Constructor
     * 
     * @param int|null $maximumChunkSize 
     *
     * @return void 
     */
    public function __construct(IDatastore $datastore, $maximumChunkSize = null)
    {
        if (null === $maximumChunkSize) {

            $maximumChunkSize = self::DEFAULT_MAXIMUM_CHUNK_SIZE;

        }

        $this->datastore = $datastore;
        $this->maximumChunkSize = $maximumChunkSize;
    }

    /**
     * Get a temporary file
     *
     * @return string
     */
    public static function getTempFile()
    {
        $name = tempnam(
            ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
            'sliced-upload'
        );

        if ($name === false) {

            throw new \RuntimeException('Failed to create temporary file');

        }

        return $name;
    }

    /**
     * Verify a file against a hash
     *
     * @param string $file
     * @param string $hash
     *
     * @return bool 
     */
    public static function verifyFile(string $file, string $hash)
    {
        if (!file_exists($file)) {

            throw new \RuntimeException('File not found');

        }

        if (filesize($file) === 0) {

            throw new \RuntimeException('File is empty');

        }

        if (mb_strlen($hash) !== 64) {

            throw new \RuntimeException('Invalid hash');

        }
        
        return (mb_strtoupper(hash_file('sha256', $file)) === mb_strtoupper($hash));
    }

    /**
     * Receive an upload
     *
     * @param callable $callback
     *
     * @return void
     */
    public function receive(callable $callback)
    {
        $this->callback = \Closure::fromCallable($callback);

        switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
            case 'POST':    return $this->readHandshake();
            case 'PATCH':   return $this->readChunk();
            case 'DELETE':  return $this->readCancel();
            // @todo implement
            #case 'HEAD':    return $instance->readStatus();
        }

        throw new \RuntimeException(sprintf(
            'Invalid method: %s',
            $_SERVER['REQUEST_METHOD']
        ), 500);
    }

    public function getUpload($uuid): Upload
    {
        return Upload::load($this->datastore, $uuid);
    }

    /**
     * Get a request parameter
     *
     * @param string $key 
     * @param bool $isRequired 
     * @param string|null $default 
     * @return mixed 
     */
    protected function fromRequest(string $key, bool $isRequired = true, ?string $default = null)
    {
        if (!isset($_POST[$key])) {

            if ($isRequired) {

                throw new \RuntimeException(sprintf(
                    "Invalid request: %s",
                    $key
                ), 400);

            }

            return $default;
        }

        return $_POST[$key];
    }

    /**
     * Respond to a request
     *
     * @param array $response 
     * @param int $code 
     * @return void 
     */
    protected function respond(array $response, int $code = 200)
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode($response);
        exit(0);
    }

    /**
     * Check if an upload is complete
     *
     * @param Upload $upload 
     * @return bool 
     */
    protected function isCompleted(Upload $upload)
    {
        return $upload->isCompleted();
    }

    /**
     * Read a chunk
     *
     * @return void 
     */
    protected function readChunk()
    {
        try {

            // Initialize request params
            $uuid       = $this->fromRequest('uuid');
            $nonce      = $this->fromRequest('nonce');
            $chunk      = Chunk::read(
                'chunk',
                $this->fromRequest('checksum')
            );

            // Find upload
            $upload = $this->getUpload($uuid);

            // Verify nonce
            if (!$upload->verifyNonce($nonce)) {

                throw new \RuntimeException(
                    'Invalid nonce',
                    400
                );

            }

            // Append chunk to upload
            $bytesWritten = $upload->append($chunk);

            // Process chunk
            if (!$this->isCompleted($upload)) {

                // If upload is not complete, send 202 with new nonce and bytes written
                return $this->respond([
                    'nonce' => $upload->getNonce(),
                    'size' => $bytesWritten,
                ], 202);            

            } else {

                // Call callback
                $this->callback->call($this, $upload->getTempFile());

                // Destroy upload
                $upload->destroy();

                // If upload is not complete, send 200 with bytes written
                return $this->respond([
                    'size' => $bytesWritten,
                ], 200);   

            }
            
        } catch (\Exception $e) {

            $this->respond([
                'error' => $e->getMessage(),
            ], $e->getCode());

        }
    }

    /**
     * Read a handshake
     *
     * @return void 
     */
    protected function readHandshake()
    {
        try {

            $fileHash = $this->fromRequest('checksum');
            $fileName = $this->fromRequest('name');
            $fileSize = $this->fromRequest('size');
            $fileType = $this->fromRequest('type');

            // New upload
            $upload = new Upload(
                $this->datastore,
                $this->datastore->generateUuid(),
                $fileHash,
                $fileName,
                $fileSize,
                $fileType,
                static::getTempFile()
            );

            // Save upload
            $upload->save();

            // Initialize temp file
            $upload->clear();

            // Respond 201 with upload UUID, nonce and max chunk size
            return $this->respond([
                'uuid' => $upload->getUuid(),
                'nonce' => $upload->getNonce(),
                'max_size' => $this->maximumChunkSize,
            ], 201);

        } catch (\Exception $e) {

            $this->respond([
                'error' => $e->getMessage(),
            ], $e->getCode());

        }
    }

    /**
     * Read a cancel
     *
     * @return void 
     */
    protected function readCancel()
    {
        try {

            $uuid = $this->fromRequest('uuid');

            // Find upload
            $upload = $this->getUpload($uuid);

            // Delete upload
            $upload->destroy();

            // Respond 200
            return $this->respond([], 200);

        } catch (\Exception $e) {

            $this->respond([
                'error' => $e->getMessage(),
            ], $e->getCode());

        }
    }
}
