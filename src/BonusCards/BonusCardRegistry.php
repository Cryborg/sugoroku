<?php

namespace Trapped\BonusCards;

use Trapped\BonusCards\Cards\TemporaryPointsCard;
use Trapped\BonusCards\Cards\MinimumDiceCard;
use Trapped\BonusCards\Cards\RevealBonusCard;
use Trapped\BonusCards\Cards\DoublePlayCard;

/**
 * Registry pour toutes les cartes bonus
 * Pattern Registry : centralise l'enregistrement et la récupération des cartes
 *
 * AJOUT D'UNE NOUVELLE CARTE :
 * 1. Créer une classe dans /src/BonusCards/Cards/ qui extends AbstractBonusCard
 * 2. Implémenter canUse() et apply()
 * 3. L'ajouter dans registerCards() ci-dessous
 * C'est tout ! Aucun autre fichier à modifier.
 */
class BonusCardRegistry
{
    private static ?BonusCardRegistry $instance = null;
    private array $cards = [];

    private function __construct()
    {
        $this->registerCards();
    }

    public static function getInstance(): BonusCardRegistry
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Enregistre toutes les cartes disponibles
     * AJOUTER ICI les nouvelles cartes bonus
     */
    private function registerCards(): void
    {
        $this->register(new TemporaryPointsCard());
        $this->register(new MinimumDiceCard());
        $this->register(new RevealBonusCard());
        $this->register(new DoublePlayCard());
    }

    /**
     * Enregistre une carte
     */
    private function register(AbstractBonusCard $card): void
    {
        $this->cards[$card->getId()] = $card;
    }

    /**
     * Récupère une carte par son ID
     */
    public function getCard(string $cardId): ?AbstractBonusCard
    {
        return $this->cards[$cardId] ?? null;
    }

    /**
     * Récupère toutes les cartes disponibles
     */
    public function getAllCards(): array
    {
        return $this->cards;
    }

    /**
     * Récupère toutes les cartes pour l'API
     */
    public function getAllCardsData(): array
    {
        $data = [];
        foreach ($this->cards as $card) {
            $data[] = $card->toArray();
        }
        return $data;
    }

    /**
     * Vérifie si une carte existe
     */
    public function exists(string $cardId): bool
    {
        return isset($this->cards[$cardId]);
    }
}
