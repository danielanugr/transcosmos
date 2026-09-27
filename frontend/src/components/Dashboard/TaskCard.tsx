'use client';

import React from 'react';
import { Task } from '@/lib/types';
import { StatusBadge, PriorityBadge } from '../UI/Badge';
import { Calendar, Paperclip, MessageSquare, Edit2, Trash2, User } from 'lucide-react';

interface TaskCardProps {
  task: Task;
  selected: boolean;
  onSelect: (taskId: number) => void;
  onView: (task: Task) => void;
  onEdit: (task: Task) => void;
  onDelete: (task: Task) => void;
}

export function TaskCard({
  task,
  selected,
  onSelect,
  onView,
  onEdit,
  onDelete,
}: TaskCardProps) {
  const attachmentCount = task.attachments?.length || 0;
  const commentCount = task.comments?.length || 0;

  return (
    <div
      className={`group relative p-4 rounded-xl border transition-all duration-200 bg-slate-900/70 hover:bg-slate-900 ${
        selected ? 'border-blue-500/80 bg-blue-950/20' : 'border-slate-800 hover:border-slate-700'
      }`}
    >
      <div className="flex items-start justify-between gap-3 mb-2.5">
        <div className="flex items-center gap-3">
          <input
            type="checkbox"
            checked={selected}
            onChange={() => onSelect(task.id)}
            className="w-4 h-4 rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500/40 cursor-pointer"
            aria-label={`Select ${task.title}`}
          />
          <div className="flex items-center gap-2">
            <StatusBadge status={task.status} />
            <PriorityBadge priority={task.priority} />
          </div>
        </div>

        <div className="flex items-center gap-1 opacity-80 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onEdit(task);
            }}
            className="p-1.5 rounded-lg text-slate-400 hover:text-blue-400 hover:bg-slate-800 transition-colors"
            title="Edit task"
            aria-label="Edit task"
          >
            <Edit2 className="w-3.5 h-3.5" />
          </button>
          <button
            type="button"
            onClick={(e) => {
              e.stopPropagation();
              onDelete(task);
            }}
            className="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors"
            title="Delete task"
            aria-label="Delete task"
          >
            <Trash2 className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>

      <div
        onClick={() => onView(task)}
        className="cursor-pointer"
        role="button"
        tabIndex={0}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onView(task);
          }
        }}
      >
        <h3 className="text-sm font-semibold text-slate-100 group-hover:text-blue-300 transition-colors line-clamp-1">
          {task.title}
        </h3>
        {task.description && (
          <p className="text-xs text-slate-400 mt-1 line-clamp-2 leading-relaxed">
            {task.description}
          </p>
        )}

        <div className="mt-3.5 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
          <div className="flex items-center gap-3">
            {task.due_date && (
              <span className="flex items-center gap-1 text-[11px]">
                <Calendar className="w-3.5 h-3.5 text-slate-500" />
                {task.due_date}
              </span>
            )}
            {task.assignee && (
              <span className="flex items-center gap-1 text-[11px]">
                <User className="w-3.5 h-3.5 text-slate-500" />
                {task.assignee.name}
              </span>
            )}
          </div>

          <div className="flex items-center gap-2.5">
            {attachmentCount > 0 && (
              <span className="flex items-center gap-1 text-[11px] text-slate-400">
                <Paperclip className="w-3 h-3 text-slate-500" />
                {attachmentCount}
              </span>
            )}
            {commentCount > 0 && (
              <span className="flex items-center gap-1 text-[11px] text-slate-400">
                <MessageSquare className="w-3 h-3 text-slate-500" />
                {commentCount}
              </span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
