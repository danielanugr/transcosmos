<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AdvancedCacheService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdvancedCacheServiceTest extends TestCase
{
    private AdvancedCacheService $cacheService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheService = new AdvancedCacheService();
        Cache::flush();
    }

    public function test_remembers_and_retrieves_data_from_cache(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return ['status' => 'success', 'timestamp' => microtime(true)];
        };

        $res1 = $this->cacheService->rememberWithLock('test_key_1', 60, $callback);
        $this->assertSame(1, $callCount);
        $this->assertSame('success', $res1['status']);

        $res2 = $this->cacheService->rememberWithLock('test_key_1', 60, $callback);
        $this->assertSame(1, $callCount);
        $this->assertSame($res1['timestamp'], $res2['timestamp']);
    }

    public function test_invalidate_namespace_bumps_version_and_refreshes_data(): void
    {
        $callCount = 0;
        $callback = function () use (&$callCount) {
            $callCount++;
            return "value_call_{$callCount}";
        };

        $val1 = $this->cacheService->rememberWithLock('test_inv_key', 60, $callback);
        $this->assertSame('value_call_1', $val1);
        $this->assertSame(1, $callCount);

        $newVersion = $this->cacheService->invalidateNamespace();
        $this->assertGreaterThan(1, $newVersion);

        $val2 = $this->cacheService->rememberWithLock('test_inv_key', 60, $callback);
        $this->assertSame('value_call_2', $val2);
        $this->assertSame(2, $callCount);
    }

    public function test_computes_etag_and_validates_match(): void
    {
        $payload = ['id' => 1, 'title' => 'Test Task'];
        $etag = $this->cacheService->computeETag($payload);

        $this->assertStringStartsWith('"', $etag);
        $this->assertStringEndsWith('"', $etag);

        $this->assertTrue($this->cacheService->isNotModified($etag, $etag));
        $this->assertTrue($this->cacheService->isNotModified(trim($etag, '"'), $etag));
        $this->assertFalse($this->cacheService->isNotModified('"different_hash"', $etag));
        $this->assertFalse($this->cacheService->isNotModified(null, $etag));
    }

    public function test_records_telemetry_metrics(): void
    {
        $this->cacheService->rememberWithLock('telemetry_key', 60, fn() => 'sample');
        $this->cacheService->rememberWithLock('telemetry_key', 60, fn() => 'sample');

        $stats = $this->cacheService->getTelemetryStats();
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('l1_hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('writes', $stats);
        $this->assertGreaterThanOrEqual(1, $stats['writes']);
    }
}
