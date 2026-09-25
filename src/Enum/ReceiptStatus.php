<?php

namespace App\Enum;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Обработка',
            self::Processed => 'Обработано',
            self::Canceled => 'Отменено',
        };
    }
}
