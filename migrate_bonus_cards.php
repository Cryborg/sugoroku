<?php

require_once __DIR__ . '/autoload.php';

use Trapped\Database\Database;

echo "=== Migration - Cartes bonus ===\n\n";

try {
    $db = Database::getInstance();

    echo "1. Vérification si la table player_bonus_cards existe déjà...\n";

    // Vérifier si la table existe (spécifique à SQLite)
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='player_bonus_cards'");
    $exists = $result->fetch();

    if ($exists) {
        echo "   ⚠️  La table player_bonus_cards existe déjà. Migration ignorée.\n\n";
        exit(0);
    }

    echo "2. Création de la table player_bonus_cards...\n";
    $sql = file_get_contents(__DIR__ . '/database/schema_bonus_cards.sql');
    $db->exec($sql);
    echo "   ✓ Table player_bonus_cards créée\n";

    echo "\n✅ Migration réussie !\n";
    echo "Le système de cartes bonus est maintenant actif.\n\n";
    echo "📋 Cartes disponibles :\n";
    echo "   - ⚡ Points temporaires : +3 points temporaires\n";
    echo "   - 🎲 Dé garanti : minimum floor(nb_joueurs/2) sur tous les dés\n";
    echo "   - 👁️  Vision des bonus : révèle les bonus/malus de bonheur\n";
    echo "   - 🔄 Double jeu : jouer deux fois dans le même tour\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur lors de la migration :\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
