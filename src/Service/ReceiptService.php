<?php

namespace App\Service;

use App\Entity\Receipt;
use App\Metrics\ReceiptMetrics;
use App\Repository\ReceiptRepository;
use App\Storage\FileStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ReceiptService
{
    public function __construct(
        private FileStorageInterface   $storage,
        private EntityManagerInterface $entityManager,
        private ReceiptRepository      $receiptRepository,
        private ReceiptMetrics         $receiptMetrics
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

    public function validateUpload(UploadedFile $uploadedFile): void
    {
        if (!$uploadedFile->isValid()) {
            throw new InvalidArgumentException(
                sprintf('Ошибка загрузки файла: %s', $uploadedFile->getErrorMessage())
            );
        }

        $size = $uploadedFile->getSize();

        if (!$size || $size > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Размер файла не должен превышать 2 MiB');
        }

        if (!in_array($uploadedFile->getMimeType(), ['image/jpeg', 'image/png'], true)) {
            throw new InvalidArgumentException('Поддерживаются только JPEG и PNG изображения');
        }

        if (mb_strlen($uploadedFile->getClientOriginalName()) > 255) {
            throw new InvalidArgumentException('Имя файла не должно превышать 255 символов');
        }
    }

    public function createFromUpload(UploadedFile $uploadedFile): Receipt
    {
        $this->validateUpload($uploadedFile);

        $storagePath = $this->storage->store($uploadedFile);

        $receipt = new Receipt();
        $receipt->setOriginalFilename($uploadedFile->getClientOriginalName());
        $receipt->setStoragePath($storagePath);
        $receipt->setUploadedAt(new DateTimeImmutable());

        $this->entityManager->persist($receipt);
        $this->entityManager->flush();

        $this->receiptMetrics->receiptUploaded();

        return $receipt;
    }

    public function get(int $receiptId): ?Receipt
    {
        return $this->receiptRepository->find($receiptId);
    }
}
