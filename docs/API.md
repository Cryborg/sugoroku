# 📡 Documentation API - Future Sugoroku

## Base URL

```
http://localhost:8000/api.php
```

## Endpoints

### 🎮 Game Management

#### Créer une partie

```http
POST /game/create
Content-Type: application/json

{
  "players": ["Alice", "Bob", "Charlie"]
}
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "gameId": 1,
    "message": "Partie créée avec succès"
  }
}
```

---

#### Démarrer une partie

```http
POST /game/{gameId}/start
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "message": "Partie démarrée",
    "state": { ... }
  }
}
```

---

#### Récupérer l'état de la partie

```http
GET /game/{gameId}/state
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "currentTurn": 3,
    "status": "playing",
    "remainingTime": 45,
    "players": [...],
    "rooms": [...]
  }
}
```

---

#### Vérifier les conditions de fin

```http
GET /game/{gameId}/check-end
```

**Réponse (partie en cours)**
```json
{
  "success": true,
  "data": {
    "gameOver": false
  }
}
```

**Réponse (victoire)**
```json
{
  "success": true,
  "data": {
    "gameOver": true,
    "reason": "victory",
    "winners": [...],
    "survivors": [...],
    "dead": [...]
  }
}
```

---

### 🚪 Door Management

#### Ouvrir une porte

Un joueur volontaire paie **1 point** pour ouvrir une porte.

```http
POST /door/{doorId}/open
Content-Type: application/json

{
  "playerId": 1
}
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "message": "Porte ouverte",
    "doorId": 5,
    "playerId": 1,
    "remainingPoints": 14
  }
}
```

**Erreurs possibles**
- Joueur n'a pas assez de points
- Porte déjà ouverte
- Joueur mort ou invalide

---

### 👤 Player Actions

#### Choisir une porte

```http
POST /player/{playerId}/choose-door
Content-Type: application/json

{
  "doorId": 5
}
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "message": "Choix enregistré",
    "playerId": 1,
    "doorId": 5,
    "turn": 3
  }
}
```

---

#### Libérer un joueur bloqué

```http
POST /player/{blockedPlayerId}/free
Content-Type: application/json

{
  "liberatorId": 2
}
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "message": "Joueur libéré",
    "liberatorId": 2,
    "freedPlayerId": 1
  }
}
```

**Conditions**
- Les deux joueurs doivent être dans la même salle
- Le libérateur doit être vivant
- Le joueur à libérer doit être bloqué

---

#### Récupérer tous les choix

```http
GET /game/{gameId}/choices
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "turn": 3,
    "choices": [
      {
        "playerId": 1,
        "playerName": "Alice",
        "doorId": 5,
        "hasChosen": true
      },
      {
        "playerId": 2,
        "playerName": "Bob",
        "doorId": null,
        "hasChosen": false
      }
    ]
  }
}
```

---

### ⏱️ Turn Management

#### Résoudre le tour

Résout automatiquement le tour si le timer est expiré.

```http
POST /turn/{gameId}/resolve
```

**Réponse**
```json
{
  "success": true,
  "data": {
    "turnResolved": 3,
    "movements": [
      {
        "playerId": 1,
        "playerName": "Alice",
        "fromRoom": 1,
        "toRoom": 2,
        "doorId": 5,
        "direction": "east"
      }
    ],
    "nextTurn": 4,
    "gameStatus": "playing"
  }
}
```

---

#### Forcer la résolution

Force la résolution même si le timer n'est pas expiré (utile pour les tests).

```http
POST /turn/{gameId}/force-resolve
```

---

#### Vérifier et auto-résoudre

Vérifie si le timer est expiré et résout automatiquement si nécessaire.

```http
GET /turn/{gameId}/check
```

**Réponse (timer non expiré)**
```json
{
  "success": true,
  "data": {
    "timerExpired": false,
    "remainingTime": 45
  }
}
```

**Réponse (timer expiré - auto-résolution)**
```json
{
  "success": true,
  "data": {
    "turnResolved": 3,
    "movements": [...],
    "nextTurn": 4,
    "gameStatus": "playing"
  }
}
```

---

## Codes d'erreur

### Erreurs communes

```json
{
  "success": false,
  "error": "Message d'erreur descriptif"
}
```

**Exemples**
- `"Partie introuvable"` - Game ID invalide
- `"Joueur introuvable"` - Player ID invalide
- `"Le joueur est mort ou ne peut pas jouer"` - Joueur éliminé
- `"Le joueur n'a pas assez de points pour ouvrir cette porte"` - Points insuffisants
- `"Cette porte est déjà ouverte"` - Porte déjà ouverte par un autre joueur
- `"Route not found"` - Endpoint inexistant

---

## Flux de jeu typique

1. **Créer une partie** → `POST /game/create`
2. **Démarrer la partie** → `POST /game/{id}/start`
3. **Phase de jeu (60 secondes)** :
   - Polling : `GET /game/{id}/state` (toutes les 2s)
   - Ouvrir des portes : `POST /door/{id}/open`
   - Choisir des portes : `POST /player/{id}/choose-door`
4. **Fin du timer** :
   - Auto-résolution : `GET /turn/{id}/check`
5. **Vérifier fin de partie** : `GET /game/{id}/check-end`
6. **Répéter 3-5** jusqu'à fin de partie

---

## Exemple complet avec cURL

```bash
# 1. Créer une partie
curl -X POST http://localhost:8000/api.php/game/create \
  -H "Content-Type: application/json" \
  -d '{"players":["Alice","Bob","Charlie"]}'

# 2. Démarrer la partie (ID 1)
curl -X POST http://localhost:8000/api.php/game/1/start

# 3. Alice (ID 1) ouvre la porte 2
curl -X POST http://localhost:8000/api.php/door/2/open \
  -H "Content-Type: application/json" \
  -d '{"playerId":1}'

# 4. Bob (ID 2) choisit la porte 2
curl -X POST http://localhost:8000/api.php/player/2/choose-door \
  -H "Content-Type: application/json" \
  -d '{"doorId":2}'

# 5. Récupérer l'état
curl http://localhost:8000/api.php/game/1/state

# 6. Forcer la résolution (pour test)
curl -X POST http://localhost:8000/api.php/turn/1/force-resolve

# 7. Vérifier fin de partie
curl http://localhost:8000/api.php/game/1/check-end
```

---

## Notes techniques

- Tous les endpoints retournent du JSON
- Les timestamps sont au format UTC ISO 8601
- Le polling est recommandé toutes les 2 secondes
- Le timer se réinitialise automatiquement à chaque nouveau tour
- La base de données est créée automatiquement au premier lancement
