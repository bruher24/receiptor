<?php

namespace App\Controller;

use App\Dto\ReceiptDto;
use App\Entity\Receipt;
use App\Message\ProcessReceiptMessage;
use App\Service\ReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiptController extends AbstractController
{
    #[Route('/receipts/', name: 'app_receipts_index', methods: ['GET'])]
    public function index(#[MapQueryParameter] ?int $lastId,ReceiptService $receiptService): Response
    {
        $receiptsData = $receiptService->getAll($lastId);
        $nextLastId = !empty($receipts) ? end($receipts)->getId() : null;

        $dtoList = array_map(
            fn (Receipt $receipt) => ReceiptDto::fromEntity($receipt),
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

    #[Route('/receipts/add', name: 'app_receipts_add', methods: ['POST'])]
    public function add(
        Request             $request,
        ReceiptService      $receiptService,
        MessageBusInterface $bus
    ): Response
    {
        $file = $request->files->get('receipt');

        if (!$file) {
            throw $this->createNotFoundException('Файл не загружен.');
        }

        $receipt = $receiptService->createFromUpload($file);

        $bus->dispatch(
            new ProcessReceiptMessage($receipt->getId())
        );

        return new Response('', Response::HTTP_ACCEPTED);
    }
}
