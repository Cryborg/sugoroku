<?php

namespace Trapped\Controllers;

use Trapped\Models\Game;
use Trapped\Models\Player;
use Trapped\Models\Room;
use Trapped\Models\Door;

/**
 * TurnController - Gestion des tours et de la résolution
 */
class TurnController
{
    /**
     * Lance les dés uniquement pour les portes des salles occupées
     */
    public function rollDiceForAllRooms(int $gameId, int $turnNumber): bool
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return false;
        }

        $rooms = $game->getRooms();
        $players = $game->getPlayers();

        // Compter le nombre de joueurs actifs (alive ou blocked)
        $activePlayers = array_filter($players, fn($p) => $p->status === 'alive' || $p->status === 'blocked');
        $numberOfPlayers = count($activePlayers);

        // Identifier les salles occupées
        $occupiedRoomIds = [];
        foreach ($players as $player) {
            if ($player->status === 'alive' && $player->currentRoomId) {
                $occupiedRoomIds[$player->currentRoomId] = true;
            }
        }

        foreach ($rooms as $room) {
            // Ne lancer les dés QUE pour les salles occupées
            if (!isset($occupiedRoomIds[$room->id])) {
                continue;
            }

            $doors = $room->getDoors();
            foreach ($doors as $door) {
                if (!$door->isDiceRolledForTurn($turnNumber)) {
                    $door->rollDice($turnNumber, $numberOfPlayers);
                }
            }
        }

        return true;
    }

    /**
     * Vérifie si le timer du tour est expiré et passe au tour suivant si nécessaire
     */
    public function checkAndResolve(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        if ($game->status !== 'playing') {
            return $this->success([
                'timerExpired' => false,
                'remainingTime' => 0
            ]);
        }

        if ($game->isTurnExpired()) {
            // Passer automatiquement au tour suivant
            $result = $this->nextTurn($gameId);
            if ($result['success']) {
                return $this->success([
                    'timerExpired' => true,
                    'newTurn' => $result['data']['newTurn']
                ]);
            }
            return $result;
        }

        return $this->success([
            'timerExpired' => false,
            'remainingTime' => $game->getRemainingTime()
        ]);
    }

    /**
     * Passe au tour suivant (réinitialise les portes et les choix)
     */
    public function nextTurn(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        if ($game->status !== 'playing') {
            return $this->error('La partie n\'est pas en cours');
        }

        $currentTurn = $game->currentTurn;
        $newTurn = $currentTurn + 1;

        // Marquer toutes les salles occupées comme visitées
        $players = $game->getPlayers();
        $rooms = $game->getRooms();

        foreach ($rooms as $room) {
            // Vérifier si un joueur est dans cette salle
            foreach ($players as $player) {
                if ($player->currentRoomId === $room->id && !$room->isVisited) {
                    $room->markAsVisited();
                    break;
                }
            }
        }

        // Incrémenter le tour
        $game->nextTurn();

        // Réinitialiser toutes les portes
        $rooms = $game->getRooms();
        foreach ($rooms as $room) {
            $doors = $room->getDoors();
            foreach ($doors as $door) {
                $door->reset();
            }
        }

        // Relancer les dés pour toutes les portes
        $this->rollDiceForAllRooms($gameId, $newTurn);

        // Effacer les choix des joueurs du tour précédent se fait automatiquement
        // car on vérifie hasChoice() avec le currentTurn

        return $this->success([
            'message' => 'Passage au tour suivant',
            'previousTurn' => $currentTurn,
            'newTurn' => $newTurn
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
