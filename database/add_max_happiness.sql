-- Ajout du champ max_happiness à la table players

-- Vérifier si la colonne existe déjà
ALTER TABLE players ADD COLUMN max_happiness INTEGER DEFAULT 0;

-- Initialiser max_happiness avec la valeur actuelle de happiness pour les joueurs existants
UPDATE players SET max_happiness = happiness WHERE max_happiness < happiness;
