import React from 'react';
import { TaskPriority, TaskStatus } from '@/lib/types';

export function StatusBadge({ status }: { status: TaskStatus }) {
  const configs: Record<TaskStatus, { label: string; className: string; dot: string }> = {
    pending: {
      label: 'Pending',
      className: 'bg-amber-950/40 text-amber-300 border-amber-800/60',
      dot: 'bg-amber-400',
    },
    in_progress: {
      label: 'In Progress',
      className: 'bg-blue-950/40 text-blue-300 border-blue-800/60',
      dot: 'bg-blue-400',
    },
    completed: {
      label: 'Completed',
      className: 'bg-emerald-950/40 text-emerald-300 border-emerald-800/60',
      dot: 'bg-emerald-400',
    },
    cancelled: {
      label: 'Cancelled',
      className: 'bg-slate-900 text-slate-400 border-slate-700/60',
      dot: 'bg-slate-500',
    },
  };

  const config = configs[status] || configs.pending;

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-medium border ${config.className}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${config.dot}`} />
      {config.label}
    </span>
  );
}

export function PriorityBadge({ priority }: { priority: TaskPriority }) {
  const configs: Record<TaskPriority, { label: string; className: string }> = {
    low: {
      label: 'Low',
      className: 'bg-slate-900 text-slate-400 border-slate-700/60',
    },
    medium: {
      label: 'Medium',
      className: 'bg-sky-950/40 text-sky-300 border-sky-800/60',
    },
    high: {
      label: 'High',
      className: 'bg-orange-950/40 text-orange-300 border-orange-800/60',
    },
    urgent: {
      label: 'Urgent',
      className: 'bg-rose-950/60 text-rose-300 border-rose-800/80 font-semibold',
    },
  };

  const config = configs[priority] || configs.medium;

  return (
    <span
      className={`inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border ${config.className}`}
    >
      {config.label}
    </span>
  );
}
