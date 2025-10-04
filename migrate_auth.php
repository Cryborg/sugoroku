<?php

require_once __DIR__ . '/autoload.php';

use Trapped\Database\Database;

echo "=== Migration - Système d'authentification ===\n\n";

try {
    $db = Database::getInstance();

    echo "⚠️  ATTENTION : Cette migration va recréer complètement la base de données.\n";
    echo "Toutes les données existantes seront PERDUES.\n\n";
    echo "Appuyez sur Entrée pour continuer ou Ctrl+C pour annuler...\n";
    fgets(STDIN);

    echo "\n1. Suppression de l'ancienne base de données...\n";
    $tables = ['player_choices', 'doors', 'rooms', 'players', 'games', 'user_favorite_players', 'users'];

    foreach ($tables as $table) {
        try {
            $db->exec("DROP TABLE IF EXISTS $table");
            echo "   ✓ Table $table supprimée\n";
        } catch (Exception $e) {
            echo "   ⚠  Erreur lors de la suppression de $table : " . $e->getMessage() . "\n";
        }
    }

    echo "\n2. Création des tables d'authentification...\n";
    $authSql = file_get_contents(__DIR__ . '/database/schema_auth.sql');
    $db->exec($authSql);
    echo "   ✓ Tables users et user_favorite_players créées\n";

    echo "\n3. Création des tables de jeu...\n";
    $gameSql = file_get_contents(__DIR__ . '/database/schema.sql');
    $db->exec($gameSql);
    echo "   ✓ Tables games, players, rooms, doors, player_choices créées\n";

    echo "\n✅ Migration réussie !\n";
    echo "Le système d'authentification est maintenant actif.\n";
    echo "N'oubliez pas d'exécuter 'php seed_admin.php' pour créer le compte admin.\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur lors de la migration :\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
