import { describe, it, expect, vi } from 'vitest';
import { api } from '../src/lib/api';

describe('Part 3 Bonus Features Unit Tests', () => {
  it('formats video streaming URL correctly', () => {
    const streamUrl = api.getVideoStreamUrl(101);
    expect(streamUrl).toContain('/attachments/101/stream');
  });

  it('provides realtime stream endpoint URL', () => {
    const sseUrl = api.getRealtimeStreamUrl();
    expect(sseUrl).toContain('/realtime/stream');
  });

  it('dispatches typing indicator payload to API', async () => {
    globalThis.fetch = vi.fn().mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => ({ success: true, message: 'Typing event emitted.' }),
    } as any);

    const res = await api.sendTyping(42, true);
    expect(res).toBeUndefined();
    expect(fetch).toHaveBeenCalledWith(
      expect.stringContaining('/realtime/typing'),
      expect.objectContaining({
        method: 'POST',
        body: JSON.stringify({ task_id: 42, is_typing: true }),
      })
    );
  });
});
