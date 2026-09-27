<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

class TaskRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findPaginated(array $filters = [], int $page = 1, int $perPage = 10, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $allowedSortCols = [
            'id' => 't.id',
            'title' => 't.title',
            'status' => 't.status',
            'priority' => 't.priority',
            'due_date' => 't.due_date',
            'created_at' => 't.created_at',
            'updated_at' => 't.updated_at',
        ];
        $orderCol = $allowedSortCols[strtolower($sortBy)] ?? 't.created_at';
        $direction = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 't.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 't.priority = :priority';
            $params['priority'] = $filters['priority'];
        }

        if (!empty($filters['assigned_user_id'])) {
            $where[] = 't.assigned_user_id = :assigned_user_id';
            $params['assigned_user_id'] = (int) $filters['assigned_user_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(t.title LIKE :search OR t.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count total matching rows
        $countSql = "SELECT COUNT(*) FROM tasks t {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Fetch paginated rows with user joins to prevent N+1 queries
        $sql = "SELECT 
                    t.id, t.title, t.description, t.status, t.priority,
                    t.assigned_user_id, t.created_by, t.due_date, t.created_at, t.updated_at,
                    au.name AS assigned_user_name, au.email AS assigned_user_email,
                    cu.name AS creator_name, cu.email AS creator_email,
                    (SELECT COUNT(*) FROM task_attachments a WHERE a.task_id = t.id) AS attachments_count,
                    (SELECT COUNT(*) FROM task_comments c WHERE c.task_id = t.id) AS comments_count
                FROM tasks t
                LEFT JOIN users au ON t.assigned_user_id = au.id
                INNER JOIN users cu ON t.created_by = cu.id
                {$whereClause}
                ORDER BY {$orderCol} {$direction}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(":{$k}", $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $tasks = $stmt->fetchAll();

        return [
            'data' => $tasks,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ];
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT 
                    t.id, t.title, t.description, t.status, t.priority,
                    t.assigned_user_id, t.created_by, t.due_date, t.created_at, t.updated_at,
                    au.name AS assigned_user_name, au.email AS assigned_user_email,
                    cu.name AS creator_name, cu.email AS creator_email
                FROM tasks t
                LEFT JOIN users au ON t.assigned_user_id = au.id
                INNER JOIN users cu ON t.created_by = cu.id
                WHERE t.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();
        return $task ?: null;
    }

    public function create(array $data): array
    {
        $sql = "INSERT INTO tasks (title, description, status, priority, assigned_user_id, created_by, due_date, created_at, updated_at)
                VALUES (:title, :description, :status, :priority, :assigned_user_id, :created_by, :due_date, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'assigned_user_id' => !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            'created_by' => (int) $data['created_by'],
            'due_date' => !empty($data['due_date']) ? $data['due_date'] : null,
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function update(int $id, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $id];

        $allowed = ['title', 'description', 'status', 'priority', 'assigned_user_id', 'due_date'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return $this->findById($id);
        }

        $fields[] = "updated_at = CURRENT_TIMESTAMP";
        $sql = "UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function bulkUpdateStatus(array $taskIds, string $status): int
    {
        if (empty($taskIds)) {
            return 0;
        }

        $inClause = implode(',', array_fill(0, count($taskIds), '?'));
        $sql = "UPDATE tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id IN ({$inClause})";
        $stmt = $this->db->prepare($sql);
        $params = array_merge([$status], array_map('intval', $taskIds));
        $stmt->execute($params);
        return $stmt->rowCount();
    }
}
