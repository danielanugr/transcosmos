import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api } from '../src/lib/api';

describe('Critical User Flows & Integration Tests', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
  });

  it('completes the full authentication cycle (login -> me -> logout)', async () => {
    const mockStorage: Record<string, string> = {};
    const mockLocalStorage = {
      getItem: (k: string) => mockStorage[k] || null,
      setItem: (k: string, v: string) => { mockStorage[k] = v; },
      removeItem: (k: string) => { delete mockStorage[k]; },
      clear: () => { Object.keys(mockStorage).forEach((k) => delete mockStorage[k]); },
    };

    vi.stubGlobal('window', { localStorage: mockLocalStorage });
    vi.stubGlobal('localStorage', mockLocalStorage);

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: {
          token: 'mock-jwt-token-12345',
          user: { id: 1, name: 'Alice Johnson', email: 'alice@example.com', role: 'admin' },
        },
      }),
    } as any);

    const loginRes = await api.login('alice@example.com', 'password123');
    expect(loginRes.token).toBe('mock-jwt-token-12345');
    expect(mockLocalStorage.getItem('auth_token')).toBe('mock-jwt-token-12345');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: { id: 1, name: 'Alice Johnson', email: 'alice@example.com', role: 'admin' },
      }),
    } as any);

    const user = await api.getCurrentUser();
    expect(user.email).toBe('alice@example.com');
    expect(user.role).toBe('admin');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ success: true, message: 'Logged out successfully.' }),
    } as any);

    await api.logout();
    expect(mockLocalStorage.getItem('auth_token')).toBeNull();
  });

  it('completes the task management lifecycle (create -> update -> bulk-status -> delete)', async () => {
    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({
        success: true,
        data: {
          id: 42,
          title: 'New Feature Task',
          description: 'Deploy new payment gateway',
          status: 'pending',
          priority: 'high',
          due_date: '2026-10-30',
        },
      }),
    } as any);

    const created = await api.createTask({
      title: 'New Feature Task',
      description: 'Deploy new payment gateway',
      status: 'pending',
      priority: 'high',
      due_date: '2026-10-30',
    });
    expect(created.id).toBe(42);
    expect(created.title).toBe('New Feature Task');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: { ...created, status: 'in_progress' },
      }),
    } as any);

    const updated = await api.updateTask(42, { status: 'in_progress' });
    expect(updated.status).toBe('in_progress');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: { queued: false, updated: 1 },
      }),
    } as any);

    const bulkRes = await api.bulkUpdateStatus([42], 'completed');
    expect(bulkRes.updated).toBe(1);

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ success: true, message: 'Task deleted successfully.' }),
    } as any);

    await expect(api.deleteTask(42)).resolves.not.toThrow();
  });

  it('completes the comment discussion flow on a task', async () => {
    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({
        success: true,
        data: {
          id: 101,
          task_id: 42,
          user_id: 1,
          comment: 'Code review completed and approved.',
          created_at: '2026-09-27T10:00:00Z',
          user: { id: 1, name: 'Alice' },
        },
      }),
    } as any);

    const comment = await api.addComment(42, 'Code review completed and approved.');
    expect(comment.id).toBe(101);
    expect(comment.comment).toBe('Code review completed and approved.');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: [comment],
      }),
    } as any);

    const comments = await api.getComments(42);
    expect(comments).toHaveLength(1);
    expect(comments[0].user?.name).toBe('Alice');

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ success: true }),
    } as any);

    await expect(api.deleteComment(101)).resolves.not.toThrow();
  });

  it('triggers background queue execution and CSV export jobs', async () => {
    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: { processed: 3, remaining: 0 },
      }),
    } as any);

    const queueRes = await api.runQueueWork();
    expect(queueRes.processed).toBe(3);
    expect(queueRes.remaining).toBe(0);

    globalThis.fetch = vi.fn().mockResolvedValueOnce({
      ok: true,
      status: 202,
      json: async () => ({
        success: true,
        data: { message: 'Export job queued.' },
      }),
    } as any);

    const exportRes = await api.exportTasks();
    expect(exportRes.message).toBe('Export job queued.');
  });
});
