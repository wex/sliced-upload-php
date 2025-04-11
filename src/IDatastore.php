<?php

namespace SlicedUpload;

interface IDatastore
{
    public function exists(array $keys): bool;
    public function load(array $keysAndValues, bool $throws = true): array|false;
    public function save(array $data, array $keys = []): array;
    public function delete(array $keys): bool;
    public function generateUuid(): string;
    public function generateNonce(): string;
}
