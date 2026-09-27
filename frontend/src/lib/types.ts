export type TaskStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

export interface User {
  id: number;
  name: string;
  email: string;
  role: 'admin' | 'user' | string;
  created_at?: string;
}

export interface TaskAttachment {
  id: number;
  task_id: number;
  file_name: string;
  file_path: string;
  file_size: number;
  mime_type: string;
  thumbnail_path: string | null;
  version: number;
  uploaded_by: number | null;
  uploaded_at: string;
  uploader?: User | null;
}

export interface TaskComment {
  id: number;
  task_id: number;
  user_id: number;
  comment: string;
  created_at: string;
  user?: User;
}

export interface Task {
  id: number;
  title: string;
  description: string | null;
  status: TaskStatus;
  priority: TaskPriority;
  due_date: string | null;
  assigned_to: number | null;
  created_by: number;
  created_at: string;
  updated_at: string;
  assignee?: User | null;
  creator?: User;
  attachments?: TaskAttachment[];
  comments?: TaskComment[];
}

export interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T;
  error?: string;
  errors?: Record<string, string[]>;
}

export interface PaginatedTasks {
  data: Task[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export interface TaskFilterParams {
  status?: string;
  priority?: string;
  assigned_to?: string | number;
  search?: string;
  sort_by?: 'created_at' | 'due_date' | 'priority' | 'title';
  sort_order?: 'asc' | 'desc';
  page?: number;
  limit?: number;
}
