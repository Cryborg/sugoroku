<?php

require_once __DIR__ . '/autoload.php';

use Trapped\Database\Database;

// Charger les variables d'environnement depuis .env
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception("Le fichier .env n'existe pas à : $path");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Ignorer les commentaires
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse la ligne
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            // Définir la variable d'environnement
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

echo "=== Seed - Compte administrateur ===\n\n";

try {
    // Charger le fichier .env
    loadEnv(__DIR__ . '/.env');

    $adminEmail = getenv('ADMIN_EMAIL');
    $adminPassword = getenv('ADMIN_PASSWORD');
    $adminUsername = getenv('ADMIN_USERNAME');

    if (!$adminEmail || !$adminPassword || !$adminUsername) {
        throw new Exception("Les variables ADMIN_EMAIL, ADMIN_PASSWORD et ADMIN_USERNAME doivent être définies dans le fichier .env");
    }

    $db = Database::getInstance();

    echo "1. Vérification si le compte admin existe déjà...\n";
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);

    if ($stmt->fetch()) {
        echo "   ⚠️  Le compte admin existe déjà.\n";
        echo "   Mise à jour du mot de passe et du statut admin...\n";

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            UPDATE users
            SET password_hash = ?, is_admin = 1, username = ?
            WHERE email = ?
        ");
        $stmt->execute([$passwordHash, $adminUsername, $adminEmail]);

        echo "   ✓ Compte admin mis à jour\n";
    } else {
        echo "   Création du compte admin...\n";

        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, username, is_admin)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$adminEmail, $passwordHash, $adminUsername]);

        echo "   ✓ Compte admin créé\n";
    }

    echo "\n✅ Seed réussi !\n";
    echo "Compte administrateur :\n";
    echo "   Email: $adminEmail\n";
    echo "   Username: $adminUsername\n";
    echo "   Statut: Admin\n\n";

} catch (Exception $e) {
    echo "\n❌ Erreur lors du seed :\n";
    echo $e->getMessage() . "\n\n";
    exit(1);
}
