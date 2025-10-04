-- Schema pour Future Sugoroku
-- Compatible SQLite et MySQL

-- Table des parties
CREATE TABLE IF NOT EXISTS games (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    current_turn INTEGER DEFAULT 1,
    status VARCHAR(20) DEFAULT 'waiting',
    turn_started_at DATETIME NULL,
    starting_points INTEGER DEFAULT 20,
    free_rooms_enabled BOOLEAN DEFAULT 0,
    CONSTRAINT check_turn CHECK (current_turn >= 1 AND current_turn <= 15),
    CONSTRAINT check_status CHECK (status IN ('waiting', 'playing', 'finished')),
    CONSTRAINT check_starting_points CHECK (starting_points >= 15 AND starting_points <= 25),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Table des joueurs
CREATE TABLE IF NOT EXISTS players (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    points INTEGER DEFAULT 20,
    current_room_id INTEGER NULL,
    status VARCHAR(20) DEFAULT 'alive',
    happiness INTEGER DEFAULT 0,
    happiness_positive INTEGER DEFAULT 0,
    happiness_negative INTEGER DEFAULT 0,
    max_happiness INTEGER DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT check_points CHECK (points >= 0 AND points <= 25),
    CONSTRAINT check_status CHECK (status IN ('alive', 'dead', 'blocked', 'winner')),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

-- Table des pièces/salles
CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    game_id INTEGER NOT NULL,
    position_x INTEGER NOT NULL,
    position_y INTEGER NOT NULL,
    points_cost INTEGER DEFAULT 0,
    door_count INTEGER NOT NULL,
    is_start BOOLEAN DEFAULT 0,
    is_exit BOOLEAN DEFAULT 0,
    is_visited BOOLEAN DEFAULT 0,
    CONSTRAINT check_position_x CHECK (position_x >= 0 AND position_x <= 4),
    CONSTRAINT check_position_y CHECK (position_y >= 0 AND position_y <= 4),
    CONSTRAINT check_points_cost CHECK (points_cost >= 0 AND points_cost <= 8),
    CONSTRAINT check_door_count CHECK (door_count >= 2 AND door_count <= 4),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    UNIQUE(game_id, position_x, position_y)
);

-- Table des portes
CREATE TABLE IF NOT EXISTS doors (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    room_id INTEGER NOT NULL,
    direction VARCHAR(10) NOT NULL,
    dice_result INTEGER NULL,
    current_turn INTEGER NULL,
    opened_by INTEGER NULL,
    happiness_modifier INTEGER DEFAULT 0,
    CONSTRAINT check_direction CHECK (direction IN ('north', 'south', 'east', 'west')),
    CONSTRAINT check_dice_result CHECK (dice_result IS NULL OR (dice_result >= 0 AND dice_result <= 9)),
    CONSTRAINT check_happiness CHECK (happiness_modifier >= -5 AND happiness_modifier <= 5),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    FOREIGN KEY (opened_by) REFERENCES players(id) ON DELETE SET NULL
);

-- Table des choix de porte des joueurs
-- door_id peut être NULL si le joueur choisit de rester dans la salle
CREATE TABLE IF NOT EXISTS player_choices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    player_id INTEGER NOT NULL,
    door_id INTEGER NULL,
    turn_number INTEGER NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (door_id) REFERENCES doors(id) ON DELETE CASCADE,
    UNIQUE(player_id, turn_number)
);

-- Index pour optimiser les requêtes
CREATE INDEX IF NOT EXISTS idx_games_user ON games(user_id);
CREATE INDEX IF NOT EXISTS idx_players_game ON players(game_id);
CREATE INDEX IF NOT EXISTS idx_rooms_game ON rooms(game_id);
CREATE INDEX IF NOT EXISTS idx_doors_room ON doors(room_id);
CREATE INDEX IF NOT EXISTS idx_choices_player ON player_choices(player_id);
CREATE INDEX IF NOT EXISTS idx_choices_turn ON player_choices(turn_number);
