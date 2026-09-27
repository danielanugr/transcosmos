<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RealtimeController extends Controller
{
    public function __construct(private RealtimeService $realtime) {}

    public function stream(Request $request): StreamedResponse|JsonResponse
    {
        if ($request->query('poll') === '1') {
            $since = (float) $request->query('since', 0);
            return response()->json([
                'success' => true,
                'events' => $this->realtime->getEventsSince($since),
                'active_users' => $this->realtime->getActiveUsers(),
                'timestamp' => microtime(true),
            ]);
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () {
            $lastTime = microtime(true) - 10;
            $startTime = time();

            // Initial connection event with active users
            $initialPayload = json_encode([
                'active_users' => $this->realtime->getActiveUsers(),
                'connected_at' => now()->toIso8601String(),
            ]);
            echo "event: connected\ndata: {$initialPayload}\n\n";
            flush();

            // SSE loop running for max 25 seconds per HTTP request to avoid gateway timeout
            while (time() - $startTime < 25) {
                if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
                    break;
                }

                $newEvents = $this->realtime->getEventsSince($lastTime);
                foreach ($newEvents as $event) {
                    $eventName = $event['event'];
                    $payload = json_encode($event['data']);
                    echo "id: {$event['id']}\nevent: {$eventName}\ndata: {$payload}\n\n";
                    $lastTime = max($lastTime, (float) $event['timestamp']);
                }

                echo ": ping\n\n";
                flush();

                sleep(1);
            }
        }, 200, $headers);
    }

    public function updatePresence(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
        }

        $taskId = $request->input('task_id') ? (int) $request->input('task_id') : null;
        $this->realtime->recordPresence(
            $user->id,
            $user->name,
            $user->role,
            $taskId
        );

        return response()->json([
            'success' => true,
            'message' => 'Presence refreshed.',
            'active_users' => $this->realtime->getActiveUsers(),
        ]);
    }

    public function getPresence(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->realtime->getActiveUsers(),
        ]);
    }

    public function typing(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'error' => 'Unauthenticated.'], 401);
        }

        $validated = $request->validate([
            'task_id' => 'required|integer',
            'is_typing' => 'required|boolean',
        ]);

        $this->realtime->broadcastTyping(
            $user->id,
            $user->name,
            (int) $validated['task_id'],
            (bool) $validated['is_typing']
        );

        return response()->json([
            'success' => true,
            'message' => 'Typing event emitted.',
        ]);
    }
}
