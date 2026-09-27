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
    if (!isAuthenticated || !user?.id) return;

    const reportPresence = () => {
      if (typeof document !== 'undefined' && document.hidden) return;
      api.sendPresence().catch(() => {});
    };

    reportPresence();
    const interval = setInterval(reportPresence, 35000);
    return () => clearInterval(interval);
  }, [isAuthenticated, user?.id]);

  useEffect(() => {
    if (!isAuthenticated) {
      setIsConnected(false);
      setOnlineUsers([]);
      return;
    }

    let isSubscribed = true;
    let isFetching = false;
    let lastTimestamp = Date.now() / 1000;
    let lastPollTime = Date.now();
    let pollInterval: NodeJS.Timeout | null = null;

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

    const poll = async () => {
      if (!isSubscribed || isFetching) return;
      if (typeof document !== 'undefined' && document.hidden) return;

      isFetching = true;
      lastPollTime = Date.now();
      try {
        const streamUrl = `${api.getRealtimeStreamUrl()}?poll=1&since=${lastTimestamp}`;
        const res = await fetch(streamUrl);
        if (!res.ok) {
          setIsConnected(false);
          return;
        }
        const json = await res.json();
        if (json.success) {
          setIsConnected(true);
          if (json.timestamp) {
            lastTimestamp = json.timestamp;
          }
          if (Array.isArray(json.active_users)) {
            setOnlineUsers(json.active_users);
          }
          if (Array.isArray(json.events)) {
            json.events.forEach((ev: any) => {
              handleMessageData(ev.event, ev.data);
            });
          }
        }
      } catch {
        setIsConnected(false);
      } finally {
        isFetching = false;
      }
    };

    // Initial poll
    poll();
    // Regular 20-second background sync interval
    pollInterval = setInterval(poll, 20000);

    const handleVisibilityChange = () => {
      if (typeof document !== 'undefined' && !document.hidden && isSubscribed) {
        // Debounce: only sync if more than 15 seconds passed since last poll
        if (Date.now() - lastPollTime > 15000) {
          poll();
        }
      }
    };

    if (typeof document !== 'undefined') {
      document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    return () => {
      isSubscribed = false;
      if (pollInterval) {
        clearInterval(pollInterval);
      }
      if (typeof document !== 'undefined') {
        document.removeEventListener('visibilitychange', handleVisibilityChange);
      }
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
