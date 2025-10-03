<?php

namespace Trapped\Database;

use PDO;
use PDOException;

/**
 * Classe Database - Gestion de la connexion PDO avec abstraction SQLite/MySQL
 * Principe SOLID : Single Responsibility - gère uniquement la connexion DB
 */
class Database
{
    private static ?PDO $instance = null;
    private array $config;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../../config/database.php';
        $this->connect();
    }

    /**
     * Singleton pour avoir une seule instance de connexion
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            new self();
        }
        return self::$instance;
    }

    /**
     * Connexion à la base selon le driver configuré
     */
    private function connect(): void
    {
        try {
            $driver = $this->config['driver'];

            if ($driver === 'sqlite') {
                $this->connectSqlite();
            } elseif ($driver === 'mysql') {
                $this->connectMysql();
            } else {
                throw new PDOException("Driver de base de données non supporté: {$driver}");
            }

            self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Initialiser le schéma si nécessaire
            $this->initializeSchema();

        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données: " . $e->getMessage());
        }
    }

    /**
     * Connexion SQLite
     */
    private function connectSqlite(): void
    {
        $dbPath = $this->config['sqlite']['path'];
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $dsn = "sqlite:{$dbPath}";
        self::$instance = new PDO($dsn);
    }

    /**
     * Connexion MySQL
     */
    private function connectMysql(): void
    {
        $cfg = $this->config['mysql'];
        $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']};charset={$cfg['charset']}";

        self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}"
        ]);
    }

    /**
     * Initialise le schéma si les tables n'existent pas
     */
    private function initializeSchema(): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';

        if (!file_exists($schemaFile)) {
            return;
        }

        // Vérifier si les tables existent déjà
        $stmt = self::$instance->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='games'"
        );

        if ($stmt->fetch() === false) {
            $schema = file_get_contents($schemaFile);
            self::$instance->exec($schema);
        }
    }

    /**
     * Empêcher le clonage
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
