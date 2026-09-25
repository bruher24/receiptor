<?php

namespace App\Analysis;

use App\Ocr\OcrResult;
use DateTimeImmutable;

final readonly class ReceiptAnalysisResult
{
    /**
     * @param ReceiptItemData[] $items
     */
    public function __construct(
        public ?string $merchant,
        public ?string $inn,
        public ?DateTimeImmutable $purchasedAt,
        public array $items,
        public int $totalAmount
    ) {
    }
}
