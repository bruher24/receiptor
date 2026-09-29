<?php

namespace App\MessageHandler;

use App\Enum\ReceiptStatus;
use App\Message\ProcessReceiptGroqMessage;
use App\Message\ProcessReceiptOcrMessage;
use App\Ocr\OcrInterface;
use App\Repository\ReceiptRepository;
use App\Storage\FileStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ProcessReceiptOcrMessageHandler
{
    public function __construct(
        private ReceiptRepository      $receipts,
        private FileStorageInterface   $storage,
        private OcrInterface           $ocr,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface    $bus,
        private LoggerInterface        $logger
    )
    {
    }

    public function __invoke(ProcessReceiptOcrMessage $message): void
    {
        $receipt = $this->receipts->find($message->receiptId);

        if ($receipt === null) {
            throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
        }

        if ($receipt->getStatus() === ReceiptStatus::Cancelled) {
            $this->logger->warning('Обработка OCR отменена', [
                'receipt' => $receipt,
            ]);
            return;
        }

        $tempFile = null;

        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'receipt_');

            if ($tempFile === false) {
                throw new RuntimeException('Не удалось создать временный файл');
            }

            $this->storage->download($receipt->getStoragePath(), $tempFile);
            $ocrResult = $this->ocr->recognize($tempFile);

            $processed = $this->entityManager->wrapInTransaction(function () use ($message, $ocrResult) {
                $receipt = $this->receipts->findForUpdate($message->receiptId);

                if ($receipt === null) {
                    throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
                }

                if ($receipt->getStatus() === ReceiptStatus::Cancelled) {
                    $this->logger->warning('Обработка OCR отменена', [
                        'receipt' => $receipt,
                    ]);
                    return false;
                }

                $receipt->setOcrProcessedAt(new DateTimeImmutable());
                $receipt->setOcrText($ocrResult->text);
                $receipt->setStatus(ReceiptStatus::OcrProcessed);
                $this->entityManager->flush();
                return true;
            });

            if (!$processed) {
                return;
            }

            $this->bus->dispatch(
                new ProcessReceiptGroqMessage($receipt->getId())
            );
        } finally {
            if (isset($tempFile) && is_file($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
