<?php

namespace Trapped\Models;

use PDO;
use Trapped\Database\Database;

/**
 * Classe User - Représente un utilisateur
 */
class User
{
    private PDO $db;

    public ?int $id = null;
    public string $email;
    public string $passwordHash;
    public string $username;
    public bool $isAdmin = false;
    public string $createdAt;
    public ?string $lastLogin = null;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée un nouveau utilisateur
     */
    public function create(): bool
    {
        // Vérifier si l'email existe déjà
        if ($this->emailExists($this->email)) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO users (email, password_hash, username)
            VALUES (?, ?, ?)
        ");

        if ($stmt->execute([
            $this->email,
            $this->passwordHash,
            $this->username
        ])) {
            $this->id = (int) $this->db->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Charge un utilisateur depuis son ID
     */
    public function load(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $this->hydrate($data);
            return true;
        }

        return false;
    }

    /**
     * Charge un utilisateur depuis son email
     */
    public function loadByEmail(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $data = $stmt->fetch();

        if ($data) {
            $this->hydrate($data);
            return true;
        }

        return false;
    }

    /**
     * Hydrate l'utilisateur avec des données
     */
    private function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->email = $data['email'];
        $this->passwordHash = $data['password_hash'];
        $this->username = $data['username'];
        $this->isAdmin = ($data['is_admin'] ?? 0) == 1;
        $this->createdAt = $data['created_at'];
        $this->lastLogin = $data['last_login'];
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result && $result['count'] > 0;
    }

    /**
     * Vérifie le mot de passe
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    /**
     * Hash un mot de passe
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /**
     * Met à jour la date de dernière connexion
     */
    public function updateLastLogin(): bool
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET last_login = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        return $stmt->execute([$this->id]);
    }

    /**
     * Récupère les joueurs favoris de l'utilisateur
     */
    public function getFavoritePlayers(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM user_favorite_players
            WHERE user_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$this->id]);

        $players = [];
        while ($row = $stmt->fetch()) {
            $players[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'gender' => $row['gender'],
                'avatar' => $row['avatar']
            ];
        }

        return $players;
    }

    /**
     * Ajoute un joueur aux favoris
     */
    public function addFavoritePlayer(string $name, string $gender, string $avatar): bool
    {
        $stmt = $this->db->prepare("
            INSERT OR IGNORE INTO user_favorite_players (user_id, name, gender, avatar)
            VALUES (?, ?, ?, ?)
        ");

        return $stmt->execute([$this->id, $name, $gender, $avatar]);
    }

    /**
     * Supprime un joueur des favoris
     */
    public function removeFavoritePlayer(int $playerId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM user_favorite_players
            WHERE id = ? AND user_id = ?
        ");

        return $stmt->execute([$playerId, $this->id]);
    }

    /**
     * Convertit l'utilisateur en tableau (sans le mot de passe)
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'isAdmin' => $this->isAdmin,
            'createdAt' => $this->createdAt ?? null,
            'lastLogin' => $this->lastLogin
        ];
    }
}
