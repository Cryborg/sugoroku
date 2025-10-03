<?php

namespace Trapped\Models;

use PDO;
use Trapped\Config;
use Trapped\Database\Database;

/**
 * Classe Game - Représente une partie
 * Principe SOLID : Single Responsibility - gère uniquement la logique d'une partie
 */
class Game
{
    private PDO $db;

    public ?int $id = null;
    public string $createdAt;
    public int $currentTurn = 1;
    public string $status = 'waiting'; // waiting, playing, finished
    public ?string $turnStartedAt = null;
    public int $startingPoints = 20;
    public bool $freeRoomsEnabled = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Crée une nouvelle partie
     */
    public function create(): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO games (created_at, current_turn, status, starting_points, free_rooms_enabled)
            VALUES (datetime('now'), 1, 'waiting', ?, ?)
        ");

        if ($stmt->execute([$this->startingPoints, $this->freeRoomsEnabled ? 1 : 0])) {
            $this->id = (int) $this->db->lastInsertId();
            $this->currentTurn = 1;
            $this->status = 'waiting';
            $this->createdAt = date('Y-m-d H:i:s');
            return true;
        }

        return false;
    }

    /**
     * Charge une partie depuis son ID
     */
    public function load(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM games WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $this->id = $data['id'];
            $this->createdAt = $data['created_at'];
            $this->currentTurn = $data['current_turn'];
            $this->status = $data['status'];
            $this->turnStartedAt = $data['turn_started_at'];
            $this->startingPoints = $data['starting_points'] ?? 20;
            $this->freeRoomsEnabled = ($data['free_rooms_enabled'] ?? 0) == 1;
            return true;
        }

        return false;
    }

    /**
     * Démarre la partie (passe en mode playing)
     */
    public function start(): bool
    {
        if ($this->status !== 'waiting') {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE games
            SET status = 'playing', turn_started_at = datetime('now')
            WHERE id = ?
        ");

        if ($stmt->execute([$this->id])) {
            $this->status = 'playing';
            $this->turnStartedAt = date('Y-m-d H:i:s');
            return true;
        }

        return false;
    }

    /**
     * Passe au tour suivant
     */
    public function nextTurn(): bool
    {
        if ($this->currentTurn >= Config::MAX_TURNS) {
            return $this->finish();
        }

        $stmt = $this->db->prepare("
            UPDATE games
            SET current_turn = current_turn + 1, turn_started_at = datetime('now')
            WHERE id = ?
        ");

        if ($stmt->execute([$this->id])) {
            $this->currentTurn++;
            $this->turnStartedAt = date('Y-m-d H:i:s');
            return true;
        }

        return false;
    }

    /**
     * Termine la partie
     */
    public function finish(): bool
    {
        $stmt = $this->db->prepare("
            UPDATE games
            SET status = 'finished'
            WHERE id = ?
        ");

        if ($stmt->execute([$this->id])) {
            $this->status = 'finished';
            return true;
        }

        return false;
    }

    /**
     * Récupère le temps restant pour le tour actuel (en secondes)
     */
    public function getRemainingTime(): int
    {
        if (!$this->turnStartedAt) {
            return Config::TURN_TIMER_SECONDS;
        }

        $start = strtotime($this->turnStartedAt);
        $now = time();
        $elapsed = $now - $start;
        $remaining = Config::TURN_TIMER_SECONDS - $elapsed;

        return max(0, $remaining);
    }

    /**
     * Vérifie si le timer du tour actuel est expiré
     */
    public function isTurnExpired(): bool
    {
        return $this->getRemainingTime() <= 0;
    }

    /**
     * Récupère tous les joueurs de la partie
     */
    public function getPlayers(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE game_id = ? ORDER BY id");
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
     * Récupère toutes les salles de la partie
     */
    public function getRooms(): array
    {
        $stmt = $this->db->prepare("SELECT * FROM rooms WHERE game_id = ? ORDER BY position_y, position_x");
        $stmt->execute([$this->id]);

        $rooms = [];
        while ($row = $stmt->fetch()) {
            $room = new Room();
            $room->hydrate($row);
            $rooms[] = $room;
        }

        return $rooms;
    }

    /**
     * Génère le plateau 5x5 avec distribution aléatoire des points
     */
    public function generateBoard(): bool
    {
        // Distribution des points selon le cahier des charges
        $pointsDistribution = [
            4 => 3, 3 => 5, 2 => 7, 1 => 8
        ];

        $pointsPool = [];
        foreach ($pointsDistribution as $points => $count) {
            for ($i = 0; $i < $count; $i++) {
                $pointsPool[] = $points;
            }
        }
        shuffle($pointsPool);

        // Définir aléatoirement la sortie parmi les 4 coins
        $corners = [
            [0, 0], // haut gauche - A1
            [4, 0], // haut droite - A5
            [0, 4], // bas gauche - E1
            [4, 4]  // bas droite - E5
        ];
        $exitCorner = $corners[array_rand($corners)];

        // Définir aléatoirement le départ sur la ligne C (C1-C5) ou la colonne 3 (A3-E3)
        // Forme une croix au centre du plateau
        do {
            if (rand(0, 1) === 0) {
                // Ligne C (y=2) : toutes les colonnes (x=0 à 4)
                $startY = 2; // Ligne C
                $startX = rand(0, Config::GRID_SIZE - 1);
            } else {
                // Colonne 3 (x=2) : toutes les lignes (y=0 à 4)
                $startX = 2; // Colonne 3
                $startY = rand(0, Config::GRID_SIZE - 1);
            }
        } while ($startX === $exitCorner[0] && $startY === $exitCorner[1]); // Garantit que départ ≠ sortie

        // Générer les pièces
        $index = 0;
        for ($y = 0; $y < Config::GRID_SIZE; $y++) {
            for ($x = 0; $x < Config::GRID_SIZE; $x++) {
                $room = new Room();
                $room->gameId = $this->id;
                $room->positionX = $x;
                $room->positionY = $y;

                // Définir départ et arrivée
                $room->isStart = ($x === $startX && $y === $startY);
                $room->isExit = ($x === $exitCorner[0] && $y === $exitCorner[1]);

                // Définir le coût (0 pour départ et arrivée)
                if ($room->isStart || $room->isExit) {
                    $room->pointsCost = 0;
                } else {
                    // Si l'option pièces gratuites est activée, 10% de chance d'avoir un coût de 0
                    if ($this->freeRoomsEnabled && rand(1, 100) <= 10) {
                        $room->pointsCost = 0;
                    } else {
                        $room->pointsCost = $pointsPool[$index++];
                    }
                }

                // Définir le nombre de portes selon la position
                $room->doorCount = $this->calculateDoorCount($x, $y);

                if (!$room->create()) {
                    return false;
                }

                // Créer les portes pour cette salle
                $this->createDoorsForRoom($room);
            }
        }

        return true;
    }

    /**
     * Calcule le nombre de portes selon la position
     */
    private function calculateDoorCount(int $x, int $y): int
    {
        $isCorner = ($x === 0 || $x === 4) && ($y === 0 || $y === 4);
        $isEdge = ($x === 0 || $x === 4 || $y === 0 || $y === 4);

        if ($isCorner) return 2;
        if ($isEdge) return 3;
        return 4;
    }

    /**
     * Crée les portes pour une salle
     */
    private function createDoorsForRoom(Room $room): void
    {
        $directions = [];

        // Nord
        if ($room->positionY > 0) $directions[] = 'north';
        // Sud
        if ($room->positionY < 4) $directions[] = 'south';
        // Ouest
        if ($room->positionX > 0) $directions[] = 'west';
        // Est
        if ($room->positionX < 4) $directions[] = 'east';

        foreach ($directions as $direction) {
            $door = new Door();
            $door->roomId = $room->id;
            $door->direction = $direction;
            $door->create();
        }
    }

    /**
     * Récupère l'état complet de la partie (pour l'API)
     */
    public function getFullState(): array
    {
        return [
            'id' => $this->id,
            'currentTurn' => $this->currentTurn,
            'status' => $this->status,
            'remainingTime' => $this->getRemainingTime(),
            'players' => array_map(fn($p) => $p->toArray($this->currentTurn), $this->getPlayers()),
            'rooms' => array_map(fn($r) => $r->toArray(), $this->getRooms())
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt ?? null,
            'currentTurn' => $this->currentTurn,
            'status' => $this->status,
            'turnStartedAt' => $this->turnStartedAt,
            'remainingTime' => $this->getRemainingTime()
        ];
    }
}
