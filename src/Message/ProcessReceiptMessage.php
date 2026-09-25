<?php

namespace App\Message;

final readonly class ProcessReceiptMessage
{
    public function __construct(public int $receiptId)
    {
    }
}
