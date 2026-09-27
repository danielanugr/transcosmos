import React from 'react';
import { Task } from '@/lib/types';
import { CheckCircle2, Clock, AlertTriangle, ListTodo } from 'lucide-react';

interface TaskStatsProps {
  tasks: Task[];
  activeStatus?: string;
  activePriority?: string;
  onFilterChange: (status: string, priority?: string) => void;
}

export function TaskStats({
  tasks,
  activeStatus = 'all',
  activePriority = 'all',
  onFilterChange,
}: TaskStatsProps) {
  const total = tasks.length;
  const inProgress = tasks.filter((t) => t.status === 'in_progress').length;
  const completed = tasks.filter((t) => t.status === 'completed').length;
  const urgent = tasks.filter((t) => t.priority === 'urgent' || t.priority === 'high').length;

  const cards = [
    {
      id: 'all',
      label: 'All Tasks',
      value: total,
      icon: ListTodo,
      isActive: activeStatus === 'all' && activePriority === 'all',
      onClick: () => onFilterChange('all', 'all'),
      color: 'text-slate-300',
    },
    {
      id: 'in_progress',
      label: 'In Progress',
      value: inProgress,
      icon: Clock,
      isActive: activeStatus === 'in_progress',
      onClick: () => onFilterChange('in_progress', 'all'),
      color: 'text-blue-400',
    },
    {
      id: 'completed',
      label: 'Completed',
      value: completed,
      icon: CheckCircle2,
      isActive: activeStatus === 'completed',
      onClick: () => onFilterChange('completed', 'all'),
      color: 'text-emerald-400',
    },
    {
      id: 'urgent',
      label: 'High & Urgent',
      value: urgent,
      icon: AlertTriangle,
      isActive: activePriority === 'urgent' || activePriority === 'high',
      onClick: () => onFilterChange('all', 'urgent'),
      color: 'text-rose-400',
    },
  ];

  return (
    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6" role="group" aria-label="Task overview metrics">
      {cards.map((c) => {
        const Icon = c.icon;
        return (
          <button
            key={c.id}
            type="button"
            onClick={c.onClick}
            className={`p-4 rounded-xl border text-left transition-all cursor-pointer ${
              c.isActive
                ? 'bg-slate-900 border-blue-500/80 shadow-sm'
                : 'bg-slate-900/50 border-slate-800/80 hover:bg-slate-900/80 hover:border-slate-700'
            }`}
          >
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-slate-400">{c.label}</span>
              <Icon className={`w-4 h-4 ${c.color}`} />
            </div>
            <p className="text-2xl font-bold text-slate-100 mt-2">{c.value}</p>
          </button>
        );
      })}
    </div>
  );
}
