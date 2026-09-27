import React from 'react';
import { Task } from '@/lib/types';
import { CheckCircle2, Clock, AlertTriangle, ListTodo } from 'lucide-react';

export function TaskStats({ tasks }: { tasks: Task[] }) {
  const total = tasks.length;
  const inProgress = tasks.filter((t) => t.status === 'in_progress').length;
  const completed = tasks.filter((t) => t.status === 'completed').length;
  const urgent = tasks.filter((t) => t.priority === 'urgent' || t.priority === 'high').length;

  const cards = [
    {
      label: 'Total Tasks',
      value: total,
      icon: ListTodo,
      color: 'text-blue-400',
      bg: 'bg-blue-500/10 border-blue-500/20',
    },
    {
      label: 'In Progress',
      value: inProgress,
      icon: Clock,
      color: 'text-amber-400',
      bg: 'bg-amber-500/10 border-amber-500/20',
    },
    {
      label: 'Completed',
      value: completed,
      icon: CheckCircle2,
      color: 'text-emerald-400',
      bg: 'bg-emerald-500/10 border-emerald-500/20',
    },
    {
      label: 'High / Urgent',
      value: urgent,
      icon: AlertTriangle,
      color: 'text-rose-400',
      bg: 'bg-rose-500/10 border-rose-500/20',
    },
  ];

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-6">
      {cards.map((c) => {
        const Icon = c.icon;
        return (
          <div
            key={c.label}
            className="p-4 rounded-xl bg-slate-900/60 border border-slate-800 flex items-center justify-between"
          >
            <div>
              <p className="text-xs font-medium text-slate-400">{c.label}</p>
              <p className="text-2xl font-bold text-slate-100 mt-1">{c.value}</p>
            </div>
            <div className={`p-2.5 rounded-xl border ${c.bg}`}>
              <Icon className={`w-5 h-5 ${c.color}`} />
            </div>
          </div>
        );
      })}
    </div>
  );
}
