<?php

namespace App\Ocr;

use Symfony\Component\Process\Process;

final class TesseractOcr implements OcrInterface
{

    public function recognize(string $filePath): OcrResult
    {
        $process = new Process([
            'tesseract',
            $filePath,
            'stdout',
            '-l',
            'rus+eng',
            '--psm',
            '4',
        ]);

        $process->mustRun();
        $ocrText = $process->getOutput();
        return new OcrResult($ocrText);
    }
}
