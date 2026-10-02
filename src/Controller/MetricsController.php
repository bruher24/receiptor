<?php

namespace App\Controller;

use App\Metrics\MetricsRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final readonly class MetricsController
{
    public function __construct(
        private MetricsRegistry $metricsRegistry,
    )
    {
    }

    #[Route('/metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        $renderer = new RenderTextFormat();

        return new Response(
            $renderer->render(
                $this->metricsRegistry
                    ->getRegistry()
                    ->getMetricFamilySamples(),
            ),
            Response::HTTP_OK,
            [
                'Content-Type' => RenderTextFormat::MIME_TYPE,
            ],
        );
    }
}
