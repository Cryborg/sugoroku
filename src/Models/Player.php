<?php

namespace Trapped\Models;

use PDO;
use Trapped\Config;
use Trapped\Database\Database;

/**
 * Classe Player - Représente un joueur
 */
class Player
{
    private PDO $db;

    public ?int $id = null;
    public int $gameId;
    public string $name;
    public int $points = Config::PLAYER_STARTING_POINTS;
    public ?int $currentRoomId = null;
    public string $status = 'alive'; // alive, dead, blocked, winner
    public int $happiness = 0; // Bonheur total accumulé
    public int $happinessPositive = 0; // Bonheur positif accumulé
    public int $happinessNegative = 0; // Malheur accumulé (valeur absolue)
    public string $gender = 'male'; // male, female
    public string $avatar = 'male_01.png'; // Nom du fichier avatar
    public string $createdAt;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée un nouveau joueur
     */
    public function create(): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO players (game_id, name, points, current_room_id, status, happiness, happiness_positive, happiness_negative, gender, avatar)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([
            $this->gameId,
            $this->name,
            $this->points,
            $this->currentRoomId,
            $this->status,
            $this->happiness,
            $this->happinessPositive,
            $this->happinessNegative,
            $this->gender,
            $this->avatar
        ])) {
            $this->id = (int) $this->db->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Charge un joueur depuis son ID
     */
    public function load(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $this->hydrate($data);
            return true;
        }

        return false;
    }

    /**
     * Hydrate le joueur avec des données
     */
    public function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->gameId = $data['game_id'];
        $this->name = $data['name'];
        $this->points = $data['points'];
        $this->currentRoomId = $data['current_room_id'];
        $this->status = $data['status'];
        $this->happiness = $data['happiness'] ?? 0;
        $this->happinessPositive = $data['happiness_positive'] ?? 0;
        $this->happinessNegative = $data['happiness_negative'] ?? 0;
        $this->gender = $data['gender'] ?? 'male';
        $this->avatar = $data['avatar'] ?? 'male_01.png';
        $this->createdAt = $data['created_at'];
    }

    /**
     * Met à jour les points du joueur
     */
    public function updatePoints(int $points): bool
    {
        $this->points = max(0, $points);

        // Si points = 0, le joueur est mort
        if ($this->points <= 0) {
            $this->status = 'dead';
        }

        return $this->save();
    }

    /**
     * Retire des points au joueur
     */
    public function removePoints(int $amount): bool
    {
        return $this->updatePoints($this->points - $amount);
    }

    /**
     * Ajoute des points au joueur
     */
    public function addPoints(int $amount): bool
    {
        return $this->updatePoints(min(Config::PLAYER_STARTING_POINTS, $this->points + $amount));
    }

    /**
     * Ajoute du bonheur au joueur
     */
    public function addHappiness(int $amount): bool
    {
        $this->happiness += $amount;

        // Ajouter aux compteurs séparés
        if ($amount > 0) {
            $this->happinessPositive += $amount;
        } else if ($amount < 0) {
            $this->happinessNegative += abs($amount);
        }

        $stmt = $this->db->prepare("
            UPDATE players
            SET happiness = ?, happiness_positive = ?, happiness_negative = ?
            WHERE id = ?
        ");

        return $stmt->execute([$this->happiness, $this->happinessPositive, $this->happinessNegative, $this->id]);
    }

    /**
     * Déplace le joueur vers une nouvelle salle
     */
    public function moveToRoom(int $roomId): bool
    {
        $this->currentRoomId = $roomId;
        return $this->save();
    }

    /**
     * Met à jour le statut du joueur
     */
    public function updateStatus(string $status): bool
    {
        $validStatuses = ['alive', 'dead', 'blocked', 'winner'];

        if (!in_array($status, $validStatuses)) {
            return false;
        }

        $this->status = $status;
        return $this->save();
    }

    /**
     * Sauvegarde les modifications du joueur
     */
    public function save(): bool
    {
        $stmt = $this->db->prepare("
            UPDATE players
            SET points = ?, current_room_id = ?, status = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $this->points,
            $this->currentRoomId,
            $this->status,
            $this->id
        ]);
    }

    /**
     * Récupère la salle actuelle du joueur
     */
    public function getCurrentRoom(): ?Room
    {
        if (!$this->currentRoomId) {
            return null;
        }

        $room = new Room();
        if ($room->load($this->currentRoomId)) {
            return $room;
        }

        return null;
    }

    /**
     * Vérifie si le joueur est vivant
     */
    public function isAlive(): bool
    {
        return $this->status === 'alive' && $this->points > 0;
    }

    /**
     * Vérifie si le joueur est bloqué
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * Enregistre le choix de porte du joueur (ou null pour rester)
     */
    public function choiceDoor(?int $doorId, int $turnNumber): bool
    {
        // Supprimer l'ancien choix pour ce tour s'il existe
        $stmt = $this->db->prepare("
            DELETE FROM player_choices
            WHERE player_id = ? AND turn_number = ?
        ");
        $stmt->execute([$this->id, $turnNumber]);

        // Enregistrer le nouveau choix (doorId peut être null = rester ici)
        $stmt = $this->db->prepare("
            INSERT INTO player_choices (player_id, door_id, turn_number)
            VALUES (?, ?, ?)
        ");

        return $stmt->execute([$this->id, $doorId, $turnNumber]);
    }

    /**
     * Récupère le choix de porte du joueur pour un tour donné
     * Retourne l'ID de la porte, ou null si le joueur a choisi de rester (ou n'a pas choisi)
     */
    public function getChoice(int $turnNumber): ?int
    {
        $stmt = $this->db->prepare("
            SELECT door_id FROM player_choices
            WHERE player_id = ? AND turn_number = ?
        ");
        $stmt->execute([$this->id, $turnNumber]);
        $result = $stmt->fetch();

        // Si aucun enregistrement, retourner null
        if (!$result) {
            return null;
        }

        // Si door_id est null (choix = rester), retourner null
        // Sinon retourner l'ID en int
        return $result['door_id'] !== null ? (int) $result['door_id'] : null;
    }

    /**
     * Vérifie si le joueur a fait un choix pour un tour donné
     */
    public function hasChoice(int $turnNumber): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count FROM player_choices
            WHERE player_id = ? AND turn_number = ?
        ");
        $stmt->execute([$this->id, $turnNumber]);
        $result = $stmt->fetch();

        return $result && $result['count'] > 0;
    }

    public function toArray(?int $currentTurn = null): array
    {
        $data = [
            'id' => $this->id,
            'gameId' => $this->gameId,
            'name' => $this->name,
            'points' => $this->points,
            'currentRoomId' => $this->currentRoomId,
            'status' => $this->status,
            'happiness' => $this->happiness,
            'happinessPositive' => $this->happinessPositive,
            'happinessNegative' => $this->happinessNegative,
            'gender' => $this->gender,
            'avatar' => $this->avatar,
            'createdAt' => $this->createdAt ?? null
        ];

        // Ajouter l'info sur le choix si on a le tour actuel
        if ($currentTurn !== null) {
            $data['chosenDoorId'] = $this->getChoice($currentTurn);
            // hasChosen = true si une entrée existe dans player_choices (même si doorId est null pour "rester")
            $data['hasChosen'] = $this->hasChoice($currentTurn);
        }

        return $data;
    }
}
