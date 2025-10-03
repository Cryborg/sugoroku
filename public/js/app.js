const { createApp } = Vue;

const API_BASE = 'api.php';

// Configuration du jeu
const GAME_CONFIG = {
    TURN_TIMER_SECONDS: 120,  // 2 minutes par tour
    MAX_TURNS: 15             // Nombre maximum de tours
};

createApp({
    data() {
        return {
            screen: 'registration', // registration, game, end
            players: JSON.parse(localStorage.getItem('trapped_players') || '[]'), // Charger depuis localStorage
            newPlayerName: '',
            tempAvatar: null,
            tempGender: 'male',
            showAvatarPicker: false,
            avatarPickerGender: 'male',
            error: '',
            gameId: null,
            game: null,
            remainingTime: GAME_CONFIG.TURN_TIMER_SECONDS,
            endData: null,
            pollingInterval: null,
            timerInterval: null,
            doorColors: {
                'north': '#3b82f6', // bleu
                'south': '#10b981', // vert
                'east': '#ef4444',  // rouge
                'west': '#f59e0b'   // jaune
            },
            showPointsLossPopup: false,
            pointsLossData: null,
            playersPointsAtTurnStart: {},  // Points au début de chaque tour
            difficulty: localStorage.getItem('trapped_difficulty') || 'normal',
            freeRoomsEnabled: localStorage.getItem('trapped_freeRooms') === 'true',
            showLoadGame: false,
            savedGames: [],
            showModal: false,
            modalData: {
                title: '',
                message: '',
                confirmText: 'Confirmer',
                cancelText: 'Annuler',
                onConfirm: null
            },
            showHelp: false
        };
    },

    watch: {
        // Sauvegarder automatiquement les joueurs dans localStorage
        players: {
            handler(newPlayers) {
                localStorage.setItem('trapped_players', JSON.stringify(newPlayers));
            },
            deep: true
        },
        // Sauvegarder la difficulté
        difficulty(newValue) {
            localStorage.setItem('trapped_difficulty', newValue);
        },
        // Sauvegarder l'option pièces gratuites
        freeRoomsEnabled(newValue) {
            localStorage.setItem('trapped_freeRooms', newValue.toString());
        }
    },

    async mounted() {
        await this.loadSavedGames();
    },

    methods: {
        // Système de modale
        showConfirmModal(title, message, onConfirm, confirmText = 'Confirmer', cancelText = 'Annuler') {
            this.modalData = {
                title,
                message,
                confirmText,
                cancelText,
                onConfirm
            };
            this.showModal = true;
        },

        confirmModal() {
            if (this.modalData.onConfirm) {
                this.modalData.onConfirm();
            }
            this.showModal = false;
        },

        cancelModal() {
            this.showModal = false;
        },

        toggleHelp() {
            this.showHelp = !this.showHelp;
        },

        // Charger la liste des parties sauvegardées
        async loadSavedGames() {
            try {
                const response = await fetch(`${API_BASE}/games/list`);
                const data = await response.json();

                if (data.success) {
                    this.savedGames = data.data;
                }
            } catch (error) {
                console.error('Erreur lors du chargement des parties:', error);
            }
        },

        // Charger une partie existante
        async loadGame(gameId) {
            try {
                const response = await fetch(`${API_BASE}/game/${gameId}/state`);
                const data = await response.json();

                if (data.success) {
                    this.gameId = gameId;
                    this.game = data.data;
                    this.screen = 'game';
                    this.startTimer();
                    this.startPolling();
                }
            } catch (error) {
                console.error('Erreur lors du chargement de la partie:', error);
                this.error = 'Impossible de charger la partie';
            }
        },

        // Supprimer une partie
        async deleteGame(gameId) {
            this.showConfirmModal(
                'Supprimer la partie',
                'Êtes-vous sûr de vouloir supprimer cette partie ?',
                async () => {
                    try {
                        const response = await fetch(`${API_BASE}/game/${gameId}/delete`, {
                            method: 'DELETE'
                        });
                        const data = await response.json();

                        if (data.success) {
                            await this.loadSavedGames();
                        }
                    } catch (error) {
                        console.error('Erreur lors de la suppression:', error);
                        this.error = 'Impossible de supprimer la partie';
                    }
                },
                'Supprimer',
                'Annuler'
            );
        },

        // Formater une date
        formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-FR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },

        // Enregistrement des joueurs
        addPlayer() {
            const name = this.newPlayerName.trim();
            if (!name || !this.tempAvatar) return;

            if (this.players.length >= 8) {
                this.error = 'Maximum 8 joueurs';
                return;
            }

            if (this.players.some(p => p.name === name)) {
                this.error = 'Ce nom est déjà pris';
                return;
            }

            this.players.push({
                name: name,
                gender: this.tempGender,
                avatar: this.tempAvatar
            });

            this.newPlayerName = '';
            this.tempAvatar = null;
            this.tempGender = 'male';
            this.error = '';
        },

        openAvatarPicker() {
            this.avatarPickerGender = this.tempGender || 'male';
            this.showAvatarPicker = true;
        },

        selectAvatar(gender, avatarNumber) {
            const avatarName = `${gender}_${String(avatarNumber).padStart(2, '0')}.png`;

            // Vérifier si l'avatar est déjà utilisé
            if (this.players.some(p => p.gender === gender && p.avatar === avatarName)) {
                return; // Ne permet pas de sélectionner un avatar déjà utilisé
            }

            this.tempAvatar = avatarName;
            this.tempGender = gender;
            this.showAvatarPicker = false;
        },

        isAvatarUsed(gender, avatarNumber) {
            const avatarName = `${gender}_${String(avatarNumber).padStart(2, '0')}.png`;
            return this.players.some(p => p.gender === gender && p.avatar === avatarName);
        },

        getAvatarCount(gender) {
            // 24 avatars féminins, 23 masculins
            return gender === 'female' ? 24 : 23;
        },

        removePlayer(index) {
            this.players.splice(index, 1);
        },

        async startGame() {
            if (this.players.length < 3) {
                this.error = 'Minimum 3 joueurs requis';
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/game/create`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        players: this.players,
                        difficulty: this.difficulty,
                        freeRoomsEnabled: this.freeRoomsEnabled
                    })
                });

                const data = await response.json();

                if (data.success) {
                    this.gameId = data.data.gameId;
                    await this.launchGame();
                } else {
                    this.error = data.error;
                }
            } catch (e) {
                this.error = 'Erreur de connexion au serveur';
                console.error(e);
            }
        },

        async launchGame() {
            try {
                const response = await fetch(`${API_BASE}/game/${this.gameId}/start`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    this.game = data.data.state;

                    // Sauvegarder les points initiaux pour le tour 1
                    console.log(`[launchGame] Sauvegarde des points initiaux:`, this.game.players.map(p => `${p.name}: ${p.points}`));
                    this.playersPointsAtTurnStart = {};
                    this.game.players.forEach(player => {
                        this.playersPointsAtTurnStart[player.id] = player.points;
                    });

                    this.screen = 'game';
                    this.startPolling();
                    this.startTimer();
                } else {
                    this.error = data.error;
                }
            } catch (e) {
                this.error = 'Erreur lors du démarrage';
                console.error(e);
            }
        },

        // Polling pour synchroniser l'état du jeu
        startPolling() {
            this.pollingInterval = setInterval(async () => {
                await this.updateGameState();
                await this.checkEndConditions();
                await this.checkNextTurn(); // Vérifier si on doit passer au tour suivant
            }, 2000); // Poll toutes les 2 secondes
        },

        stopPolling() {
            if (this.pollingInterval) {
                clearInterval(this.pollingInterval);
                this.pollingInterval = null;
            }
        },

        async updateGameState() {
            try {
                const response = await fetch(`${API_BASE}/game/${this.gameId}/state`);
                const data = await response.json();

                if (data.success) {
                    // Forcer la réactivité en créant un nouvel objet
                    this.game = { ...data.data };
                    // Ne pas écraser le timer local qui tourne déjà côté client
                }
            } catch (e) {
                console.error('Erreur de polling:', e);
            }
        },

        // Timer visuel
        startTimer() {
            this.timerInterval = setInterval(() => {
                if (this.remainingTime > 0) {
                    this.remainingTime--;
                }

                // Vérifier auto-résolution à 0
                if (this.remainingTime === 0) {
                    this.checkAutoResolve();
                }
            }, 1000);
        },

        stopTimer() {
            if (this.timerInterval) {
                clearInterval(this.timerInterval);
                this.timerInterval = null;
            }
        },

        async checkAutoResolve() {
            try {
                const response = await fetch(`${API_BASE}/turn/${this.gameId}/check`);
                const data = await response.json();

                if (data.success && data.data.timerExpired) {
                    // Le tour a été résolu automatiquement
                    await this.updateGameState();
                    this.remainingTime = GAME_CONFIG.TURN_TIMER_SECONDS;
                }
            } catch (e) {
                console.error('Erreur auto-resolve:', e);
            }
        },

        // Actions des joueurs
        async openDoor(doorId, playerId) {
            try {
                const response = await fetch(`${API_BASE}/door/${doorId}/open`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ playerId })
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Erreur ouverture porte:', e);
            }
        },

        async chooseDoor(playerId, doorId) {
            try {
                const response = await fetch(`${API_BASE}/player/${playerId}/choose-door`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ doorId })
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Erreur choix porte:', e);
            }
        },

        async stayInRoom(playerId) {
            try {
                // Enregistrer un choix avec doorId null = rester ici
                const response = await fetch(`${API_BASE}/player/${playerId}/stay`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Erreur rester ici:', e);
            }
        },

        goHome() {
            this.showConfirmModal(
                'Retour à l\'accueil',
                'Êtes-vous sûr de vouloir retourner à l\'accueil ? La partie est sauvegardée automatiquement.',
                async () => {
                    this.stopPolling();
                    this.stopTimer();
                    this.screen = 'registration';
                    this.gameId = null;
                    this.game = null;
                    await this.loadSavedGames();
                }
            );
        },

        async giveUp(playerId) {
            this.showConfirmModal(
                'Abandonner',
                'Êtes-vous sûr de vouloir abandonner la partie ?',
                async () => {
                    try {
                        const response = await fetch(`${API_BASE}/player/${playerId}/give-up`, {
                            method: 'POST'
                        });

                        const data = await response.json();

                        if (data.success) {
                            await this.updateGameState();
                        }
                    } catch (e) {
                        console.error('Erreur abandon:', e);
                    }
                }
            );
        },

        async freePlayer(liberatorId, blockedPlayerId) {
            try {
                const response = await fetch(`${API_BASE}/player/${blockedPlayerId}/free`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ liberatorId })
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Erreur libération:', e);
            }
        },

        // Vérification fin de partie
        async checkEndConditions() {
            try {
                const response = await fetch(`${API_BASE}/game/${this.gameId}/check-end`);
                const data = await response.json();

                if (data.success && data.data.gameOver) {
                    this.endGame(data.data);
                }
            } catch (e) {
                console.error('Erreur check end:', e);
            }
        },

        async checkNextTurn() {
            if (!this.game || this.game.status !== 'playing') return;

            // Vérifier si tous les joueurs vivants ont choisi
            const alivePlayers = this.game.players.filter(p => p.status === 'alive');
            const playersWhoChose = alivePlayers.filter(p => p.hasChosen);

            // Si tous les joueurs vivants ont fait leur action, passer au tour suivant
            if (alivePlayers.length > 0 && playersWhoChose.length === alivePlayers.length) {
                const currentTurn = this.game.currentTurn;

                // Points AVANT (début du tour, sauvegardés dans updateGameState)
                const playersBeforeTurn = this.game.players.map(p => ({
                    id: p.id,
                    name: p.name,
                    points: this.playersPointsAtTurnStart[p.id] ?? p.points,
                    animatedPoints: this.playersPointsAtTurnStart[p.id] ?? p.points
                }));

                console.log(`[checkNextTurn] Tour ${currentTurn} terminé`);
                console.log(`[checkNextTurn] Points AVANT (début tour):`, playersBeforeTurn.map(p => `${p.name}: ${p.points}`));
                console.log(`[checkNextTurn] Points ACTUELS (avant nextTurn):`, this.game.players.map(p => `${p.name}: ${p.points}`));

                // Appeler nextTurn pour passer au tour suivant (déduit les points de salle côté serveur)
                await this.nextTurn();

                // Points APRÈS (après déduction des points de salle par le serveur)
                const playersAfterTurn = this.game.players.map(p => ({
                    id: p.id,
                    name: p.name,
                    points: p.points
                }));

                console.log(`[checkNextTurn] Points APRÈS (après nextTurn):`, playersAfterTurn.map(p => `${p.name}: ${p.points}`));

                // Afficher la popup de perte de points
                await this.showPointsLoss(currentTurn, playersBeforeTurn, playersAfterTurn);
            }
        },

        async nextTurn() {
            try {
                const response = await fetch(`${API_BASE}/turn/${this.gameId}/next`, {
                    method: 'POST'
                });
                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                    this.remainingTime = GAME_CONFIG.TURN_TIMER_SECONDS;

                    // Sauvegarder les points du NOUVEAU tour (pour la prochaine fois)
                    console.log(`[nextTurn] Sauvegarde des points pour le tour ${this.game.currentTurn}:`, this.game.players.map(p => `${p.name}: ${p.points}`));
                    console.log(`[nextTurn] Salles visitées:`, this.game.rooms.filter(r => r.isVisited).map(r => `${r.positionX},${r.positionY} (exit:${r.isExit})`));
                    this.playersPointsAtTurnStart = {};
                    this.game.players.forEach(player => {
                        this.playersPointsAtTurnStart[player.id] = player.points;
                    });
                } else {
                    console.error('Erreur passage tour:', data.error);
                }
            } catch (e) {
                console.error('Erreur passage tour:', e);
            }
        },

        endGame(endData) {
            this.stopPolling();
            this.stopTimer();
            this.endData = endData;
            this.screen = 'end';
        },

        async resetGame() {
            this.screen = 'registration';
            // Ne pas réinitialiser this.players pour garder les joueurs précédents
            this.newPlayerName = '';
            this.error = '';
            this.gameId = null;
            this.game = null;
            this.remainingTime = GAME_CONFIG.TURN_TIMER_SECONDS;
            this.endData = null;
            await this.loadSavedGames();
        },

        async showPointsLoss(turn, oldPlayersData, newPlayersData) {
            const startTime = Date.now();
            console.log(`[${Date.now() - startTime}ms] === DÉBUT showPointsLoss ===`);
            console.log(`[${Date.now() - startTime}ms] Points d'origine:`, oldPlayersData.map(p => `${p.name}: ${p.points}`));
            console.log(`[${Date.now() - startTime}ms] Points finaux:`, newPlayersData.map(p => `${p.name}: ${p.points}`));

            // Afficher la popup avec les points d'origine + calculer les pertes + statut final
            this.pointsLossData = {
                turn,
                players: oldPlayersData.map(oldPlayer => {
                    const newPlayer = newPlayersData.find(p => p.id === oldPlayer.id);
                    const pointsLost = oldPlayer.points - (newPlayer?.points ?? oldPlayer.points);

                    // Récupérer le statut final depuis this.game.players (après nextTurn)
                    const currentPlayer = this.game?.players?.find(p => p.id === oldPlayer.id);
                    const finalStatus = currentPlayer?.status ?? oldPlayer.status;

                    return {
                        ...oldPlayer,
                        pointsLost,
                        showLoss: false,
                        finalStatus
                    };
                })
            };
            this.showPointsLossPopup = true;
            console.log(`[${Date.now() - startTime}ms] Popup affichée avec points:`, this.pointsLossData.players.map(p => `${p.name}: ${p.animatedPoints}`));

            // Attendre 2 secondes avant de commencer la décrémentation
            console.log(`[${Date.now() - startTime}ms] Attente 2 secondes...`);
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Activer l'animation de perte (fade up)
            this.pointsLossData.players.forEach(p => p.showLoss = true);

            // Trouver le nombre maximum de points à décrémenter
            let maxPointsToRemove = 0;
            for (const oldPlayer of oldPlayersData) {
                const newPlayer = newPlayersData.find(p => p.id === oldPlayer.id);
                if (newPlayer) {
                    const diff = oldPlayer.points - newPlayer.points;
                    maxPointsToRemove = Math.max(maxPointsToRemove, diff);
                }
            }

            console.log(`[${Date.now() - startTime}ms] DÉBUT ANIMATION - maxPointsToRemove: ${maxPointsToRemove}`);

            // Animer chaque point un par un
            for (let i = 1; i <= maxPointsToRemove; i++) {
                this.pointsLossData.players = oldPlayersData.map(oldPlayer => {
                    const newPlayer = newPlayersData.find(p => p.id === oldPlayer.id);
                    if (!newPlayer) return oldPlayer;

                    const totalDiff = oldPlayer.points - newPlayer.points;
                    const newValue = Math.max(newPlayer.points, oldPlayer.points - i);

                    // Récupérer le statut final depuis this.game.players
                    const currentPlayer = this.game?.players?.find(p => p.id === oldPlayer.id);
                    const finalStatus = currentPlayer?.status ?? oldPlayer.status;

                    return {
                        ...oldPlayer,
                        animatedPoints: newValue,
                        pointsLost: totalDiff,
                        showLoss: true,
                        finalStatus
                    };
                });

                if (i === 1 || i === maxPointsToRemove) {
                    console.log(`[${Date.now() - startTime}ms] Frame ${i}/${maxPointsToRemove}:`, this.pointsLossData.players.map(p => `${p.name}: ${p.animatedPoints}`));
                }

                // 150ms entre chaque décrémentation (environ 6-7 points par seconde)
                await new Promise(resolve => setTimeout(resolve, 150));
            }

            console.log(`[${Date.now() - startTime}ms] FIN ANIMATION`);
            console.log(`[${Date.now() - startTime}ms] Points finaux affichés:`, this.pointsLossData.players.map(p => `${p.name}: ${p.animatedPoints}`));

            // Attendre 2 secondes après la fin de l'animation (temps pour le fade up de finir)
            console.log(`[${Date.now() - startTime}ms] Attente 2 secondes avant fermeture...`);
            await new Promise(resolve => setTimeout(resolve, 2000));

            console.log(`[${Date.now() - startTime}ms] Fermeture popup`);
            this.showPointsLossPopup = false;
            this.pointsLossData = null;
        },

        // Helpers
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        },

        getPlayerPoints(playerId) {
            const player = this.game?.players?.find(p => p.id === playerId);
            return player?.points ?? 0;
        }
    },

    beforeUnmount() {
        this.stopPolling();
        this.stopTimer();
    },

    components: {
        'game-board': {
            props: ['game', 'remainingTime'],
            emits: ['open-door', 'choose-door', 'free-player', 'stay-in-room', 'give-up'],
            data() {
                return {
                    doorColors: {
                        'north': { color: '#3b82f6', name: 'Bleu' },
                        'south': { color: '#10b981', name: 'Vert' },
                        'east': { color: '#ef4444', name: 'Rouge' },
                        'west': { color: '#f59e0b', name: 'Jaune' }
                    },
                    doorIcons: {
                        'north': '↑',
                        'south': '↓',
                        'east': '→',
                        'west': '←'
                    },
                    hoveredPlayer: null,
                    hoveredPlayerBadge: null,
                    controlsStyle: {
                        top: '0px',
                        left: '0px'
                    },
                    hideTimeout: null
                };
            },
            computed: {
                orderedRooms() {
                    return this.game.rooms.slice().sort((a, b) => {
                        if (a.positionY !== b.positionY) return a.positionY - b.positionY;
                        return a.positionX - b.positionX;
                    });
                },
                someoneInExit() {
                    // Trouver la salle de sortie
                    const exitRoom = this.game.rooms.find(r => r.isExit);
                    if (!exitRoom) return false;

                    // La salle de sortie doit être visitée (visible) pour éviter le spoil
                    if (!exitRoom.isVisited) return false;

                    // Vérifier si au moins un joueur vivant est dans la sortie
                    return this.game.players.some(p =>
                        p.status === 'alive' &&
                        p.currentRoomId === exitRoom.id &&
                        p.points >= 1
                    );
                },
                allPlayersHavePlayed() {
                    if (!this.game) return false;
                    const alivePlayers = this.game.players.filter(p => p.status === 'alive');
                    if (alivePlayers.length === 0) return true;
                    const playersWhoChose = alivePlayers.filter(p => p.hasChosen);
                    return playersWhoChose.length === alivePlayers.length;
                },
                maxTurns() {
                    return GAME_CONFIG.MAX_TURNS;
                }
            },
            methods: {
                formatTime(seconds) {
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
                },
                pointsClass(points) {
                    if (points <= 3) return 'critical';
                    if (points <= 6) return 'low';
                    return '';
                },
                statusLabel(status) {
                    const labels = {
                        'alive': '✓ Vivant',
                        'dead': '💀 Mort',
                        'blocked': '🚫 Bloqué',
                        'winner': '🏆 Gagnant'
                    };
                    return labels[status] || status;
                },
                onPlayerBadgeEnter(event, player) {
                    // Les joueurs morts ou qui ont déjà choisi ne peuvent pas jouer
                    if (player.status === 'dead' || player.status === 'winner' || player.hasChosen) return;

                    if (this.hideTimeout) {
                        clearTimeout(this.hideTimeout);
                        this.hideTimeout = null;
                    }

                    const badge = event.currentTarget;
                    const rect = badge.getBoundingClientRect();

                    // Centrer les contrôles sur le badge
                    this.controlsStyle.top = `${rect.top + rect.height / 2}px`;
                    this.controlsStyle.left = `${rect.left + rect.width / 2}px`;

                    this.hoveredPlayer = player;
                    this.hoveredPlayerBadge = badge;
                },
                onPlayerBadgeLeave() {
                    this.hideTimeout = setTimeout(() => {
                        this.hoveredPlayer = null;
                        this.hoveredPlayerBadge = null;
                    }, 200);
                },
                onPlayerBadgeTouch(event, player) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Si on touche le même joueur, on ferme
                    if (this.hoveredPlayer && this.hoveredPlayer.id === player.id) {
                        this.hoveredPlayer = null;
                        this.hoveredPlayerBadge = null;
                        return;
                    }

                    // Sinon on affiche les contrôles
                    this.onPlayerBadgeEnter(event, player);
                },
                onControlsEnter() {
                    if (this.hideTimeout) {
                        clearTimeout(this.hideTimeout);
                        this.hideTimeout = null;
                    }
                },
                onControlsLeave() {
                    this.hoveredPlayer = null;
                    this.hoveredPlayerBadge = null;
                },
                getPlayerRoom(playerId) {
                    const player = this.game.players.find(p => p.id === playerId);
                    if (!player) return null;
                    return this.game.rooms.find(r => r.id === player.currentRoomId);
                },
                getSortedDoors(playerId) {
                    const room = this.getPlayerRoom(playerId);
                    if (!room || !room.doors) return [];

                    // Ordre : north, south, west, east
                    const directionOrder = { 'north': 1, 'south': 2, 'west': 3, 'east': 4 };
                    return room.doors.slice().sort((a, b) =>
                        directionOrder[a.direction] - directionOrder[b.direction]
                    );
                },
                roomClass(room) {
                    let classes = ['room'];
                    if (room.isStart) classes.push('start');
                    if (room.isExit) classes.push('exit');
                    if (room.isVisited) classes.push('visited');
                    if (this.playersInRoom(room.id).length > 0) classes.push('has-players');
                    return classes.join(' ');
                },
                playersInRoom(roomId) {
                    return this.game.players.filter(p => p.currentRoomId === roomId);
                },
                getRoomPosition(roomId) {
                    const room = this.game.rooms.find(r => r.id === roomId);
                    if (!room) return '?';
                    const letter = String.fromCharCode(65 + room.positionY); // A-E
                    const number = room.positionX + 1; // 1-5
                    return `${letter}${number}`;
                },
                getRoomLabel(room) {
                    const letter = String.fromCharCode(65 + room.positionY); // A-E
                    const number = room.positionX + 1; // 1-5
                    return `${letter}${number}`;
                },
                canOpenDoor(door, player) {
                    if (door.isOpen) return false;
                    return player && player.points >= 1 && player.status === 'alive';
                },
                isDoorFull(door) {
                    // Compter combien de joueurs ont déjà choisi cette porte
                    const playersWhoChose = this.game.players.filter(p => p.chosenDoorId === door.id);
                    return playersWhoChose.length >= door.diceResult;
                },
                openDoorAction(doorId, playerId) {
                    this.$emit('open-door', doorId, playerId);
                    this.hoveredPlayer = null; // Fermer la popup
                },
                chooseDoorAction(doorId, playerId) {
                    this.$emit('choose-door', playerId, doorId);
                    this.hoveredPlayer = null; // Fermer la popup
                },
                stayInRoomAction(playerId) {
                    this.$emit('stay-in-room', playerId);
                    this.hoveredPlayer = null; // Fermer la popup
                },
                getHappinessPercentage(player) {
                    const positive = player.happinessPositive || 0;
                    const negative = player.happinessNegative || 0;
                    const total = positive + negative;

                    if (total === 0) {
                        return 50; // Neutre
                    }

                    // Formule : bonheur / (bonheur + malheur) * 100
                    return Math.round((positive / total) * 100);
                },
                getHappinessLabel(player) {
                    const positive = player.happinessPositive || 0;
                    const negative = player.happinessNegative || 0;

                    if (positive === 0 && negative === 0) {
                        return '50% (neutre)';
                    }

                    const percentage = this.getHappinessPercentage(player);
                    return `${percentage}% (+${positive}/-${negative})`;
                }
            },
            template: `
                <div class="game-container-fullscreen">
                    <!-- Header avec timer et infos -->
                    <div class="game-header">
                        <!-- Boutons navigation -->
                        <div class="game-nav-buttons">
                            <button @click="$emit('go-home')" class="btn-home" title="Retour à l'accueil">
                                🏠
                            </button>
                            <button @click="$emit('toggle-help')" class="btn-help" title="Aide">
                                ?
                            </button>
                        </div>

                        <!-- Tour -->
                        <div class="turn-section">
                            <div class="turn-label">Tour {{ game.currentTurn }}/{{ maxTurns }}</div>
                        </div>

                        <!-- Timer -->
                        <div :class="['timer-section', {danger: remainingTime < 60}]">
                            ⏱️ {{ formatTime(remainingTime) }}
                        </div>

                        <!-- Barre de progression des joueurs -->
                        <div class="progress-section">
                            <div class="progress-label">
                                {{ game.players.filter(p => (p.status === 'alive' || p.status === 'blocked') && p.hasChosen).length }}
                                {{ game.players.filter(p => (p.status === 'alive' || p.status === 'blocked') && p.hasChosen).length > 1 ? 'joueurs ont joué' : 'joueur a joué' }}
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill"
                                     :style="{width: (game.players.filter(p => (p.status === 'alive' || p.status === 'blocked') && p.hasChosen).length / Math.max(1, game.players.filter(p => p.status === 'alive' || p.status === 'blocked').length) * 100) + '%'}">
                                </div>
                                <div class="progress-dots">
                                    <div v-for="p in game.players.filter(p => p.status === 'alive' || p.status === 'blocked')" :key="p.id"
                                         :class="['progress-dot', {active: p.hasChosen}]"
                                         :title="p.name">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="board-grid">
                        <div v-for="room in orderedRooms" :key="room.id" :class="roomClass(room)">
                            <div class="room-header-row">
                                <div class="room-label">{{ getRoomLabel(room) }}</div>
                                <div class="room-info-right">
                                    <div v-if="room.isStart" class="start-flag">🏁</div>
                                    <div v-else-if="room.isExit && room.isVisited" class="exit-flag">🏁</div>
                                    <div v-else-if="room.isVisited && !room.isExit" class="cost-badge">-{{ room.pointsCost }}</div>
                                    <div v-else-if="!room.isExit" class="cost-hidden">???</div>
                                </div>
                            </div>
                            <div v-if="playersInRoom(room.id).length > 0" class="room-players-list">
                                <div v-for="p in playersInRoom(room.id)" :key="p.id"
                                     :class="['player-badge-interactive', {
                                         'player-badge-active': hoveredPlayer && hoveredPlayer.id === p.id,
                                         'player-badge-dead': p.status === 'dead',
                                         'player-badge-played': p.hasChosen,
                                         'happy-bounce': room.isExit && room.isVisited && allPlayersHavePlayed
                                     }]"
                                     @mouseenter="(e) => onPlayerBadgeEnter(e, p)"
                                     @mouseleave="onPlayerBadgeLeave"
                                     @touchstart="(e) => onPlayerBadgeTouch(e, p)"
                                     :title="p.name + ' - ' + p.points + ' pts'">
                                    <span v-if="p.status === 'dead'" class="player-badge-death">💀</span>
                                    <img v-else :src="'avatars/' + p.gender + '/' + p.avatar" class="player-badge-img" :alt="p.name">
                                </div>
                            </div>
                            <div v-if="playersInRoom(room.id).length > 0" class="doors-container">
                                <div v-for="door in room.doors" :key="door.id" :class="['door-widget', door.direction]" :style="{borderColor: doorColors[door.direction].color}">
                                    <div class="dice-result" :style="{backgroundColor: doorColors[door.direction].color}">{{ door.diceResult }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contrôles directionnels autour du badge -->
                    <div v-if="hoveredPlayer" class="player-controls-overlay" :style="controlsStyle"
                         @mouseenter="onControlsEnter"
                         @mouseleave="onControlsLeave">
                        <!-- Nom et avatar du joueur -->
                        <div class="player-controls-name">
                            <img :src="'avatars/' + hoveredPlayer.gender + '/' + hoveredPlayer.avatar" class="player-controls-avatar" :alt="hoveredPlayer.name">
                            <span>{{ hoveredPlayer.name }}</span>
                        </div>

                        <!-- Nord -->
                        <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'north')" :key="'n-'+door.id"
                             class="direction-button direction-north"
                             :class="{disabled: door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))}"
                             @click="door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                            <div class="direction-arrow">↑</div>
                            <div class="direction-details">
                                <span class="direction-capacity" :style="{backgroundColor: doorColors.north.color}">{{ door.diceResult }}</span>
                                <span v-if="door.happinessModifier !== 0" class="direction-happiness" :class="door.happinessModifier > 0 ? 'positive' : 'negative'">
                                    {{ door.happinessModifier > 0 ? '+' : '' }}{{ door.happinessModifier }}
                                </span>
                            </div>
                        </div>

                        <!-- Sud -->
                        <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'south')" :key="'s-'+door.id"
                             class="direction-button direction-south"
                             :class="{disabled: door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))}"
                             @click="door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                            <div class="direction-arrow">↓</div>
                            <div class="direction-details">
                                <span class="direction-capacity" :style="{backgroundColor: doorColors.south.color}">{{ door.diceResult }}</span>
                                <span v-if="door.happinessModifier !== 0" class="direction-happiness" :class="door.happinessModifier > 0 ? 'positive' : 'negative'">
                                    {{ door.happinessModifier > 0 ? '+' : '' }}{{ door.happinessModifier }}
                                </span>
                            </div>
                        </div>

                        <!-- Ouest -->
                        <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'west')" :key="'w-'+door.id"
                             class="direction-button direction-west"
                             :class="{disabled: door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))}"
                             @click="door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                            <div class="direction-arrow">←</div>
                            <div class="direction-details">
                                <span class="direction-capacity" :style="{backgroundColor: doorColors.west.color}">{{ door.diceResult }}</span>
                                <span v-if="door.happinessModifier !== 0" class="direction-happiness" :class="door.happinessModifier > 0 ? 'positive' : 'negative'">
                                    {{ door.happinessModifier > 0 ? '+' : '' }}{{ door.happinessModifier }}
                                </span>
                            </div>
                        </div>

                        <!-- Est -->
                        <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'east')" :key="'e-'+door.id"
                             class="direction-button direction-east"
                             :class="{disabled: door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))}"
                             @click="door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                            <div class="direction-arrow">→</div>
                            <div class="direction-details">
                                <span class="direction-capacity" :style="{backgroundColor: doorColors.east.color}">{{ door.diceResult }}</span>
                                <span v-if="door.happinessModifier !== 0" class="direction-happiness" :class="door.happinessModifier > 0 ? 'positive' : 'negative'">
                                    {{ door.happinessModifier > 0 ? '+' : '' }}{{ door.happinessModifier }}
                                </span>
                            </div>
                        </div>

                        <!-- Centre : Rester -->
                        <div class="direction-button direction-center"
                             @click="stayInRoomAction(hoveredPlayer.id)">
                            <div class="direction-stay-label">Rester</div>
                        </div>

                        <!-- Bouton Abandonner (si disponible) -->
                        <div v-if="someoneInExit" class="direction-button direction-give-up"
                             @click="$emit('give-up', hoveredPlayer.id)">
                            💀
                        </div>
                    </div>
                </div>
            `
        }
    }
}).mount('#app');
