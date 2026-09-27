import { describe, it, expect, vi, beforeEach } from 'vitest';
import { api } from '../src/lib/api';

describe('Frontend API Client Tests', () => {
  beforeEach(() => {
    vi.restoreAllMocks();
    if (typeof window !== 'undefined') {
      localStorage.clear();
    }
  });

  it('should format attachment download URL correctly', () => {
    const url = api.getAttachmentDownloadUrl(42);
    expect(url).toContain('/attachments/42/download');
  });

  it('should handle API errors and preserve error status and message', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 401,
      json: async () => ({ message: 'Unauthenticated.' }),
    } as any);

    await expect(api.getCurrentUser()).rejects.toThrow('Unauthenticated.');
  });

  it('should fetch and deserialize tasks with pagination', async () => {
    const mockTasks = [
      { id: 1, title: 'Task 1', status: 'pending', priority: 'high' },
      { id: 2, title: 'Task 2', status: 'completed', priority: 'low' },
    ];

    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({
        success: true,
        data: {
          data: mockTasks,
          current_page: 1,
          last_page: 1,
          total: 2,
          per_page: 10,
        },
      }),
    } as any);

    const result = await api.getTasks({ status: 'pending', priority: 'high' });
    expect(result.data).toHaveLength(2);
    expect(result.total).toBe(2);
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('status=pending'),
      expect.anything()
    );
  });
});
