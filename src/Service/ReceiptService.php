<?php

namespace App\Service;

use App\Entity\Receipt;
use App\Repository\ReceiptRepository;
use App\Storage\FileStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ReceiptService
{
    public function __construct(
        private FileStorageInterface   $storage,
        private EntityManagerInterface $entityManager,
        private ReceiptRepository      $receiptRepository
    )
    {
    }

    public function getAll(?int $lastId = null): array
    {
        $limit = 50;

        $qb = $this->receiptRepository->createQueryBuilder('r')
            ->orderBy('r.id', 'DESC')
            ->setMaxResults($limit + 1);

        if (isset($lastId)) {
            $qb->where('r.id < :lastId')
                ->setParameter('lastId', $lastId);
        }

        $query = $qb->getQuery();
        $receipts = $query->getResult();
        $hasMore = count($receipts) > $limit;

        if ($hasMore) {
            array_pop($receipts);
        }

        return ['receipts' => $receipts, 'hasMore' => $hasMore];
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

    public function get(int $receiptId): ?Receipt
    {
        return $this->receiptRepository->find($receiptId);
    }
}
