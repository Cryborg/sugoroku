# 📝 Changelog - Future Sugoroku

## ✨ Version 1.2 - 2025-10-03 (Après-midi)

### 🎨 Interface de jeu repensée

#### Taille des éléments
- **Pièces agrandies de 70%** pour une meilleure lisibilité
- Gap de 15px entre les pièces
- Police à 16px, badges à 14px
- Dés passés à 28x28px
- Badges joueurs à 32x32px

#### Panneau des joueurs optimisé
- **Timer déplacé en haut** du panneau joueurs
- Liste compactée (suppression du statut "vivant" et de la position)
- **Joueurs morts** : grisés fortement (30% opacité + grayscale) avec crâne et croix rouge (💀 ✕)
- Survol amélioré avec translation

#### Système de popin au survol
- **Popin d'actions** qui apparaît au survol de n'importe quel joueur vivant
- Positionnement fixe à droite du panneau (plus de problèmes de scroll)
- Délai de 100ms avant fermeture pour navigation fluide
- Reste visible quand on survole la popin elle-même

#### Actions des joueurs
- **Bouton "Ouvrir (-1 pt)"** : indique clairement le coût
- **Bouton "Aller"** devient "✓ Choisi" après sélection
- **Blocage automatique** : un joueur qui a fait son choix ne peut plus agir ce tour
- Indicateur "✓ Choix fait" dans le titre de la popin
- Porte choisie mise en surbrillance

#### Sortie masquée
- **La sortie n'est plus visible** sur le plateau
- Plus de style particulier (bordure rouge, background)
- Les joueurs doivent la deviner ! 🤫

#### Départ discret
- **Drapeau 🏁** placé dans le coin supérieur gauche
- Semi-transparent (60% opacité)
- Petite taille (18px)
- N'empêche plus de voir le numéro de la pièce

#### Affichage multi-joueurs amélioré
- **Grid responsive** pour les badges joueurs dans une pièce
- Meilleure répartition avec `auto-fit`
- Ombre portée sur les badges pour meilleure distinction
- Supporte jusqu'à 10 joueurs dans une pièce sans débordement

### 🔧 Backend

#### Système de suivi des choix
- Ajout de `hasChosen` et `chosenDoorId` dans `Player::toArray()`
- Modification de `Game::getFullState()` pour inclure le tour actuel
- Vérification automatique si un joueur a déjà choisi ce tour
- Utilisation de la table `player_choices` existante

#### Console de debug
- Ajout de `console.log` dans les actions pour faciliter le debug
- Logs pour "Opening door" et "Choosing door"

### 🐛 Corrections de bugs
- **Popin** : résolution du problème de scroll horizontal
- **Événements** : correction du passage des paramètres `playerId` et `doorId`
- **Z-index** : popin en `position: fixed` avec `z-index: 9999`

## ✨ Version 1.1 - 2025-10-03 (Matin)

## ✨ Améliorations apportées

### 🗺️ Numérotation du plateau

#### Cases numérotées A-E / 1-5
- 📍 Les cases sont maintenant identifiées avec une **notation claire** :
  - **Colonnes** : 1 à 5 (horizontal)
  - **Lignes** : A à E (vertical)
  - Exemples : A1, C3, E5
- Facilite la communication entre joueurs
- Standard type "bataille navale"

### 🎮 Gameplay

#### Timer étendu pour debug
- ⏱️ Timer passé de **60 secondes à 10 minutes** (600s) pour faciliter les tests
- Permet de tester le jeu sans pression de temps

#### Départ et sortie aléatoires
- 🎲 La **sortie** est choisie **aléatoirement** parmi les **4 coins** du plateau (A1, A5, E1, E5)
- 🎲 Le **départ** est choisi **aléatoirement** sur une **croix centrale** :
  - **Ligne C** : C1, C2, C3, C4, C5
  - **Colonne 3** : A3, B3, C3, D3, E3
- Garantit une **distance minimum** entre départ et sortie (pas de spawn à côté de la sortie)
- Les joueurs ne connaissent pas la sortie au début de la partie (brouillard de guerre)
- Ajoute de la rejouabilité à chaque partie

