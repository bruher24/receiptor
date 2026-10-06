<?php

namespace App\Dto;

use App\Entity\Receipt;
use App\Enum\ReceiptStatus;
use DateTimeImmutable;

final readonly class ReceiptDto
{
    public function __construct(
        public string             $originalFilename,
        public ReceiptStatus      $status,
        public ?int               $id = null,
        public ?DateTimeImmutable $uploadedAt = null,
        public ?DateTimeImmutable $ocrProcessedAt = null,
        public ?DateTimeImmutable $groqProcessedAt = null,
        public ?string            $ocrText = null,
        public ?DateTimeImmutable $purchasedAt = null,
        public ?string            $merchant = null,
        public ?string            $inn = null,
        public ?int               $totalAmount = null,
        public array              $items = [],
    )
    {
    }

    public static function fromEntity(Receipt $receipt): self
    {
        return new self(
            originalFilename: $receipt->getOriginalFilename(),
            status: $receipt->getStatus(),
            id: $receipt->getId(),
            uploadedAt: $receipt->getUploadedAt(),
            ocrProcessedAt: $receipt->getOcrProcessedAt(),
            groqProcessedAt: $receipt->getGroqProcessedAt(),
            ocrText: $receipt->getOcrText(),
            purchasedAt: $receipt->getPurchasedAt(),
            merchant: $receipt->getMerchant(),
            inn: $receipt->getInn(),
            totalAmount: $receipt->getTotalAmount(),
            items: array_map(
                fn($receiptItem) => ReceiptItemDto::fromEntity($receiptItem),
                $receipt->getItems()->toArray()
            ),
        );
    }
}
