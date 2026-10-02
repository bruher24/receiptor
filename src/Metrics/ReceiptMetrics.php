<?php

namespace App\Metrics;

use Prometheus\Counter;
use Prometheus\Histogram;

final class ReceiptMetrics
{
    private Counter $uploaded;
    private Counter $processed;
    private Counter $canceled;
    private Histogram $processingDuration;

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

        $this->processingDuration = $registry->getOrRegisterHistogram(
            'receiptor',
            'receipt_processing_duration_seconds',
            'Time spent processing a receipt in seconds',
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

    public function observeProcessingDuration(float $seconds): void
    {
        $this->processingDuration->observe($seconds);
    }
}
