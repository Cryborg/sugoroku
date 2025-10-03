<?php

namespace Trapped\Controllers;

use Trapped\Models\Game;
use Trapped\Models\Player;
use Trapped\Models\Door;

/**
 * PlayerController - Gestion des actions des joueurs
 */
class PlayerController
{
    /**
     * Ouvre une porte (un joueur volontaire paie 1 point)
     * Enregistre automatiquement le choix ET déplace immédiatement le joueur
     */
    public function openDoor(int $playerId, int $doorId): array
    {
        $player = new Player();
        if (!$player->load($playerId)) {
            return $this->error('Joueur introuvable');
        }

        if (!$player->isAlive()) {
            return $this->error('Le joueur est mort ou ne peut pas jouer');
        }

        if ($player->points < 1) {
            return $this->error('Le joueur n\'a pas assez de points pour ouvrir cette porte');
        }

        $door = new Door();
        if (!$door->load($doorId)) {
            return $this->error('Porte introuvable');
        }

        if ($door->isOpen()) {
            return $this->error('Cette porte est déjà ouverte');
        }

        // Récupérer le tour actuel
        $game = new Game();
        if (!$game->load($player->gameId)) {
            return $this->error('Partie introuvable');
        }

        if ($door->open($playerId)) {
            // Enregistrer automatiquement le choix de cette porte
            $player->choiceDoor($doorId, $game->currentTurn);

            // Recharger le joueur pour avoir les points à jour après l'ouverture
            $player->load($playerId);

            // Exécuter immédiatement le déplacement
            $result = $this->resolvePlayerMovement($player, $door);

            return $this->success([
                'message' => 'Porte ouverte - ' . $result['message'],
                'doorId' => $doorId,
                'playerId' => $playerId,
                'moved' => $result['moved'],
                'newRoomId' => $result['newRoomId'] ?? null,
                'blocked' => $result['blocked'] ?? false
            ]);
        }

        return $this->error('Impossible d\'ouvrir la porte');
    }

    /**
     * Enregistre le choix de porte d'un joueur ET exécute immédiatement le déplacement
     */
    public function chooseDoor(int $playerId, int $doorId): array
    {
        $player = new Player();
        if (!$player->load($playerId)) {
            return $this->error('Joueur introuvable');
        }

        if (!$player->isAlive() && !$player->isBlocked()) {
            return $this->error('Le joueur est mort ou ne peut pas jouer');
        }

        $door = new Door();
        if (!$door->load($doorId)) {
            return $this->error('Porte introuvable');
        }

        if (!$door->isOpen()) {
            return $this->error('Cette porte n\'est pas ouverte');
        }

        // Récupérer le tour actuel
        $game = new Game();
        if (!$game->load($player->gameId)) {
            return $this->error('Partie introuvable');
        }

        // Enregistrer le choix
        if (!$player->choiceDoor($doorId, $game->currentTurn)) {
            return $this->error('Impossible d\'enregistrer le choix');
        }

        // Exécuter immédiatement le déplacement
        $result = $this->resolvePlayerMovement($player, $door);

        return $this->success([
            'message' => $result['message'],
            'playerId' => $playerId,
            'doorId' => $doorId,
            'moved' => $result['moved'],
            'newRoomId' => $result['newRoomId'] ?? null,
            'blocked' => $result['blocked'] ?? false
        ]);
    }

    /**
     * Enregistre que le joueur choisit de rester dans la salle actuelle
     */
    public function stayInRoom(int $playerId): array
    {
        $player = new Player();
        if (!$player->load($playerId)) {
            return $this->error('Joueur introuvable');
        }

        if (!$player->isAlive() && !$player->isBlocked()) {
            return $this->error('Le joueur est mort ou ne peut pas jouer');
        }

        // Récupérer le tour actuel
        $game = new Game();
        if (!$game->load($player->gameId)) {
            return $this->error('Partie introuvable');
        }

        // Enregistrer un choix avec doorId = null (reste ici)
        if ($player->choiceDoor(null, $game->currentTurn)) {
            return $this->success([
                'message' => 'Le joueur reste dans la salle',
                'playerId' => $playerId,
                'doorId' => null,
                'moved' => false,
                'stayed' => true
            ]);
        }

        return $this->error('Impossible d\'enregistrer le choix');
    }

