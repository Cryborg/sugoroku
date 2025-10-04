<?php

namespace Trapped\BonusCards;

use PDO;
use Trapped\Database\Database;

/**
 * Gestionnaire des cartes bonus des joueurs
 * Gère l'attribution, l'utilisation et le stockage des cartes
 */
class PlayerCardManager
{
    private PDO $db;
    private BonusCardRegistry $registry;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->registry = BonusCardRegistry::getInstance();
    }

    /**
     * Donne une carte à un joueur
     */
    public function giveCard(int $playerId, string $cardId): bool
    {
        if (!$this->registry->exists($cardId)) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO player_bonus_cards (player_id, card_id, used)
            VALUES (?, ?, 0)
        ");

        return $stmt->execute([$playerId, $cardId]);
    }

    /**
     * Récupère toutes les cartes d'un joueur
     */
    public function getPlayerCards(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, card_id, used, used_at
            FROM player_bonus_cards
            WHERE player_id = ?
            ORDER BY id ASC
        ");
        $stmt->execute([$playerId]);

        $cards = [];
        while ($row = $stmt->fetch()) {
            $card = $this->registry->getCard($row['card_id']);
            if ($card) {
                $cards[] = [
                    'id' => $row['id'],
                    'card_id' => $row['card_id'],
                    'used' => (bool) $row['used'],
                    'used_at' => $row['used_at'],
                    'card_data' => $card->toArray()
                ];
            }
        }

        return $cards;
    }

    /**
     * Récupère les cartes non utilisées d'un joueur
     */
    public function getAvailableCards(int $playerId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, card_id
            FROM player_bonus_cards
            WHERE player_id = ? AND used = 0
            ORDER BY id ASC
        ");
        $stmt->execute([$playerId]);

        $cards = [];
        while ($row = $stmt->fetch()) {
            $card = $this->registry->getCard($row['card_id']);
            if ($card) {
                $cards[] = [
                    'id' => $row['id'],
                    'card_id' => $row['card_id'],
                    'card_data' => $card->toArray()
                ];
            }
        }

        return $cards;
    }

    /**
     * Utilise une carte
     */
    public function useCard(int $playerCardId, int $playerId, array $context): array
    {
        // Vérifier que la carte appartient au joueur et n'est pas utilisée
        $stmt = $this->db->prepare("
            SELECT card_id, used
            FROM player_bonus_cards
            WHERE id = ? AND player_id = ?
        ");
        $stmt->execute([$playerCardId, $playerId]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'success' => false,
                'message' => 'Carte non trouvée'
            ];
        }

        if ($row['used']) {
            return [
                'success' => false,
                'message' => 'Carte déjà utilisée'
            ];
        }

        // Récupérer la carte
        $card = $this->registry->getCard($row['card_id']);
        if (!$card) {
            return [
                'success' => false,
                'message' => 'Carte invalide'
            ];
        }

        // Vérifier si la carte peut être utilisée
        if (!$card->canUse($context)) {
            return [
                'success' => false,
                'message' => 'Impossible d\'utiliser cette carte maintenant'
            ];
        }

        // Appliquer l'effet de la carte
        $result = $card->apply($context);

        if ($result['success']) {
            // Marquer la carte comme utilisée
            $stmt = $this->db->prepare("
                UPDATE player_bonus_cards
                SET used = 1, used_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$playerCardId]);
        }

        return $result;
    }

    /**
     * Annule l'effet d'une carte si possible
     */
    public function revertCard(int $playerCardId, int $playerId, array $context): array
    {
        $stmt = $this->db->prepare("
            SELECT card_id
            FROM player_bonus_cards
            WHERE id = ? AND player_id = ? AND used = 1
        ");
        $stmt->execute([$playerCardId, $playerId]);
        $row = $stmt->fetch();

        if (!$row) {
            return [
                'success' => false,
                'message' => 'Carte non trouvée ou non utilisée'
            ];
        }

        $card = $this->registry->getCard($row['card_id']);
        if (!$card) {
            return [
                'success' => false,
                'message' => 'Carte invalide'
            ];
        }

        return $card->revert($context);
    }

    /**
     * Compte le nombre de cartes disponibles d'un joueur
     */
    public function countAvailableCards(int $playerId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM player_bonus_cards
            WHERE player_id = ? AND used = 0
        ");
        $stmt->execute([$playerId]);
        $result = $stmt->fetch();

        return $result ? (int) $result['count'] : 0;
    }
}
