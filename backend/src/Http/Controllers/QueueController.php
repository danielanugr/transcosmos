<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class QueueController extends Controller
{
    public function stats(): JsonResponse
    {
        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pending' => $pending,
                'failed' => $failed,
                'driver' => config('queue.default'),
            ],
        ]);
    }

    public function work(Request $request): JsonResponse
    {
        $limit = min(20, max(1, (int) $request->input('limit', 1)));
        $processed = 0;

        for ($i = 0; $i < $limit; $i++) {
            $hasJob = DB::table('jobs')->exists();
            if (!$hasJob) {
                break;
            }
            Artisan::call('queue:work', ['--once' => true, '--stop-when-empty' => true]);
            $processed++;
        }

        $pending = DB::table('jobs')->count();
        $failed = DB::table('failed_jobs')->count();

        return response()->json([
            'success' => true,
            'message' => 'Queue processed.',
            'data' => [
                'processed_count' => $processed,
                'stats' => [
                    'pending' => $pending,
                    'failed' => $failed,
                ],
            ],
        ]);
    }
}
