<?php

namespace App\MessageHandler;

use App\Analysis\ReceiptAnalyzerInterface;
use App\Entity\ReceiptItem;
use App\Enum\ReceiptStatus;
use App\Message\ProcessReceiptMessage;
use App\Ocr\OcrInterface;
use App\Repository\ReceiptRepository;
use App\Storage\FileStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class ProcessReceiptMessageHandler
{
    public function __construct(
        private ReceiptRepository        $receipts,
        private FileStorageInterface     $storage,
        private OcrInterface             $ocr,
        private ReceiptAnalyzerInterface $analyzer,
        private EntityManagerInterface   $entityManager,
        private HubInterface             $hub,
        private LoggerInterface          $logger,
    )
    {
    }

    public function __invoke(ProcessReceiptMessage $message): void
    {
        $receipt = $this->receipts->find($message->receiptId);

        if ($receipt === null) {
            throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
        }

        $tempFile = null;

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'receipt_');

            if ($tempFile === false) {
                throw new RuntimeException('Не удалось создать временный файл');
            }

            $this->storage->download($receipt->getStoragePath(), $tempFile);
            $ocrResult = $this->ocr->recognize($tempFile);
            $analysis = $this->analyzer->analyze($ocrResult->text);

            $this->entityManager->wrapInTransaction(
                function () use ($receipt, $analysis, $ocrResult) {
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
                    $receipt->setOcrText($ocrResult->text);
                    $receipt->setInn($analysis->inn);
                    $receipt->setTotalAmount($analysis->totalAmount);
                    $receipt->setStatus(ReceiptStatus::Processed);
                    $receipt->setProcessedAt(new DateTimeImmutable());
                    $this->entityManager->flush();
                }
            );

            try {
                $this->hub->publish(
                    new Update(
                        'receipts',
                        json_encode([
                            'type' => 'receipt.processed',
                            'receiptId' => $receipt->getId(),
                            'status' => $receipt->getStatus()->value,
                            'statusText' => $receipt->getStatus()->label(),
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    )
                );
            } catch (Throwable $e) {
                $this->logger->error(
                    'Ошибка публикации события: ' . $e->getMessage(),
                    [
                        'receiptId' => $receipt->getId(),
                        'exception' => $e,
                    ]
                );
            }
        } finally {
            if (isset($tempFile) && is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
