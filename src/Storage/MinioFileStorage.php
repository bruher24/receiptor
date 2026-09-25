<?php

namespace App\Storage;

use Aws\S3\S3Client;
use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

final readonly class MinioFileStorage implements FileStorageInterface
{
    public function __construct(
        private S3Client $client,
        private string   $bucket,
    )
    {
    }

    public function store(UploadedFile $file): string
    {
        $extension = $file->guessExtension() ?? 'bin';
        $path = sprintf('receipts/%s.%s', Uuid::v7()->toRfc4122(), $extension);

        $this->client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
            'Body' => fopen($file->getPathname(), 'rb'),
            'ContentType' => $file->getMimeType() ?? 'application/octet-stream',
        ]);

        return $path;
    }

    public function delete(string $path): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);
    }

    public function readStream(string $path): StreamInterface
    {
        $file = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
        ]);

        return $file['Body'];
    }

    public function download(string $path, string $destination): void
    {
        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $path,
            'SaveAs' => $destination,
        ]);
    }
}
