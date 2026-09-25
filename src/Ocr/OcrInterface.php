<?php

namespace App\Ocr;

interface OcrInterface
{
    public function recognize(string $filePath): OcrResult;
}
