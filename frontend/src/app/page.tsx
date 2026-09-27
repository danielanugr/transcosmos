'use client';

import React, { useState, useEffect, useCallback } from 'react';
import dynamic from 'next/dynamic';
import { useAuth } from '@/lib/authContext';
import { LoginForm } from '@/components/Auth/LoginForm';
import { DashboardHeader } from '@/components/Dashboard/DashboardHeader';
import { TaskStats } from '@/components/Dashboard/TaskStats';
import { TaskFilters } from '@/components/Dashboard/TaskFilters';
import { TaskList } from '@/components/Dashboard/TaskList';
import { api } from '@/lib/api';
import { Task, TaskFilterParams } from '@/lib/types';
import { useToast } from '@/components/UI/Toast';
import { useRealtime } from '@/lib/useRealtime';

const TaskModal = dynamic(
  () => import('@/components/Dashboard/TaskModal').then((mod) => mod.TaskModal),
  { ssr: false }
);
const TaskDetailModal = dynamic(
  () => import('@/components/Dashboard/TaskDetailModal').then((mod) => mod.TaskDetailModal),
  { ssr: false }
);
const DeleteConfirmModal = dynamic(
  () => import('@/components/Dashboard/DeleteConfirmModal').then((mod) => mod.DeleteConfirmModal),
  { ssr: false }
);

export default function Home() {
  const { isAuthenticated, user, loading: authLoading } = useAuth();
  const { toast } = useToast();

  const [tasks, setTasks] = useState<Task[]>([]);
  const [loading, setLoading] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);

  const [filters, setFilters] = useState<TaskFilterParams>({
    status: 'all',
    priority: 'all',
    sort_by: 'created_at',
    sort_order: 'desc',
    page: 1,
    limit: 12,
  });

  const [selectedIds, setSelectedIds] = useState<number[]>([]);
  const [isTaskModalOpen, setIsTaskModalOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);

  const [detailTaskId, setDetailTaskId] = useState<number | null>(null);
  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false);

  const [deletingTask, setDeletingTask] = useState<Task | null>(null);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);

  const loadTasks = useCallback(async (quiet = false) => {
    if (!quiet) setLoading(true);
    try {
      const res = await api.getTasks(filters);
      setTasks(res.data || []);
      setCurrentPage(res.current_page || 1);
      setLastPage(res.last_page || 1);
      setTotal(res.total || 0);
    } catch (err: any) {
      if (!quiet) {
        toast(err.message || 'Failed to fetch tasks.', 'error');
      }
    } finally {
      if (!quiet) setLoading(false);
    }
  }, [filters, toast]);

  useEffect(() => {
    if (isAuthenticated) {
      loadTasks();
    }
  }, [isAuthenticated, loadTasks]);

  const handleToggleSelect = (taskId: number) => {
    setSelectedIds((prev) =>
      prev.includes(taskId) ? prev.filter((id) => id !== taskId) : [...prev, taskId]
    );
  };

  const handleSelectAll = () => {
    if (tasks.every((t) => selectedIds.includes(t.id))) {
      setSelectedIds([]);
    } else {
      setSelectedIds(tasks.map((t) => t.id));
    }
  };

  const handleBulkUpdate = async (status: string) => {
    if (selectedIds.length === 0) return;
    try {
      await api.bulkUpdateStatus(selectedIds, status);
      toast(`Updated ${selectedIds.length} tasks to ${status}.`, 'success');
      setSelectedIds([]);
      loadTasks();
    } catch (err: any) {
      toast(err.message || 'Bulk update failed.', 'error');
    }
  };

  if (authLoading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="flex flex-col items-center gap-3">
          <div className="w-8 h-8 border-2 border-blue-500/20 border-t-blue-500 rounded-full animate-spin" />
          <p className="text-xs text-slate-500">Checking credentials...</p>
        </div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return (
      <main className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4">
        <LoginForm />
      </main>
    );
  }

  const { onlineUsers, typingMap, sendTyping, isConnected } = useRealtime({
    onTaskChange: () => loadTasks(true),
    onCommentChange: () => loadTasks(true),
  });

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      <DashboardHeader
        onNewTask={() => {
          setEditingTask(null);
          setIsTaskModalOpen(true);
        }}
        onRefresh={() => loadTasks(false)}
        onlineUsers={onlineUsers}
        isRealtimeConnected={isConnected}
      />

      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <TaskStats
          tasks={tasks}
          activeStatus={filters.status || 'all'}
          activePriority={filters.priority || 'all'}
          onFilterChange={(status, priority) =>
            setFilters((prev) => ({
              ...prev,
              status,
              priority: priority || 'all',
              page: 1,
            }))
          }
        />

        <div className="p-5 rounded-2xl bg-slate-900/40 border border-slate-800/80 space-y-5">
          <TaskFilters
            filters={filters}
            onChange={(newFilters) => setFilters(newFilters)}
            selectedCount={selectedIds.length}
            onBulkUpdate={handleBulkUpdate}
          />

          <TaskList
            tasks={tasks}
            loading={loading}
            selectedIds={selectedIds}
            onToggleSelect={handleToggleSelect}
            onSelectAll={handleSelectAll}
            onViewTask={(task) => {
              setDetailTaskId(task.id);
              setIsDetailModalOpen(true);
            }}
            onEditTask={(task) => {
              setEditingTask(task);
              setIsTaskModalOpen(true);
            }}
            onDeleteTask={(task) => {
              setDeletingTask(task);
              setIsDeleteModalOpen(true);
            }}
            currentPage={currentPage}
            lastPage={lastPage}
            total={total}
            onPageChange={(page) => setFilters((prev) => ({ ...prev, page }))}
            onResetFilters={() =>
              setFilters({
                status: 'all',
                priority: 'all',
                sort_by: 'created_at',
                sort_order: 'desc',
                page: 1,
                limit: 12,
              })
            }
            onCreateTask={() => {
              setEditingTask(null);
              setIsTaskModalOpen(true);
            }}
          />
        </div>
      </main>

      <TaskModal
        isOpen={isTaskModalOpen}
        onClose={() => {
          setIsTaskModalOpen(false);
          setEditingTask(null);
        }}
        task={editingTask}
        onSaved={() => loadTasks()}
      />

      <TaskDetailModal
        isOpen={isDetailModalOpen}
        onClose={() => {
          setIsDetailModalOpen(false);
          setDetailTaskId(null);
        }}
        taskId={detailTaskId}
        onTaskUpdated={() => loadTasks(true)}
        typingUser={
          detailTaskId && typingMap[detailTaskId]?.is_typing && typingMap[detailTaskId]?.user_id !== user?.id
            ? typingMap[detailTaskId].user_name
            : null
        }
        onTyping={(isTyping) => {
          if (detailTaskId) sendTyping(detailTaskId, isTyping);
        }}
      />

      <DeleteConfirmModal
        isOpen={isDeleteModalOpen}
        onClose={() => {
          setIsDeleteModalOpen(false);
          setDeletingTask(null);
        }}
        task={deletingTask}
        onDeleted={() => loadTasks()}
      />
    </div>
  );
}