    /**
     * Résout le mouvement d'un joueur à travers une porte (immédiat, premier arrivé premier servi)
     */
    private function resolvePlayerMovement(Player $player, Door $door): array
    {
        // Récupérer le jeu pour avoir le tour actuel
        $game = new \Trapped\Models\Game();
        $game->load($player->gameId);

        // D'abord récupérer la salle actuelle du joueur
        $currentRoom = new \Trapped\Models\Room();
        if (!$currentRoom->load($player->currentRoomId)) {
            return ['moved' => false, 'message' => 'Salle actuelle introuvable'];
        }

        // Obtenir la salle cible selon la direction de la porte
        $targetRoom = $currentRoom->getAdjacentRoom($door->direction);
        if (!$targetRoom) {
            return ['moved' => false, 'message' => 'Salle cible introuvable'];
        }

        // Vérifier combien de joueurs sont déjà passés par cette porte ce tour
        $playersWhoChose = $door->getPlayerChoices($game->currentTurn);
        $alreadyPassedCount = 0;

        foreach ($playersWhoChose as $otherPlayer) {
            if ($otherPlayer->id === $player->id) continue; // Ne pas compter le joueur actuel

            // Vérifier si ce joueur est déjà dans la salle cible (= il est passé)
            if ($otherPlayer->currentRoomId === $targetRoom->id) {
                $alreadyPassedCount++;
            }
        }

        $capacity = $door->getCapacity();
        $remainingCapacity = $capacity - $alreadyPassedCount;

        if ($remainingCapacity > 0) {
            // Il reste de la place, le joueur passe

            // Déplacer le joueur
            $player->moveToRoom($targetRoom->id);

            // NE PAS marquer la salle comme visitée maintenant
            // On le fera à la fin du tour pour éviter de révéler les infos

            // Le coût de la salle sera appliqué à la fin du tour pour TOUS les joueurs présents
            // (pour que ce soit une surprise)

            return [
                'moved' => true,
                'newRoomId' => $targetRoom->id,
                'message' => "Déplacement vers {$door->direction}"
            ];
        } else {
            // Porte pleine, le joueur est bloqué
            $player->updateStatus('blocked');

            return [
                'moved' => false,
                'blocked' => true,
                'message' => 'Porte pleine ! Joueur bloqué'
            ];
        }
    }

    /**
     * Libère un joueur bloqué
     */
    public function freePlayer(int $liberatorId, int $blockedPlayerId): array
    {
        $liberator = new Player();
        if (!$liberator->load($liberatorId)) {
            return $this->error('Libérateur introuvable');
        }

        if (!$liberator->isAlive()) {
            return $this->error('Le libérateur ne peut pas agir');
        }

        $blocked = new Player();
        if (!$blocked->load($blockedPlayerId)) {
            return $this->error('Joueur bloqué introuvable');
        }

        if (!$blocked->isBlocked()) {
            return $this->error('Ce joueur n\'est pas bloqué');
        }

        // Vérifier que les deux joueurs sont dans la même salle
        if ($liberator->currentRoomId !== $blocked->currentRoomId) {
            return $this->error('Les joueurs ne sont pas dans la même salle');
        }

        // Libérer le joueur (pas de coût supplémentaire selon les règles)
        if ($blocked->updateStatus('alive')) {
            return $this->success([
                'message' => 'Joueur libéré',
                'liberatorId' => $liberatorId,
                'freedPlayerId' => $blockedPlayerId
            ]);
        }

        return $this->error('Impossible de libérer le joueur');
    }

    /**
     * Récupère les choix de tous les joueurs pour le tour actuel
     */
    public function getAllChoices(int $gameId): array
    {
        $game = new Game();
        if (!$game->load($gameId)) {
            return $this->error('Partie introuvable');
        }

        $players = $game->getPlayers();
        $choices = [];

        foreach ($players as $player) {
            $doorId = $player->getChoice($game->currentTurn);
            $choices[] = [
                'playerId' => $player->id,
                'playerName' => $player->name,
                'doorId' => $doorId,
                'hasChosen' => $doorId !== null
            ];
        }

        return $this->success([
            'turn' => $game->currentTurn,
            'choices' => $choices
        ]);
    }

    /**
     * Permet à un joueur d'abandonner la partie
     * Disponible uniquement si au moins un joueur est dans la sortie
     */
    public function giveUp(int $playerId): array
    {
        $player = new Player();
        if (!$player->load($playerId)) {
            return $this->error('Joueur introuvable');
        }

        if ($player->status !== 'alive') {
            return $this->error('Ce joueur ne peut pas abandonner');
        }

        // Vérifier qu'au moins un joueur est dans la sortie
        $game = new \Trapped\Models\Game();
        if (!$game->load($player->gameId)) {
            return $this->error('Partie introuvable');
        }

        $rooms = $game->getRooms();
        $exitRoom = null;
        foreach ($rooms as $room) {
            if ($room->isExit) {
                $exitRoom = $room;
                break;
            }
        }

        if (!$exitRoom) {
            return $this->error('Salle de sortie introuvable');
        }

        // Vérifier qu'au moins un joueur est dans la sortie
        $players = $game->getPlayers();
        $someoneInExit = false;

        foreach ($players as $p) {
            if ($p->currentRoomId === $exitRoom->id && $p->points >= 1 && $p->status === 'alive') {
                $someoneInExit = true;
                break;
            }
        }

        if (!$someoneInExit) {
            return $this->error('Aucun joueur n\'est encore arrivé. Vous ne pouvez pas abandonner maintenant.');
        }

        // Marquer le joueur comme mort
        $player->updateStatus('dead');

        return $this->success([
            'message' => "{$player->name} a abandonné la partie",
            'playerId' => $playerId
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
