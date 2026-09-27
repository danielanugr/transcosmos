<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

class JobRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function push(string $jobClass, array $payload, string $queue = 'default', int $delaySeconds = 0): int
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $sql = "INSERT INTO jobs (queue, job_class, payload, attempts, status, available_at, created_at, updated_at)
                VALUES (:queue, :job_class, :payload, 0, 'pending', :available_at, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'queue' => $queue,
            'job_class' => $jobClass,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'available_at' => $availableAt,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function reserveNext(string $queue = 'default'): ?array
    {
        return Database::transaction(function (PDO $pdo) use ($queue) {
            $now = date('Y-m-d H:i:s');
            $sql = "SELECT * FROM jobs 
                    WHERE queue = :queue 
                      AND status = 'pending' 
                      AND available_at <= :now 
                    ORDER BY id ASC 
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(['queue' => $queue, 'now' => $now]);
            $job = $stmt->fetch();

            if (!$job) {
                return null;
            }

            $updateSql = "UPDATE jobs 
                          SET status = 'processing', 
                              attempts = attempts + 1, 
                              reserved_at = :reserved_at,
                              updated_at = CURRENT_TIMESTAMP 
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                'reserved_at' => $now,
                'id' => $job['id'],
            ]);

            $job['attempts'] = (int) $job['attempts'] + 1;
            $job['status'] = 'processing';
            return $job;
        });
    }

    public function markCompleted(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE jobs SET status = 'completed', updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function markFailed(int $id, string $errorMessage): void
    {
        $stmt = $this->db->prepare("UPDATE jobs SET status = 'failed', error_message = :err, updated_at = CURRENT_TIMESTAMP WHERE id = :id");
        $stmt->execute(['id' => $id, 'err' => $errorMessage]);
    }

    public function release(int $id, int $delaySeconds, string $errorMessage): void
    {
        $availableAt = date('Y-m-d H:i:s', time() + $delaySeconds);
        $sql = "UPDATE jobs 
                SET status = 'pending', 
                    reserved_at = NULL, 
                    available_at = :available_at, 
                    error_message = :err, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'available_at' => $availableAt,
            'err' => $errorMessage,
            'id' => $id,
        ]);
    }

    public function getStats(): array
    {
        $sql = "SELECT status, COUNT(*) as count FROM jobs GROUP BY status";
        $stmt = $this->db->query($sql);
        $rows = $stmt->fetchAll();

        $stats = ['pending' => 0, 'processing' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($rows as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }
        return $stats;
    }
}
