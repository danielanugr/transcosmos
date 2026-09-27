import React from 'react';
import { TaskPriority, TaskStatus } from '@/lib/types';

export function StatusBadge({ status }: { status: TaskStatus }) {
  const configs: Record<TaskStatus, { label: string; className: string }> = {
    pending: {
      label: 'Pending',
      className: 'bg-amber-500/10 text-amber-400 border-amber-500/30',
    },
    in_progress: {
      label: 'In Progress',
      className: 'bg-blue-500/10 text-blue-400 border-blue-500/30',
    },
    completed: {
      label: 'Completed',
      className: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
    },
    cancelled: {
      label: 'Cancelled',
      className: 'bg-slate-500/10 text-slate-400 border-slate-500/30',
    },
  };

  const config = configs[status] || configs.pending;

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${config.className}`}
    >
      {config.label}
    </span>
  );
}

export function PriorityBadge({ priority }: { priority: TaskPriority }) {
  const configs: Record<TaskPriority, { label: string; className: string }> = {
    low: {
      label: 'Low',
      className: 'bg-slate-500/10 text-slate-400 border-slate-500/20',
    },
    medium: {
      label: 'Medium',
      className: 'bg-sky-500/10 text-sky-400 border-sky-500/30',
    },
    high: {
      label: 'High',
      className: 'bg-orange-500/10 text-orange-400 border-orange-500/30',
    },
    urgent: {
      label: 'Urgent',
      className: 'bg-rose-500/15 text-rose-400 border-rose-500/40 font-semibold animate-pulse',
    },
  };

  const config = configs[priority] || configs.medium;

  return (
    <span
      className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border ${config.className}`}
    >
      {config.label}
    </span>
  );
}
