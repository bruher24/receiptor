<?php

namespace App\Message;

final readonly class ProcessReceiptCancelMessage
{
    public function __construct(public int $receiptId)
    {
    }
}
