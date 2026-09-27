'use client';

import React from 'react';
import { TaskFilterParams } from '@/lib/types';
import { Search, ArrowUpDown, CheckSquare } from 'lucide-react';

interface TaskFiltersProps {
  filters: TaskFilterParams;
  onChange: (newFilters: TaskFilterParams) => void;
  selectedCount: number;
  onBulkUpdate: (status: string) => void;
}

export function TaskFilters({
  filters,
  onChange,
  selectedCount,
  onBulkUpdate,
}: TaskFiltersProps) {
  const statuses = [
    { id: 'all', label: 'All Status' },
    { id: 'pending', label: 'Pending' },
    { id: 'in_progress', label: 'In Progress' },
    { id: 'completed', label: 'Completed' },
    { id: 'cancelled', label: 'Cancelled' },
  ];

  return (
    <div className="space-y-3">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="w-4 h-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            placeholder="Search tasks by keyword..."
            value={filters.search || ''}
            onChange={(e) => onChange({ ...filters, search: e.target.value, page: 1 })}
            className="w-full pl-10 pr-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:border-blue-500 transition-all"
          />
        </div>

        <div className="flex items-center gap-2.5 flex-wrap">
          <select
            value={filters.priority || 'all'}
            onChange={(e) => onChange({ ...filters, priority: e.target.value, page: 1 })}
            className="px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs font-medium text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
          >
            <option value="all">All Priorities</option>
            <option value="low">Low Priority</option>
            <option value="medium">Medium Priority</option>
            <option value="high">High Priority</option>
            <option value="urgent">Urgent</option>
          </select>

          <div className="flex items-center gap-1.5 bg-slate-900 border border-slate-800 rounded-lg px-2.5 py-1">
            <ArrowUpDown className="w-3.5 h-3.5 text-slate-400" />
            <select
              value={filters.sort_by || 'created_at'}
              onChange={(e) => onChange({ ...filters, sort_by: e.target.value as any, page: 1 })}
              className="bg-transparent text-xs text-slate-300 font-medium focus:outline-none"
            >
              <option value="created_at" className="bg-slate-900">Newest Created</option>
              <option value="due_date" className="bg-slate-900">Due Date</option>
              <option value="priority" className="bg-slate-900">Priority Level</option>
              <option value="title" className="bg-slate-900">Title (A-Z)</option>
            </select>
            <button
              type="button"
              onClick={() =>
                onChange({
                  ...filters,
                  sort_order: filters.sort_order === 'asc' ? 'desc' : 'asc',
                  page: 1,
                })
              }
              className="text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 hover:text-white"
              title="Toggle sort direction"
            >
              {filters.sort_order === 'asc' ? 'ASC' : 'DESC'}
            </button>
          </div>
        </div>
      </div>

      <div className="flex items-center justify-between gap-4 flex-wrap pt-1">
        <div className="flex items-center gap-1.5 flex-wrap">
          {statuses.map((s) => {
            const active = (filters.status || 'all') === s.id;
            return (
              <button
                key={s.id}
                type="button"
                onClick={() => onChange({ ...filters, status: s.id, page: 1 })}
                className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                  active
                    ? 'bg-blue-600 text-white font-semibold shadow-sm'
                    : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border border-slate-800 hover:border-slate-700'
                }`}
              >
                {s.label}
              </button>
            );
          })}
        </div>

        {selectedCount > 0 && (
          <div className="flex items-center gap-2 bg-blue-950/40 border border-blue-500/30 px-3 py-1 rounded-lg text-xs text-blue-300">
            <CheckSquare className="w-3.5 h-3.5 text-blue-400" />
            <span>{selectedCount} selected</span>
            <div className="h-3 w-[1px] bg-blue-500/30 mx-1" />
            <span className="text-slate-400">Set status:</span>
            <button
              type="button"
              onClick={() => onBulkUpdate('completed')}
              className="px-2 py-0.5 rounded bg-emerald-600/20 text-emerald-300 hover:bg-emerald-600/30 border border-emerald-500/30"
            >
              Completed
            </button>
            <button
              type="button"
              onClick={() => onBulkUpdate('in_progress')}
              className="px-2 py-0.5 rounded bg-blue-600/20 text-blue-300 hover:bg-blue-600/30 border border-blue-500/30"
            >
              In Progress
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
