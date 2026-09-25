<?php

namespace App\Analysis;

interface ReceiptAnalyzerInterface
{
    public function analyze(string $ocrText): ReceiptAnalysisResult;
}
