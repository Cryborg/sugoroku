<?php

namespace Trapped\BonusCards\Cards;

use Trapped\BonusCards\AbstractBonusCard;

/**
 * Carte : Vision des bonus
 * Permet de voir les bonus/malus de bonheur d'une salle avant d'y entrer
 */
class RevealBonusCard extends AbstractBonusCard
{
    protected string $id = 'reveal_bonus';
    protected string $name = 'Vision des bonus';
    protected string $description = 'Révèle tous les bonus/malus de bonheur de la salle actuelle avant de faire ton choix.';
    protected string $icon = '👁️';
    protected string $type = 'active';

    public function canUse(array $context): bool
    {
        // Peut être utilisé avant de choisir une porte
        $hasChosen = $context['has_chosen'] ?? false;
        return !$hasChosen;
    }

    public function apply(array $context): array
    {
        $playerId = $context['player_id'] ?? null;
        $roomId = $context['room_id'] ?? null;

        if (!$playerId || !$roomId) {
            return [
                'success' => false,
                'message' => 'Joueur ou salle non spécifié'
            ];
        }

        return [
            'success' => true,
            'message' => 'Bonus/malus révélés pour cette salle',
            'effects' => [
                'type' => 'reveal_bonus',
                'player_id' => $playerId,
                'room_id' => $roomId,
                'reveal_happiness' => true
            ]
        ];
    }
}
