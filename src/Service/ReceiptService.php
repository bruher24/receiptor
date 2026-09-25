<?php

namespace App\Service;

use App\Entity\Receipt;
use App\Storage\FileStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ReceiptService
{
    public function __construct(
        private FileStorageInterface $storage,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function createFromUpload(UploadedFile $uploadedFile): Receipt
    {
        $storagePath = $this->storage->store($uploadedFile);

        $receipt = new Receipt();
        $receipt->setOriginalFilename($uploadedFile->getClientOriginalName());
        $receipt->setStoragePath($storagePath);
        $receipt->setUploadedAt(new DateTimeImmutable());

        $this->entityManager->persist($receipt);
        $this->entityManager->flush();

        return $receipt;
    }
}
