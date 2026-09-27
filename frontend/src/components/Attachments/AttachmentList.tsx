'use client';

import React, { useState } from 'react';
import { TaskAttachment } from '@/lib/types';
import { api } from '@/lib/api';
import { useToast } from '../UI/Toast';
import { Download, Trash2, FileText, Image as ImageIcon, Video } from 'lucide-react';

interface AttachmentListProps {
  attachments: TaskAttachment[];
  onAttachmentDeleted: () => void;
}

export function AttachmentList({ attachments, onAttachmentDeleted }: AttachmentListProps) {
  const { toast } = useToast();
  const [deletingId, setDeletingId] = useState<number | null>(null);

  const formatSize = (bytes: number) => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const handleDelete = async (id: number, name: string) => {
    setDeletingId(id);
    try {
      await api.deleteAttachment(id);
      toast(`Attachment "${name}" deleted.`, 'info');
      onAttachmentDeleted();
    } catch (err: any) {
      toast(err.message || 'Failed to delete attachment.', 'error');
    } finally {
      setDeletingId(null);
    }
  };

  if (!attachments || attachments.length === 0) {
    return (
      <div className="py-6 text-center text-xs text-slate-500">
        No attachments uploaded yet.
      </div>
    );
  }

  return (
    <div className="space-y-2">
      {attachments.map((att) => {
        const isImage = att.mime_type.startsWith('image/');
        const isVideo = att.mime_type.startsWith('video/');
        const downloadUrl = api.getAttachmentDownloadUrl(att.id);

        return (
          <div
            key={att.id}
            className="flex items-center justify-between p-3 rounded-lg bg-slate-950/60 border border-slate-800 hover:border-slate-700 transition-colors"
          >
            <div className="flex items-center gap-3 min-w-0">
              <div className="p-2 rounded-lg bg-slate-900 text-slate-400 shrink-0 border border-slate-800">
                {isImage ? (
                  <ImageIcon className="w-4 h-4 text-emerald-400" />
                ) : isVideo ? (
                  <Video className="w-4 h-4 text-purple-400" />
                ) : (
                  <FileText className="w-4 h-4 text-blue-400" />
                )}
              </div>

              <div className="min-w-0">
                <div className="flex items-center gap-2">
                  <span className="text-xs font-semibold text-slate-200 truncate">
                    {att.file_name}
                  </span>
                  <span className="text-[10px] font-semibold px-1.5 py-0.2 rounded bg-slate-800 text-slate-400">
                    v{att.version}
                  </span>
                </div>
                <div className="flex items-center gap-2 text-[11px] text-slate-500 mt-0.5">
                  <span>{formatSize(att.file_size)}</span>
                  <span>•</span>
                  <span>{new Date(att.uploaded_at).toLocaleDateString()}</span>
                </div>
              </div>
            </div>

            <div className="flex items-center gap-1.5 shrink-0 pl-3">
              <a
                href={downloadUrl}
                target="_blank"
                rel="noreferrer"
                download
                className="p-1.5 rounded-lg text-slate-400 hover:text-blue-400 hover:bg-slate-800 transition-colors"
                title="Download file"
                aria-label={`Download ${att.file_name}`}
              >
                <Download className="w-4 h-4" />
              </a>
              <button
                type="button"
                onClick={() => handleDelete(att.id, att.file_name)}
                disabled={deletingId === att.id}
                className="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors disabled:opacity-50"
                title="Delete attachment"
                aria-label={`Delete ${att.file_name}`}
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>
        );
      })}
    </div>
  );
}
