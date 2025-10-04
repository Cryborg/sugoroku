<?php

require_once __DIR__ . '/autoload.php';

use Trapped\Database\Database;

echo "=== Migration - Ajout du champ max_happiness ===\n\n";

try {
    $db = Database::getInstance();

    echo "Vérification si la colonne max_happiness existe déjà...\n";

    // Vérifier si la colonne existe (spécifique à SQLite)
    $result = $db->query("PRAGMA table_info(players)");
    $columns = $result->fetchAll(PDO::FETCH_ASSOC);
    $columnExists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'max_happiness') {
            $columnExists = true;
            break;
        }
    }

    if ($columnExists) {
        echo "⚠️  La colonne max_happiness existe déjà. Migration ignorée.\n\n";
        exit(0);
    }

    echo "Ajout de la colonne max_happiness...\n";
    $db->exec("ALTER TABLE players ADD COLUMN max_happiness INTEGER DEFAULT 0");

    echo "Initialisation des valeurs existantes...\n";
    $db->exec("UPDATE players SET max_happiness = happiness WHERE max_happiness < happiness");

    echo "\n✅ Migration réussie !\n";
    echo "Le champ max_happiness a été ajouté à la table players.\n";
    echo "Les valeurs existantes ont été initialisées avec le bonheur actuel.\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur lors de la migration :\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
