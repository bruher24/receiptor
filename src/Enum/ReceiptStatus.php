<?php

namespace App\Enum;

enum ReceiptStatus: string
{
    case Pending = 'pending';
    case OcrProcessed = 'ocrProcessed';
    case GroqProcessed = 'groqProcessed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Обработка',
            self::OcrProcessed => 'Обработано OCR',
            self::GroqProcessed => 'Обработано Groq',
            self::Cancelled => 'Отменено',
        };
    }
}
