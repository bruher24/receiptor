<?php

namespace App\Ocr;

final readonly class OcrWord
{
    public function __construct(
        public int $page,
        public int $block,
        public int $paragraph,
        public int $line,
        public int $word,
        public int $left,
        public int $top,
        public int $width,
        public int $height,
        public float $confidence,
        public string $text,
    ) {
    }
}
