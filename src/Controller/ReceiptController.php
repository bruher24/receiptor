<?php

namespace App\Controller;

use App\Message\ProcessReceiptMessage;
use App\Service\ReceiptService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ReceiptController extends AbstractController
{
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
