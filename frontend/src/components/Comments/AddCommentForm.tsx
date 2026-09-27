'use client';

import React, { useState } from 'react';
import { api } from '@/lib/api';
import { useToast } from '../UI/Toast';
import { Send } from 'lucide-react';

interface AddCommentFormProps {
  taskId: number;
  onCommentAdded: () => void;
}

export function AddCommentForm({ taskId, onCommentAdded }: AddCommentFormProps) {
  const { toast } = useToast();
  const [comment, setComment] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!comment.trim()) return;

    setSubmitting(true);
    try {
      await api.addComment(taskId, comment.trim());
      setComment('');
      onCommentAdded();
      toast('Comment posted.', 'success');
    } catch (err: any) {
      toast(err.message || 'Failed to post comment.', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      handleSubmit(e);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="relative mt-3">
      <textarea
        rows={2}
        value={comment}
        onChange={(e) => setComment(e.target.value)}
        onKeyDown={handleKeyDown}
        placeholder="Write a comment... (Ctrl+Enter to post)"
        className="w-full pl-3.5 pr-12 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/40 resize-none transition-all"
      />
      <button
        type="submit"
        disabled={submitting || !comment.trim()}
        className="absolute right-2.5 bottom-3.5 p-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 disabled:opacity-40 disabled:hover:bg-blue-600 text-white transition-colors cursor-pointer"
        title="Post comment"
        aria-label="Post comment"
      >
        <Send className="w-3.5 h-3.5" />
      </button>
    </form>
  );
}
