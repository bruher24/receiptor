<?php

namespace App\Analysis;

final readonly class ReceiptItemData
{
    public function __construct(
        public string $name,
        public int $quantity,
        public int $unitPrice,
        public int $totalPrice,
    ) {
    }
}
