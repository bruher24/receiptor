<?php

namespace App\MessageHandler;

use App\Analysis\ReceiptAnalyzerInterface;
use App\Entity\ReceiptItem;
use App\Enum\ReceiptStatus;
use App\Message\ProcessReceiptGroqMessage;
use App\Metrics\ReceiptMetrics;
use App\Publish\HubManager;
use App\Repository\ReceiptRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessReceiptGroqMessageHandler
{
    public function __construct(
        private ReceiptRepository        $receipts,
        private ReceiptAnalyzerInterface $analyzer,
        private EntityManagerInterface   $entityManager,
        private HubManager               $hub,
        private LoggerInterface          $logger,
        private ReceiptMetrics           $receiptMetrics
    )
    {
    }

    public function __invoke(ProcessReceiptGroqMessage $message): void
    {
        $start = time();
        $receipt = $this->receipts->find($message->receiptId);

        if ($receipt === null) {
            throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
        }

        if ($receipt->getStatus() === ReceiptStatus::Cancelled) {
            $this->logger->warning('Обработка Groq отменена', [
                'receipt' => $receipt,
            ]);
            return;
        }

        $ocrText = $receipt->getOcrText();

        if (empty($ocrText)) {
            throw new RuntimeException('Пустой результат OCR');
        }

        $analysis = $this->analyzer->analyze($ocrText);

        $processed = $this->entityManager->wrapInTransaction(
            function () use ($message, $analysis) {
                $receipt = $this->receipts->findForUpdate($message->receiptId);

                if ($receipt === null) {
                    throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
                }

                if ($receipt->getStatus() === ReceiptStatus::Cancelled) {
                    $this->logger->warning('Обработка Groq отменена', [
                        'receipt' => $receipt,
                    ]);
                    return false;
                }

                $receipt->clearItems();

                foreach ($analysis->items as $item) {
                    $receiptItem = new ReceiptItem();
                    $receiptItem->setName($item->name);
                    $receiptItem->setQuantity($item->quantity);
                    $receiptItem->setUnitPrice($item->unitPrice);
                    $receiptItem->setTotalPrice($item->totalPrice);
                    $receipt->addItem($receiptItem);
                    $this->entityManager->persist($receiptItem);
                }

                $receipt->setMerchant($analysis->merchant);
                $receipt->setPurchasedAt($analysis->purchasedAt);
                $receipt->setInn($analysis->inn);
                $receipt->setTotalAmount($analysis->totalAmount);
                $receipt->setGroqProcessedAt(new DateTimeImmutable());
                $receipt->setStatus(ReceiptStatus::GroqProcessed);
                $this->entityManager->flush();
                return true;
            });

        if (!$processed) {
            return;
        }

        $groqProcessingDuration = time() - $start;
        $this->receiptMetrics->receiptProcessed();
        $this->receiptMetrics->observeGroqProcessingDuration($groqProcessingDuration);
        $fullProcessingDuration = $receipt->getGroqProcessedAt()->getTimestamp() - $receipt->getUploadedAt()->getTimestamp();
        $this->receiptMetrics->observeFullProcessingDuration($fullProcessingDuration);

        $this->hub->publish('receipts', [
            'type' => 'receipt.processed',
            'receiptId' => $receipt->getId(),
            'status' => $receipt->getStatus()->value,
            'statusText' => $receipt->getStatus()->label(),
        ]);
    }
}
