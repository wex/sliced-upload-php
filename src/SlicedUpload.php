<?php

namespace SlicedUpload;

use RuntimeException;
use SlicedUpload\SlicedUpload\Chunk;
use SlicedUpload\SlicedUpload\Upload;

class SlicedUpload
{
    const DEFAULT_MAXIMUM_CHUNK_SIZE = 1_048_576;

    protected $maximumChunkSize = self::DEFAULT_MAXIMUM_CHUNK_SIZE;
    protected $callback;

    /**
     * Constructor
     * 
     * @param int $maximumChunkSize 
     *
     * @return void 
     */
    public function __construct($maximumChunkSize = self::DEFAULT_MAXIMUM_CHUNK_SIZE)
    {
        $this->maximumChunkSize = $maximumChunkSize;
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

    // @todo rewrite
    public static function process(callable $callback, Datastore $datastore)
    {
        Upload::$datastore = $datastore;
        
        $instance = new static();
        $instance->callback = $callback;

        switch (strtoupper($_SERVER['REQUEST_METHOD'])) {
            case 'POST': return $instance->readHandshake();
            case 'PATCH': return $instance->readChunk();
            case 'DELETE': return $instance->readCancel();
            #case 'HEAD': return $instance->readStatus();
        }

        throw new \RuntimeException(sprintf(
            'Invalid method: %s',
            $_SERVER['REQUEST_METHOD']
        ), 500);
    }

    /**
     * Get a request parameter
     *
     * @param string $key 
     * @param bool $isRequired 
     * @param string|null $default 
     * @return mixed 
     */
    protected function fromRequest(string $key, bool $isRequired = true, string $default = null)
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
        return filesize($upload->tempFile) >= $upload->fileSize;
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
            $upload = Upload::find($uuid, $nonce);

            // Append chunk to upload
            $bytesWritten =$upload->append($chunk);

            // Process chunk
            if ($this->isCompleted($upload)) {

                // If upload is not complete, send 202 with new nonce and bytes written
                return $this->respond([
                    'nonce' => $upload->nonce,
                    'size' => $bytesWritten,
                ], 202);            

            } else {

                // Call callback
                call_user_func($this->callback, $upload->tempFile);

                // If upload is not complete, send 200 with bytes written
                return Helper::ok([
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

            // Create upload
            $upload = Upload::create(
                $fileHash, 
                $fileName, 
                $fileSize, 
                $fileType
            );

            // Respond 201 with upload UUID, nonce and max chunk size
            return $this->respond([
                'uuid' => $upload->uuid,
                'nonce' => $upload->nonce,
                'max_size' => Helper::getMaxSize(),
            ], 201);

        } catch (\Exception $e) {

            $this->respond([
                'error' => $e->getMessage(),
            ], $e->getCode());
        }
    }

    protected function readCancel()
    {
        try {

            $uuid = Request::post('uuid');

            // Fetch upload
            $upload = Upload::fetch($uuid);
            
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
