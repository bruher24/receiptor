<?php

namespace App\Metrics;

use Prometheus\Counter;
use Prometheus\Histogram;

final class ReceiptMetrics
{
    private Counter $uploaded;
    private Counter $processed;
    private Counter $canceled;
    private Histogram $ocrProcessingDuration;
    private Histogram $groqProcessingDuration;
    private Histogram $fullProcessingDuration;

    public function __construct(MetricsRegistry $metricsRegistry)
    {
        $registry = $metricsRegistry->getRegistry();

        $this->uploaded = $registry->getOrRegisterCounter(
            'receiptor',
            'receipts_uploaded_total',
            'Total number of uploaded receipts',
        );

        $this->processed = $registry->getOrRegisterCounter(
            'receiptor',
            'receipts_processed_total',
            'Total number of successfully processed receipts',
        );

        $this->canceled = $registry->getOrRegisterCounter(
            'receiptor',
            'receipts_canceled_total',
            'Total number of canceled receipts',
        );

        $this->ocrProcessingDuration = $registry->getOrRegisterHistogram(
            'receiptor',
            'receipt_ocr_processing_duration_seconds',
            'Time spent processing a receipt by OCR in seconds',
        );

        $this->groqProcessingDuration = $registry->getOrRegisterHistogram(
            'receiptor',
            'receipt_groq_processing_duration_seconds',
            'Time spent processing a receipt by Groq in seconds',
        );

        $this->fullProcessingDuration = $registry->getOrRegisterHistogram(
            'receiptor',
            'receipt_full_processing_duration_seconds',
            'Time spent processing a receipt total in seconds',
        );
    }

    public function receiptUploaded(): void
    {
        $this->uploaded->inc();
    }

    public function receiptProcessed(): void
    {
        $this->processed->inc();
    }

    public function receiptCanceled(): void
    {
        $this->canceled->inc();
    }

    public function observeOcrProcessingDuration(float $seconds): void
    {
        $this->ocrProcessingDuration->observe($seconds);
    }

    public function observeGroqProcessingDuration(float $seconds): void
    {
        $this->groqProcessingDuration->observe($seconds);
    }

    public function observeFullProcessingDuration(float $seconds): void
    {
        $this->fullProcessingDuration->observe($seconds);
    }
}
