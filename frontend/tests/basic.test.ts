import { describe, it, expect } from 'vitest';

describe('Frontend Sanity Test', () => {
  it('should verify test runner works correctly', () => {
    expect(1 + 1).toBe(2);
  });

  it('should verify project environment variables', () => {
    const apiBase = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';
    expect(apiBase).toContain('/api');
  });
});
