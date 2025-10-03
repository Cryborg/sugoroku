<?php

namespace Trapped\Models;

use PDO;
use Trapped\Config;
use Trapped\Database\Database;

/**
 * Classe Door - Représente une porte d'une salle
 */
class Door
{
    private PDO $db;

    public ?int $id = null;
    public int $roomId;
    public string $direction; // north, south, east, west
    public ?int $diceResult = null;
    public ?int $currentTurn = null;
    public ?int $openedBy = null; // ID du joueur qui a payé pour ouvrir cette porte
    public int $happinessModifier = 0; // Bonheur/malheur de -5 à +5

    public function __construct()
    {
        $this->db = Database::getInstance();
        // Générer un modificateur de bonheur aléatoire entre -5 et +5
        $this->happinessModifier = rand(-5, 5);
    }

    /**
     * Crée une nouvelle porte
     */
    public function create(): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO doors (room_id, direction, dice_result, current_turn, happiness_modifier)
            VALUES (?, ?, ?, ?, ?)
        ");

        if ($stmt->execute([
            $this->roomId,
            $this->direction,
            $this->diceResult,
            $this->currentTurn,
            $this->happinessModifier
        ])) {
            $this->id = (int) $this->db->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Charge une porte depuis son ID
     */
    public function load(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT * FROM doors WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch();

        if ($data) {
            $this->hydrate($data);
            return true;
        }

        return false;
    }

    /**
     * Hydrate la porte avec des données
     */
    public function hydrate(array $data): void
    {
        $this->id = $data['id'];
        $this->roomId = $data['room_id'];
        $this->direction = $data['direction'];
        $this->diceResult = $data['dice_result'];
        $this->currentTurn = $data['current_turn'];
        $this->openedBy = $data['opened_by'] ?? null;
        $this->happinessModifier = $data['happiness_modifier'] ?? 0;
    }

    /**
     * Lance le dé pour cette porte (nombre de faces = nombre de joueurs + 1)
     */
    public function rollDice(int $turnNumber, int $numberOfPlayers = Config::DEFAULT_DICE_FACES): int
    {
        // Dé avec (nombre de joueurs + 1) faces : D(joueurs+1)
        // Exemple : 1 joueur → D2 (0-2), 2 joueurs → D3 (0-3)
        $maxValue = max(1, $numberOfPlayers) + 1;
        $this->diceResult = rand(0, $maxValue);
        $this->currentTurn = $turnNumber;
        $this->save();

        return $this->diceResult;
    }

    /**
     * Vérifie si le dé a été lancé pour le tour actuel
     */
    public function isDiceRolledForTurn(int $turnNumber): bool
    {
        return $this->currentTurn === $turnNumber && $this->diceResult !== null;
    }

    /**
     * Récupère la capacité de la porte (résultat du dé)
     */
    public function getCapacity(): int
    {
        return $this->diceResult ?? 0;
    }

    /**
     * Vérifie si la porte est ouverte pour le tour actuel
     */
    public function isOpen(): bool
    {
        return $this->openedBy !== null;
    }

    /**
     * Ouvre la porte (un joueur volontaire paie 1 point)
     */
    public function open(int $playerId): bool
    {
        $player = new Player();
        if (!$player->load($playerId)) {
            return false;
        }

        // Vérifier que le joueur a assez de points
        if ($player->points < 1) {
            return false;
        }

        // Retirer 1 point au joueur
        if (!$player->removePoints(1)) {
            return false;
        }

        $this->openedBy = $playerId;
        return $this->save(); // IMPORTANT: Sauvegarder en base !
    }

    /**
     * Réinitialise la porte pour un nouveau tour
     */
    public function reset(): bool
    {
        $this->openedBy = null;
        return $this->save();
    }

    /**
     * Récupère tous les joueurs qui ont choisi cette porte
     */
    public function getPlayerChoices(int $turnNumber): array
    {
        $stmt = $this->db->prepare("
            SELECT p.* FROM players p
            INNER JOIN player_choices pc ON p.id = pc.player_id
            WHERE pc.door_id = ? AND pc.turn_number = ?
        ");
        $stmt->execute([$this->id, $turnNumber]);

        $players = [];
        while ($row = $stmt->fetch()) {
            $player = new Player();
            $player->hydrate($row);
            $players[] = $player;
        }

        return $players;
    }

    /**
     * Sauvegarde les modifications
     */
    private function save(): bool
    {
        $stmt = $this->db->prepare("
            UPDATE doors
            SET dice_result = ?, current_turn = ?, opened_by = ?
            WHERE id = ?
        ");

        return $stmt->execute([
            $this->diceResult,
            $this->currentTurn,
            $this->openedBy,
            $this->id
        ]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'roomId' => $this->roomId,
            'direction' => $this->direction,
            'diceResult' => $this->diceResult,
            'currentTurn' => $this->currentTurn,
            'isOpen' => $this->isOpen(),
            'openedBy' => $this->openedBy,
            'happinessModifier' => $this->happinessModifier
        ];
    }
}
