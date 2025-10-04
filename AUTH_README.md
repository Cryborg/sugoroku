# Système d'authentification - Trapped

## Configuration

### Fichier .env

Le fichier `.env` contient les informations sensibles du compte administrateur. Il est déjà configuré avec :

```
ADMIN_EMAIL=cryborg.live@gmail.com
ADMIN_PASSWORD=Célibataire1979$
ADMIN_USERNAME=Franck
```

**Important** : Ce fichier est dans `.gitignore` et ne sera jamais commité.

## Migration initiale

Pour créer la base de données avec toutes les tables :

```bash
php migrate_auth.php
```

Cette commande :
- Supprime toutes les tables existantes
- Recrée les tables `users`, `user_favorite_players`, `games`, `players`, etc.
- Crée un compte de test : `test@test.com` / `test123`

## Création du compte admin

Pour créer ou mettre à jour le compte administrateur depuis le fichier `.env` :

```bash
php seed_admin.php
```

Cette commande :
- Lit les identifiants depuis `.env`
- Crée le compte admin s'il n'existe pas
- Met à jour le mot de passe et le statut admin s'il existe déjà

## Comptes disponibles

### Compte Administrateur
- **Email** : cryborg.live@gmail.com
- **Mot de passe** : Célibataire1979$
- **Statut** : Admin (is_admin = 1)

### Compte de test
- **Email** : test@test.com
- **Mot de passe** : test123
- **Statut** : Utilisateur normal

## Fonctionnalités

### Authentification
- ✅ Inscription de nouveaux utilisateurs
- ✅ Connexion avec email et mot de passe
- ✅ Déconnexion
- ✅ Sessions PHP persistantes
- ✅ Mots de passe hashés (bcrypt)

### Gestion des utilisateurs
- ✅ Profil utilisateur (username, email)
- ✅ Statut admin (is_admin)
- ✅ Badge admin visible dans l'interface

### Joueurs favoris
- ✅ Sauvegarde automatique des joueurs créés
- ✅ Chargement des joueurs favoris à la connexion
- ✅ Stockage en base de données (plus de localStorage)

### Parties
- ✅ Parties liées à l'utilisateur connecté
- ✅ Chaque utilisateur voit uniquement ses propres parties
- ✅ Isolation complète des données entre utilisateurs

## API Routes

### Publiques (pas d'authentification requise)
- `POST /auth/register` - Inscription
- `POST /auth/login` - Connexion
- `POST /auth/logout` - Déconnexion
- `GET /auth/me` - Obtenir l'utilisateur connecté

### Protégées (authentification requise)
- `POST /game/create` - Créer une partie
- `GET /games/list` - Lister les parties de l'utilisateur
- `GET /players/favorites` - Obtenir les joueurs favoris
- `POST /players/favorites` - Sauvegarder les joueurs favoris
- Et toutes les autres routes de jeu...

## Structure de la base

### Table users
```sql
- id (PRIMARY KEY)
- email (UNIQUE)
- password_hash
- username
- is_admin (BOOLEAN, default 0)
- created_at
- last_login
```

### Table user_favorite_players
```sql
- id (PRIMARY KEY)
- user_id (FOREIGN KEY -> users.id)
- name
- gender
- avatar
- created_at
```

### Table games
```sql
- id (PRIMARY KEY)
- user_id (FOREIGN KEY -> users.id)
- created_at
- current_turn
- status
- ...
```

## Sécurité

- ✅ Mots de passe hashés avec `password_hash()` (bcrypt)
- ✅ Sessions PHP sécurisées
- ✅ Validation des entrées
- ✅ Protection CSRF via sessions
- ✅ Isolation des données par utilisateur
- ✅ Fichier .env pour les secrets
