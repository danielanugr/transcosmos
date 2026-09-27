<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RealtimeService
{
    private const EVENTS_CACHE_KEY = 'realtime_recent_events';
    private const PRESENCE_SET_KEY = 'realtime_active_users';
    private const MAX_EVENTS = 100;

    public function broadcast(string $event, array $data): array
    {
        $payload = [
            'id' => (string) hrtime(true),
            'event' => $event,
            'data' => $data,
            'timestamp' => microtime(true),
        ];

        $events = Cache::get(self::EVENTS_CACHE_KEY, []);
        $events[] = $payload;

        if (count($events) > self::MAX_EVENTS) {
            $events = array_slice($events, -self::MAX_EVENTS);
        }

        Cache::put(self::EVENTS_CACHE_KEY, $events, now()->addMinutes(30));

        return $payload;
    }

    public function getEventsSince(float $timestamp): array
    {
        $events = Cache::get(self::EVENTS_CACHE_KEY, []);
        return array_values(array_filter($events, fn($e) => ($e['timestamp'] ?? 0) > $timestamp));
    }

    public function recordPresence(int $userId, string $userName, ?string $role = null, ?int $activeTaskId = null): void
    {
        $userKey = "realtime_user_{$userId}";
        $data = [
            'id' => $userId,
            'name' => $userName,
            'role' => $role ?? 'member',
            'active_task_id' => $activeTaskId,
            'last_seen' => time(),
        ];

        Cache::put($userKey, $data, now()->addSeconds(60));

        $activeIds = Cache::get(self::PRESENCE_SET_KEY, []);
        if (!in_array($userId, $activeIds, true)) {
            $activeIds[] = $userId;
            Cache::put(self::PRESENCE_SET_KEY, $activeIds, now()->addHours(1));
        }

        $this->broadcast('presence.updated', [
            'active_users' => $this->getActiveUsers(),
        ]);
    }

    public function getActiveUsers(): array
    {
        $activeIds = Cache::get(self::PRESENCE_SET_KEY, []);
        $now = time();
        $onlineUsers = [];
        $validIds = [];

        foreach ($activeIds as $id) {
            $data = Cache::get("realtime_user_{$id}");
            if ($data && ($now - $data['last_seen']) < 60) {
                $onlineUsers[] = $data;
                $validIds[] = $id;
            }
        }

        if (count($validIds) !== count($activeIds)) {
            Cache::put(self::PRESENCE_SET_KEY, $validIds, now()->addHours(1));
        }

        return $onlineUsers;
    }

    public function broadcastTyping(int $userId, string $userName, int $taskId, bool $isTyping): void
    {
        $this->broadcast('user.typing', [
            'user_id' => $userId,
            'user_name' => $userName,
            'task_id' => $taskId,
            'is_typing' => $isTyping,
            'timestamp' => time(),
        ]);
    }
}
