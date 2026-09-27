'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { useAuth } from '@/lib/authContext';
import { LoginForm } from '@/components/Auth/LoginForm';
import { DashboardHeader } from '@/components/Dashboard/DashboardHeader';
import { TaskStats } from '@/components/Dashboard/TaskStats';
import { TaskFilters } from '@/components/Dashboard/TaskFilters';
import { TaskList } from '@/components/Dashboard/TaskList';
import { TaskModal } from '@/components/Dashboard/TaskModal';
import { TaskDetailModal } from '@/components/Dashboard/TaskDetailModal';
import { DeleteConfirmModal } from '@/components/Dashboard/DeleteConfirmModal';
import { api } from '@/lib/api';
import { Task, TaskFilterParams } from '@/lib/types';
import { useToast } from '@/components/UI/Toast';

export default function Home() {
  const { isAuthenticated, loading: authLoading } = useAuth();
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

  // Real-time synchronization poll every 10 seconds (SSE / Live update simulation)
  useEffect(() => {
    if (!isAuthenticated) return;
    const interval = setInterval(() => {
      loadTasks(true);
    }, 10000);
    return () => clearInterval(interval);
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
      <main className="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 relative overflow-hidden">
        <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-blue-900/20 via-slate-950 to-slate-950 -z-10" />
        <LoginForm />
      </main>
    );
  }

  return (
    <div className="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
      <DashboardHeader
        onNewTask={() => {
          setEditingTask(null);
          setIsTaskModalOpen(true);
        }}
        onRefresh={() => loadTasks(false)}
      />

      <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <TaskStats tasks={tasks} />

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
