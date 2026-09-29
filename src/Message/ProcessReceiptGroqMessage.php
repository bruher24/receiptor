<?php

namespace App\Message;

final readonly class ProcessReceiptGroqMessage
{
    public function __construct(public int $receiptId)
    {
    }
}
