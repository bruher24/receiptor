<?php

namespace App\Metrics;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;

final class MetricsRegistry
{
    private CollectorRegistry $registry;

    public function __construct()
    {
        Redis::setDefaultOptions([
            'host' => 'redis',
            'port' => 6379,
            'database' => 1,
        ]);

        Redis::setPrefix('receiptor_metrics');

        $this->registry = new CollectorRegistry(
            new Redis()
        );
    }

    public function getRegistry(): CollectorRegistry
    {
        return $this->registry;
    }
}
