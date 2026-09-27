'use client';

import React, { useState } from 'react';
import { useAuth } from '@/lib/authContext';
import { useToast } from '../UI/Toast';
import { api } from '@/lib/api';
import { LogOut, Plus, Download, RefreshCw, Layers } from 'lucide-react';
import { OnlineUser } from '@/lib/useRealtime';

interface DashboardHeaderProps {
  onNewTask: () => void;
  onRefresh: () => void;
  onlineUsers?: OnlineUser[];
  isRealtimeConnected?: boolean;
}

export function DashboardHeader({
  onNewTask,
  onRefresh,
  onlineUsers = [],
  isRealtimeConnected = false,
}: DashboardHeaderProps) {
  const { user, logout } = useAuth();
  const { toast } = useToast();
  const [exporting, setExporting] = useState(false);
  const [workingQueue, setWorkingQueue] = useState(false);

  const handleExport = async () => {
    setExporting(true);
    try {
      await api.exportTasks();
      toast('Export job dispatched. The CSV will be generated via background queue.', 'success');
    } catch (err: any) {
      toast(err.message || 'Export failed.', 'error');
    } finally {
      setExporting(false);
    }
  };

  const handleRunQueue = async () => {
    setWorkingQueue(true);
    try {
      const res = await api.runQueueWork();
      toast(`Background queue processed: ${res.processed} jobs executed, ${res.remaining} remaining.`, 'info');
      onRefresh();
    } catch (err: any) {
      toast(err.message || 'Queue trigger failed.', 'error');
    } finally {
      setWorkingQueue(false);
    }
  };

  return (
    <header className="border-b border-slate-800 bg-slate-900 sticky top-0 z-30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-lg bg-slate-800 text-slate-200 border border-slate-700/60">
            <Layers className="w-5 h-5 text-blue-400" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-bold text-slate-100 text-base tracking-tight">TaskManager</span>
              {isRealtimeConnected && (
                <span className="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-950/60 text-emerald-400 border border-emerald-800/60">
                  <span className="w-1.5 h-1.5 rounded-full bg-emerald-400" />
                  Live
                </span>
              )}
            </div>
            <p className="text-xs text-slate-400">Workspace Tasks & Processing</p>
          </div>

          {onlineUsers && onlineUsers.length > 0 && (
            <div className="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950 border border-slate-800 text-[11px] text-slate-300 ml-2">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>{onlineUsers.length} Online</span>
              <div className="flex -space-x-1 overflow-hidden ml-1">
                {onlineUsers.slice(0, 3).map((u) => (
                  <span
                    key={u.id}
                    title={`${u.name} (${u.role})`}
                    className="inline-flex items-center justify-center h-4 w-4 rounded-full ring-1 ring-slate-900 bg-slate-800 text-[9px] text-slate-200 font-bold"
                  >
                    {u.name.charAt(0)}
                  </span>
                ))}
              </div>
            </div>
          )}
        </div>

        <div className="flex items-center gap-2 sm:gap-3 flex-wrap">
          <button
            type="button"
            onClick={onRefresh}
            className="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 transition-colors"
            title="Refresh tasks"
            aria-label="Refresh tasks"
          >
            <RefreshCw className="w-4 h-4" />
          </button>

          <button
            type="button"
            onClick={handleRunQueue}
            disabled={workingQueue}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700/60 transition-colors disabled:opacity-50"
            title="Execute pending queue jobs"
          >
            <RefreshCw className={`w-3.5 h-3.5 text-amber-400 ${workingQueue ? 'animate-spin' : ''}`} />
            Run Queue
          </button>

          <button
            type="button"
            onClick={handleExport}
            disabled={exporting}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700/60 transition-colors disabled:opacity-50"
          >
            <Download className="w-3.5 h-3.5 text-sky-400" />
            Export CSV
          </button>

          <button
            type="button"
            onClick={onNewTask}
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold transition-colors cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            New Task
          </button>

          <div className="h-6 w-[1px] bg-slate-800 mx-1 hidden sm:block" />

          {user && (
            <div className="flex items-center gap-2.5 pl-1">
              <div className="text-right hidden md:block">
                <div className="text-xs font-semibold text-slate-200">{user.name}</div>
                <div className="text-[11px] text-slate-400 flex items-center justify-end gap-1.5">
                  <span className="capitalize">{user.role}</span>
                </div>
              </div>
              <button
                type="button"
                onClick={logout}
                className="p-2 rounded-lg bg-slate-800/80 hover:bg-rose-950/40 hover:text-rose-400 text-slate-400 transition-colors border border-slate-700/60"
                title="Sign out"
                aria-label="Sign out"
              >
                <LogOut className="w-4 h-4" />
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
