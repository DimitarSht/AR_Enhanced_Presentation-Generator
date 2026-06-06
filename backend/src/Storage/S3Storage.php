<?php

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class S3Storage implements FileStorage
{
    private S3Client $client;

    public function __construct(
        private string $bucket,
        string $region,
        private string $prefix = '',
        ?string $endpoint = null,
        bool $pathStyle = false
    ) {
        if ($bucket === '') {
            throw new InvalidArgumentException('AWS_S3_BUCKET is required when STORAGE_DRIVER=s3.');
        }

        $options = [
            'version' => 'latest',
            'region' => $region,
            'use_path_style_endpoint' => $pathStyle,
        ];

        if ($endpoint !== null) {
            $options['endpoint'] = $endpoint;
        }

        $this->client = new S3Client($options);
        $this->prefix = trim($prefix, '/');
    }

    public function put(string $key, string $localPath): void
    {
        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($key),
            'SourceFile' => $localPath,
            'ServerSideEncryption' => 'AES256',
        ]);
    }

    public function download(string $key, string $localPath): void
    {
        $directory = dirname($localPath);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create staging directory: {$directory}");
        }

        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($key),
            'SaveAs' => $localPath,
        ]);
    }

    public function exists(string $key): bool
    {
        try {
            $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($key),
            ]);
            return true;
        } catch (AwsException $e) {
            $status = $e->getStatusCode();
            if ($status === 404) {
                return false;
            }
            throw $e;
        }
    }

    public function size(string $key): ?int
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key' => $this->key($key),
            ]);
            return isset($result['ContentLength']) ? (int) $result['ContentLength'] : null;
        } catch (AwsException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    public function delete(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($key),
        ]);
    }

    private function key(string $key): string
    {
        $key = ltrim(str_replace('\\', '/', $key), '/');
        return $this->prefix === '' ? $key : $this->prefix . '/' . $key;
    }
}
