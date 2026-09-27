<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AdvancedCacheService
{
    private const VERSION_KEY = 'tasks_cache_version';
    private const STATS_KEY = 'cache_telemetry_stats';

    /**
     * Statically memoized request-scoped L1 cache
     */
    private static array $requestMemoryCache = [];

    /**
     * Fetch from L1/L2 cache with cache stampede (dogpile) protection
     */
    public function rememberWithLock(string $baseKey, int $ttlSeconds, Closure $callback): mixed
    {
        $version = $this->getVersion();
        $namespacedKey = "v{$version}:{$baseKey}";

        // 1. L1 Request-scoped memory cache check
        if (array_key_exists($namespacedKey, self::$requestMemoryCache)) {
            $this->recordMetric('l1_hit');
            return self::$requestMemoryCache[$namespacedKey];
        }

        // 2. L2 Persistent cache check
        if (Cache::has($namespacedKey)) {
            $value = Cache::get($namespacedKey);
            self::$requestMemoryCache[$namespacedKey] = $value;
            $this->recordMetric('l2_hit');
            return $value;
        }

        $this->recordMetric('miss');

        // 3. Cache Stampede Protection using atomic mutex lock
        $lockKey = "lock:{$namespacedKey}";
        $lock = Cache::lock($lockKey, 10);

        try {
            // Wait up to 3 seconds for lock; if another process is calculating, read their result
            return $lock->block(3, function () use ($namespacedKey, $ttlSeconds, $callback) {
                // Double-checked locking
                if (Cache::has($namespacedKey)) {
                    $val = Cache::get($namespacedKey);
                    self::$requestMemoryCache[$namespacedKey] = $val;
                    return $val;
                }

                $freshValue = $callback();
                Cache::put($namespacedKey, $freshValue, now()->addSeconds($ttlSeconds));
                self::$requestMemoryCache[$namespacedKey] = $freshValue;
                $this->recordMetric('write');

                return $freshValue;
            });
        } catch (\Throwable $e) {
            Log::warning("Cache lock acquisition fallback: " . $e->getMessage());
            // Fallback directly to callback if lock driver fails
            $freshValue = $callback();
            Cache::put($namespacedKey, $freshValue, now()->addSeconds($ttlSeconds));
            return $freshValue;
        }
    }

    /**
     * Increment namespace version, instantaneously invalidating all dependent cache keys
     */
    public function invalidateNamespace(): int
    {
        self::$requestMemoryCache = [];
        $current = $this->getVersion();
        $newVersion = $current + 1;
        Cache::put(self::VERSION_KEY, $newVersion, now()->addDays(30));
        $this->recordMetric('invalidation');

        return $newVersion;
    }

    /**
     * Get current cache version
     */
    public function getVersion(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    /**
     * Compute HTTP ETag hash for payload
     */
    public function computeETag(mixed $data): string
    {
        $serialized = is_string($data) ? $data : json_encode($data);
        return '"' . sha1($serialized) . '"';
    }

    /**
     * Check if client's If-None-Match matches ETag
     */
    public function isNotModified(?string $ifNoneMatch, string $etag): bool
    {
        if (empty($ifNoneMatch)) {
            return false;
        }

        $cleanHeader = trim($ifNoneMatch);
        return $cleanHeader === $etag || $cleanHeader === str_replace('"', '', $etag);
    }

    /**
     * Record cache telemetry metric
     */
    private function recordMetric(string $type): void
    {
        try {
            $stats = Cache::get(self::STATS_KEY, [
                'l1_hits' => 0,
                'l2_hits' => 0,
                'misses' => 0,
                'writes' => 0,
                'invalidations' => 0,
            ]);

            $key = match ($type) {
                'l1_hit' => 'l1_hits',
                'l2_hit' => 'l2_hits',
                'miss' => 'misses',
                'write' => 'writes',
                'invalidation' => 'invalidations',
                default => null,
            };

            if ($key) {
                $stats[$key]++;
                Cache::put(self::STATS_KEY, $stats, now()->addDays(7));
            }
        } catch (\Throwable) {
            // Non-blocking telemetry
        }
    }

    /**
     * Get telemetry stats
     */
    public function getTelemetryStats(): array
    {
        return Cache::get(self::STATS_KEY, [
            'l1_hits' => 0,
            'l2_hits' => 0,
            'misses' => 0,
            'writes' => 0,
            'invalidations' => 0,
        ]);
    }
}
