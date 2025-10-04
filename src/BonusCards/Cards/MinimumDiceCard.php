<?php

namespace Trapped\BonusCards\Cards;

use Trapped\BonusCards\AbstractBonusCard;

/**
 * Carte : Dé garanti
 * Assure d'avoir au moins floor(nbPlayers/2) à chaque dé
 */
class MinimumDiceCard extends AbstractBonusCard
{
    protected string $id = 'minimum_dice';
    protected string $name = 'Dé garanti';
    protected string $description = 'Assure un résultat minimum de floor(nb_joueurs/2) sur tous les dés pendant un tour.';
    protected string $icon = '🎲';
    protected string $type = 'passive';

    public function canUse(array $context): bool
    {
        // Peut être utilisé au début d'un tour
        return true;
    }

    public function apply(array $context): array
    {
        $playerId = $context['player_id'] ?? null;
        $playerCount = $context['player_count'] ?? 0;

        if (!$playerId) {
            return [
                'success' => false,
                'message' => 'Joueur non spécifié'
            ];
        }

        $minimumDice = floor($playerCount / 2);

        return [
            'success' => true,
            'message' => "Dé garanti : minimum {$minimumDice} sur tous les dés",
            'effects' => [
                'type' => 'minimum_dice',
                'player_id' => $playerId,
                'minimum_value' => $minimumDice,
                'duration' => 'current_turn'
            ]
        ];
    }
}
