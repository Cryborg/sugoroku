# 📋 Cahier des charges - Future Sugoroku (POC Web)

## 🎯 Objectif du projet
Créer un POC jouable en navigateur du jeu "Future Sugoroku" vu dans Alice in Borderland, respectant les principes **DRY, KISS et SOLID**.

---

## 🎨 Interface & UX

### Thème
- **Dark mode obligatoire** (palette sombre, contrastes confortables)
- Design minimaliste et lisible
- Responsive (au moins desktop + tablette)

### Écrans principaux

#### 1. **Écran d'enregistrement des joueurs**
- Formulaire pour ajouter/supprimer des joueurs (3-8 joueurs)
- Champs : nom du joueur (obligatoire)
- Liste des joueurs enregistrés avec possibilité de retirer
- Bouton "Démarrer la partie" (actif si ≥ 3 joueurs)

#### 2. **Écran de jeu**
- **Plateau 5×5** (25 pièces) affiché visuellement
- **Marqueur de tour** (actuel / 15)
- **Panel des joueurs** (visible en permanence) :
  - Nom
  - **Points restants** (sur 15 au départ) - **affiché en temps réel**
  - Position actuelle (pièce)
  - Statut (vivant / éliminé / bloqué)

> 💡 **Important** : Les points de chaque joueur doivent être **toujours visibles** pendant toute la partie (HUD permanent)

#### 3. **Interface de phase de jeu**
Pour chaque phase (avec **timer de 60 secondes**) :
- **Timer visible** (compte à rebours)
- Affichage de la pièce courante avec ses portes
- Lancer des dés automatique (autant que de portes)
- Résultats des dés affichés par porte avec capacités
- **Phase collaborative** : les joueurs peuvent communiquer et choisir leur porte
- À la fin du timer : résolution automatique
- Affichage : qui passe, qui est bloqué (lockdown)
- Décompte des points
- Passage à la phase suivante