#### Système de couleurs pour les portes
- 🎨 Chaque direction de porte a sa **couleur unique** :
  - **Nord** ↑ : Bleu (#3b82f6)
  - **Sud** ↓ : Vert (#10b981)
  - **Est** → : Rouge (#ef4444)
  - **Ouest** ← : Jaune (#f59e0b)
- Les **dés** ont la **même couleur** que leur porte associée
- Facilite grandement l'identification et la coordination entre joueurs

### 🖥️ Interface utilisateur

#### Interface de jeu complète
- ✅ **Sélection de joueur** : cliquer sur un joueur dans le panneau de gauche pour le sélectionner
- ✅ **Actions disponibles** : panneau en bas affichant toutes les portes de la salle actuelle du joueur sélectionné
- ✅ **Boutons d'action** :
  - **Ouvrir** : payer 1 point pour ouvrir une porte (affiche les points restants)
  - **Choisir** : sélectionner une porte ouverte pour passer
  - Boutons désactivés si action impossible (pas assez de points, porte fermée, etc.)

#### Affichage des dés
- 🎲 **Dés visibles** uniquement sur les salles **contenant au moins un joueur**
- Réduit la confusion visuelle
- Couleur du dé = couleur de la porte
- Résultat du dé clairement affiché (0-9)
- Pas d'icône de cadenas (inutile car réouvrir = payer à nouveau)

#### Amélioration du plateau
- 📍 **Position** de chaque salle affichée avec notation **A-E / 1-5** (type bataille navale)
- 👥 **Joueurs présents** dans chaque salle (initiales dans des badges)
- 💰 **Coût de la salle** visible si visitée, sinon "???"
- 🏁 **Départ** et 🚪 **Sortie** clairement marqués
- 🎲 **Dés affichés uniquement** sur les salles contenant au moins un joueur (réduit la confusion)
- 📏 **Taille optimisée** : pièces, dés et éléments réduits pour éviter le scroll (plateau max 600px)

#### Panel des joueurs amélioré
- ✨ **Sélection visuelle** : joueur sélectionné surligné en bleu
- 📊 **Informations complètes** :
  - Nom + indicateur de sélection (●)
  - Points restants (avec code couleur : vert/jaune/rouge)
  - Statut (✓ Vivant, 💀 Mort, 🚫 Bloqué, 🏆 Gagnant)
  - Position actuelle (A-E / 1-5)
- 👆 **Aide contextuelle** : "Cliquez sur un joueur"

### 💾 Persistance des données

#### Sauvegarde automatique des joueurs
- 💾 Les **noms des joueurs** sont sauvegardés dans **localStorage**
- Lors d'une **nouvelle partie**, la liste des joueurs est **pré-remplie**
- Plus besoin de retaper les noms à chaque fois !
- Idéal pour jouer plusieurs parties avec le même groupe

### 🎯 Améliorations UX

#### Auto-sélection du premier joueur
- Le **premier joueur** est automatiquement sélectionné au démarrage de la partie
- Prêt à jouer immédiatement

#### Feedback visuel
- Boutons désactivés si action impossible
- Couleurs cohérentes (rouge = danger, vert = succès, jaune = warning, bleu = action)
- Animations au survol des boutons

#### Organisation de l'interface
- Layout responsive adapté aux écrans larges
- Panneau de gauche : joueurs
- Centre : plateau de jeu
- Bas : actions du joueur sélectionné
- Scrollbars personnalisées (dark mode)

---

## 🔧 Modifications techniques

### Backend
- Génération aléatoire des coins de départ/sortie dans `Game.php:generateBoard()`
- Timer étendu dans `Game.php:getRemainingTime()`

### Frontend
- Nouveau système de sélection de joueur
- Composant `game-board` entièrement refait
- Ajout de `watch` pour localStorage
- Mapping des couleurs par direction

### CSS
- +200 lignes de styles pour la nouvelle interface
- Classes pour portes colorées, dés, actions
- Améliorations responsive

### Documentation
- Cahier des charges mis à jour avec couleurs
- Ajout de ce CHANGELOG

---

## 🚀 Prochaines étapes possibles

- [ ] Animations de déplacement des joueurs
- [ ] Sons et effets
- [ ] Cartes "Futurs possibles"
- [ ] WebSockets pour synchronisation temps réel multi-devices
- [ ] Historique des tours
- [ ] Export des statistiques de partie

---

**Version actuelle** : POC v1.1
**Dernière mise à jour** : 2025-10-03
