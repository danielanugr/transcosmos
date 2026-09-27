'use client';

import React, { useState } from 'react';
import { Modal } from '../UI/Modal';
import { Task } from '@/lib/types';
import { api } from '@/lib/api';
import { useToast } from '../UI/Toast';
import { AlertTriangle } from 'lucide-react';

interface DeleteConfirmModalProps {
  isOpen: boolean;
  onClose: () => void;
  task: Task | null;
  onDeleted: () => void;
}

export function DeleteConfirmModal({
  isOpen,
  onClose,
  task,
  onDeleted,
}: DeleteConfirmModalProps) {
  const { toast } = useToast();
  const [deleting, setDeleting] = useState(false);

  if (!task) return null;

  const handleDelete = async () => {
    setDeleting(true);
    try {
      await api.deleteTask(task.id);
      toast(`Task "${task.title}" deleted.`, 'info');
      onDeleted();
      onClose();
    } catch (err: any) {
      toast(err.message || 'Failed to delete task.', 'error');
    } finally {
      setDeleting(false);
    }
  };

  return (
    <Modal isOpen={isOpen} onClose={onClose} title="Confirm Task Deletion" maxWidth="sm">
      <div className="space-y-4">
        <div className="flex items-start gap-3">
          <div className="p-2.5 rounded-xl bg-rose-500/10 text-rose-400 shrink-0 border border-rose-500/20">
            <AlertTriangle className="w-5 h-5" />
          </div>
          <div>
            <p className="text-sm font-semibold text-slate-200">
              Are you sure you want to delete this task?
            </p>
            <p className="text-xs text-slate-400 mt-1">
              &quot;{task.title}&quot; and all associated attachments and comments will be permanently removed.
            </p>
          </div>
        </div>

        <div className="flex items-center justify-end gap-2.5 pt-4 border-t border-slate-800">
          <button
            type="button"
            onClick={onClose}
            disabled={deleting}
            className="px-3.5 py-1.5 text-xs font-semibold text-slate-400 hover:text-slate-200 transition-colors"
          >
            Cancel
          </button>
          <button
            type="button"
            onClick={handleDelete}
            disabled={deleting}
            className="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold transition-colors cursor-pointer disabled:opacity-50"
          >
            {deleting ? 'Deleting...' : 'Delete Task'}
          </button>
        </div>
      </div>
    </Modal>
  );
}
