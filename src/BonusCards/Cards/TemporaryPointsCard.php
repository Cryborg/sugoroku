<?php

namespace Trapped\BonusCards\Cards;

use Trapped\BonusCards\AbstractBonusCard;

/**
 * Carte : Points temporaires
 * Octroie un petit nombre de points temporaires (3 points)
 * Si le joueur n'en a finalement pas besoin, les points sont retirés
 */
class TemporaryPointsCard extends AbstractBonusCard
{
    protected string $id = 'temporary_points';
    protected string $name = 'Points temporaires';
    protected string $description = 'Gagne 3 points temporaires. S\'ils ne sont pas utilisés pour passer une porte, ils sont retirés.';
    protected string $icon = '⚡';
    protected string $type = 'active';

    private const TEMP_POINTS = 3;

    public function canUse(array $context): bool
    {
        // Peut être utilisé à tout moment
        return true;
    }

    public function apply(array $context): array
    {
        $playerId = $context['player_id'] ?? null;

        if (!$playerId) {
            return [
                'success' => false,
                'message' => 'Joueur non spécifié'
            ];
        }

        return [
            'success' => true,
            'message' => '+' . self::TEMP_POINTS . ' points temporaires',
            'effects' => [
                'type' => 'temp_points',
                'player_id' => $playerId,
                'points' => self::TEMP_POINTS,
                'is_temporary' => true
            ]
        ];
    }

    public function revert(array $context): array
    {
        $playerId = $context['player_id'] ?? null;
        $pointsUsed = $context['points_used'] ?? 0;

        if (!$playerId) {
            return [
                'success' => false,
                'message' => 'Joueur non spécifié'
            ];
        }

        // Si les points n'ont pas été utilisés, on les retire
        if ($pointsUsed === 0) {
            return [
                'success' => true,
                'message' => 'Points temporaires retirés (non utilisés)',
                'effects' => [
                    'type' => 'remove_temp_points',
                    'player_id' => $playerId,
                    'points' => -self::TEMP_POINTS
                ]
            ];
        }

        return [
            'success' => true,
            'message' => 'Points temporaires conservés (utilisés)',
            'effects' => []
        ];
    }
}
