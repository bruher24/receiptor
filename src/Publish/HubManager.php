<?php

namespace App\Publish;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

final readonly class HubManager
{
    public function __construct(
        private HubInterface    $hub,
        private LoggerInterface $logger
    )
    {
    }

    public function publish(array|string $topics, array $data): void
    {
        try {
            $this->hub->publish(
                new Update(
                    $topics,
                    json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                )
            );
        } catch (Throwable $e) {
            $this->logger->error(
                'Ошибка публикации события: ' . $e->getMessage(),
                [
                    'data' => $data,
                    'exception' => $e,
                ]
            );
        }
    }
}
