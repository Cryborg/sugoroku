# Système de Cartes Bonus 🎴

## Architecture

Le système de cartes bonus utilise le **Pattern Strategy** pour une isolation complète. Chaque carte est indépendante et l'ajout/modification d'une carte ne peut **jamais** causer de bug dans le reste du code.

### Structure

```
src/BonusCards/
├── AbstractBonusCard.php       # Classe de base abstraite
├── BonusCardRegistry.php       # Registry pour enregistrer les cartes
├── PlayerCardManager.php       # Gestionnaire des cartes des joueurs
└── Cards/                      # Toutes les cartes concrètes
    ├── TemporaryPointsCard.php
    ├── MinimumDiceCard.php
    ├── RevealBonusCard.php
    └── DoublePlayCard.php
```

## Cartes disponibles

### ⚡ Points temporaires
- **ID** : `temporary_points`
- **Type** : `active`
- **Effet** : Donne 3 points temporaires. Si non utilisés pour passer une porte, ils sont retirés.
- **Méthodes** : `apply()` + `revert()`

### 🎲 Dé garanti
- **ID** : `minimum_dice`
- **Type** : `passive`
- **Effet** : Assure un minimum de `floor(nb_joueurs/2)` sur tous les dés pendant un tour.

### 👁️ Vision des bonus
- **ID** : `reveal_bonus`
- **Type** : `active`
- **Effet** : Révèle tous les bonus/malus de bonheur de la salle actuelle.

### 🔄 Double jeu
- **ID** : `double_play`
- **Type** : `active`
- **Effet** : Permet de jouer deux fois dans le même tour.

## Ajouter une nouvelle carte

### Étape 1 : Créer la classe de carte

Créer un fichier dans `src/BonusCards/Cards/` :

```php
<?php

namespace Trapped\BonusCards\Cards;

use Trapped\BonusCards\AbstractBonusCard;

class MaSuperCarte extends AbstractBonusCard
{
    protected string $id = 'ma_super_carte';
    protected string $name = 'Ma Super Carte';
    protected string $description = 'Description de l\'effet';
    protected string $icon = '🌟';
    protected string $type = 'active'; // 'instant', 'passive', 'active'

    public function canUse(array $context): bool
    {
        // Retourner true si la carte peut être utilisée
        return true;
    }

    public function apply(array $context): array
    {
        return [
            'success' => true,
            'message' => 'Effet appliqué',
            'effects' => [
                'type' => 'mon_effet',
                // ... données de l'effet
            ]
        ];
    }

    // Optionnel : pour annuler l'effet
    public function revert(array $context): array
    {
        return [
            'success' => true,
            'message' => 'Effet annulé',
            'effects' => []
        ];
    }
}
```

### Étape 2 : Enregistrer la carte

Dans `src/BonusCards/BonusCardRegistry.php`, méthode `registerCards()` :

```php
private function registerCards(): void
{
    $this->register(new TemporaryPointsCard());
    $this->register(new MinimumDiceCard());
    $this->register(new RevealBonusCard());
    $this->register(new DoublePlayCard());
    $this->register(new MaSuperCarte()); // <- AJOUTER ICI
}
```

**C'est tout !** Aucun autre fichier à modifier.

## Utilisation

### Donner une carte à un joueur

```php
use Trapped\BonusCards\PlayerCardManager;

$manager = new PlayerCardManager();
$manager->giveCard($playerId, 'temporary_points');
```

### Récupérer les cartes d'un joueur

```php
$cards = $manager->getPlayerCards($playerId);
// Retourne toutes les cartes (utilisées et non utilisées)

$availableCards = $manager->getAvailableCards($playerId);
// Retourne uniquement les cartes non utilisées
```

### Utiliser une carte

```php
$context = [
    'player_id' => $playerId,
    'room_id' => $roomId,
    'has_chosen' => false,
    'player_count' => 4,
    // ... autres données nécessaires
];

$result = $manager->useCard($playerCardId, $playerId, $context);

if ($result['success']) {
    // Appliquer les effets
    $effects = $result['effects'];
}
```

### Annuler une carte

```php
$result = $manager->revertCard($playerCardId, $playerId, $context);
```

## Types de cartes

- **instant** : Effet immédiat, ne peut pas être annulé
- **passive** : Effet automatique quand les conditions sont remplies
- **active** : Le joueur décide quand l'utiliser

## Base de données

### Table `player_bonus_cards`

```sql
- id (PRIMARY KEY)
- player_id (FOREIGN KEY -> players.id)
- card_id (VARCHAR) - l'ID de la carte
- used (BOOLEAN) - 0 = disponible, 1 = utilisée
- used_at (DATETIME) - Date d'utilisation
- created_at (DATETIME)
```

## API Routes (à venir)

- `GET /players/{id}/bonus-cards` - Liste des cartes du joueur
- `POST /players/{id}/bonus-cards/{cardId}/use` - Utiliser une carte
- `GET /bonus-cards/available` - Liste toutes les cartes disponibles

## Isolation et sécurité

✅ **Isolation complète** : Chaque carte est dans son propre fichier
✅ **Aucune dépendance** : Les cartes ne dépendent que de `AbstractBonusCard`
✅ **Registry centralisé** : Un seul point d'enregistrement
✅ **Validation** : `canUse()` valide avant application
✅ **Réversibilité** : `revert()` pour annuler si besoin
✅ **Extensibilité** : Ajout de carte = 1 nouveau fichier + 1 ligne dans le registry

## Tests

Pour tester une nouvelle carte :

1. Créer la classe de carte
2. L'enregistrer dans le Registry
3. Donner la carte à un joueur de test
4. Utiliser la carte et vérifier les effets
5. Si la carte a `revert()`, tester l'annulation

**Aucun risque de régression** : les autres cartes ne sont pas affectées ! 🎉
