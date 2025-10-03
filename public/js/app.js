const { createApp } = Vue;

const API_BASE = '/api.php';

// Configuration du jeu
const GAME_CONFIG = {
    TURN_TIMER_SECONDS: 600,  // 10 minutes par tour
    MAX_TURNS: 15              // Nombre maximum de tours
};

createApp({
    data() {
        return {
            screen: 'registration', // registration, game, end
            players: JSON.parse(localStorage.getItem('trapped_players') || '[]'), // Charger depuis localStorage
            newPlayerName: '',
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
            }
        };
    },

    watch: {
        // Sauvegarder automatiquement les joueurs dans localStorage
        players: {
            handler(newPlayers) {
                localStorage.setItem('trapped_players', JSON.stringify(newPlayers));
            },
            deep: true
        }
    },

    methods: {
        // Enregistrement des joueurs
        addPlayer() {
            const name = this.newPlayerName.trim();
            if (!name) return;

            if (this.players.length >= 8) {
                this.error = 'Maximum 8 joueurs';
                return;
            }

            if (this.players.includes(name)) {
                this.error = 'Ce nom est déjà pris';
                return;
            }

            this.players.push(name);
            this.newPlayerName = '';
            this.error = '';
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
                    body: JSON.stringify({ players: this.players })
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
                    this.remainingTime = data.data.remainingTime;
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

        async giveUp(playerId) {
            if (!confirm('Êtes-vous sûr de vouloir abandonner la partie ?')) {
                return;
            }

            try {
                const response = await fetch(`${API_BASE}/player/${playerId}/give-up`, {
                    method: 'POST'
                });

                const data = await response.json();

                if (data.success) {
                    await this.updateGameState();
                    alert(data.data.message);
                } else {
                    alert(data.error);
                }
            } catch (e) {
                console.error('Erreur abandon:', e);
            }
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
                await this.nextTurn();
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

        resetGame() {
            this.screen = 'registration';
            // Ne pas réinitialiser this.players pour garder les joueurs précédents
            this.newPlayerName = '';
            this.error = '';
            this.gameId = null;
            this.game = null;
            this.remainingTime = GAME_CONFIG.TURN_TIMER_SECONDS;
            this.endData = null;
        },

        // Helpers
        formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs.toString().padStart(2, '0')}`;
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
                    popupStyle: {
                        top: '0px'
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
                maxTurns() {
                    return GAME_CONFIG.MAX_TURNS;
                }
            },
            methods: {
                formatTime(seconds) {
                    const mins = Math.floor(seconds / 60);
                    const secs = seconds % 60;
                    return `${mins}:${secs.toString().padStart(2, '0')}`;
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
                onPlayerMouseEnter(event, player) {
                    // Les joueurs morts ne peuvent pas jouer
                    if (player.status === 'dead' || player.status === 'winner') return;
                    // Ne pas afficher la popup si le joueur a déjà fait son choix
                    if (player.hasChosen) return;
                    if (this.hideTimeout) {
                        clearTimeout(this.hideTimeout);
                        this.hideTimeout = null;
                    }
                    const rect = event.currentTarget.getBoundingClientRect();

                    // Calculer la position de la popup en évitant qu'elle sorte de l'écran
                    const popupHeight = 400; // Hauteur approximative de la popup
                    const viewportHeight = window.innerHeight;

                    // Si la popup dépasserait en bas, la positionner au-dessus
                    if (rect.bottom + popupHeight > viewportHeight) {
                        this.popupStyle.top = `${Math.max(10, rect.bottom - popupHeight)}px`;
                    } else {
                        this.popupStyle.top = `${rect.top}px`;
                    }

                    this.hoveredPlayer = player;
                },
                onPlayerMouseLeave() {
                    this.hideTimeout = setTimeout(() => {
                        this.hoveredPlayer = null;
                    }, 100);
                },
                onPopupMouseEnter() {
                    if (this.hideTimeout) {
                        clearTimeout(this.hideTimeout);
                        this.hideTimeout = null;
                    }
                },
                onPopupMouseLeave() {
                    this.hoveredPlayer = null;
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
                }
            },
            template: `
                <div class="game-container">
                    <div class="players-panel">
                        <div class="timer-panel">
                            <div class="timer-section">
                                <div class="turn-label">Tour {{ game.currentTurn }}/{{ maxTurns }}</div>
                                <div :class="['timer-display', {danger: remainingTime < 60}]">
                                    ⏱️ {{ formatTime(remainingTime) }}
                                </div>
                            </div>

                            <!-- Barre de progression des joueurs -->
                            <div class="progress-section">
                                <div class="progress-label">
                                    {{ game.players.filter(p => (p.status === 'alive' || p.status === 'blocked') && p.hasChosen).length }}
                                    {{ game.players.filter(p => (p.status === 'alive' || p.status === 'blocked') && p.hasChosen).length > 1 ? 'joueurs vivants ont joué' : 'joueur vivant a joué' }}
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

                        <h2 class="players-title">Joueurs</h2>
                        <div class="players-list-container">
                            <div v-for="player in game.players" :key="player.id"
                                 :class="['player-card', player.status, {played: player.hasChosen, 'popup-open': hoveredPlayer && hoveredPlayer.id === player.id}]"
                                 @mouseenter="(e) => onPlayerMouseEnter(e, player)"
                                 @mouseleave="onPlayerMouseLeave">
                                <div class="player-header">
                                    <div class="player-name">{{ player.name }}</div>
                                    <div :class="['player-points', pointsClass(player.points)]">{{ player.points }} pts</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popin d'actions - en dehors du panneau -->
                    <div v-if="hoveredPlayer" class="player-actions-popup" :style="popupStyle"
                         @mouseenter="onPopupMouseEnter"
                         @mouseleave="onPopupMouseLeave">
                        <div class="popup-title">
                            Actions de {{ hoveredPlayer.name }}
                            <span v-if="hoveredPlayer.hasChosen" style="color: var(--accent-success); margin-left: 8px;">✓ Choix fait</span>
                        </div>

                        <!-- Bouton abandonner (visible seulement si quelqu'un est dans la sortie) -->
                        <div v-if="someoneInExit" class="give-up-action">
                            <button @click="$emit('give-up', hoveredPlayer.id)"
                                    class="btn-give-up"
                                    :disabled="hoveredPlayer.hasChosen">
                                💀 Abandonner
                            </button>
                        </div>

                        <!-- Grille directionnelle (comme un clavier) -->
                        <div class="directions-grid">
                            <!-- Nord -->
                            <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'north')" :key="door.id"
                                 class="direction-cell north"
                                 :class="{
                                     selected: hoveredPlayer.chosenDoorId === door.id,
                                     disabled: hoveredPlayer.hasChosen || door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))
                                 }"
                                 @click="!hoveredPlayer.hasChosen && door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                                <div class="direction-icon">↑</div>
                                <div class="direction-info">
                                    <span class="direction-capacity" :style="{backgroundColor: doorColors.north.color}">{{ door.diceResult }}</span>
                                    <span v-if="!door.isOpen && door.diceResult > 0" class="direction-key">🔑-1</span>
                                </div>
                            </div>

                            <!-- Ouest -->
                            <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'west')" :key="door.id"
                                 class="direction-cell west"
                                 :class="{
                                     selected: hoveredPlayer.chosenDoorId === door.id,
                                     disabled: hoveredPlayer.hasChosen || door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))
                                 }"
                                 @click="!hoveredPlayer.hasChosen && door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                                <div class="direction-icon">←</div>
                                <div class="direction-info">
                                    <span class="direction-capacity" :style="{backgroundColor: doorColors.west.color}">{{ door.diceResult }}</span>
                                    <span v-if="!door.isOpen && door.diceResult > 0" class="direction-key">🔑-1</span>
                                </div>
                            </div>

                            <!-- Centre : Rester ici -->
                            <div class="direction-cell center"
                                 :class="{
                                     selected: hoveredPlayer.hasChosen && !hoveredPlayer.chosenDoorId,
                                     disabled: hoveredPlayer.hasChosen
                                 }"
                                 @click="!hoveredPlayer.hasChosen && stayInRoomAction(hoveredPlayer.id)">
                                <div class="direction-icon">●</div>
                                <div class="direction-label">Rester</div>
                            </div>

                            <!-- Est -->
                            <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'east')" :key="door.id"
                                 class="direction-cell east"
                                 :class="{
                                     selected: hoveredPlayer.chosenDoorId === door.id,
                                     disabled: hoveredPlayer.hasChosen || door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))
                                 }"
                                 @click="!hoveredPlayer.hasChosen && door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                                <div class="direction-icon">→</div>
                                <div class="direction-info">
                                    <span class="direction-capacity" :style="{backgroundColor: doorColors.east.color}">{{ door.diceResult }}</span>
                                    <span v-if="!door.isOpen && door.diceResult > 0" class="direction-key">🔑-1</span>
                                </div>
                            </div>

                            <!-- Sud -->
                            <div v-for="door in getSortedDoors(hoveredPlayer.id).filter(d => d.direction === 'south')" :key="door.id"
                                 class="direction-cell south"
                                 :class="{
                                     selected: hoveredPlayer.chosenDoorId === door.id,
                                     disabled: hoveredPlayer.hasChosen || door.diceResult === 0 || isDoorFull(door) || (!door.isOpen && !canOpenDoor(door, hoveredPlayer))
                                 }"
                                 @click="!hoveredPlayer.hasChosen && door.diceResult > 0 && !isDoorFull(door) && (door.isOpen ? chooseDoorAction(door.id, hoveredPlayer.id) : (canOpenDoor(door, hoveredPlayer) && openDoorAction(door.id, hoveredPlayer.id)))">
                                <div class="direction-icon">↓</div>
                                <div class="direction-info">
                                    <span class="direction-capacity" :style="{backgroundColor: doorColors.south.color}">{{ door.diceResult }}</span>
                                    <span v-if="!door.isOpen && door.diceResult > 0" class="direction-key">🔑-1</span>
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
                                    <div v-else-if="room.isVisited && !room.isExit" class="cost-badge">-{{ room.pointsCost }}</div>
                                    <div v-else-if="!room.isExit" class="cost-hidden">???</div>
                                </div>
                            </div>
                            <div v-if="playersInRoom(room.id).length > 0" class="room-players-list">
                                <div v-for="p in playersInRoom(room.id)" :key="p.id" class="player-dot">
                                    {{ p.status === 'dead' ? '💀' : p.name[0] }}
                                </div>
                            </div>
                            <div v-if="playersInRoom(room.id).length > 0" class="doors-container">
                                <div v-for="door in room.doors" :key="door.id" :class="['door-widget', door.direction]" :style="{borderColor: doorColors[door.direction].color}">
                                    <div class="dice-result" :style="{backgroundColor: doorColors[door.direction].color}">{{ door.diceResult }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `
        }
    }
}).mount('#app');
