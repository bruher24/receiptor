<?php

namespace App\Message;

final readonly class ProcessReceiptOcrMessage
{
    public function __construct(public int $receiptId)
    {
    }
}
