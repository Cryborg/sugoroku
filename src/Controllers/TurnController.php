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
                    error_log("Rolled dice for door {$door->id} (room {$room->id}): {$door->diceResult} (D{$numberOfPlayers})");
                }
            }
        }

        return true;
    }

    /**
     * Résout le tour actuel (après expiration du timer)
     */
    public function resolveTurn(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        if ($game->status !== 'playing') {
            return $this->error('La partie n\'est pas en cours');
        }

        $turnNumber = $game->currentTurn;

        error_log("=== RESOLUTION TOUR $turnNumber ===");

        // Récupérer toutes les salles avec des joueurs
        $rooms = $game->getRooms();
        $movements = [];
        $blockedPlayers = [];

        foreach ($rooms as $room) {
            $players = $room->getPlayers();

            if (count($players) === 0) {
                continue;
            }

            // Récupérer les portes de cette salle
            $doors = $room->getDoors();

            foreach ($doors as $door) {
                // Résoudre le passage pour cette porte
                $result = $door->resolvePassage($turnNumber);

                error_log("Door {$door->id} ({$door->direction}) - Passed: " . count($result['passed']) . " - Blocked: " . count($result['blocked']) . " - Open: " . ($door->isOpen() ? 'yes' : 'no'));

                if (count($result['passed']) > 0) {
                    // Déplacer les joueurs qui passent vers la nouvelle salle
                    $targetRoom = $room->getAdjacentRoom($door->direction);

                    if ($targetRoom) {
                        foreach ($result['passed'] as $player) {
                            error_log("Moving player {$player->name} from room {$room->id} to room {$targetRoom->id}");
                            $player->moveToRoom($targetRoom->id);
                            $player->updateStatus('alive');

                            $movements[] = [
                                'playerId' => $player->id,
                                'playerName' => $player->name,
                                'fromRoom' => $room->id,
                                'toRoom' => $targetRoom->id,
                                'doorId' => $door->id,
                                'direction' => $door->direction
                            ];
                        }

                        // Marquer la salle comme visitée
                        $targetRoom->markAsVisited();

                        // Appliquer le coût de la salle aux joueurs présents
                        $targetRoom->applyRoomCost();
                    }
                }

                if (count($result['blocked']) > 0) {
                    // Marquer les joueurs bloqués
                    foreach ($result['blocked'] as $player) {
                        error_log("Blocking player {$player->name}");
                        $player->updateStatus('blocked');
                        $blockedPlayers[] = [
                            'playerId' => $player->id,
                            'playerName' => $player->name
                        ];
                    }
                }
            }

            // Appliquer le coût de la salle actuelle aux joueurs qui n'ont pas bougé
            $room->applyRoomCost();
        }

        // Réinitialiser toutes les portes (fermer et relancer les dés)
        foreach ($rooms as $room) {
            $doors = $room->getDoors();
            foreach ($doors as $door) {
                $door->reset();
            }
        }

        // Passer au tour suivant
        if ($game->currentTurn < 15) {
            $game->nextTurn();
            // Lancer les dés pour le nouveau tour
            $this->rollDiceForAllRooms($gameId, $game->currentTurn);
        } else {
            $game->finish();
        }

        error_log("Total movements: " . count($movements));
        error_log("Total blocked: " . count($blockedPlayers));

        return $this->success([
            'turnResolved' => $turnNumber,
            'movements' => $movements,
            'blockedPlayers' => $blockedPlayers,
            'nextTurn' => $game->currentTurn,
            'gameStatus' => $game->status
        ]);
    }

    /**
     * Vérifie si le timer du tour est expiré et résout si nécessaire
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
            // Résoudre automatiquement le tour
            return $this->resolveTurn($gameId);
        }

        return $this->success([
            'timerExpired' => false,
            'remainingTime' => $game->getRemainingTime()
        ]);
    }

    /**
     * Force la résolution du tour (pour les tests ou si les joueurs sont prêts)
     */
    public function forceResolve(int $gameId): array
    {
        return $this->resolveTurn($gameId);
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

        error_log("=== PASSAGE AU TOUR $newTurn ===");

        // Marquer toutes les salles occupées comme visitées
        $players = $game->getPlayers();
        $rooms = $game->getRooms();

        foreach ($rooms as $room) {
            // Vérifier si un joueur est dans cette salle
            foreach ($players as $player) {
                if ($player->currentRoomId === $room->id && !$room->isVisited) {
                    $room->markAsVisited();
                    error_log("Room {$room->id} marked as visited");
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

        error_log("Tour $newTurn démarré");

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
