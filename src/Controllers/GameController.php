<?php

namespace Trapped\Controllers;

use Trapped\Config;
use Trapped\Models\Game;
use Trapped\Models\Player;
use Trapped\Models\Room;

/**
 * GameController - Gestion des parties
 */
class GameController
{
    /**
     * Crée une nouvelle partie
     */
    public function create(array $players, string $difficulty = 'normal', bool $freeRoomsEnabled = false): array
    {
        if (count($players) < 3 || count($players) > 8) {
            return $this->error('Le nombre de joueurs doit être entre 3 et 8');
        }

        // Convertir la difficulté en points de départ
        $startingPoints = match($difficulty) {
            'easy' => 25,
            'hard' => 15,
            default => 20
        };

        $game = new Game();
        $game->startingPoints = $startingPoints;
        $game->freeRoomsEnabled = $freeRoomsEnabled;

        if (!$game->create()) {
            return $this->error('Impossible de créer la partie');
        }

        // Générer le plateau
        if (!$game->generateBoard()) {
            return $this->error('Impossible de générer le plateau');
        }

        // Récupérer la salle de départ
        $rooms = $game->getRooms();
        $startRoom = null;
        foreach ($rooms as $room) {
            if ($room->isStart) {
                $startRoom = $room;
                break;
            }
        }

        if (!$startRoom) {
            return $this->error('Salle de départ introuvable');
        }

        // Créer les joueurs
        foreach ($players as $playerData) {
            $player = new Player();
            $player->gameId = $game->id;

            // Support both old format (string) and new format (object)
            if (is_string($playerData)) {
                $player->name = trim($playerData);
                $player->gender = 'male';
                $player->avatar = 'male_01.png';
            } else {
                $player->name = trim($playerData['name']);
                $player->gender = $playerData['gender'] ?? 'male';
                $player->avatar = $playerData['avatar'] ?? 'male_01.png';
            }

            $player->currentRoomId = $startRoom->id;
            $player->points = $startingPoints;

            if (!$player->create()) {
                return $this->error("Impossible de créer le joueur {$player->name}");
            }
        }

        // Marquer la salle de départ comme visitée
        $startRoom->markAsVisited();

        return $this->success([
            'gameId' => $game->id,
            'message' => 'Partie créée avec succès'
        ]);
    }

    /**
     * Démarre une partie
     */
    public function start(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        if ($game->status !== 'waiting') {
            return $this->error('La partie a déjà commencé ou est terminée');
        }

        // Lance les dés pour toutes les portes du premier tour
        $turnController = new TurnController();
        $turnController->rollDiceForAllRooms($gameId, 1);

        if (!$game->start()) {
            return $this->error('Impossible de démarrer la partie');
        }

        return $this->success([
            'message' => 'Partie démarrée',
            'state' => $game->getFullState()
        ]);
    }

    /**
     * Récupère l'état complet de la partie
     */
    public function getState(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        return $this->success($game->getFullState());
    }

    /**
     * Vérifie les conditions de victoire/défaite
     */
    public function checkEndConditions(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        $players = $game->getPlayers();
        $rooms = $game->getRooms();

        // Trouver la salle de sortie
        $exitRoom = null;
        foreach ($rooms as $room) {
            if ($room->isExit) {
                $exitRoom = $room;
                break;
            }
        }

        // Vérifier les joueurs par statut
        $winners = [];
        $alive = [];
        $dead = [];

        foreach ($players as $player) {
            if ($player->points <= 0 || $player->status === 'dead') {
                $dead[] = $player;
                continue;
            }

            // Marquer comme gagnant dès qu'il arrive à la sortie avec au moins 1 point
            if ($player->currentRoomId === $exitRoom->id && $player->points >= 1) {
                if ($player->status !== 'winner') {
                    $player->updateStatus('winner');
                }
                $winners[] = $player;
            } else {
                $alive[] = $player;
            }
        }

        // Victoire si TOUS les joueurs vivants sont dans la sortie
        if (count($winners) > 0 && count($alive) === 0) {
            $game->finish();

            // Trier les gagnants par bonheur décroissant
            usort($winners, fn($a, $b) => $b->happiness <=> $a->happiness);

            return $this->success([
                'gameOver' => true,
                'reason' => 'victory',
                'winners' => array_map(fn($p) => $p->toArray(), $winners),
                'dead' => array_map(fn($p) => $p->toArray(), $dead)
            ]);
        }

        // Si tour max atteint et personne n'a gagné
        if ($game->currentTurn >= Config::MAX_TURNS) {
            $game->finish();
            return $this->success([
                'gameOver' => true,
                'reason' => 'timeout',
                'survivors' => array_map(fn($p) => $p->toArray(), $alive),
                'dead' => array_map(fn($p) => $p->toArray(), $dead)
            ]);
        }

        // Si tous les joueurs sont morts
        if (count($alive) === 0 && count($winners) === 0) {
            $game->finish();
            return $this->success([
                'gameOver' => true,
                'reason' => 'all_dead',
                'dead' => array_map(fn($p) => $p->toArray(), $dead)
            ]);
        }

        return $this->success([
            'gameOver' => false
        ]);
    }

    /**
     * Liste toutes les parties (en cours ou terminées)
     */
    public function listGames(): array
    {
        $db = \Trapped\Database\Database::getInstance();

        $stmt = $db->query("
            SELECT
                g.id,
                g.created_at,
                g.current_turn,
                g.status,
                g.starting_points,
                COUNT(DISTINCT p.id) as player_count,
                GROUP_CONCAT(p.name, ', ') as player_names
            FROM games g
            LEFT JOIN players p ON p.game_id = g.id
            WHERE g.status IN ('playing', 'finished')
            GROUP BY g.id
            ORDER BY g.created_at DESC
            LIMIT 50
        ");

        $games = [];
        while ($row = $stmt->fetch()) {
            $games[] = [
                'id' => (int) $row['id'],
                'createdAt' => $row['created_at'],
                'currentTurn' => (int) $row['current_turn'],
                'status' => $row['status'],
                'startingPoints' => (int) $row['starting_points'],
                'playerCount' => (int) $row['player_count'],
                'playerNames' => $row['player_names']
            ];
        }

        return $this->success($games);
    }

    /**
     * Supprime une partie et toutes ses données associées
     */
    public function deleteGame(int $gameId): array
    {
        $db = \Trapped\Database\Database::getInstance();

        // Vérifier que la partie existe
        $stmt = $db->prepare("SELECT id FROM games WHERE id = ?");
        $stmt->execute([$gameId]);
        if (!$stmt->fetch()) {
            return $this->error('Partie introuvable');
        }

        // Supprimer la partie (les cascades supprimeront automatiquement les données liées)
        $stmt = $db->prepare("DELETE FROM games WHERE id = ?");
        if ($stmt->execute([$gameId])) {
            return $this->success(['message' => 'Partie supprimée avec succès']);
        }

        return $this->error('Impossible de supprimer la partie');
    }

    /**
     * Réponse de succès
     */
    private function success(mixed $data): array
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Réponse d'erreur
     */
    private function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
