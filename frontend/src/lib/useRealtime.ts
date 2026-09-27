'use client';

import { useEffect, useState, useRef, useCallback } from 'react';
import { api } from './api';
import { useAuth } from './authContext';

export interface OnlineUser {
  id: number;
  name: string;
  role: string;
  active_task_id: number | null;
  last_seen: number;
}

export interface TypingEvent {
  user_id: number;
  user_name: string;
  task_id: number;
  is_typing: boolean;
  timestamp: number;
}

interface UseRealtimeOptions {
  onTaskChange?: () => void;
  onCommentChange?: (taskId: number) => void;
}

export function useRealtime(options: UseRealtimeOptions = {}) {
  const { isAuthenticated, user } = useAuth();
  const [onlineUsers, setOnlineUsers] = useState<OnlineUser[]>([]);
  const [typingMap, setTypingMap] = useState<Record<number, TypingEvent>>({});
  const [isConnected, setIsConnected] = useState(false);

  const optionsRef = useRef(options);
  optionsRef.current = options;

  useEffect(() => {
    if (!isAuthenticated || !user) return;

    const reportPresence = () => {
      api.sendPresence().catch(() => {});
    };

    reportPresence();
    const interval = setInterval(reportPresence, 25000);
    return () => clearInterval(interval);
  }, [isAuthenticated, user]);

  useEffect(() => {
    if (!isAuthenticated) {
      setIsConnected(false);
      setOnlineUsers([]);
      return;
    }

    let eventSource: EventSource | null = null;
    let pollInterval: any = null;
    let lastTimestamp = Date.now() / 1000 - 10;
    let isSubscribed = true;

    const handleMessageData = (event: string, payload: any) => {
      if (!isSubscribed) return;

      if (event === 'presence.updated' && Array.isArray(payload?.active_users)) {
        setOnlineUsers(payload.active_users);
      } else if (event === 'connected' && Array.isArray(payload?.active_users)) {
        setOnlineUsers(payload.active_users);
      } else if (event === 'user.typing' && payload?.task_id) {
        setTypingMap((prev) => ({
          ...prev,
          [payload.task_id]: payload,
        }));
      } else if (
        event === 'task.created' ||
        event === 'task.updated' ||
        event === 'task.deleted' ||
        event === 'task.bulk_status'
      ) {
        optionsRef.current.onTaskChange?.();
      } else if (event === 'comment.created' || event === 'comment.deleted') {
        if (payload?.task_id) {
          optionsRef.current.onCommentChange?.(payload.task_id);
        }
        optionsRef.current.onTaskChange?.();
      }
    };

    try {
      const streamUrl = api.getRealtimeStreamUrl();
      eventSource = new EventSource(streamUrl);

      eventSource.onopen = () => {
        if (isSubscribed) setIsConnected(true);
      };

      const eventNames = [
        'connected',
        'presence.updated',
        'user.typing',
        'task.created',
        'task.updated',
        'task.deleted',
        'task.bulk_status',
        'comment.created',
        'comment.deleted',
      ];

      eventNames.forEach((name) => {
        eventSource?.addEventListener(name, (e: MessageEvent) => {
          try {
            const data = JSON.parse(e.data);
            handleMessageData(name, data);
          } catch {}
        });
      });

      eventSource.onerror = () => {
        // Close on error and rely on polling fallback
        eventSource?.close();
        setIsConnected(false);
      };
    } catch {
      setIsConnected(false);
    }

    // Polling fallback every 6 seconds to ensure reliable delta updates
    pollInterval = setInterval(async () => {
      if (!isSubscribed) return;
      try {
        const streamUrl = `${api.getRealtimeStreamUrl()}?poll=1&since=${lastTimestamp}`;
        const res = await fetch(streamUrl);
        if (!res.ok) return;
        const json = await res.json();
        if (json.success) {
          if (Array.isArray(json.active_users)) {
            setOnlineUsers(json.active_users);
          }
          if (Array.isArray(json.events)) {
            json.events.forEach((ev: any) => {
              handleMessageData(ev.event, ev.data);
              if (ev.timestamp > lastTimestamp) {
                lastTimestamp = ev.timestamp;
              }
            });
          }
        }
      } catch {}
    }, 6000);

    return () => {
      isSubscribed = false;
      if (eventSource) eventSource.close();
      if (pollInterval) clearInterval(pollInterval);
    };
  }, [isAuthenticated]);

  const sendTyping = useCallback(
    (taskId: number, isTyping: boolean) => {
      if (!isAuthenticated) return;
      api.sendTyping(taskId, isTyping).catch(() => {});
    },
    [isAuthenticated]
  );

  return {
    onlineUsers,
    typingMap,
    sendTyping,
    isConnected,
  };
}
