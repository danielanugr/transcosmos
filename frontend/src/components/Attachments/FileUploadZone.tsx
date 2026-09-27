'use client';

import React, { useState, useRef } from 'react';
import { api } from '@/lib/api';
import { useToast } from '../UI/Toast';
import { UploadCloud, CheckCircle2 } from 'lucide-react';

interface FileUploadZoneProps {
  taskId: number;
  onUploaded: () => void;
}

export function FileUploadZone({ taskId, onUploaded }: FileUploadZoneProps) {
  const { toast } = useToast();
  const [isDragging, setIsDragging] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [statusText, setStatusText] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  const processFile = async (file: File) => {
    setUploading(true);
    setProgress(5);
    setStatusText('Preparing upload...');

    try {
      // If file > 5MB, perform chunked upload
      const CHUNK_SIZE = 1024 * 1024;
      if (file.size > 5 * 1024 * 1024) {
        const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
        const uploadId = 'up_' + Math.random().toString(36).substring(2, 10);
        setStatusText(`Uploading chunk 1 of ${totalChunks}...`);

        for (let i = 0; i < totalChunks; i++) {
          const start = i * CHUNK_SIZE;
          const end = Math.min(file.size, start + CHUNK_SIZE);
          const chunkBlob = file.slice(start, end);

          setStatusText(`Uploading chunk ${i + 1} of ${totalChunks}...`);
          await api.uploadChunk(taskId, uploadId, file.name, i, totalChunks, chunkBlob);

          const currentPct = Math.round(((i + 1) / totalChunks) * 100);
          setProgress(currentPct);
        }
        setStatusText('Assembling and scanning file...');
      } else {
        setStatusText('Uploading file...');
        setProgress(50);
        await api.uploadAttachment(taskId, file);
        setProgress(100);
      }

      toast(`"${file.name}" uploaded successfully.`, 'success');
      onUploaded();
    } catch (err: any) {
      toast(err.message || 'File upload failed.', 'error');
    } finally {
      setTimeout(() => {
        setUploading(false);
        setProgress(0);
        setStatusText('');
      }, 800);
    }
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    setIsDragging(false);
    const files = e.dataTransfer.files;
    if (files.length > 0) {
      processFile(files[0]);
    }
  };

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      processFile(files[0]);
    }
  };

  return (
    <div className="space-y-3">
      <div
        onDragOver={(e) => {
          e.preventDefault();
          setIsDragging(true);
        }}
        onDragLeave={() => setIsDragging(false)}
        onDrop={handleDrop}
        onClick={() => fileInputRef.current?.click()}
        className={`p-6 rounded-xl border-2 border-dashed transition-all text-center cursor-pointer ${
          isDragging
            ? 'border-blue-500 bg-blue-500/10'
            : 'border-slate-800 hover:border-slate-700 bg-slate-950/40'
        }`}
      >
        <input
          ref={fileInputRef}
          type="file"
          onChange={handleFileSelect}
          className="hidden"
          disabled={uploading}
        />
        <div className="flex flex-col items-center justify-center gap-2">
          <div className="p-2.5 rounded-xl bg-blue-500/10 text-blue-400">
            <UploadCloud className="w-6 h-6" />
          </div>
          <div>
            <span className="text-xs font-semibold text-slate-200">
              Click to upload or drag & drop
            </span>
            <p className="text-[11px] text-slate-500 mt-0.5">
              Images (PNG, JPG, GIF), Documents (PDF, DOCX, TXT), Videos (MP4) up to 100MB
            </p>
          </div>
        </div>
      </div>

      {uploading && (
        <div className="p-3 rounded-lg bg-slate-950 border border-slate-800 space-y-1.5 animate-fade-in">
          <div className="flex items-center justify-between text-xs">
            <span className="text-slate-300 font-medium flex items-center gap-1.5">
              {progress === 100 ? (
                <CheckCircle2 className="w-3.5 h-3.5 text-emerald-400" />
              ) : (
                <div className="w-3.5 h-3.5 border-2 border-blue-500/30 border-t-blue-500 rounded-full animate-spin" />
              )}
              {statusText}
            </span>
            <span className="font-semibold text-blue-400">{progress}%</span>
          </div>
          <div className="w-full h-1.5 bg-slate-800 rounded-full overflow-hidden">
            <div
              className="h-full bg-blue-500 transition-all duration-300 rounded-full"
              style={{ width: `${progress}%` }}
            />
          </div>
        </div>
      )}
    </div>
  );
}
