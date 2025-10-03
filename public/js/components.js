// Composant GameBoard - Interface de jeu complète
export const GameBoard = {
    props: ['game', 'remainingTime', 'selectedPlayer'],
    emits: ['open-door', 'choose-door', 'free-player', 'select-player'],
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
            }
        };
    },
    template: `
        <div class="game-container">
            <!-- Panel des joueurs -->
            <div class="players-panel">
                <h2>Joueurs</h2>
                <div v-for="player in game.players" :key="player.id"
                     :class="['player-card', player.status, {selected: selectedPlayer === player.id}]"
                     @click="$emit('select-player', player.id)">
                    <div class="player-header">
                        <div class="player-name">
                            {{ player.name }}
                            <span v-if="selectedPlayer === player.id" class="badge-selected">●</span>
                        </div>
                        <div :class="['player-points', pointsClass(player.points)]">
                            {{ player.points }} pts
                        </div>
                    </div>
                    <span :class="['player-status', player.status]">
                        {{ statusLabel(player.status) }}
                    </span>
                    <div class="player-room">
                        Salle: {{ getRoomPosition(player.currentRoomId) }}
                    </div>
                </div>
                <div class="player-help">
                    Cliquez sur un joueur pour le sélectionner
                </div>
            </div>

            <!-- Plateau principal -->
            <div class="main-content">
                <div class="game-header">
                    <div class="turn-info">
                        <div class="turn-number">Tour <strong>{{ game.currentTurn }}/15</strong></div>
                        <div :class="['timer', {danger: remainingTime < 60}]">
                            ⏱️ {{ formatTime(remainingTime) }}
                        </div>
                    </div>
                </div>

                <div class="board-grid">
                    <div v-for="room in orderedRooms" :key="room.id"
                         :class="roomClass(room)"
                         @click="selectRoom(room)">

                        <!-- Label de la salle -->
                        <div class="room-label">
                            <span v-if="room.isStart">🏁 DÉPART</span>
                            <span v-else-if="room.isExit">🚪 SORTIE</span>
                            <span v-else>({{ room.positionX }},{{ room.positionY }})</span>
                        </div>

                        <!-- Coût de la salle -->
                        <div class="room-cost-display">
                            <div v-if="room.isVisited && !room.isStart && !room.isExit" class="cost-badge">
                                -{{ room.pointsCost }} pts
                            </div>
                            <div v-else-if="!room.isStart && !room.isExit" class="cost-hidden">
                                ???
                            </div>
                        </div>

                        <!-- Joueurs présents -->
                        <div v-if="playersInRoom(room.id).length > 0" class="room-players-list">
                            <div v-for="p in playersInRoom(room.id)" :key="p.id" class="player-dot" :title="p.name">
                                {{ p.name[0] }}
                            </div>
                        </div>

                        <!-- Portes avec dés -->
                        <div class="doors-container">
                            <div v-for="door in room.doors" :key="door.id"
                                 :class="['door-widget', door.direction]"
                                 :style="{borderColor: doorColors[door.direction].color}">
                                <div class="dice-result"
                                     :style="{backgroundColor: doorColors[door.direction].color}">
                                    {{ door.diceResult }}
                                </div>
                                <div class="door-status">
                                    <span v-if="door.isOpen" class="door-open" title="Porte ouverte">🔓</span>
                                    <span v-else class="door-closed" title="Porte fermée">🔒</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Interface d'actions -->
                <div class="actions-panel">
                    <div class="actions-section" v-if="selectedPlayer && currentPlayerRoom">
                        <h3>Actions pour {{ selectedPlayerName }}</h3>

                        <!-- Portes disponibles -->
                        <div class="doors-actions">
                            <div v-for="door in currentPlayerRoom.doors" :key="door.id" class="door-action">
                                <div class="door-info">
                                    <div class="door-color-indicator"
                                         :style="{backgroundColor: doorColors[door.direction].color}"></div>
                                    <div class="door-details">
                                        <strong>{{ doorIcons[door.direction] }} {{ doorColors[door.direction].name }}</strong>
                                        <span class="door-capacity">Capacité: {{ door.diceResult }}</span>
                                    </div>
                                </div>
                                <div class="door-buttons">
                                    <button v-if="!door.isOpen"
                                            @click="openDoor(door.id)"
                                            class="btn-open"
                                            :disabled="!canOpenDoor(door)">
                                        Ouvrir (−1 pt)
                                    </button>
                                    <span v-else class="door-opened">✓ Ouverte</span>
                                    <button @click="chooseDoor(door.id)"
                                            class="btn-choose"
                                            :disabled="!door.isOpen">
                                        Choisir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="actions-help">
                        Sélectionnez un joueur dans le panneau de gauche pour voir les actions disponibles
                    </div>
                </div>
            </div>
        </div>
    `,
    computed: {
        orderedRooms() {
            // Ordonner les salles par position (y, x) pour affichage en grille
            return this.game.rooms.slice().sort((a, b) => {
                if (a.positionY !== b.positionY) return a.positionY - b.positionY;
                return a.positionX - b.positionX;
            });
        },
        selectedPlayerName() {
            const player = this.game.players.find(p => p.id === this.selectedPlayer);
            return player ? player.name : '';
        },
        currentPlayerRoom() {
            if (!this.selectedPlayer) return null;
            const player = this.game.players.find(p => p.id === this.selectedPlayer);
            if (!player) return null;
            return this.game.rooms.find(r => r.id === player.currentRoomId);
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
            return room ? `(${room.positionX},${room.positionY})` : '?';
        },
        selectRoom(room) {
            // Future: afficher détails de la salle
        },
        canOpenDoor(door) {
            if (door.isOpen) return false;
            const player = this.game.players.find(p => p.id === this.selectedPlayer);
            return player && player.points >= 1 && player.status === 'alive';
        },
        openDoor(doorId) {
            this.$emit('open-door', doorId, this.selectedPlayer);
        },
        chooseDoor(doorId) {
            this.$emit('choose-door', this.selectedPlayer, doorId);
        }
    }
};
