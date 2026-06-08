<?php

interface FileStorage
{
    public function put(string $key, string $localPath): void;

    public function download(string $key, string $localPath): void;

    public function exists(string $key): bool;

    public function size(string $key): ?int;

    public function delete(string $key): void;

    public function deleteByPrefix(string $prefix): void;
}
