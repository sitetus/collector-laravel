<?php

namespace Sitetus\LaravelCollector\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Proves the queue worker is alive: a scheduled dispatch that,
 * once a worker picks it up, stamps the queue heartbeat file the collector
 * reads in Level 1.
 */
final class WriteQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        @file_put_contents(
            storage_path('app/sitetus-queue-heartbeat.json'),
            json_encode(['ts' => time(), 'php' => PHP_VERSION]),
        );
    }
}
