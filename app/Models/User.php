<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class User
{
    public ?int $id = null;
    public ?int $role_id = null;
    public ?string $full_name = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?string $status = null;

    public function __construct()
    {
    }

    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function findByEmail(string $email): ?self
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return self::fromArray($row);
        }

        return null;
    }

    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = $data['id'] ?? null;
        $user->role_id = $data['role_id'] ?? null;
        $user->full_name = $data['full_name'] ?? null;
        $user->email = $data['email'] ?? null;
        $user->password = $data['password'] ?? null;
        $user->status = $data['status'] ?? null;
        return $user;
    }

    public function save(): bool
    {
        $db = Database::getInstance();

        if ($this->id === null) {
            $stmt = $db->prepare("INSERT INTO users (role_id, full_name, email, password, status) VALUES (:role_id, :full_name, :email, :password, :status)");
            $stmt->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $this->full_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);
            $stmt->bindParam(':status', $this->status);

            if ($stmt->execute()) {
                $this->id = (int)$db->lastInsertId();
                return true;
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET role_id = :role_id, full_name = :full_name, email = :email, password = :password, status = :status WHERE id = :id");
            $stmt->bindParam(':role_id', $this->role_id, PDO::PARAM_INT);
            $stmt->bindParam(':full_name', $this->full_name);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);
            $stmt->bindParam(':status', $this->status);
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

            return $stmt->execute();
        }

        return false;
    }

    public function getRole(): ?string
    {
        if ($this->role_id === null) {
            return null;
        }

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT name FROM roles WHERE id = :id LIMIT 1");
        $stmt->bindParam(':id', $this->role_id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() ?: null;
    }
}
