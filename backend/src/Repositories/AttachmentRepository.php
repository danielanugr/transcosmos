<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

class AttachmentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT a.*, u.name as uploader_name, u.email as uploader_email 
                FROM task_attachments a
                LEFT JOIN users u ON a.uploaded_by = u.id
                WHERE a.id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findByTaskId(int $taskId): array
    {
        $sql = "SELECT a.*, u.name as uploader_name 
                FROM task_attachments a
                LEFT JOIN users u ON a.uploaded_by = u.id
                WHERE a.task_id = :task_id
                ORDER BY a.version DESC, a.uploaded_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['task_id' => $taskId]);
        return $stmt->fetchAll();
    }

    public function getLatestVersion(int $taskId, string $fileName): int
    {
        $stmt = $this->db->prepare('SELECT MAX(version) FROM task_attachments WHERE task_id = :task_id AND file_name = :file_name');
        $stmt->execute(['task_id' => $taskId, 'file_name' => $fileName]);
        $max = $stmt->fetchColumn();
        return $max ? (int) $max : 0;
    }

    public function create(array $data): array
    {
        $sql = "INSERT INTO task_attachments (task_id, file_name, file_path, file_size, mime_type, thumbnail_path, version, uploaded_by, uploaded_at)
                VALUES (:task_id, :file_name, :file_path, :file_size, :mime_type, :thumbnail_path, :version, :uploaded_by, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'task_id' => (int) $data['task_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'file_size' => (int) $data['file_size'],
            'mime_type' => $data['mime_type'],
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'version' => (int) ($data['version'] ?? 1),
            'uploaded_by' => !empty($data['uploaded_by']) ? (int) $data['uploaded_by'] : null,
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM task_attachments WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function saveChunk(array $data): void
    {
        $sql = "INSERT INTO file_chunks (upload_id, task_id, file_name, chunk_index, total_chunks, chunk_size, chunk_path, created_at)
                VALUES (:upload_id, :task_id, :file_name, :chunk_index, :total_chunks, :chunk_size, :chunk_path, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'upload_id' => $data['upload_id'],
            'task_id' => (int) $data['task_id'],
            'file_name' => $data['file_name'],
            'chunk_index' => (int) $data['chunk_index'],
            'total_chunks' => (int) $data['total_chunks'],
            'chunk_size' => (int) $data['chunk_size'],
            'chunk_path' => $data['chunk_path'],
        ]);
    }

    public function getChunks(string $uploadId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM file_chunks WHERE upload_id = :upload_id ORDER BY chunk_index ASC');
        $stmt->execute(['upload_id' => $uploadId]);
        return $stmt->fetchAll();
    }

    public function clearChunks(string $uploadId): void
    {
        $stmt = $this->db->prepare('DELETE FROM file_chunks WHERE upload_id = :upload_id');
        $stmt->execute(['upload_id' => $uploadId]);
    }
}
