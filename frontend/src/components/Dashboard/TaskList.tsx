'use client';

import React from 'react';
import { Task } from '@/lib/types';
import { TaskCard } from './TaskCard';
import { Inbox, ChevronLeft, ChevronRight } from 'lucide-react';

interface TaskListProps {
  tasks: Task[];
  loading: boolean;
  selectedIds: number[];
  onToggleSelect: (taskId: number) => void;
  onSelectAll: () => void;
  onViewTask: (task: Task) => void;
  onEditTask: (task: Task) => void;
  onDeleteTask: (task: Task) => void;
  currentPage: number;
  lastPage: number;
  total: number;
  onPageChange: (page: number) => void;
}

export function TaskList({
  tasks,
  loading,
  selectedIds,
  onToggleSelect,
  onSelectAll,
  onViewTask,
  onEditTask,
  onDeleteTask,
  currentPage,
  lastPage,
  total,
  onPageChange,
}: TaskListProps) {
  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div
            key={i}
            className="p-4 rounded-xl border border-slate-800 bg-slate-900/40 animate-pulse space-y-3"
          >
            <div className="flex items-center justify-between">
              <div className="h-4 w-20 bg-slate-800 rounded-full" />
              <div className="h-4 w-16 bg-slate-800 rounded-full" />
            </div>
            <div className="h-5 w-3/4 bg-slate-800 rounded" />
            <div className="h-3 w-full bg-slate-800 rounded" />
            <div className="h-3 w-2/3 bg-slate-800 rounded" />
            <div className="pt-3 border-t border-slate-800 flex justify-between">
              <div className="h-3 w-16 bg-slate-800 rounded" />
              <div className="h-3 w-16 bg-slate-800 rounded" />
            </div>
          </div>
        ))}
      </div>
    );
  }

  if (tasks.length === 0) {
    return (
      <div className="py-16 text-center rounded-2xl border border-slate-800 bg-slate-900/30">
        <div className="inline-flex p-3 rounded-2xl bg-slate-800/80 text-slate-400 mb-3 border border-slate-700/60">
          <Inbox className="w-8 h-8" />
        </div>
        <h3 className="text-base font-semibold text-slate-200">No tasks found</h3>
        <p className="text-xs text-slate-400 max-w-sm mx-auto mt-1">
          No tasks match the active filters or search terms. Try broadening your criteria or create a new task.
        </p>
      </div>
    );
  }

  const allSelected = tasks.length > 0 && tasks.every((t) => selectedIds.includes(t.id));

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between text-xs text-slate-400 px-1">
        <label className="flex items-center gap-2 cursor-pointer hover:text-slate-200">
          <input
            type="checkbox"
            checked={allSelected}
            onChange={onSelectAll}
            className="w-4 h-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/40 cursor-pointer"
          />
          <span>Select all on this page ({tasks.length})</span>
        </label>
        <span>Showing {tasks.length} of {total} total tasks</span>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {tasks.map((task) => (
          <TaskCard
            key={task.id}
            task={task}
            selected={selectedIds.includes(task.id)}
            onSelect={onToggleSelect}
            onView={onViewTask}
            onEdit={onEditTask}
            onDelete={onDeleteTask}
          />
        ))}
      </div>

      {lastPage > 1 && (
        <div className="flex items-center justify-between pt-4 border-t border-slate-800 text-xs text-slate-400">
          <div>
            Page {currentPage} of {lastPage}
          </div>
          <div className="flex items-center gap-1.5">
            <button
              type="button"
              disabled={currentPage <= 1}
              onClick={() => onPageChange(currentPage - 1)}
              className="p-1.5 rounded-lg border border-slate-800 hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent text-slate-300 transition-colors"
              aria-label="Previous page"
            >
              <ChevronLeft className="w-4 h-4" />
            </button>
            <button
              type="button"
              disabled={currentPage >= lastPage}
              onClick={() => onPageChange(currentPage + 1)}
              className="p-1.5 rounded-lg border border-slate-800 hover:bg-slate-800 disabled:opacity-40 disabled:hover:bg-transparent text-slate-300 transition-colors"
              aria-label="Next page"
            >
              <ChevronRight className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
