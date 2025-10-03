-- Migration pour permettre door_id NULL dans player_choices
-- Permet aux joueurs de choisir de rester dans une salle

-- Créer une nouvelle table temporaire avec la bonne structure
CREATE TABLE IF NOT EXISTS player_choices_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    door_id INTEGER NULL,
    turn_number INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (door_id) REFERENCES doors(id) ON DELETE CASCADE,
    UNIQUE(player_id, turn_number)
);

-- Copier les données de l'ancienne table vers la nouvelle
INSERT INTO player_choices_new (id, player_id, door_id, turn_number, created_at)
SELECT id, player_id, door_id, turn_number, created_at FROM player_choices;

-- Supprimer l'ancienne table
DROP TABLE player_choices;

-- Renommer la nouvelle table
ALTER TABLE player_choices_new RENAME TO player_choices;

-- Recréer les index
CREATE INDEX IF NOT EXISTS idx_choices_player ON player_choices(player_id);
CREATE INDEX IF NOT EXISTS idx_choices_turn ON player_choices(turn_number);
