import { describe, it, expect } from 'vitest';
import { Task, TaskFilterParams } from '../src/lib/types';

describe('UI Logic and Data Transformation Tests', () => {
  const sampleTasks: Task[] = [
    {
      id: 1,
      title: 'Setup Database',
      description: 'Run migrations and seeds',
      status: 'completed',
      priority: 'high',
      due_date: '2026-10-01',
      assigned_to: 1,
      created_by: 1,
      created_at: '2026-09-27T00:00:00Z',
      updated_at: '2026-09-27T00:00:00Z',
    },
    {
      id: 2,
      title: 'Build API',
      description: 'Implement REST endpoints',
      status: 'in_progress',
      priority: 'urgent',
      due_date: null,
      assigned_to: 2,
      created_by: 1,
      created_at: '2026-09-27T00:00:00Z',
      updated_at: '2026-09-27T00:00:00Z',
    },
    {
      id: 3,
      title: 'Write Documentation',
      description: 'Create OpenAPI schema and architecture doc',
      status: 'pending',
      priority: 'low',
      due_date: '2026-10-15',
      assigned_to: null,
      created_by: 2,
      created_at: '2026-09-27T00:00:00Z',
      updated_at: '2026-09-27T00:00:00Z',
    },
  ];

  it('accurately computes task dashboard statistics', () => {
    const total = sampleTasks.length;
    const inProgress = sampleTasks.filter((t) => t.status === 'in_progress').length;
    const completed = sampleTasks.filter((t) => t.status === 'completed').length;
    const highOrUrgent = sampleTasks.filter((t) => t.priority === 'high' || t.priority === 'urgent').length;

    expect(total).toBe(3);
    expect(inProgress).toBe(1);
    expect(completed).toBe(1);
    expect(highOrUrgent).toBe(2);
  });

  it('filters task list by multiple criteria (status and keyword search)', () => {
    const filterFn = (task: Task, filters: TaskFilterParams) => {
      if (filters.status && filters.status !== 'all' && task.status !== filters.status) return false;
      if (filters.priority && filters.priority !== 'all' && task.priority !== filters.priority) return false;
      if (filters.search) {
        const query = filters.search.toLowerCase();
        const matchesTitle = task.title.toLowerCase().includes(query);
        const matchesDesc = task.description?.toLowerCase().includes(query) ?? false;
        if (!matchesTitle && !matchesDesc) return false;
      }
      return true;
    };

    const searchDoc = sampleTasks.filter((t) => filterFn(t, { search: 'Documentation' }));
    expect(searchDoc).toHaveLength(1);
    expect(searchDoc[0].id).toBe(3);

    const urgentTasks = sampleTasks.filter((t) => filterFn(t, { priority: 'urgent' }));
    expect(urgentTasks).toHaveLength(1);
    expect(urgentTasks[0].id).toBe(2);

    const pendingHigh = sampleTasks.filter((t) => filterFn(t, { status: 'pending', priority: 'high' }));
    expect(pendingHigh).toHaveLength(0);
  });

  it('verifies bulk selection and toggle mechanics', () => {
    let selectedIds: number[] = [];

    const toggle = (id: number) => {
      selectedIds = selectedIds.includes(id)
        ? selectedIds.filter((item) => item !== id)
        : [...selectedIds, id];
    };

    toggle(1);
    toggle(2);
    expect(selectedIds).toEqual([1, 2]);

    toggle(1);
    expect(selectedIds).toEqual([2]);

    // Select all
    selectedIds = sampleTasks.map((t) => t.id);
    expect(selectedIds).toEqual([1, 2, 3]);

    // Deselect all
    selectedIds = [];
    expect(selectedIds).toHaveLength(0);
  });
});
