<?php

require_once __DIR__ . '/autoload.php';

use Trapped\Database\Database;

echo "=== Migration de la base de données ===\n\n";

try {
    $db = Database::getInstance();

    echo "Lecture du script de migration...\n";
    $sql = file_get_contents(__DIR__ . '/database/migrate_player_choices.sql');

    echo "Exécution de la migration...\n";
    $db->exec($sql);

    echo "\n✅ Migration réussie !\n";
    echo "La table player_choices accepte maintenant door_id NULL.\n";
    echo "Les joueurs peuvent maintenant choisir de rester dans une salle.\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur lors de la migration :\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
