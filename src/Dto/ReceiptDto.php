<?php

namespace App\Dto;

use App\Entity\Receipt;
use App\Enum\ReceiptStatus;
use DateTimeImmutable;

final readonly class ReceiptDto
{
    public function __construct(
        public ?int               $id = null,
        public string             $originalFilename,
        public string             $storagePath,
        public ReceiptStatus      $status,
        public ?DateTimeImmutable $uploadedAt = null,
        public ?DateTimeImmutable $processedAt = null,
        public array              $items,
        public ?string            $ocrText = null,
        public ?DateTimeImmutable $purchasedAt = null,
        public ?string            $merchant = null,
        public ?string            $inn = null,
        public ?int               $totalAmount = null
    )
    {
    }

    public static function fromEntity(Receipt $receipt): self
    {
        return new self(
            $receipt->getId(),
            $receipt->getOriginalFilename(),
            $receipt->getStoragePath(),
            $receipt->getStatus(),
            $receipt->getUploadedAt(),
            $receipt->getProcessedAt(),
            array_map(
                fn($receiptItem) => ReceiptItemDto::fromEntity($receiptItem),
                $receipt->getItems()->toArray()
            ),
            $receipt->getOcrText(),
            $receipt->getPurchasedAt(),
            $receipt->getMerchant(),
            $receipt->getInn(),
            $receipt->getTotalAmount(),
        );
    }
}
