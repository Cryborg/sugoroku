<?php

namespace Trapped\Models;

use PDO;
use Trapped\Database\Database;

/**
 * Classe Room - Représente une salle du plateau
 */
class Room
{
    private PDO $db;

    public ?int $id = null;
    public int $gameId;
    public int $positionX;
    public int $positionY;
    public int $pointsCost = 0;
    public int $doorCount;
    public bool $isStart = false;
    public bool $isExit = false;
    public bool $isVisited = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une nouvelle salle
     */
    public function create(): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO rooms (game_id, position_x, position_y, points_cost, door_count, is_start, is_exit, is_visited)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([
            $this->gameId,
            $this->positionX,
            $this->positionY,
            $this->pointsCost,
            $this->doorCount,
            $this->isStart ? 1 : 0,
            $this->isExit ? 1 : 0,
            $this->isVisited ? 1 : 0
        ])) {
            $this->id = (int) $this->db->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Charge une salle depuis son ID
     */
    public function load(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $this->hydrate($data);
            return true;
        }

        return false;
    }

    /**
     * Hydrate la salle avec des données
     */
    public function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->gameId = $data['game_id'];
        $this->positionX = $data['position_x'];
        $this->positionY = $data['position_y'];
        $this->pointsCost = $data['points_cost'];
        $this->doorCount = $data['door_count'];
        $this->isStart = (bool) $data['is_start'];
        $this->isExit = (bool) $data['is_exit'];
        $this->isVisited = (bool) $data['is_visited'];
    }

    /**
     * Marque la salle comme visitée
     */
    public function markAsVisited(): bool
    {
        $this->isVisited = true;

        $stmt = $this->db->prepare("UPDATE rooms SET is_visited = 1 WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    /**
     * Récupère toutes les portes de la salle
     */
    public function getDoors(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM doors WHERE room_id = ?");
        $stmt->execute([$this->id]);

        $doors = [];
        while ($row = $stmt->fetch()) {
            $door = new Door();
            $door->hydrate($row);
            $doors[] = $door;
        }

        return $doors;
    }

    /**
     * Récupère tous les joueurs présents dans la salle
     */
    public function getPlayers(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE current_room_id = ?");
        $stmt->execute([$this->id]);

        $players = [];
        while ($row = $stmt->fetch()) {
            $player = new Player();
            $player->hydrate($row);
            $players[] = $player;
        }

        return $players;
    }

    /**
     * Applique le coût en points à tous les joueurs présents dans la salle
     */
    public function applyRoomCost(): bool
    {
        if ($this->pointsCost === 0) {
            return true;
        }

        $players = $this->getPlayers();

        foreach ($players as $player) {
            $player->removePoints($this->pointsCost);
        }

        return true;
    }

    /**
     * Récupère la salle adjacente dans une direction donnée
     */
    public function getAdjacentRoom(string $direction): ?Room
    {
        $x = $this->positionX;
        $y = $this->positionY;

        switch ($direction) {
            case 'north':
                $y--;
                break;
            case 'south':
                $y++;
                break;
            case 'east':
                $x++;
                break;
            case 'west':
                $x--;
                break;
            default:
                return null;
        }

        // Vérifier que la position est valide
        if ($x < 0 || $x > 4 || $y < 0 || $y > 4) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM rooms
            WHERE game_id = ? AND position_x = ? AND position_y = ?
        ");
        $stmt->execute([$this->gameId, $x, $y]);
        $data = $stmt->fetch();

        if ($data) {
            $room = new Room();
            $room->hydrate($data);
            return $room;
        }

        return null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'gameId' => $this->gameId,
            'positionX' => $this->positionX,
            'positionY' => $this->positionY,
            'pointsCost' => $this->pointsCost,
            'doorCount' => $this->doorCount,
            'isStart' => $this->isStart,
            'isExit' => $this->isExit,
            'isVisited' => $this->isVisited,
            'doors' => array_map(fn($d) => $d->toArray(), $this->getDoors())
        ];
    }
}
