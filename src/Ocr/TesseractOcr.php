<?php

namespace App\Ocr;

use RuntimeException;
use Symfony\Component\Process\Process;

final class TesseractOcr implements OcrInterface
{
    public function __construct(private OcrParser $ocrParser){}

    public function recognize(string $filePath): OcrResult
    {
        $outputFile = tempnam(sys_get_temp_dir(), 'ocr_');

        if ($outputFile === false) {
            throw new RuntimeException('Не удалось создать временный файл');
        }

        try {
            $process = new Process([
                'tesseract',
                $filePath,
                'stdout',
                '-l',
                'rus+eng',
                '--psm',
                '4',
                'tsv'
            ]);

            $process->mustRun();

            $tsv = $process->getOutput();
            $parsed = $this->ocrParser->parse($tsv);

            return new OcrResult(json_encode($parsed));
        } finally {
            @unlink($outputFile);
            @unlink($outputFile . '.txt');
        }
    }
}
