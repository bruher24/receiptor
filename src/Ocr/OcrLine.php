<?php

namespace App\Ocr;

final readonly class OcrLine
{
    /**
     * @param OcrWord[] $words
     */
    public function __construct(
        public int $page,
        public int $block,
        public int $paragraph,
        public int $line,
        public array $words,
    ) {
    }

    public function text(): string
    {
        return implode(' ', array_map(
            static fn (OcrWord $word) => $word->text,
            $this->words
        ));
    }
}
