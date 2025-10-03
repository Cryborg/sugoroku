-- Ajout des champs gender et avatar à la table players
ALTER TABLE players ADD COLUMN gender VARCHAR(10) DEFAULT 'male';
ALTER TABLE players ADD COLUMN avatar VARCHAR(50) DEFAULT 'male_01.png';
