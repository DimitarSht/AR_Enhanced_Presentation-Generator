<?php

function storage(): FileStorage
{
    static $storage = null;

    if ($storage === null) {
        $storage = STORAGE_DRIVER === 's3'
            ? new S3Storage(AWS_S3_BUCKET, AWS_REGION, AWS_S3_PREFIX, AWS_S3_ENDPOINT, AWS_S3_PATH_STYLE)
            : new LocalStorage(STORAGE_DIR);
    }

    return $storage;
}

function storageKey(string $category, string $filename): string
{
    $categories = [
        'presentations' => 'presentations',
        'processed' => 'processed',
        'qrcodes' => 'qrcodes',
        'ai_images' => 'ai_generated/images',
        'ai_texts' => 'ai_generated/texts',
    ];

    if (!isset($categories[$category])) {
        throw new InvalidArgumentException("Unknown storage category: {$category}");
    }

    return $categories[$category] . '/' . basename($filename);
}

function persistFile(string $category, string $filename, string $localPath): void
{
    storage()->put(storageKey($category, $filename), $localPath);
}

function ensureLocalFile(string $category, string $filename, string $localPath): bool
{
    if (is_file($localPath)) {
        return true;
    }

    $key = storageKey($category, $filename);
    if (!storage()->exists($key)) {
        return false;
    }

    storage()->download($key, $localPath);
    return is_file($localPath);
}

function storedFileExists(string $category, string $filename): bool
{
    return storage()->exists(storageKey($category, $filename));
}

function storedFileSize(string $category, string $filename): ?int
{
    return storage()->size(storageKey($category, $filename));
}

function deleteStoredFile(string $category, string $filename, string $localPath): void
{
    storage()->delete(storageKey($category, $filename));

    if (STORAGE_DRIVER === 's3' && is_file($localPath)) {
        unlink($localPath);
    }
}
