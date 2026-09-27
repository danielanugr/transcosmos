<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use PDO;

class UserRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, role, created_at, updated_at FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmailWithPassword(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, password, role, created_at, updated_at FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, role, created_at, updated_at FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public function all(): array
    {
        $stmt = $this->db->query('SELECT id, name, email, role, created_at, updated_at FROM users ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $sql = 'INSERT INTO users (name, email, password, role, created_at, updated_at) 
                VALUES (:name, :email, :password, :role, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'member',
        ]);

        $id = (int) $this->db->lastInsertId();
        return $this->findById($id);
    }
}
