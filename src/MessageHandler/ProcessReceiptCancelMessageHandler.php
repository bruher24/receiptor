<?php

namespace App\MessageHandler;

use App\Enum\ReceiptStatus;
use App\Message\ProcessReceiptCancelMessage;
use App\Metrics\ReceiptMetrics;
use App\Publish\HubManager;
use App\Repository\ReceiptRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessReceiptCancelMessageHandler
{
    public function __construct(
        private ReceiptRepository      $receipts,
        private EntityManagerInterface $entityManager,
        private HubManager             $hub,
        private ReceiptMetrics         $receiptMetrics
    )
    {
    }

    public function __invoke(ProcessReceiptCancelMessage $message): void
    {
        $receipt = $this->receipts->find($message->receiptId);

        if ($receipt === null) {
            throw new RuntimeException(sprintf('Чек %d не найден', $message->receiptId));
        }

        $this->entityManager->wrapInTransaction(
            function () use ($receipt) {
                $receipt->clearItems();
                $receipt->setStatus(ReceiptStatus::Cancelled);
                $this->entityManager->flush();
            }
        );

        $this->receiptMetrics->receiptCanceled();

        $this->hub->publish('receipts', [
            'type' => 'receipt.cancelled',
            'receiptId' => $receipt->getId(),
            'status' => $receipt->getStatus()->value,
            'statusText' => $receipt->getStatus()->label(),
        ]);
    }
}
