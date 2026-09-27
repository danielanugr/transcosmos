'use client';

import React, { useState } from 'react';
import { TaskComment } from '@/lib/types';
import { useAuth } from '@/lib/authContext';
import { api } from '@/lib/api';
import { useToast } from '../UI/Toast';
import { Trash2, User } from 'lucide-react';

interface CommentListProps {
  comments: TaskComment[];
  onCommentDeleted: () => void;
}

export function CommentList({ comments, onCommentDeleted }: CommentListProps) {
  const { user } = useAuth();
  const { toast } = useToast();
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const handleDelete = async (id: number) => {
    setDeletingId(id);
    try {
      await api.deleteComment(id);
      toast('Comment deleted.', 'info');
      onCommentDeleted();
    } catch (err: any) {
      toast(err.message || 'Failed to delete comment.', 'error');
    } finally {
      setDeletingId(null);
    }
  };

  if (!comments || comments.length === 0) {
    return (
      <div className="py-6 text-center text-xs text-slate-500">
        No comments yet. Start the conversation below.
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {comments.map((c) => {
        const canDelete = user && (user.role === 'admin' || user.id === c.user_id);
        const formattedDate = new Date(c.created_at).toLocaleString();

        return (
          <div
            key={c.id}
            className="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800/80 space-y-1.5"
          >
            <div className="flex items-center justify-between text-xs">
              <div className="flex items-center gap-2">
                <div className="w-5 h-5 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-[10px] text-slate-300 font-semibold">
                  <User className="w-3 h-3 text-slate-400" />
                </div>
                <span className="font-semibold text-slate-200">
                  {c.user?.name || `User #${c.user_id}`}
                </span>
                <span className="text-[11px] text-slate-500">{formattedDate}</span>
              </div>

              {canDelete && (
                <button
                  type="button"
                  onClick={() => handleDelete(c.id)}
                  disabled={deletingId === c.id}
                  className="text-slate-500 hover:text-rose-400 p-1 rounded transition-colors disabled:opacity-50"
                  title="Delete comment"
                  aria-label="Delete comment"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              )}
            </div>

            <p className="text-xs text-slate-300 leading-relaxed pl-7">{c.comment}</p>
          </div>
        );
      })}
    </div>
  );
}
