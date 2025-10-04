<?php

namespace Trapped\BonusCards;

/**
 * Classe abstraite pour toutes les cartes bonus
 * Pattern Strategy : chaque carte est isolée et indépendante
 */
abstract class AbstractBonusCard
{
    protected string $id;
    protected string $name;
    protected string $description;
    protected string $icon;
    protected string $type; // 'instant', 'passive', 'active'

    /**
     * Retourne l'ID unique de la carte
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Retourne le nom de la carte
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retourne la description de la carte
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Retourne l'icône de la carte (emoji ou classe CSS)
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Retourne le type de la carte
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Vérifie si la carte peut être utilisée dans le contexte actuel
     */
    abstract public function canUse(array $context): bool;

    /**
     * Applique l'effet de la carte
     * Retourne un tableau avec le résultat de l'application
     */
    abstract public function apply(array $context): array;

    /**
     * Annule l'effet de la carte si possible (pour les effets temporaires)
     */
    public function revert(array $context): array
    {
        return [
            'success' => true,
            'message' => 'Aucun effet à annuler'
        ];
    }

    /**
     * Retourne les données de la carte pour l'API
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'type' => $this->type
        ];
    }
}
