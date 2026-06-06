<?php

require_once __DIR__ . '/../backend/src/Storage/FileStorage.php';
require_once __DIR__ . '/../backend/src/Storage/LocalStorage.php';

$root = sys_get_temp_dir() . '/ar-storage-' . uniqid('', true);
$source = tempnam(sys_get_temp_dir(), 'ar-source-');
$download = tempnam(sys_get_temp_dir(), 'ar-download-');

if ($source === false || $download === false) {
    throw new RuntimeException('Unable to create temporary test files.');
}

unlink($download);
file_put_contents($source, 'storage-smoke-test');

try {
    $storage = new LocalStorage($root);
    $key = 'processed/example.pptx';

    $storage->put($key, $source);
    if (!$storage->exists($key) || $storage->size($key) !== 18) {
        throw new RuntimeException('Stored file metadata did not match.');
    }

    $storage->download($key, $download);
    if (file_get_contents($download) !== 'storage-smoke-test') {
        throw new RuntimeException('Downloaded file content did not match.');
    }

    $storage->delete($key);
    if ($storage->exists($key)) {
        throw new RuntimeException('Stored file was not deleted.');
    }

    echo "Storage smoke test passed.\n";
} finally {
    @unlink($source);
    @unlink($download);
    @rmdir($root . '/processed');
    @rmdir($root);
}
