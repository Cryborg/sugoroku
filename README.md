# 🎮 Future Sugoroku - Trapped

POC jouable en navigateur du jeu "Future Sugoroku" vu dans Alice in Borderland.

## 🚀 Installation et lancement rapide

### Prérequis
- PHP 8.0 ou supérieur
- Extension PDO SQLite activée (généralement incluse par défaut)

### Lancement

```bash
# Naviguer dans le dossier du projet
cd /Users/franck/PhpstormProjects/perso/trapped

# Lancer le serveur PHP built-in
php -S localhost:8000 -t public
```

Ouvrir le navigateur à l'adresse : **http://localhost:8000**

## 📁 Structure du projet

```
trapped/
├── config/
│   └── database.php          # Configuration de la base de données
├── database/
│   ├── schema.sql            # Schéma de la base de données
│   └── trapped.db            # Base SQLite (créée automatiquement)
├── docs/
│   └── cahier-des-charges.md # Documentation complète du projet
├── public/
│   ├── css/
│   │   └── style.css         # Styles (dark mode)
│   ├── js/
│   │   └── app.js            # Frontend Vue.js
│   ├── api.php               # Point d'entrée API REST
│   └── index.html            # Interface web
├── src/
│   ├── Controllers/
│   │   ├── GameController.php
│   │   ├── PlayerController.php
│   │   └── TurnController.php
│   ├── Database/
│   │   └── Database.php      # Abstraction PDO (SQLite/MySQL)
│   └── Models/
│       ├── Game.php
│       ├── Player.php
│       ├── Room.php
│       └── Door.php
├── autoload.php              # Autoloader PSR-4
└── README.md
```

## 🎯 Comment jouer

### 1. Enregistrement des joueurs
- Entrez 3 à 8 noms de joueurs
- Cliquez sur "Démarrer la partie"

### 2. Déroulement d'une partie
- **Objectif** : Atteindre la sortie (coin bas-droite) en moins de 15 tours avec au moins 1 point
- **Timer** : Chaque phase dure 60 secondes
- **Points** : Chaque joueur démarre avec 15 points

### 3. Règles du jeu

#### Ouverture de porte
- Un joueur **volontaire** paie **1 point** pour ouvrir une porte
- Les autres joueurs peuvent ensuite passer par cette porte (dans la limite du dé)

#### Déplacement
- Chaque porte a une **capacité** (résultat d'un dé D10 : 0-9)
- Si trop de joueurs choisissent la même porte → **lockdown** (sélection aléatoire)
- Les joueurs peuvent **se séparer** et aller dans différentes salles

#### Coût des salles
- **TOUS les joueurs présents** dans une salle perdent ses points (1 à 8)
- Les salles de départ et d'arrivée coûtent **0 point**
- Les coûts ne sont visibles que pour les **salles visitées** (brouillard de guerre)

#### Conditions de victoire/défaite
- ✅ **Victoire** : Atteindre la sortie avec ≥ 1 point avant le tour 15
- ❌ **Défaite** : Points ≤ 0 (mort) ou tour 15 atteint sans victoire

## 🔧 API REST

### Endpoints disponibles

#### Game

```bash
# Créer une partie
POST /api.php/game/create
Body: {"players": ["Alice", "Bob", "Charlie"]}

# Démarrer une partie
POST /api.php/game/{id}/start

# Récupérer l'état de la partie
GET /api.php/game/{id}/state

# Vérifier fin de partie
GET /api.php/game/{id}/check-end
```

#### Player

```bash
# Ouvrir une porte
POST /api.php/door/{doorId}/open
Body: {"playerId": 1}

# Choisir une porte
POST /api.php/player/{playerId}/choose-door
Body: {"doorId": 5}

# Libérer un joueur bloqué
POST /api.php/player/{blockedPlayerId}/free
Body: {"liberatorId": 2}

# Récupérer tous les choix
GET /api.php/game/{gameId}/choices
```

#### Turn

```bash
# Résoudre le tour (automatique si timer expiré)
POST /api.php/turn/{gameId}/resolve

# Forcer la résolution
POST /api.php/turn/{gameId}/force-resolve

# Vérifier et auto-résoudre si nécessaire
GET /api.php/turn/{gameId}/check
```

## 🏗️ Architecture

### Principes SOLID respectés

- **Single Responsibility** : Chaque classe a une seule responsabilité
- **Open/Closed** : Extension facile (nouvelles règles) sans modification du core
- **Liskov Substitution** : Abstraction DB permet de changer de driver
- **Interface Segregation** : Séparation claire Model/View/Controller
- **Dependency Inversion** : Dépendances via interfaces

### Patterns utilisés

- **MVC** : Séparation stricte Modèle/Vue/Contrôleur
- **Singleton** : Connexion DB unique
- **Repository** : Models encapsulent l'accès aux données

## 🗄️ Base de données

### SQLite (POC)
La base de données est créée automatiquement au premier lancement dans `database/trapped.db`.

### Migration vers MySQL/PostgreSQL

Modifier `.env` (copier `.env.example`) :

```env
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=trapped
DB_USER=root
DB_PASS=your_password
```

Le schéma SQL (`database/schema.sql`) est compatible MySQL.

## 🎨 Frontend

- **Vue.js 3** (via CDN)
- **Polling AJAX** toutes les 2 secondes pour synchroniser l'état
- **Timer local** avec auto-résolution
- **Dark mode** par défaut

## 📝 TODO / Améliorations futures

- [ ] Interface pour choisir les portes et les ouvrir (actuellement basique)
- [ ] Animations des déplacements
- [ ] Son et effets visuels
- [ ] Cartes "Futurs possibles" (événements aléatoires)
- [ ] WebSockets pour synchronisation temps réel
- [ ] Historique des tours
- [ ] Sauvegarde de partie
- [ ] Mode multijoueur en ligne

## 🐛 Debug

### Problèmes courants

**Base de données non créée**
```bash
# Vérifier les permissions du dossier database/
chmod 755 database/
```

**Erreur PDO**
```bash
# Vérifier que l'extension SQLite est activée
php -m | grep sqlite
```

**API ne répond pas**
```bash
# Vérifier que le serveur PHP est lancé sur le bon port
php -S localhost:8000 -t public
```

## 👨‍💻 Développeur

Développé par Franck avec ❤️ et Claude Code

## 📄 Licence

POC éducatif - Tous droits réservés
