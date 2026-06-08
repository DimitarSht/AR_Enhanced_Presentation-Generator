<?php

function storage(): FileStorage
{
    static $storage = null;

    if ($storage === null) {
        $storage = STORAGE_DRIVER === 's3'
            ? new S3Storage(
                AWS_S3_BUCKET,
                AWS_REGION,
                AWS_S3_PREFIX,
                AWS_S3_ENDPOINT,
                AWS_S3_PATH_STYLE,
                AWS_ACCESS_KEY_ID,
                AWS_SECRET_ACCESS_KEY,
                AWS_SESSION_TOKEN
            )
            : new LocalStorage(STORAGE_DIR);
    }

    return $storage;
}

function storageKey(string $category, string $filename): string
{
    return storageCategoryPath($category) . '/' . basename($filename);
}

function storageCategoryPath(string $category): string
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

    return $categories[$category];
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

function deleteStoredFilesByPrefix(string $category, string $filenamePrefix, string $localDirectory): void
{
    $filenamePrefix = basename($filenamePrefix);
    storage()->deleteByPrefix(storageCategoryPath($category) . '/' . $filenamePrefix);

    if (STORAGE_DRIVER === 's3' && is_dir($localDirectory)) {
        foreach (new DirectoryIterator($localDirectory) as $file) {
            if ($file->isFile() && str_starts_with($file->getFilename(), $filenamePrefix)) {
                unlink($file->getPathname());
            }
        }
    }
}
