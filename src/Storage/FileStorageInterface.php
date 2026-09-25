<?php

namespace App\Storage;

use Psr\Http\Message\StreamInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileStorageInterface
{
    public function store(UploadedFile $file): string;

    public function delete(string $path): void;

    public function readStream(string $path): StreamInterface;

    public function download(string $path, string $destination): void;
}
