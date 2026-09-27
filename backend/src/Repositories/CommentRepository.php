<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

class CommentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findByTaskId(int $taskId): array
    {
        $sql = "SELECT c.id, c.task_id, c.user_id, c.comment, c.created_at,
                       u.name as user_name, u.email as user_email, u.role as user_role
                FROM task_comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.task_id = :task_id
                ORDER BY c.created_at ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT c.id, c.task_id, c.user_id, c.comment, c.created_at,
                       u.name as user_name, u.email as user_email, u.role as user_role
                FROM task_comments c
                INNER JOIN users u ON c.user_id = u.id
                WHERE c.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $sql = "INSERT INTO task_comments (task_id, user_id, comment, created_at)
                VALUES (:task_id, :user_id, :comment, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'task_id' => (int) $data['task_id'],
            'user_id' => (int) $data['user_id'],
            'comment' => $data['comment'],
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM task_comments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
