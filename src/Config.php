<?php

namespace Trapped;

/**
 * Configuration globale du jeu Future Sugoroku
 */
class Config
{
    // Configuration du plateau
    public const GRID_SIZE = 5;             // Taille de la grille (5x5)

    // Configuration des joueurs
    public const PLAYER_STARTING_POINTS = 15;  // Points de départ de chaque joueur
    public const PLAYER_MIN_POINTS = 0;        // Points minimum (mort si atteint)

    // Configuration des tours
    public const MAX_TURNS = 15;               // Nombre maximum de tours
    public const TURN_TIMER_SECONDS = 600;     // Durée d'un tour en secondes (10 minutes)

    // Configuration du dé
    public const DEFAULT_DICE_FACES = 10;      // Nombre de faces par défaut si pas de joueurs
}
