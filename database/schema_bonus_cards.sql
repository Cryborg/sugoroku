-- Table des cartes bonus des joueurs
CREATE TABLE IF NOT EXISTS player_bonus_cards (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    card_id VARCHAR(50) NOT NULL,
    used BOOLEAN DEFAULT 0,
    used_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
);

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_player_bonus_cards_player ON player_bonus_cards(player_id);
CREATE INDEX IF NOT EXISTS idx_player_bonus_cards_used ON player_bonus_cards(player_id, used);