#### 4. **Écran de fin**
- Gagnants (ceux qui ont atteint la sortie avec ≥1 point)
- Perdants (éliminés ou n'ayant pas atteint la sortie)
- Stats de la partie (tours utilisés, survivants, etc.)
- Bouton "Nouvelle partie"

---

## 🧮 Règles du jeu (logique métier)

### ⏱️ Système de temps réel
**IMPORTANT** : Contrairement à un jeu au tour par tour classique, le jeu se déroule en **temps réel** avec des **timers** pour chaque phase, comme dans la série.

- Chaque phase de jeu dispose d'un **timer de 60 secondes** (600 secondes/10 minutes en mode debug)
- Les joueurs doivent **se coordonner** et faire leurs choix pendant ce temps
- À la fin du timer, la résolution est **automatique**
- Pas de pause entre les phases (sauf affichage des résultats)

### Plateau
- **Grille 5×5** = 25 pièces
- **Numérotation** :
  - Colonnes : **1 à 5** (horizontal, axe X)
  - Lignes : **A à E** (vertical, axe Y)
  - Exemple : A1 (coin haut-gauche), E5 (coin bas-droite)
- Chaque pièce a un **type** selon sa position :
  - **Coins** (4 pièces : A1, A5, E1, E5) : 2 portes
  - **Bords** (12 pièces) : 3 portes
  - **Centre** (9 pièces) : 4 portes

- **Case départ** : choisie **aléatoirement** sur une **croix centrale** :
  - Ligne C : C1, C2, C3, C4, C5
  - Colonne 3 : A3, B3, C3, D3, E3
  - Garantit une distance minimum de la sortie
- **Case sortie** : choisie **aléatoirement** parmi les **4 coins** (A1, A5, E1, E5)

### Distribution des points perdus par pièce
À **chaque nouvelle partie**, les points perdus sont **distribués aléatoirement** selon ce tableau :

| Points perdus | Nombre de pièces |
|--------------|------------------|
| 4            | 3                |
| 3            | 5                |
| 2            | 7                |
| 1            | 8                |

**Total : 23 pièces (+ départ et arrive)**

**Règle** : les pièces de **départ** et d'**arrivée** ne font **pas perdre de points** (0 point).

### Points de vie
- Chaque joueur démarre avec **15 points**
- **Ouvrir une porte** : **-1 point** pour le joueur volontaire qui choisit de l'ouvrir
- **Entrer dans une salle** : **-X points** (selon le coût de la salle) pour **TOUS les joueurs présents** dans cette salle
- Si **points ≤ 0** → joueur **éliminé**
- Si aucune porte n'est ouverte, les joueurs restent dans la salle actuelle et subissent à nouveau son coût

### Système de dés et portes

#### Couleurs des portes
Chaque porte a une **couleur spécifique** selon sa direction :
- **Nord** ↑ : Bleu (#3b82f6)
- **Sud** ↓ : Vert (#10b981)
- **Est** → : Rouge (#ef4444)
- **Ouest** ← : Jaune (#f59e0b)

Les dés affichés ont la même couleur que leur porte associée pour faciliter l'identification.

#### Lancer de dés
- Chaque pièce a **N portes** (2, 3 ou 4)
- Au début de chaque tour, on lance **N dés D10** (valeurs 0-9)
- Chaque résultat = **capacité max** de joueurs pour cette porte
- Les dés sont **lancés automatiquement** au début de chaque tour

#### Ouverture et choix des portes
- Les joueurs ont **60 secondes** pour se coordonner
- **Phase 1 - Ouverture** : un joueur volontaire paie **1 point** pour ouvrir une porte spécifique
  - Une porte non ouverte ne peut pas être franchie
  - Plusieurs portes peuvent être ouvertes (si plusieurs joueurs paient)
- **Phase 2 - Choix** : chaque joueur choisit la porte par laquelle il veut passer
  - Les choix sont visibles par tous (transparence pour favoriser la coordination)
  - **Les joueurs peuvent se séparer** et se retrouver dans des salles différentes

#### Résolution (fin du timer)
- Pour **chaque porte ouverte** :
  - Si **nombre de joueurs ≤ capacité du dé** → tous passent
  - Si **nombre de joueurs > capacité** → **lockdown** (excédent bloqué)
    - Les joueurs qui passent sont choisis **aléatoirement** parmi ceux qui ont choisi cette porte
    - Les autres restent bloqués dans la pièce actuelle
- **Pour les portes non ouvertes** : les joueurs qui les ont choisies restent bloqués

### Contraintes de déplacement
- **Pas de retour immédiat** : on ne peut pas repasser par la porte d'où on vient au tour suivant
- Les joueurs bloqués (lockdown) peuvent **réessayer** au prochain tour
- **Libération** : un joueur peut débloquer un autre joueur bloqué **sans coût supplémentaire** (seuls les points de la salle sont déduits)

### Visibilité des pièces
- Les **points perdus** d'une pièce ne sont visibles que **si elle a déjà été visitée**
- Les pièces non visitées affichent "?" ou restent masquées
- Cela ajoute un élément de risque et d'exploration

### Conditions de victoire/défaite

#### Victoire
- Atteindre la **case sortie** avant la fin du **tour 15**
- Avoir **≥ 1 point** en arrivant sur la sortie

#### Défaite
- **Points ≤ 0** → éliminé
- **Tour 15 terminé** sans avoir atteint la sortie → défaite collective

---

## 🏗️ Architecture technique

### Stack retenue
- **Backend** : PHP 8+ avec architecture MVC
- **Base de données** : SQLite pour le POC (avec abstraction pour migration future vers MySQL/PostgreSQL en production)
- **Frontend** : HTML5, CSS3 (ou SCSS), framework JS léger (Vue.js ou Alpine.js)
- **Communication temps réel** :
  - Polling AJAX pour le POC (simple et efficace)
  - Migration possible vers WebSockets pour la prod
- **Serveur** : PHP built-in server pour le dev, Apache/Nginx pour la prod

### Structure du code (principes SOLID)

#### Séparation des responsabilités (Architecture MVC)

**1. Backend PHP**

**Models** - Logique métier et accès données
- `Game.php` : gestion de l'état global (tours, joueurs, plateau)
- `Player.php` : représentation d'un joueur (nom, points, position, statut)
- `Room.php` : représentation d'une pièce (position, portes, points perdus)
- `Board.php` : génération et gestion du plateau 5×5
- `Database.php` : abstraction de la couche DB (PDO avec SQLite)

**Controllers** - Orchestration et endpoints API
- `GameController.php` : gestion du flux de jeu, création/chargement partie
- `PlayerController.php` : actions joueurs (choix porte, libération)
- `TurnController.php` : gestion des phases et timers

**Views** - Templates PHP
- `game.php` : vue principale du jeu
- `registration.php` : enregistrement des joueurs
- `end.php` : écran de fin

**2. Frontend JS**

**Composants Vue.js/Alpine.js**
- `GameBoard.vue` : affichage du plateau 5×5
- `PlayerPanel.vue` : panel des joueurs avec stats
- `TurnTimer.vue` : compte à rebours
- `DoorChoice.vue` : interface de choix de porte
- `GameState.js` : gestion de l'état côté client (synchronisé avec backend)

### Points d'attention SOLID
- **Single Responsibility** : chaque classe a une seule raison de changer
- **Open/Closed** : possibilité d'étendre (ex: nouvelles règles) sans modifier le core
- **Liskov Substitution** : types de pièces interchangeables
- **Interface Segregation** : interfaces claires entre Model/View/Controller
- **Dependency Inversion** : dépendances via interfaces, pas d'implémentations concrètes

### Principe DRY
- Fonctions réutilisables pour :
  - Génération aléatoire (dés, distribution points)
  - Calculs de capacité/résolution
  - Validation des règles

### Principe KISS
- Pas de features superflues dans le POC
- Code lisible et simple
- Commentaires uniquement si nécessaire

---

## 📦 Fonctionnalités du POC (MVP)

### ✅ Inclus
- [ ] Enregistrement des joueurs (3-8)
- [ ] Génération aléatoire du plateau (distribution points perdus)
- [ ] Affichage visuel du plateau 5×5 avec brouillard de guerre (salles non visitées)
- [ ] Système de phases avec timers (max 15 tours)
- [ ] Timer de 60 secondes par phase (compte à rebours visible)
- [ ] Lancer de dés automatique par pièce (D10)
- [ ] Interface de choix de porte collaborative
- [ ] Résolution lockdown avec sélection aléatoire
- [ ] Décompte automatique des points (tour + entrée salle)
- [ ] Système de libération entre joueurs
- [ ] Détection victoire/défaite
- [ ] Écran de fin avec stats
- [ ] Thème sombre
- [ ] API REST pour communication frontend/backend
- [ ] Persistance en base de données (SQLite)

### ⏳ Post-MVP (si temps)
- [ ] Cartes "Futurs possibles" (événements aléatoires)
- [ ] Animation des déplacements
- [ ] Historique des tours
- [ ] Sauvegarde de partie (localStorage)
- [ ] Mode multijoueur en ligne (WebSockets)
- [ ] Sons & effets

### ❌ Hors scope POC
- Gestion de comptes utilisateurs
- Statistiques multi-parties
- Système de classement
- Chat intégré (les joueurs communiquent via Discord/vocal)

---

## ✅ Décisions validées

1. **Mode de jeu** : Temps réel avec timer de 60 secondes par phase (pas de tour par tour)

2. **Choix des portes** : Phase collaborative où les joueurs se coordonnent et choisissent leur porte en temps réel

3. **Résolution du lockdown** : Si trop de joueurs sur une porte, sélection **aléatoire** parmi les candidats

4. **Pièces départ/arrivée** : Ne font **pas perdre de points** (0 point)

5. **Libération** : Un joueur peut débloquer un autre joueur **sans coût supplémentaire** (seuls les points de la salle sont déduits)

6. **Affichage des points perdus** : Visibles **uniquement pour les salles déjà visitées** (brouillard de guerre)

7. **Stack technique** : PHP 8+ avec SQLite (POC) / MySQL ou PostgreSQL (prod) + Vue.js ou Alpine.js

---

## 📅 Livrables POC

1. **Code source** organisé selon architecture MVC PHP
2. **Base de données SQLite** avec schéma de tables
3. **API REST** documentée (endpoints pour le frontend)
4. **Interface web** fonctionnelle et jouable en temps réel
5. **README** avec :
   - Instructions d'installation et lancement
   - Règles du jeu
   - Architecture du code
   - Documentation API
6. **Scripts de migration** DB (pour passage SQLite → MySQL/PostgreSQL)

---

## 🎮 Schéma de base de données (proposition)

### Tables principales

**games**
- id (PK)
- created_at
- current_turn (1-15)
- status (waiting, playing, finished)
- turn_started_at (timestamp pour le timer)

**players**
- id (PK)
- game_id (FK)
- name
- points (15 au départ)
- current_room_id (FK)
- status (alive, dead, blocked, winner)
- created_at

**rooms**
- id (PK)
- game_id (FK)
- position_x (0-4)
- position_y (0-4)
- points_cost (0-8)
- door_count (2, 3, ou 4)
- is_start (boolean)
- is_exit (boolean)
- is_visited (boolean)

**doors**
- id (PK)
- room_id (FK)
- direction (north, south, east, west)
- dice_result (0-9, null si pas encore lancé)
- current_turn (pour quel tour ce résultat est valide)

**player_choices**
- id (PK)
- player_id (FK)
- door_id (FK)
- turn_number
- created_at

---

## 🚀 Prochaines étapes

1. ✅ Validation du cahier des charges
2. Setup du projet (structure de dossiers, dépendances)
3. Création du schéma de base de données
4. Développement des Models (logique métier)
5. Développement des Controllers (API REST)
6. Développement du frontend (Vue.js + interface)
7. Tests et ajustements
8. Documentation finale
