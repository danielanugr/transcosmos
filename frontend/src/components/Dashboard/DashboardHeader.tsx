'use client';

import React, { useState } from 'react';
import { useAuth } from '@/lib/authContext';
import { useToast } from '../UI/Toast';
import { api } from '@/lib/api';
import { LogOut, Plus, Download, RefreshCw, Layers } from 'lucide-react';

interface DashboardHeaderProps {
  onNewTask: () => void;
  onRefresh: () => void;
}

export function DashboardHeader({ onNewTask, onRefresh }: DashboardHeaderProps) {
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
    <header className="border-b border-slate-800 bg-slate-900/80 backdrop-blur-md sticky top-0 z-30">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-blue-600/10 border border-blue-500/20 text-blue-400">
            <Layers className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <span className="font-bold text-slate-100 text-base tracking-tight">TaskManager</span>
              <span className="text-[10px] uppercase font-semibold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
                v1.0
              </span>
            </div>
            <p className="text-xs text-slate-400">Full-Stack Assessment Platform</p>
          </div>
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
            className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold shadow-md shadow-blue-600/20 transition-all cursor-pointer"
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
