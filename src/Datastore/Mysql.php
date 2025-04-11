<?php

namespace SlicedUpload\Datastore;

use PDO;
use SlicedUpload\IDatastore;

class Mysql implements IDatastore
{
    const TABLE = '__uploads';

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * Constructor
     *
     * @param PDO $pdo 
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
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

    /**
     * See if a row exists by given keys
     *
     * @param array $keys 
     *
     * @return bool 
     */
    public function exists(array $keys): bool
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE %s',
            static::TABLE,
            implode(
                ' AND ',
                $this->_kvpToWhere($keys)
            )
        );

        return $this->pdo->query($sql)->fetchColumn(0) > 0;
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
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE %s LIMIT 1',
            static::TABLE,
            implode(
                ' AND ',
                $this->_kvpToWhere($keysAndValues)
            )
        );
        
        $smt = $this->pdo->prepare($sql);
        $smt->execute();

        $row = $smt->fetch(\PDO::FETCH_ASSOC);

        $smt->closeCursor();

        if ($row === false && $throws) {

            throw new \RuntimeException(
                'No record found',
                404
            );

        }

        return $row;
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
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            static::TABLE,
            implode(
                ' AND ',
                $this->_kvpToWhere($keys)
            )
        );

        return $this->pdo->exec($sql);
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
        $existing = $this->load($keys, false);

        if ($existing) {

            // Update
            $sql = sprintf(
                'UPDATE `%s` SET %s WHERE %s',
                static::TABLE,
                implode(', ', $this->_kvpToWhere($data)),
                implode(', ', $this->_kvpToWhere($keys))
            );

            if (!$this->pdo->exec($sql)) {

                throw new \RuntimeException(
                    'Failed to update record',
                    500
                );

            }

        } else {

            // Insert
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                static::TABLE,
                implode(', ', $this->_kvpToFields(array_merge($data, $keys))),
                implode(', ', $this->_kvpToValues(array_merge($data, $keys)))
            );

            if (!$this->pdo->exec($sql)) {

                throw new \RuntimeException(
                    'Failed to insert record',
                    500
                );

            }

        }

        return $this->load($keys);
    }

    /**
     * Convert an array of keys and values to a WHERE clause
     *
     * @param array $keysAndValues 
     *
     * @return array 
     */
    protected function _kvpToWhere(array $keysAndValues)
    {
        return array_map(
            function ($value, $key) {
                return sprintf(
                    '`%s` = %s',
                    $key,
                    $this->pdo->quote($value, \PDO::PARAM_STR)
                );
            },
            $keysAndValues,
            array_keys($keysAndValues)
        );
    }

    /**
     * Convert an array of keys and values to a list of fields
     *
     * @param array $keysAndValues 
     *
     * @return array 
     */
    protected function _kvpToFields(array $keysAndValues)
    {
        return array_map(
            function ($key) {
                return sprintf(
                    '`%s`',
                    $key
                );
            },
            array_keys($keysAndValues)
        );
    }

    /**
     * Convert an array of keys and values to a list of values
     *
     * @param array $keysAndValues 
     *
     * @return array 
     */
    protected function _kvpToValues(array $keysAndValues)
    {
        return array_map(
            function ($value) {
                return sprintf(
                    '%s',
                    (null === $value) ? 'NULL' : $this->pdo->quote($value, \PDO::PARAM_STR)
                );
            },
            $keysAndValues
        );
    }

}