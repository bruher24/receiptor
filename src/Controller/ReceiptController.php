<?php

namespace App\Controller;

use App\Dto\ReceiptDto;
use App\Entity\Receipt;
use App\Message\ProcessReceiptCancelMessage;
use App\Message\ProcessReceiptOcrMessage;
use App\Service\ReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiptController extends AbstractController
{
    #[Route('/receipts', name: 'app_receipts_index', methods: ['GET'])]
    public function index(#[MapQueryParameter] ?int $lastId, ReceiptService $receiptService): Response
    {
        $receiptsData = $receiptService->getAll($lastId);
        $nextLastId = !empty($receiptsData['receipts']) ? end($receiptsData['receipts'])->getId() : null;

        $dtoList = array_map(
            fn(Receipt $receipt) => ReceiptDto::fromEntity($receipt),
            $receiptsData['receipts']
        );

        $data = [
            'receipts' => $dtoList,
            'nextLastId' => $nextLastId,
            'hasMore' => $receiptsData['hasMore'],
        ];

        return $this->json(
            data: $data,
            context: ['json_encode_options' => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT]
        );
    }

    #[Route('/receipts', name: 'app_receipts_add', methods: ['POST'])]
    public function add(
        Request             $request,
        ReceiptService      $receiptService,
        MessageBusInterface $bus
    ): Response
    {
        $files = $request->files->all();

        if (empty($files)) {
            throw $this->createNotFoundException('Файл не загружен');
        }

        $receipts = [];

        foreach ($files as $file) {
            $receipt = $receiptService->createFromUpload($file);

            $bus->dispatch(
                new ProcessReceiptOcrMessage($receipt->getId())
            );

            $receipts[] = ReceiptDto::fromEntity($receipt);
        }

        return $this->json(
            data: $receipts,
            context: ['json_encode_options' => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT]
        );
    }

    #[Route('/receipts/{receiptId}', name: 'app_receipts_show', methods: ['GET'])]
    public function show(int $receiptId, ReceiptService $receiptService): Response
    {
        $receipt = $receiptService->get($receiptId);

        if (empty($receipt)) {
            throw $this->createNotFoundException('Чек не найден');
        }

        $dto = ReceiptDto::fromEntity($receipt);

        return $this->json(
            data: $dto,
            context: ['json_encode_options' => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT]
        );
    }

    #[Route('/receipts/{receiptId}/cancel', name: 'app_receipts_cancel', methods: ['PATCH'])]
    public function cancel(
        int                 $receiptId,
        MessageBusInterface $bus
    ): Response
    {
        $bus->dispatch(
            new ProcessReceiptCancelMessage($receiptId)
        );

        return $this->json(
            data: [],
            status: Response::HTTP_ACCEPTED,
            context: ['json_encode_options' => JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT]
        );
    }
}
