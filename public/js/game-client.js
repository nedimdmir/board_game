/**
 * WebSocket Game Client & Networking Layer
 * Connects to the WebSocket server if available, or seamlessly falls back
 * to the built-in standalone engine (LocalServer) for GitHub Pages and offline play!
 */
class ClientEngine {
  constructor() {
    this.ws = null;
    this.connected = false;
    this.isStandalone = false;
    this.roomId = null;
    this.player = null; // My player object
    this.state = null; // Current room state
    this.reconnectAttempts = 0;
  }

  connect() {
    return new Promise((resolve) => {
      // 1. If running on file://, GitHub Pages (*.github.io), or static hosts without a Node backend:
      const isStaticOrFile = window.location.protocol === 'file:' || 
                             window.location.hostname.endsWith('github.io') ||
                             window.location.hostname.includes('vercel.app') ||
                             window.location.hostname.includes('netlify.app') ||
                             (!window.location.port && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1');

      if (isStaticOrFile) {
        console.log('🌐 Statik / GitHub Pages ortamı tespit edildi. Bağımsız Yerel Motor (Standalone) aktif!');
        this.isStandalone = true;
        this.connected = true;
        return resolve();
      }

      // 2. If already connected
      if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
        return resolve();
      }

      try {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.host}`;
        this.ws = new WebSocket(wsUrl);

        let timeout = setTimeout(() => {
          if (!this.connected) {
            console.warn('⚠️ WebSocket bağlantı zaman aşımı. Bağımsız Yerel Motor (Standalone) moduna geçildi.');
            this.isStandalone = true;
            this.connected = true;
            resolve();
          }
        }, 1200);

        this.ws.onopen = () => {
          clearTimeout(timeout);
          console.log('✅ WebSocket sunucusuna bağlanıldı!');
          this.connected = true;
          this.isStandalone = false;
          this.reconnectAttempts = 0;
          resolve();
        };

        this.ws.onmessage = (event) => {
          try {
            const msg = JSON.parse(event.data);
            this.handleServerMessage(msg);
          } catch (e) {
            console.error('Mesaj ayrıştırma hatası:', e);
          }
        };

        this.ws.onclose = () => {
          this.connected = false;
          if (this.roomId && !this.isStandalone && this.reconnectAttempts < 5) {
            this.reconnectAttempts++;
            setTimeout(() => this.reconnect(), 2000);
          }
        };

        this.ws.onerror = (err) => {
          clearTimeout(timeout);
          console.warn('⚠️ WebSocket sunucusuna ulaşılamadı. Yerel Motor (LocalServer) ile devam ediliyor.');
          this.isStandalone = true;
          this.connected = true;
          resolve(); // Resolve, do NOT reject!
        };
      } catch (err) {
        this.isStandalone = true;
        this.connected = true;
        resolve();
      }
    });
  }

  reconnect() {
    console.log(`Yeniden bağlanılıyor (Deneme ${this.reconnectAttempts})...`);
    this.connect().then(() => {
      if (this.roomId && !this.isStandalone) {
        this.joinRoom(this.roomId);
      }
    });
  }

  send(type, payload = {}) {
    if (this.isStandalone || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
      if (window.LocalServer) {
        const sessId = this.player ? this.player.sessionId : (PlayerProgression.profile ? PlayerProgression.profile.sessionId : 'host_session');
        window.LocalServer.handleMessage(sessId, type, payload);
      }
      return;
    }
    this.ws.send(JSON.stringify({ type, ...payload }));
  }

  createRoom(rounds = 10, mapTheme = 'istanbul') {
    const prof = PlayerProgression.profile;
    if (this.isStandalone && window.LocalServer) {
      const hostPlayer = {
        sessionId: prof.sessionId,
        name: prof.name,
        avatar: prof.avatar,
        hat: prof.hat || 'none',
        color: prof.color || '#3B82F6',
        diceSkin: prof.diceSkin,
        level: prof.level,
        title: prof.title
      };
      this.player = hostPlayer;
      window.LocalServer.createRoom(hostPlayer, {
        totalRounds: parseInt(rounds) || 10,
        mapTheme: mapTheme
      });
      return;
    }

    this.send('create_room', {
      sessionId: prof.sessionId,
      playerName: prof.name,
      avatar: prof.avatar,
      hat: prof.hat || 'none',
      color: prof.color || '#3B82F6',
      diceSkin: prof.diceSkin,
      level: prof.level,
      title: prof.title,
      rounds: rounds,
      mapTheme: mapTheme
    });
  }

  joinRoom(roomId) {
    if (this.isStandalone && window.LocalServer) {
      this.createRoom(10, 'istanbul');
      return;
    }
    const prof = PlayerProgression.profile;
    this.send('join_room', {
      roomId: roomId.trim().toUpperCase(),
      sessionId: prof.sessionId,
      playerName: prof.name,
      avatar: prof.avatar,
      hat: prof.hat || 'none',
      color: prof.color || '#3B82F6',
      diceSkin: prof.diceSkin,
      level: prof.level,
      title: prof.title
    });
  }

  updateProfile(profileData) {
    this.send('update_profile', profileData);
  }

  switchMap(mapTheme) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'switch_map', { mapTheme });
      return;
    }
    this.send('switch_map', { mapTheme });
  }

  addBot() {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'add_bot', {});
      return;
    }
    this.send('add_bot');
  }

  startGame() {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'start_game', {});
      return;
    }
    this.send('start_game', { roomId: this.roomId });
  }

  rollDice() {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'roll_dice', {});
      return;
    }
    this.send('roll_dice');
  }

  useItem(itemId, targetPlayerId = null) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'use_item', { itemId, targetPlayerId });
      return;
    }
    this.send('use_item', { itemId, targetPlayerId });
  }

  buyItem(itemId) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'buy_item', { itemId });
      return;
    }
    this.send('buy_item', { itemId });
  }

  closeShop() {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'close_shop', {});
      return;
    }
    this.send('close_shop');
  }

  sendEmote(emoteId) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'send_emote', { emoteId });
      return;
    }
    this.send('send_emote', { emoteId });
  }

  sendQuickChat(text) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'send_quick_chat', { text });
      return;
    }
    this.send('send_quick_chat', { text });
  }

  sendTradeOffer(offer) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'send_trade_offer', { offer });
      return;
    }
    this.send('send_trade_offer', { offer });
  }

  respondTradeOffer(tradeId, accepted) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'respond_trade_offer', { tradeId, accepted });
      return;
    }
    this.send('respond_trade_offer', { tradeId, accepted });
  }

  sendMiniGameScore(score) {
    if (this.isStandalone && window.LocalServer) {
      window.LocalServer.handleMessage(this.player ? this.player.sessionId : 's_host', 'minigame_score', { score });
      return;
    }
    this.send('minigame_score', { score });
  }

  handleServerMessage(msg) {
    switch (msg.type) {
      case 'room_created':
      case 'room_joined':
        this.roomId = msg.roomId;
        this.player = msg.player;
        UI.showLobbyScreen(msg.roomId, this.player);
        break;

      case 'state_update':
        this.state = msg;
        window.CURRENT_ROOM_STATE = msg;
        UI.updateGameState(msg);
        break;

      case 'dice_rolled':
        UI.showDiceRollAnimation(msg);
        break;

      case 'mystery_triggered':
        UI.showMysteryRoulette(msg);
        break;

      case 'tile_event':
        UI.showTileEventCard(msg);
        break;

      case 'open_shop':
        UI.openShopModal(msg.items, msg.playerId === (this.player ? this.player.id : null));
        break;

      case 'minigame_start':
        MiniGames.start(msg.minigame);
        break;

      case 'minigame_end':
        MiniGames.close();
        UI.showMiniGameResults(msg);
        break;

      case 'player_emote':
        UI.showPlayerEmote(msg.playerId, msg.emoteId);
        break;

      case 'player_chat':
        UI.showPlayerChatBubble(msg.playerId, msg.playerName, msg.text);
        break;

      case 'incoming_trade_offer':
        UI.showIncomingTradeModal(msg);
        break;

      case 'trade_completed':
        AudioPlayer.playStarFanfare();
        UI.showToast(`🤝 ${msg.senderName} ve ${msg.receiverName} başarıyla takas yaptı!`, 'success');
        break;

      case 'trade_rejected':
        UI.showToast(`❌ Takas teklifi reddedildi.`, 'info');
        break;

      case 'game_over':
        UI.showVictoryPodium(msg);
        break;

      case 'error':
        UI.showToast(msg.message, 'error');
        break;
    }
  }
}

const GameClient = new ClientEngine();
window.GameClient = GameClient;
