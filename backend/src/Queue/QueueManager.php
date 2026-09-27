<?php

declare(strict_types=1);

namespace App\Queue;

use App\Repositories\JobRepository;
use App\Utils\Logger;
use Throwable;

class QueueManager
{
    private JobRepository $jobRepository;
    private int $maxAttempts;
    private int $retryDelaySeconds;

    public function __construct(?JobRepository $jobRepository = null)
    {
        $this->jobRepository = $jobRepository ?? new JobRepository();
        $this->maxAttempts = (int) config('app.queue.max_attempts', 3);
        $this->retryDelaySeconds = (int) config('app.queue.retry_delay_seconds', 5);
    }

    public function push(string $jobClass, array $payload, string $queue = 'default', int $delaySeconds = 0): int
    {
        return $this->jobRepository->push($jobClass, $payload, $queue, $delaySeconds);
    }

    public function processNextJob(string $queue = 'default'): ?array
    {
        $job = $this->jobRepository->reserveNext($queue);
        if (!$job) {
            return null;
        }

        $jobId = (int) $job['id'];
        $jobClass = $job['job_class'];
        $payload = json_decode($job['payload'], true) ?: [];
        $attempts = (int) $job['attempts'];

        $startTime = microtime(true);
        Logger::info("Processing queue job #{$jobId} ({$jobClass})", [
            'queue' => $queue,
            'attempt' => $attempts,
        ]);

        try {
            if (!class_exists($jobClass)) {
                throw new \RuntimeException("Job class {$jobClass} not found.");
            }

            $jobInstance = new $jobClass();
            if (!$jobInstance instanceof JobInterface) {
                throw new \RuntimeException("Class {$jobClass} must implement JobInterface.");
            }

            $jobInstance->handle($payload);

            $this->jobRepository->markCompleted($jobId);
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Logger::info("Job #{$jobId} completed in {$duration}ms", [
                'job_id' => $jobId,
                'class' => $jobClass,
            ]);

            return [
                'success' => true,
                'job_id' => $jobId,
                'class' => $jobClass,
                'duration_ms' => $duration,
            ];
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
            Logger::error("Job #{$jobId} failed on attempt {$attempts}: {$errorMessage}", [
                'job_id' => $jobId,
                'class' => $jobClass,
                'trace' => $e->getTraceAsString(),
            ]);

            if ($attempts >= $this->maxAttempts) {
                $this->jobRepository->markFailed($jobId, $errorMessage);
                return [
                    'success' => false,
                    'job_id' => $jobId,
                    'class' => $jobClass,
                    'status' => 'failed',
                    'error' => $errorMessage,
                ];
            }

            // Exponential backoff retry: 5s, 10s, 20s...
            $backoff = $this->retryDelaySeconds * (int) pow(2, $attempts - 1);
            $this->jobRepository->release($jobId, $backoff, $errorMessage);

            return [
                'success' => false,
                'job_id' => $jobId,
                'class' => $jobClass,
                'status' => 'released_for_retry',
                'retry_delay' => $backoff,
                'error' => $errorMessage,
            ];
        }
    }

    public function runWorker(string $queue = 'default', int $maxJobs = 0, int $sleepSeconds = 2): void
    {
        $processed = 0;
        echo "Starting queue worker for queue: [{$queue}]...\n";

        while (true) {
            $result = $this->processNextJob($queue);

            if ($result !== null) {
                $processed++;
                echo sprintf("[%s] Processed job #%d (%s) - %s\n",
                    date('Y-m-d H:i:s'),
                    $result['job_id'],
                    $result['class'],
                    $result['success'] ? 'SUCCESS' : 'FAILED: ' . ($result['error'] ?? '')
                );

                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    echo "Reached maximum job count ({$maxJobs}). Exiting.\n";
                    break;
                }
            } else {
                if ($maxJobs > 0) {
                    break;
                }
                sleep($sleepSeconds);
            }
        }
    }

    public function getStats(): array
    {
        return $this->jobRepository->getStats();
    }
}
