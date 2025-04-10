<?php

namespace SlicedUpload\SlicedUpload;

use SlicedUpload\Helper;
use SlicedUpload\IDatastore;

class Upload
{
    protected $datastore;
    protected $uuid;
    protected $fileHash;
    protected $fileName;
    protected $fileSize;
    protected $fileType;
    protected $tempFile;
    protected $nonce;

    /**
     * Constructor
     *
     * @param IDatastore $datastore 
     * @param string $uuid
     * @param string $fileHash
     * @param string $tempFile
     * @param string $fileName
     * @param int $fileSize
     * @param string $fileType
     * @param string $nonce
     */
    public function __construct(
        IDatastore $datastore,
        $uuid,
        $fileHash,
        $fileName,
        $fileSize,
        $fileType,
        $tempFile,
        $nonce = null
    ) {
        $this->datastore = $datastore;
        $this->uuid      = $uuid;
        $this->fileHash  = $fileHash;
        $this->fileName  = $fileName;
        $this->fileSize  = $fileSize;
        $this->fileType  = $fileType;
        $this->tempFile  = $tempFile;
        $this->nonce     = $nonce;
    }

    /**
     * Check if the upload is completed
     *
     * @return bool
     */
    public function isCompleted()
    {
        $realSize = filesize($this->tempFile);

        if ($realSize === false || $realSize > $this->fileSize) {

            throw new \RuntimeException(
                'Upload failed',
                500
            );

        }

        return $realSize === $this->fileSize;
    }

    /**
     * Get the temporary file
     * 
     * @return string
     */
    public function getTempFile()
    {
        return $this->tempFile;
    }

    /**
     * Get the nonce
     *
     * @return void 
     */
    public function getNonce()
    {
        $this->nonce = $this->datastore->generateNonce();
        $this->save();

        return $this->nonce;
    }

    /**
     * Get the UUID
     *
     * @return string
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Verify the nonce
     * 
     * @param string $nonce 
     * @return bool 
     */
    public function verifyNonce($nonce)
    {
        return (mb_strtolower($nonce) === mb_strtolower($this->nonce));
    }

    /**
     * Append a chunk to the upload
     *
     * @param Chunk $chunk
     * @return int 
     */
    public function append(Chunk $chunk)
    {
        // Append chunk to temp file
        $bytesWritten = file_put_contents(
            $this->tempFile,
            $chunk->getContent(),
            FILE_APPEND
        );

        $chunk->delete();

        if ($bytesWritten === false) {

            throw new \RuntimeException(
                'Failed to append chunk',
                500
            );

        }

        return $bytesWritten;
    }

    /**
     * Clear the temp file
     *
     * @return void 
     */
    public function clear()
    {
        file_put_contents($this->tempFile, '');
    }

    /**
     * Load the upload from the datastore
     *
     * @param mixed $uuid 
     *
     * @return static
     */
    public static function load(IDatastore $datastore, $uuid)
    {
        $data = $datastore->load(['uuid' => $uuid]);

        return new static(
            $datastore,
            $data['uuid'],
            $data['file_hash'],
            $data['file_name'],
            $data['file_size'],
            $data['file_type'],
            $data['temp_file'],
            $data['nonce']
        );
    }

    /**
     * Save the upload to the datastore
     *
     * @return static
     */
    public function save()
    {
        $this->datastore->save(
            [
                'uuid'      => $this->uuid,
                'temp_file' => $this->tempFile,
                'file_hash' => $this->fileHash,
                'file_name' => $this->fileName,
                'file_size' => $this->fileSize,
                'file_type' => $this->fileType,
                'nonce'     => $this->nonce,
            ],
            [
                'uuid'      => $this->uuid
            ]
        );

        return $this;
    }

    /**
     * Destroy the upload
     *
     * @return true 
     */
    public function destroy()
    {
        // Delete from datastore
        $this->datastore->delete([
            'uuid' => $this->uuid
        ]);

        // Delete temp file
        if (file_exists($this->tempFile)) {

            unlink($this->tempFile);

        }

        return true;
    }
}
