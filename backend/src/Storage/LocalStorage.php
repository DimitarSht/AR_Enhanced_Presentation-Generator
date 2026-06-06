<?php

class LocalStorage implements FileStorage
{
    public function __construct(private string $root)
    {
        $this->root = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
    }

    public function put(string $key, string $localPath): void
    {
        $destination = $this->path($key);
        $this->ensureDirectory(dirname($destination));

        if (realpath($localPath) !== realpath($destination) && !copy($localPath, $destination)) {
            throw new RuntimeException("Unable to store file: {$key}");
        }
    }

    public function download(string $key, string $localPath): void
    {
        $source = $this->path($key);
        if (!is_file($source)) {
            throw new RuntimeException("Stored file does not exist: {$key}");
        }

        $this->ensureDirectory(dirname($localPath));
        if (realpath($source) !== realpath($localPath) && !copy($source, $localPath)) {
            throw new RuntimeException("Unable to stage file: {$key}");
        }
    }

    public function exists(string $key): bool
    {
        return is_file($this->path($key));
    }

    public function size(string $key): ?int
    {
        $path = $this->path($key);
        return is_file($path) ? filesize($path) : null;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException("Unable to delete file: {$key}");
        }
    }

    private function path(string $key): string
    {
        return $this->root . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($key, '/\\'));
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }
    }
}
