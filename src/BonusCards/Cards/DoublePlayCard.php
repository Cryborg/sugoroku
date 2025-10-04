<?php

namespace Trapped\BonusCards\Cards;

use Trapped\BonusCards\AbstractBonusCard;

/**
 * Carte : Double jeu
 * Permet de jouer deux fois dans le même tour
 */
class DoublePlayCard extends AbstractBonusCard
{
    protected string $id = 'double_play';
    protected string $name = 'Double jeu';
    protected string $description = 'Te permet de jouer deux fois dans le même tour. Tu peux choisir deux portes différentes ou la même deux fois.';
    protected string $icon = '🔄';
    protected string $type = 'active';

    public function canUse(array $context): bool
    {
        // Peut être utilisé au début d'un tour
        $hasChosen = $context['has_chosen'] ?? false;
        return !$hasChosen;
    }

    public function apply(array $context): array
    {
        $playerId = $context['player_id'] ?? null;
        $turnNumber = $context['turn_number'] ?? null;

        if (!$playerId || !$turnNumber) {
            return [
                'success' => false,
                'message' => 'Joueur ou tour non spécifié'
            ];
        }

        return [
            'success' => true,
            'message' => 'Tu peux jouer deux fois ce tour !',
            'effects' => [
                'type' => 'double_play',
                'player_id' => $playerId,
                'turn_number' => $turnNumber,
                'extra_plays' => 1 // Permet 1 choix supplémentaire
            ]
        ];
    }
}
