<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Queue\QueueManager;

class QueueController
{
    private QueueManager $queueManager;

    public function __construct(?QueueManager $queueManager = null)
    {
        $this->queueManager = $queueManager ?? new QueueManager();
    }

    public function stats(Request $request): Response
    {
        $stats = $this->queueManager->getStats();
        return Response::success($stats);
    }

    public function work(Request $request): Response
    {
        $queue = (string) $request->input('queue', 'default');
        $limit = min(50, max(1, (int) $request->input('limit', 1)));

        $processed = [];
        for ($i = 0; $i < $limit; $i++) {
            $result = $this->queueManager->processNextJob($queue);
            if ($result === null) {
                break;
            }
            $processed[] = $result;
        }

        return Response::success([
            'processed_count' => count($processed),
            'results' => $processed,
            'stats' => $this->queueManager->getStats(),
        ], 'Queue processed.');
    }
}
