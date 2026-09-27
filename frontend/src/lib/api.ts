import { ApiResponse, PaginatedTasks, Task, TaskAttachment, TaskComment, TaskFilterParams, User } from './types';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://127.0.0.1:8000/api';

class ApiClient {
  private getToken(): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem('auth_token');
  }

  private async request<T>(
    endpoint: string,
    options: RequestInit = {}
  ): Promise<T> {
    const token = this.getToken();
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...(options.headers as Record<string, string>),
    };

    if (token) {
      headers['Authorization'] = `Bearer ${token}`;
    }

    if (!(options.body instanceof FormData) && !headers['Content-Type']) {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(`${API_BASE}${endpoint}`, {
      ...options,
      headers,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      const errorMessage = data?.message || data?.error || `HTTP error ${response.status}`;
      const error: any = new Error(errorMessage);
      error.status = response.status;
      error.errors = data?.errors;
      throw error;
    }

    return data;
  }

  async login(email: string, password: string): Promise<{ token: string; user: User }> {
    const res = await this.request<ApiResponse<{ token: string; user: User }>>('/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });
    if (typeof window !== 'undefined' && res.data?.token) {
      localStorage.setItem('auth_token', res.data.token);
    }
    return res.data;
  }

  async logout(): Promise<void> {
    try {
      await this.request('/auth/logout', { method: 'POST' });
    } finally {
      if (typeof window !== 'undefined') {
        localStorage.removeItem('auth_token');
      }
    }
  }

  async getCurrentUser(): Promise<User> {
    const res = await this.request<ApiResponse<User>>('/auth/me');
    return res.data;
  }

  async getTasks(params: TaskFilterParams = {}): Promise<PaginatedTasks> {
    const query = new URLSearchParams();
    if (params.status && params.status !== 'all') query.set('status', params.status);
    if (params.priority && params.priority !== 'all') query.set('priority', params.priority);
    if (params.assigned_to) query.set('assigned_to', String(params.assigned_to));
    if (params.search) query.set('search', params.search);
    if (params.sort_by) query.set('sort_by', params.sort_by);
    if (params.sort_order) query.set('sort_order', params.sort_order);
    if (params.page) query.set('page', String(params.page));
    if (params.limit) query.set('limit', String(params.limit));

    const queryString = query.toString();
    const endpoint = `/tasks${queryString ? `?${queryString}` : ''}`;
    const res = await this.request<ApiResponse<PaginatedTasks>>(endpoint);
    return res.data;
  }

  async getTask(id: number): Promise<Task> {
    const res = await this.request<ApiResponse<Task>>(`/tasks/${id}`);
    return res.data;
  }

  async createTask(payload: {
    title: string;
    description?: string;
    status?: string;
    priority?: string;
    due_date?: string | null;
    assigned_to?: number | null;
  }): Promise<Task> {
    const res = await this.request<ApiResponse<Task>>('/tasks', {
      method: 'POST',
      body: JSON.stringify(payload),
    });
    return res.data;
  }

  async updateTask(id: number, payload: Partial<Task>): Promise<Task> {
    const res = await this.request<ApiResponse<Task>>(`/tasks/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    });
    return res.data;
  }

  async deleteTask(id: number): Promise<void> {
    await this.request(`/tasks/${id}`, {
      method: 'DELETE',
    });
  }

  async bulkUpdateStatus(taskIds: number[], status: string): Promise<{ queued: boolean; updated: number }> {
    const res = await this.request<ApiResponse<{ queued: boolean; updated: number }>>('/tasks/bulk-status', {
      method: 'POST',
      body: JSON.stringify({ task_ids: taskIds, status }),
    });
    return res.data;
  }

  async exportTasks(): Promise<{ message: string }> {
    const res = await this.request<ApiResponse<{ message: string }>>('/tasks/export', {
      method: 'POST',
      body: JSON.stringify({}),
    });
    return res.data;
  }

  async uploadAttachment(taskId: number, file: File): Promise<TaskAttachment> {
    const formData = new FormData();
    formData.append('file', file);

    const res = await this.request<ApiResponse<TaskAttachment>>(`/tasks/${taskId}/attachments`, {
      method: 'POST',
      body: formData,
    });
    return res.data;
  }

  async uploadChunk(
    taskId: number,
    uploadId: string,
    fileName: string,
    chunkIndex: number,
    totalChunks: number,
    chunkBlob: Blob
  ): Promise<{ complete: boolean; received_chunks: number; attachment?: TaskAttachment }> {
    const formData = new FormData();
    formData.append('upload_id', uploadId);
    formData.append('file_name', fileName);
    formData.append('chunk_index', String(chunkIndex));
    formData.append('total_chunks', String(totalChunks));
    formData.append('chunk', chunkBlob, fileName);

    const res = await this.request<ApiResponse<any>>(`/tasks/${taskId}/attachments/chunk`, {
      method: 'POST',
      body: formData,
    });
    return res.data;
  }

  async deleteAttachment(id: number): Promise<void> {
    await this.request(`/attachments/${id}`, {
      method: 'DELETE',
    });
  }

  getAttachmentDownloadUrl(id: number): string {
    return `${API_BASE}/attachments/${id}/download`;
  }

  async getComments(taskId: number): Promise<TaskComment[]> {
    const res = await this.request<ApiResponse<TaskComment[]>>(`/tasks/${taskId}/comments`);
    return res.data;
  }

  async addComment(taskId: number, comment: string): Promise<TaskComment> {
    const res = await this.request<ApiResponse<TaskComment>>(`/tasks/${taskId}/comments`, {
      method: 'POST',
      body: JSON.stringify({ comment }),
    });
    return res.data;
  }

  async deleteComment(id: number): Promise<void> {
    await this.request(`/comments/${id}`, {
      method: 'DELETE',
    });
  }

  async getQueueStats(): Promise<{ pending: number; failed: number; total: number }> {
    const res = await this.request<ApiResponse<{ pending: number; failed: number; total: number }>>('/queue/stats');
    return res.data;
  }

  async runQueueWork(): Promise<{ processed: number; remaining: number }> {
    const res = await this.request<ApiResponse<{ processed: number; remaining: number }>>('/queue/work', {
      method: 'POST',
      body: JSON.stringify({ limit: 10 }),
    });
    return res.data;
  }
}

export const api = new ApiClient();
