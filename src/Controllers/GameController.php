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
    public function create(array $playerNames): array
    {
        if (count($playerNames) < 3 || count($playerNames) > 8) {
            return $this->error('Le nombre de joueurs doit être entre 3 et 8');
        }

        $game = new Game();
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
        foreach ($playerNames as $name) {
            $player = new Player();
            $player->gameId = $game->id;
            $player->name = trim($name);
            $player->currentRoomId = $startRoom->id;

            if (!$player->create()) {
                return $this->error("Impossible de créer le joueur {$name}");
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
        $playersInExit = [];
        $alive = [];
        $dead = [];

        foreach ($players as $player) {
            if ($player->points <= 0 || $player->status === 'dead') {
                $dead[] = $player;
                continue;
            }

            if ($player->currentRoomId === $exitRoom->id && $player->points >= 1) {
                $playersInExit[] = $player;
            } else {
                $alive[] = $player;
            }
        }

        // Victoire uniquement si TOUS les joueurs vivants sont dans la sortie
        if (count($playersInExit) > 0 && count($alive) === 0) {
            // Marquer tous les joueurs dans la sortie comme gagnants
            foreach ($playersInExit as $player) {
                $player->updateStatus('winner');
            }

            $game->finish();
            return $this->success([
                'gameOver' => true,
                'reason' => 'victory',
                'winners' => array_map(fn($p) => $p->toArray(), $playersInExit),
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
        if (count($alive) === 0 && count($playersInExit) === 0) {
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
