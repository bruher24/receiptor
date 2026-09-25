<?php

namespace App\Dto;

use App\Entity\ReceiptItem;

final readonly class ReceiptItemDto
{
    public function __construct(
        public int    $id,
        public string $name,
        public int    $quantity,
        public int    $unitPrice,
        public int    $totalPrice,
    )
    {
    }

    public static function fromEntity(ReceiptItem $receiptItem): self
    {
        return new self(
            $receiptItem->getId(),
            $receiptItem->getName(),
            $receiptItem->getQuantity(),
            $receiptItem->getUnitPrice(),
            $receiptItem->getTotalPrice(),
        );
    }
}
