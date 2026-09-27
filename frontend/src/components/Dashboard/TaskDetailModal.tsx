'use client';

import React, { useState, useEffect } from 'react';
import { Modal } from '../UI/Modal';
import { Task } from '@/lib/types';
import { api } from '@/lib/api';
import { StatusBadge, PriorityBadge } from '../UI/Badge';
import { FileUploadZone } from '../Attachments/FileUploadZone';
import { AttachmentList } from '../Attachments/AttachmentList';
import { CommentList } from '../Comments/CommentList';
import { AddCommentForm } from '../Comments/AddCommentForm';
import { useToast } from '../UI/Toast';
import { Calendar, User, Paperclip, MessageSquare, Clock } from 'lucide-react';

interface TaskDetailModalProps {
  isOpen: boolean;
  onClose: () => void;
  taskId: number | null;
  onTaskUpdated: () => void;
  typingUser?: string | null;
  onTyping?: (isTyping: boolean) => void;
}

export function TaskDetailModal({
  isOpen,
  onClose,
  taskId,
  onTaskUpdated,
  typingUser = null,
  onTyping,
}: TaskDetailModalProps) {
  const { toast } = useToast();
  const [task, setTask] = useState<Task | null>(null);
  const [loading, setLoading] = useState(false);
  const [activeTab, setActiveTab] = useState<'attachments' | 'comments'>('attachments');

  const fetchTask = async () => {
    if (!taskId) return;
    setLoading(true);
    try {
      const data = await api.getTask(taskId);
      setTask(data);
    } catch (err: any) {
      toast(err.message || 'Failed to load task details.', 'error');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (isOpen && taskId) {
      fetchTask();
    } else {
      setTask(null);
    }
  }, [isOpen, taskId]);

  const handleStatusChange = async (newStatus: any) => {
    if (!task) return;
    try {
      const updated = await api.updateTask(task.id, { status: newStatus });
      setTask(updated);
      toast(`Status updated to ${newStatus}.`, 'success');
      onTaskUpdated();
    } catch (err: any) {
      toast(err.message || 'Failed to update status.', 'error');
    }
  };

  if (!isOpen) return null;

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={task?.title || 'Task Details'}
      maxWidth="2xl"
    >
      {loading && !task ? (
        <div className="py-12 flex items-center justify-center">
          <div className="w-6 h-6 border-2 border-blue-500/30 border-t-blue-500 rounded-full animate-spin" />
        </div>
      ) : task ? (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-3 p-4 rounded-xl bg-slate-950/60 border border-slate-800">
            <div className="flex items-center gap-2">
              <StatusBadge status={task.status} />
              <PriorityBadge priority={task.priority} />
            </div>

            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-400">Quick Status:</span>
              <select
                value={task.status}
                onChange={(e) => handleStatusChange(e.target.value)}
                className="px-2.5 py-1 bg-slate-900 border border-slate-700/80 rounded-lg text-xs font-medium text-slate-200 focus:outline-none"
              >
                <option value="pending">Pending</option>
                <option value="in_progress">In Progress</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          <div>
            <h4 className="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
              Description
            </h4>
            <p className="text-sm text-slate-200 leading-relaxed bg-slate-950/30 p-3.5 rounded-xl border border-slate-800/60 whitespace-pre-wrap">
              {task.description || 'No description provided.'}
            </p>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
            <div className="p-3 rounded-lg bg-slate-950/40 border border-slate-800/60">
              <span className="text-slate-500 flex items-center gap-1.5">
                <Calendar className="w-3.5 h-3.5 text-slate-400" />
                Due Date
              </span>
              <p className="font-medium text-slate-200 mt-1">{task.due_date || 'No due date'}</p>
            </div>

            <div className="p-3 rounded-lg bg-slate-950/40 border border-slate-800/60">
              <span className="text-slate-500 flex items-center gap-1.5">
                <User className="w-3.5 h-3.5 text-slate-400" />
                Assignee
              </span>
              <p className="font-medium text-slate-200 mt-1">
                {task.assignee?.name || 'Unassigned'}
              </p>
            </div>

            <div className="p-3 rounded-lg bg-slate-950/40 border border-slate-800/60 col-span-2 sm:col-span-1">
              <span className="text-slate-500 flex items-center gap-1.5">
                <Clock className="w-3.5 h-3.5 text-slate-400" />
                Created At
              </span>
              <p className="font-medium text-slate-200 mt-1">
                {new Date(task.created_at).toLocaleDateString()}
              </p>
            </div>
          </div>

          <div>
            <div className="flex items-center gap-2 border-b border-slate-800 mb-4">
              <button
                type="button"
                onClick={() => setActiveTab('attachments')}
                className={`flex items-center gap-2 pb-2.5 px-3 text-xs font-semibold border-b-2 transition-colors ${
                  activeTab === 'attachments'
                    ? 'border-blue-500 text-blue-400'
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                <Paperclip className="w-3.5 h-3.5" />
                Attachments ({task.attachments?.length || 0})
              </button>
              <button
                type="button"
                onClick={() => setActiveTab('comments')}
                className={`flex items-center gap-2 pb-2.5 px-3 text-xs font-semibold border-b-2 transition-colors ${
                  activeTab === 'comments'
                    ? 'border-blue-500 text-blue-400'
                    : 'border-transparent text-slate-400 hover:text-slate-200'
                }`}
              >
                <MessageSquare className="w-3.5 h-3.5" />
                Comments ({task.comments?.length || 0})
              </button>
            </div>

            {activeTab === 'attachments' ? (
              <div className="space-y-4">
                <FileUploadZone
                  taskId={task.id}
                  onUploaded={() => {
                    fetchTask();
                    onTaskUpdated();
                  }}
                />
                <AttachmentList
                  attachments={task.attachments || []}
                  onAttachmentDeleted={() => {
                    fetchTask();
                    onTaskUpdated();
                  }}
                />
              </div>
            ) : (
              <div className="space-y-3">
                <CommentList
                  comments={task.comments || []}
                  onCommentDeleted={() => {
                    fetchTask();
                    onTaskUpdated();
                  }}
                />

                {typingUser && (
                  <div className="flex items-center gap-2 text-[11px] text-blue-400 italic py-1 px-1">
                    <span className="flex gap-1 items-center">
                      <span className="w-1.5 h-1.5 rounded-full bg-blue-400 animate-bounce" />
                      <span className="w-1.5 h-1.5 rounded-full bg-blue-400 animate-bounce [animation-delay:0.2s]" />
                      <span className="w-1.5 h-1.5 rounded-full bg-blue-400 animate-bounce [animation-delay:0.4s]" />
                    </span>
                    <span>{typingUser} is typing a comment...</span>
                  </div>
                )}

                <AddCommentForm
                  taskId={task.id}
                  onCommentAdded={() => {
                    fetchTask();
                    onTaskUpdated();
                  }}
                  onTyping={onTyping}
                />
              </div>
            )}
          </div>
        </div>
      ) : null}
    </Modal>
  );
}
