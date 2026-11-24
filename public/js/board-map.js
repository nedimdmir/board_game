/**
 * Wonder Board Map Engine - Ultra-Crisp High-DPI Edition
 * Features 3 Interactive Maps (Tropics, Cyber Station, Candy Valley),
 * High-DPI auto-scaling, Anti-Overlap Token Cluster Layout & Interactive Tile Inspector.
 */

const MAP_THEMES = {
  istanbul: {
    id: 'istanbul',
    name: 'İstanbul - Boğazın İncisi',
    desc: 'Tarihi yarımada, Galata Kulesi, Boğaz Köprüsü ve Ayasofya!',
    bgColor: '#0B0F19',
    islandBase: '#1E293B',
    roadBase: '#334155',
    roadBorder: '#0284C7',
    landmark: { name: 'Kız Kulesi & Vapur', x: 880, y: 520, icon: '🏰' }
  },
  tokyo: {
    id: 'tokyo',
    name: 'Tokyo - Neon Şehri',
    desc: 'Shibuya neonları, Tokyo Kulesi, Skytree ve Torii tapınakları!',
    bgColor: '#060714',
    islandBase: '#0F172A',
    roadBase: '#1E1B4B',
    roadBorder: '#06B6D4',
    landmark: { name: 'Tokyo Kulesi & Tapınak', x: 880, y: 520, icon: '🗼' }
  },
  paris: {
    id: 'paris',
    name: 'Paris - Işıklar Şehri',
    desc: 'Eyfel Kulesi, Zafer Takı, Sen Nehri ve Louvre Piramidi!',
    bgColor: '#090D1A',
    islandBase: '#14532D',
    roadBase: '#1E293B',
    roadBorder: '#38BDF8',
    landmark: { name: 'Eyfel & Louvre', x: 880, y: 520, icon: '🗼' }
  },
  newyork: {
    id: 'newyork',
    name: 'New York - Büyük Elma',
    desc: 'Empire State, Özgürlük Heykeli, Brooklyn Köprüsü ve sarı taksiler!',
    bgColor: '#05070F',
    islandBase: '#1E293B',
    roadBase: '#334155',
    roadBorder: '#F59E0B',
    landmark: { name: 'Özgürlük Heykeli', x: 880, y: 520, icon: '🗽' }
  },
  tropics: {
    id: 'tropics',
    aliasOf: 'istanbul',
    name: 'İstanbul - Boğazın İncisi',
    desc: 'Tarihi yarımada, Galata Kulesi, Boğaz Köprüsü ve Ayasofya!',
    bgColor: '#0F172A',
    islandBase: '#FEF3C7',
    roadBase: '#E2E8F0',
    roadBorder: '#0284C7',
    landmark: { name: 'Kız Kulesi & Vapur', x: 880, y: 520, icon: '🏰' }
  },
  cyber: {
    id: 'cyber',
    aliasOf: 'tokyo',
    name: 'Tokyo - Neon Şehri',
    desc: 'Shibuya neonları, Tokyo Kulesi, Skytree ve Torii tapınakları!',
    bgColor: '#090A1A',
    islandBase: '#1E1B4B',
    roadBase: '#1E293B',
    roadBorder: '#06B6D4',
    landmark: { name: 'Tokyo Kulesi & Tapınak', x: 880, y: 520, icon: '🗼' }
  },
  candy: {
    id: 'candy',
    aliasOf: 'paris',
    name: 'Paris - Işıklar Şehri',
    desc: 'Eyfel Kulesi, Zafer Takı, Sen Nehri ve Louvre Piramidi!',
    bgColor: '#1E1B4B',
    islandBase: '#FCE7F3',
    roadBase: '#FFE4E6',
    roadBorder: '#EC4899',
    landmark: { name: 'Eyfel & Louvre', x: 880, y: 520, icon: '🗼' }
  }
};

const BOARD_TILES = [
  { id: 0, type: 'start', title: 'Başlangıç', desc: '+10 Altın Geçiş Bonusu!', icon: 'flag', color: '#10B981' },
  { id: 1, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 2, type: 'mystery', title: 'Şans Kutusu', desc: 'Sürpriz bir olay seni bekliyor!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 3, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 4, type: 'trap', penalty: 4, title: 'Tuzak Karosu', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
  { id: 5, type: 'shop', title: 'Eşya Dükkanı', desc: 'Güçlü eşyalar satın al!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 6, type: 'coin', amount: 6, title: 'Büyük Altın', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 7, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 8, type: 'warp', target: 16, title: 'Hava Geçidi', desc: '16. karoya ışınlan!', icon: 'zap', color: '#06B6D4' },
  { id: 9, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 10, type: 'duel', title: 'Düello Meydanı', desc: 'Rakipten altın kap!', icon: 'swords', color: '#EC4899' },
  { id: 11, type: 'tax', title: 'Korsan / Vergi', desc: '%25 Altın cezası en fakir oyuncuya dağıtılır!', icon: 'coins', color: '#E11D48' },
  { id: 12, type: 'mystery', title: 'Görünmez Sandık', desc: 'Rulet çarkını çevir!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 13, type: 'coin', amount: 7, title: 'Zengin Kuyu', desc: '+7 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 14, type: 'shop', title: 'Gezgin Satıcı', desc: 'Nadir taktik eşyaları!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 15, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 16, type: 'coin', amount: 4, title: 'Altın Karosu', desc: '+4 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 17, type: 'jail', title: 'Kodes / Hapis', desc: '1 tur kilitli kal veya 5 altın kefalet öde!', icon: 'shield', color: '#DC2626' },
  { id: 18, type: 'mystery', title: 'Sihirli Çark', desc: 'Büyük ödüller veya sürprizler!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 19, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 20, type: 'duel', title: 'Altın Arenası', desc: 'Rakiplerle düello!', icon: 'swords', color: '#EC4899' },
  { id: 21, type: 'freeze', title: 'Buz Tuzağı', desc: 'Donma etkisi! 1 tur eşya kullanamazsın!', icon: 'zap', color: '#0284C7' },
  { id: 22, type: 'coin', amount: 6, title: 'Altın Karosu', desc: '+6 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 23, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' },
  { id: 24, type: 'reverse', title: 'Ters Yön Laneti', desc: 'Pusula bozuldu! Sonraki zarda ters yöne yürü!', icon: 'undo-2', color: '#9333EA' },
  { id: 25, type: 'mystery', title: 'Hazine Sandığı', desc: 'Şansını sına!', icon: 'sparkles', color: '#8B5CF6' },
  { id: 26, type: 'shop', title: 'Parti Marketi', desc: 'Zafer için eşya kuşan!', icon: 'shopping-bag', color: '#F59E0B' },
  { id: 27, type: 'coin', amount: 8, title: 'Dev Hazine', desc: '+8 Altın!', icon: 'coins', color: '#3B82F6' },
  { id: 28, type: 'trap', penalty: 4, title: 'Tuzak Karosu', desc: '-4 Altın kaybettin!', icon: 'flame', color: '#EF4444' },
  { id: 29, type: 'duel', title: 'Büyük Hesaplaşma', desc: 'Liderden altın arakla!', icon: 'swords', color: '#EC4899' },
  { id: 30, type: 'coin', amount: 5, title: 'Altın Karosu', desc: '+5 Altın kazandın!', icon: 'coins', color: '#3B82F6' },
  { id: 31, type: 'star', title: 'Yıldız Kaidesi', desc: '20 Altına 1 Altın Yıldız!', icon: 'star', color: '#FBBF24' }
];

const SHOP_ITEMS = [
  { id: 'double_dice', name: 'Çift Zar', cost: 7, desc: 'Bir sonraki turda 2 zar birden atarsın (2-12 adım).', icon: 'dices' },
  { id: 'rocket', name: 'Süper Roket', cost: 12, desc: 'Hedefine doğru direkt 7 adım uçar!', icon: 'rocket' },
  { id: 'shield', name: 'Manyetik Kalkan', cost: 8, desc: 'Tuzaklara ve altın çalma hamlelerine karşı 2 tur korur.', icon: 'shield' },
  { id: 'magnet', name: 'Altın Mıknatısı', cost: 10, desc: 'Bütün rakiplerden 4\'er altın çeker!', icon: 'magnet' },
  { id: 'banana', name: 'Muz Kabuğu', cost: 5, desc: 'Bulunduğun karoya tuzak kur; üstüne basan 3 adım geri kayar!', icon: 'banana' },
  { id: 'swap_wand', name: 'Takas Asası', cost: 15, desc: 'İstediğin bir rakiple haritadaki yerlerini takas et!', icon: 'refresh-cw' },
  { id: 'ghost_cloak', name: 'Hayalet Pelerin', cost: 9, desc: '2 tur boyunca hiçbir tuzaktan veya cezadan etkilenmezsin!', icon: 'shield' },
  { id: 'lucky_die', name: 'Şanslı Altın Zar', cost: 14, desc: 'Sıradaki zarda 1-6 arasında istediğin sayıyı sen seçersin!', icon: 'dices' }
];

class BoardRenderer {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    this.ctx = this.canvas ? this.canvas.getContext('2d') : null;
    
    // Virtual resolution (game coordinates)
    this.virtualWidth = 1100;
    this.virtualHeight = 760;
    this.dpr = 1;
    this.baseScale = 1;
    this.offsetX = 0;
    this.offsetY = 0;

    this.tileCoords = this.calculateTilePositions();
    this.playerOffsets = {}; // playerId -> { currentX, currentY, hopOffset }
    this.animationFrame = null;
    this.starPulse = 0;
    this.autoFollow = true;
    this.selectedTileIndex = null;
    this.activeTheme = 'istanbul';

    // Interactive Landmark state
    this.landmarkPulse = 0;
    this.particles = [];

    // Viewport pan & zoom (Center-origin tracking)
    this.cameraFocus = { x: 550, y: 380 };
    this.camera = { dragX: 0, dragY: 0, zoom: 1 };
    this.isDragging = false;
    this.dragStart = { x: 0, y: 0 };
    this.dragDistance = 0;

    this.resizeCanvas();
    this.bindEvents();
    this.startLoop();
  }

  setTheme(themeId) {
    if (MAP_THEMES[themeId]) {
      this.activeTheme = MAP_THEMES[themeId].aliasOf || themeId;
    }
  }

  resizeCanvas() {
    if (!this.canvas) return;
    const parent = this.canvas.parentElement;
    const rect = parent.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2.5); // High-DPI sharpness without lag

    this.dpr = dpr;
    this.canvas.width = Math.round(rect.width * dpr);
    this.canvas.height = Math.round(rect.height * dpr);
    this.canvas.style.width = `${rect.width}px`;
    this.canvas.style.height = `${rect.height}px`;

    // Uniform fit scale
    const scaleX = rect.width / this.virtualWidth;
    const scaleY = rect.height / this.virtualHeight;
    this.baseScale = Math.min(scaleX, scaleY);
    this.offsetX = (rect.width - (this.virtualWidth * this.baseScale)) / 2;
    this.offsetY = (rect.height - (this.virtualHeight * this.baseScale)) / 2;
  }

  calculateTilePositions() {
    // Dynamic, organic, fluid racetrack (Mario Party / Dokapon style)
    // Curving gracefully across scenic bays, bridges, coastal promenades and skyline heights
    return [
      { x: 206, y: 492 }, // 0: Start Meydanı (Liman Başlangıç)
      { x: 267, y: 530 }, // 1: Sahil Kordonu
      { x: 329, y: 564 }, // 2: Balıkçı İskelesi
      { x: 395, y: 592 }, // 3: Çeşme Meydanı
      { x: 463, y: 613 }, // 4: Boğaz Yolu
      { x: 533, y: 626 }, // 5: Marina Kordonu
      { x: 604, y: 629 }, // 6: Kıyı Parkı
      { x: 675, y: 623 }, // 7: Star Pedestal
      { x: 745, y: 608 }, // 8: Doğu Sahili
      { x: 813, y: 586 }, // 9: Hisar Yokuşu
      { x: 877, y: 555 }, // 10: Düello Meydanı
      { x: 937, y: 517 }, // 11: Köprü Yaklaşımı
      { x: 990, y: 469 }, // 12: Şans Kemeri
      { x: 1028, y: 409 }, // 13: Seyir Tepesi
      { x: 1034, y: 339 }, // 14: Kule Yolu
      { x: 1000, y: 277 }, // 15: Star Pedestal
      { x: 944, y: 235 }, // 16: Hava Geçidi
      { x: 878, y: 207 }, // 17: Kodes & Hisar
      { x: 809, y: 191 }, // 18: Sihirli Çark
      { x: 738, y: 182 }, // 19: Panorama Terası
      { x: 667, y: 180 }, // 20: Çamlıca Zirvesi
      { x: 595, y: 179 }, // 21: Donma Vadisi
      { x: 524, y: 177 }, // 22: Saray Sırtı
      { x: 453, y: 174 }, // 23: Star Pedestal
      { x: 381, y: 174 }, // 24: Ters Rüzgar Tepesi
      { x: 310, y: 179 }, // 25: Sandık Bahçesi
      { x: 241, y: 194 }, // 26: Kapalıçarşı Kemeri
      { x: 174, y: 220 }, // 27: Hazine Meydanı
      { x: 118, y: 263 }, // 28: Dikenli Vadi
      { x: 88, y: 326 },  // 29: Büyük Arena
      { x: 104, y: 394 }, // 30: Gülhane Yolu
      { x: 149, y: 449 }  // 31: Star Kaidesi
    ];
  }

  bindEvents() {
    if (!this.canvas) return;

    window.addEventListener('resize', () => this.resizeCanvas());

    // Mouse Controls (Center-origin offset)
    this.canvas.addEventListener('mousedown', (e) => {
      this.isDragging = true;
      this.dragDistance = 0;
      this.dragStart = { x: e.clientX - this.camera.dragX, y: e.clientY - this.camera.dragY };
    });

    window.addEventListener('mousemove', (e) => {
      if (this.isDragging) {
        const dx = e.clientX - (this.dragStart.x + this.camera.dragX);
        const dy = e.clientY - (this.dragStart.y + this.camera.dragY);
        this.dragDistance += Math.hypot(dx, dy);
        this.camera.dragX = e.clientX - this.dragStart.x;
        this.camera.dragY = e.clientY - this.dragStart.y;
        this.autoFollow = false;
      }
    });

    window.addEventListener('mouseup', (e) => {
      if (this.isDragging && this.dragDistance < 6) {
        // Was a Click! (not a drag)
        this.handleCanvasClick(e.clientX, e.clientY);
      }
      this.isDragging = false;
    });

    // Touch Controls
    let touchStart = { x: 0, y: 0 };
    this.canvas.addEventListener('touchstart', (e) => {
      if (e.touches.length === 1) {
        this.isDragging = true;
        this.dragDistance = 0;
        touchStart = {
          x: e.touches[0].clientX - this.camera.dragX,
          y: e.touches[0].clientY - this.camera.dragY
        };
      }
    }, { passive: true });

    this.canvas.addEventListener('touchmove', (e) => {
      if (this.isDragging && e.touches.length === 1) {
        const dx = e.touches[0].clientX - (touchStart.x + this.camera.dragX);
        const dy = e.touches[0].clientY - (touchStart.y + this.camera.dragY);
        this.dragDistance += Math.hypot(dx, dy);
        this.camera.dragX = e.touches[0].clientX - touchStart.x;
        this.camera.dragY = e.touches[0].clientY - touchStart.y;
        this.autoFollow = false;
      }
    }, { passive: true });

    this.canvas.addEventListener('touchend', (e) => {
      if (this.isDragging && this.dragDistance < 10 && e.changedTouches.length === 1) {
        this.handleCanvasClick(e.changedTouches[0].clientX, e.changedTouches[0].clientY);
      }
      this.isDragging = false;
    });

    // Mouse Wheel Zoom
    this.canvas.addEventListener('wheel', (e) => {
      e.preventDefault();
      const zoomFactor = e.deltaY < 0 ? 1.08 : 0.92;
      this.camera.zoom = Math.max(0.65, Math.min(2.0, this.camera.zoom * zoomFactor));
    }, { passive: false });
  }

  handleCanvasClick(screenX, screenY) {
    const rect = this.canvas.getBoundingClientRect();
    const canvasX = (screenX - rect.left) * (this.canvas.width / rect.width) / this.dpr;
    const canvasY = (screenY - rect.top) * (this.canvas.height / rect.height) / this.dpr;
    const viewportW = this.canvas.width / this.dpr;
    const viewportH = this.canvas.height / this.dpr;

    const totalScale = this.baseScale * this.camera.zoom;
    const worldX = ((canvasX - (viewportW / 2 + this.camera.dragX)) / totalScale) + this.cameraFocus.x;
    const worldY = ((canvasY - (viewportH / 2 + this.camera.dragY)) / totalScale) + this.cameraFocus.y;

    // Check click on interactive Landmark
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    const lm = theme.landmark;
    if (Math.hypot(worldX - lm.x, worldY - lm.y) < 44) {
      this.triggerLandmarkInteraction(lm.x, lm.y);
      return;
    }

    // Check click on any tile
    for (let i = 0; i < this.tileCoords.length; i++) {
      const pos = this.tileCoords[i];
      if (Math.hypot(worldX - pos.x, worldY - pos.y) < 36) {
        this.selectTile(i);
        return;
      }
    }
  }

  triggerLandmarkInteraction(x, y) {
    AudioPlayer.playStarFanfare();
    // Burst particles
    for (let i = 0; i < 18; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 2 + Math.random() * 4;
      this.particles.push({
        x, y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        life: 1.0,
        color: ['#FBBF24', '#38BDF8', '#EC4899', '#10B981'][Math.floor(Math.random() * 4)]
      });
    }
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    if (window.UI) UI.showToast(`✨ ${theme.landmark.name} ile etkileşime geçtin!`, 'info');
  }

  selectTile(index) {
    this.selectedTileIndex = index;
    AudioPlayer.playClick();

    const roomState = window.CURRENT_ROOM_STATE;
    const currentBoard = (roomState && roomState.board) ? roomState.board : BOARD_TILES;
    const tile = currentBoard[index];
    const playersOnTile = roomState ? roomState.players.filter(p => p.position === index) : [];
    
    // Distance from active player
    let stepsAway = 0;
    if (roomState && roomState.players) {
      const activeP = roomState.players[roomState.turnIndex];
      if (activeP) {
        stepsAway = (index - activeP.position + currentBoard.length) % currentBoard.length;
      }
    }

    if (window.UI && window.UI.showTileInspector) {
      window.UI.showTileInspector(tile, index, playersOnTile, stepsAway);
    }
  }

  focusPlayer(player) {
    if (!player || !this.autoFollow) return;
    const anim = this.playerHopState && this.playerHopState[player.id];
    const px = anim ? anim.displayX : (this.tileCoords[player.position] ? this.tileCoords[player.position].x : 550);
    const py = anim ? (anim.displayY + anim.hopArcY) : (this.tileCoords[player.position] ? this.tileCoords[player.position].y : 380);
    // Smooth lerp focus directly onto character token without drift
    this.cameraFocus.x += (px - this.cameraFocus.x) * 0.08;
    this.cameraFocus.y += (py - this.cameraFocus.y) * 0.08;
  }

  resetCamera() {
    this.camera = { dragX: 0, dragY: 0, zoom: 1 };
    this.cameraFocus = { x: 550, y: 380 };
    this.autoFollow = true;
  }

  zoomIn() {
    this.camera.zoom = Math.min(2.0, this.camera.zoom * 1.15);
  }

  zoomOut() {
    this.camera.zoom = Math.max(0.65, this.camera.zoom * 0.85);
  }

  startLoop() {
    const render = () => {
      this.draw();
      this.animationFrame = requestAnimationFrame(render);
    };
    this.animationFrame = requestAnimationFrame(render);
  }

  draw() {
    if (!this.ctx) return;
    const ctx = this.ctx;

    // Reset transform & clear
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

    const viewportW = this.canvas.width / this.dpr;
    const viewportH = this.canvas.height / this.dpr;

    // Apply high-DPI
    ctx.scale(this.dpr, this.dpr);

    // Deep dark celestial background for entire viewport (No white borders on any screen!)
    const currentTheme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.fillStyle = currentTheme.bgColor || '#0B0F19';
    ctx.fillRect(0, 0, viewportW, viewportH);

    // Apply Center-Origin Camera Projection (Camera stays 100% centered on character on zoom!)
    ctx.translate(viewportW / 2 + this.camera.dragX, viewportH / 2 + this.camera.dragY);
    const totalScale = this.baseScale * this.camera.zoom;
    ctx.scale(totalScale, totalScale);
    ctx.translate(-this.cameraFocus.x, -this.cameraFocus.y);

    // 1. Draw Environment Backdrop (City-specific)
    this.drawEnvironment(ctx);

    // 2. Draw Road Pathways with Directional Flow Chevrons
    this.drawPathways(ctx);

    // 3. Draw Projected Step Trajectory
    this.drawStepTrajectory(ctx);

    // 4. Draw 3D Stylized Platform Tiles
    const roomState = window.CURRENT_ROOM_STATE;
    const currentBoard = (roomState && roomState.board) ? roomState.board : BOARD_TILES;
    const activeStar = roomState ? roomState.activeStarTile : 7;
    const traps = roomState ? (roomState.trapsOnBoard || {}) : {};

    for (let i = 0; i < this.tileCoords.length; i++) {
      const tile = currentBoard[i] || BOARD_TILES[i];
      const pos = this.tileCoords[i];
      const isStarTile = (i === activeStar);
      const isSelected = (i === this.selectedTileIndex);
      const hasTrap = !!traps[i];
      this.drawPlatformTile(ctx, i, tile, pos, isStarTile, isSelected, hasTrap);
    }

    // 5. Draw Interactive Landmark
    this.drawLandmark(ctx);

    // 6. Draw Dramatic Turn Spotlight
    this.drawTurnSpotlight(ctx);

    // 7. Draw Particles
    this.drawParticles(ctx);

    // 8. Draw Spatially Separated, Non-Overlapping Player Tokens (Y-Sorted)
    if (roomState && roomState.players) {
      this.drawPlayerTokens(ctx, roomState.players);
      const activePlayer = roomState.players[roomState.turnIndex];
      if (activePlayer && this.autoFollow) {
        this.focusPlayer(activePlayer);
      }
    }

    this.starPulse += 0.04;
  }

  drawEnvironment(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.save();

    // 1. Full-Canvas Thematic Sky & Atmosphere (Twinkling stars, sunset glow, clouds)
    this.drawSkyAndAtmosphere(ctx, theme);

    // 2. Full-Canvas Terrain & Water Bodies (Replaces the empty void with living diorama)
    this.drawWaterAndTerrain(ctx, theme);

    // 3. Iconic City Architecture & Living Scenery
    const themeKey = theme.aliasOf || this.activeTheme;
    if (themeKey === 'tokyo') {
      this.drawTokyoEnvironment(ctx);
    } else if (themeKey === 'paris') {
      this.drawParisEnvironment(ctx);
    } else if (themeKey === 'newyork') {
      this.drawNewYorkEnvironment(ctx);
    } else {
      this.drawIstanbulEnvironment(ctx);
    }

    // 4. Ambient Street Lamps, Animated Vehicles & Foliage (Eliminates any bare canvas)
    this.drawAmbientWorldDetails(ctx, theme);

    ctx.restore();
  }

  /* ----------------------------------------------------
     ATMOSPHERIC SKY & HORIZON (Full 1100x760 Canvas)
     ---------------------------------------------------- */
  drawSkyAndAtmosphere(ctx, theme) {
    const themeKey = theme.aliasOf || this.activeTheme;

    // Atmospheric Gradient Backdrop
    const skyGrad = ctx.createLinearGradient(0, 0, 0, 760);
    if (themeKey === 'tokyo') {
      skyGrad.addColorStop(0, '#060714');
      skyGrad.addColorStop(0.35, '#0F1035');
      skyGrad.addColorStop(0.7, '#1E1B4B');
      skyGrad.addColorStop(1, '#090A1A');
    } else if (themeKey === 'paris') {
      skyGrad.addColorStop(0, '#1E1B4B');
      skyGrad.addColorStop(0.4, '#4C1D95');
      skyGrad.addColorStop(0.75, '#831843');
      skyGrad.addColorStop(1, '#3B0764');
    } else if (themeKey === 'newyork') {
      skyGrad.addColorStop(0, '#030712');
      skyGrad.addColorStop(0.4, '#0F172A');
      skyGrad.addColorStop(0.75, '#1E293B');
      skyGrad.addColorStop(1, '#111827');
    } else {
      // Istanbul Twilight
      skyGrad.addColorStop(0, '#0A192F');
      skyGrad.addColorStop(0.35, '#0F2B48');
      skyGrad.addColorStop(0.65, '#1E3A8A');
      skyGrad.addColorStop(0.85, '#D97706');
      skyGrad.addColorStop(1, '#78350F');
    }

    ctx.fillStyle = skyGrad;
    ctx.fillRect(-2500, -2500, 6000, 6000);

    // Twinkling Stars in upper sky
    ctx.save();
    const starCoords = [
      { x: 90, y: 35, s: 1.6 }, { x: 210, y: 55, s: 1.2 }, { x: 330, y: 30, s: 2.0 },
      { x: 470, y: 50, s: 1.4 }, { x: 590, y: 25, s: 1.8 }, { x: 710, y: 45, s: 1.3 },
      { x: 840, y: 30, s: 2.2 }, { x: 970, y: 60, s: 1.5 }, { x: 1040, y: 40, s: 1.7 },
      { x: 150, y: 95, s: 1.3 }, { x: 380, y: 110, s: 1.1 }, { x: 670, y: 90, s: 1.6 },
      { x: 890, y: 105, s: 1.4 }
    ];
    starCoords.forEach((st, i) => {
      const alpha = 0.35 + 0.65 * Math.abs(Math.sin(this.starPulse * 2.2 + i * 1.5));
      ctx.fillStyle = `rgba(255, 255, 255, ${alpha})`;
      ctx.beginPath();
      ctx.arc(st.x, st.y, st.s, 0, Math.PI * 2);
      ctx.fill();
    });

    // Glowing Celestial Body
    if (themeKey === 'tokyo') {
      ctx.save();
      ctx.fillStyle = '#F43F5E';
      ctx.shadowColor = '#F43F5E';
      ctx.shadowBlur = 25;
      ctx.beginPath();
      ctx.arc(930, 85, 24, 0, Math.PI * 2);
      ctx.fill();
      ctx.fillStyle = 'rgba(244, 63, 94, 0.2)';
      ctx.beginPath();
      ctx.arc(930, 85, 38, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    } else {
      ctx.save();
      ctx.translate(140, 75);
      ctx.fillStyle = '#FEF08A';
      ctx.shadowColor = '#FDE047';
      ctx.shadowBlur = 18;
      ctx.beginPath();
      ctx.arc(0, 0, 16, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(6, -4, 14, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }

    // Drifting Stylized Sunset Clouds
    ctx.fillStyle = (themeKey === 'paris') ? 'rgba(252, 231, 243, 0.25)' : 'rgba(255, 255, 255, 0.14)';
    const cloudOffsets = [0, 380, 760, 1140];
    cloudOffsets.forEach((co, idx) => {
      const cx = ((this.starPulse * 14 + co) % 1320) - 120;
      const cy = 45 + (idx % 2) * 35;
      ctx.beginPath();
      ctx.arc(cx, cy, 20, 0, Math.PI * 2);
      ctx.arc(cx + 20, cy - 8, 26, 0, Math.PI * 2);
      ctx.arc(cx + 44, cy, 18, 0, Math.PI * 2);
      ctx.fill();
    });
    ctx.restore();
  }

  /* ----------------------------------------------------
     BASE WATERWAYS & 3D FLOATING DIORAMA TERRAIN
     ---------------------------------------------------- */
  drawWaterAndTerrain(ctx, theme) {
    const themeKey = theme.aliasOf || this.activeTheme;
    ctx.save();

    // 1. Surrounding Atmospheric Ocean / Void Base
    const oceanGrad = ctx.createRadialGradient(550, 390, 200, 550, 390, 950);
    if (themeKey === 'tokyo') {
      oceanGrad.addColorStop(0, '#090D1E');
      oceanGrad.addColorStop(0.6, '#050711');
      oceanGrad.addColorStop(1, '#020308');
    } else if (themeKey === 'paris') {
      oceanGrad.addColorStop(0, '#0D1527');
      oceanGrad.addColorStop(0.6, '#080C18');
      oceanGrad.addColorStop(1, '#03050B');
    } else if (themeKey === 'newyork') {
      oceanGrad.addColorStop(0, '#0B132B');
      oceanGrad.addColorStop(0.6, '#060B18');
      oceanGrad.addColorStop(1, '#020409');
    } else {
      oceanGrad.addColorStop(0, '#0C2340');
      oceanGrad.addColorStop(0.6, '#081729');
      oceanGrad.addColorStop(1, '#030810');
    }
    ctx.fillStyle = oceanGrad;
    ctx.fillRect(-2500, -2500, 6000, 6000);

    // 2. Volumetric Drop Shadow under the Floating Diorama Island
    ctx.beginPath();
    ctx.ellipse(550, 440, 540, 310, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(0, 0, 0, 0.55)';
    ctx.fill();

    // 3. 3D Floating Island Cliff Underside (Layered Bedrock Strata)
    ctx.beginPath();
    ctx.ellipse(550, 422, 528, 305, 0, 0, Math.PI); // bottom half of lower rim
    ctx.ellipse(550, 392, 528, 305, 0, Math.PI, 0, true); // bottom half of upper rim reversed
    ctx.closePath();
    const cliffGrad = ctx.createLinearGradient(0, 390, 0, 428);
    cliffGrad.addColorStop(0, '#1E293B');
    cliffGrad.addColorStop(0.5, '#0F172A');
    cliffGrad.addColorStop(1, '#020617');
    ctx.fillStyle = cliffGrad;
    ctx.fill();

    // Cliff vertical rock strata ribs
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.05)';
    ctx.lineWidth = 1.5;
    for (let angle = 0.2; angle < Math.PI - 0.2; angle += 0.14) {
      const rx = 528 * Math.cos(angle);
      const ry1 = 392 + 305 * Math.sin(angle);
      const ry2 = 422 + 305 * Math.sin(angle);
      ctx.beginPath();
      ctx.moveTo(550 + rx, ry1);
      ctx.lineTo(550 + rx, ry2);
      ctx.stroke();
    }

    // 4. Coastal Shoreline & Golden Sand Beach Shelf
    ctx.beginPath();
    ctx.ellipse(550, 390, 528, 305, 0, 0, Math.PI * 2);
    const sandGrad = ctx.createLinearGradient(550, 85, 550, 695);
    if (themeKey === 'tokyo') {
      sandGrad.addColorStop(0, '#1E1B4B');
      sandGrad.addColorStop(1, '#312E81');
    } else {
      sandGrad.addColorStop(0, '#FDE68A');
      sandGrad.addColorStop(0.5, '#F59E0B');
      sandGrad.addColorStop(1, '#D97706');
    }
    ctx.fillStyle = sandGrad;
    ctx.fill();

    // Water shoreline foam line
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.35)';
    ctx.lineWidth = 2.5;
    ctx.beginPath();
    ctx.ellipse(550, 390, 529, 306, 0, 0, Math.PI * 2);
    ctx.stroke();

    // 5. Main Island Plateau (Lush Green Parklands / City Ground)
    ctx.beginPath();
    ctx.ellipse(550, 388, 508, 292, 0, 0, Math.PI * 2);
    const parkGrad = ctx.createRadialGradient(550, 388, 50, 550, 388, 500);
    if (themeKey === 'paris') {
      parkGrad.addColorStop(0, '#166534'); // Rich Parisian garden green
      parkGrad.addColorStop(0.7, '#14532D');
      parkGrad.addColorStop(1, '#0F3E22');
    } else if (themeKey === 'tokyo') {
      parkGrad.addColorStop(0, '#0F172A');
      parkGrad.addColorStop(0.7, '#1E1B4B');
      parkGrad.addColorStop(1, '#090D1A');
    } else if (themeKey === 'newyork') {
      parkGrad.addColorStop(0, '#1F2937');
      parkGrad.addColorStop(0.65, '#166534'); // Central Park lushness
      parkGrad.addColorStop(1, '#111827');
    } else {
      // Istanbul
      parkGrad.addColorStop(0, '#1E293B');
      parkGrad.addColorStop(0.65, '#14532D');
      parkGrad.addColorStop(1, '#0F172A');
    }
    ctx.fillStyle = parkGrad;
    ctx.fill();

    // 6. Inner Cobblestone Promenade Ring
    ctx.strokeStyle = (themeKey === 'tokyo') ? 'rgba(56, 189, 248, 0.22)' : 'rgba(255, 255, 255, 0.12)';
    ctx.lineWidth = 14;
    ctx.beginPath();
    ctx.ellipse(550, 388, 435, 235, 0, 0, Math.PI * 2);
    ctx.stroke();

    // 7. Radial Parisian/City Boulevards connecting center to racetrack
    ctx.strokeStyle = (themeKey === 'tokyo') ? 'rgba(56, 189, 248, 0.15)' : 'rgba(255, 255, 255, 0.08)';
    ctx.lineWidth = 10;
    const radAngles = [0, Math.PI / 4, 3 * Math.PI / 4, Math.PI, 5 * Math.PI / 4, 7 * Math.PI / 4];
    radAngles.forEach(ang => {
      ctx.beginPath();
      ctx.moveTo(550 + Math.cos(ang) * 90, 388 + Math.sin(ang) * 55);
      ctx.lineTo(550 + Math.cos(ang) * 420, 388 + Math.sin(ang) * 225);
      ctx.stroke();
    });

    ctx.restore();
  }

  /* ----------------------------------------------------
     1. İSTANBUL ENVIRONMENT (Boğaz, Galata, Ayasofya, Köprü)
     ---------------------------------------------------- */
  drawIstanbulEnvironment(ctx) {
    // 1. Bosphorus Strait Water Channel (Boğaz)
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(380, 50);
    ctx.bezierCurveTo(460, 220, 340, 420, 520, 730);
    ctx.lineTo(660, 730);
    ctx.bezierCurveTo(490, 420, 610, 220, 520, 50);
    ctx.closePath();
    const waterGrad = ctx.createLinearGradient(400, 50, 600, 730);
    waterGrad.addColorStop(0, '#0284C7');
    waterGrad.addColorStop(0.5, '#0369A1');
    waterGrad.addColorStop(1, '#075985');
    ctx.fillStyle = waterGrad;
    ctx.fill();

    // Animated water ripples
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.28)';
    ctx.lineWidth = 1.8;
    for (let r = 0; r < 6; r++) {
      const rx = 440 + Math.sin(this.starPulse + r * 1.5) * 40;
      const ry = 140 + r * 90 + Math.cos(this.starPulse + r) * 15;
      ctx.beginPath();
      ctx.arc(rx, ry, 22 + r * 4, 0.2 * Math.PI, 0.8 * Math.PI);
      ctx.stroke();
    }

    // 2. Şehir Hatları Vapuru (Istanbul Ferry)
    const ferryBob = Math.sin(this.starPulse * 1.5) * 3;
    const fx = 470, fy = 360 + ferryBob;
    ctx.save();
    ctx.translate(fx, fy);
    // Hull
    ctx.beginPath();
    ctx.moveTo(-36, 10); ctx.lineTo(36, 10); ctx.lineTo(28, 24); ctx.lineTo(-28, 24); ctx.closePath();
    ctx.fillStyle = '#1E293B'; ctx.fill();
    ctx.beginPath();
    ctx.moveTo(-38, 2); ctx.lineTo(38, 2); ctx.lineTo(34, 10); ctx.lineTo(-34, 10); ctx.closePath();
    ctx.fillStyle = '#FFFFFF'; ctx.fill();
    // Cabins
    ctx.fillStyle = '#F8FAFC';
    ctx.fillRect(-24, -12, 48, 14);
    ctx.fillStyle = '#0284C7';
    for (let p = -18; p <= 18; p += 9) {
      ctx.fillRect(p, -8, 5, 5);
    }
    // Smokestack & Chimney smoke
    ctx.fillStyle = '#EF4444';
    ctx.fillRect(-4, -20, 8, 8);
    ctx.fillStyle = '#FBBF24';
    ctx.fillRect(-4, -16, 8, 2);
    // Smoke puffs
    ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.beginPath();
    ctx.arc(-2 + Math.sin(this.starPulse * 2) * 4, -26, 4, 0, Math.PI * 2);
    ctx.arc(-5 + Math.sin(this.starPulse * 2.2) * 6, -34, 6, 0, Math.PI * 2);
    ctx.fill();
    // Turkish Flag
    ctx.fillStyle = '#DC2626';
    ctx.fillRect(28, -6, 10, 6);
    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 5px sans-serif';
    ctx.fillText('☪', 33, -2);
    ctx.restore();

    // 3. 15 Temmuz Şehitler Köprüsü (Bosphorus Bridge)
    ctx.save();
    ctx.strokeStyle = '#94A3B8';
    ctx.lineWidth = 4;
    // European Tower
    ctx.beginPath(); ctx.moveTo(400, 110); ctx.lineTo(400, 200); ctx.stroke();
    // Asian Tower
    ctx.beginPath(); ctx.moveTo(520, 95); ctx.lineTo(520, 185); ctx.stroke();
    // Bridge roadway deck
    ctx.strokeStyle = '#334155';
    ctx.lineWidth = 6;
    ctx.beginPath(); ctx.moveTo(350, 160); ctx.lineTo(570, 145); ctx.stroke();
    // Catenary Cables
    ctx.strokeStyle = '#CBD5E1';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(350, 160);
    ctx.quadraticCurveTo(460, 175, 570, 145);
    ctx.stroke();
    // Traffic dots (glowing red and white lights)
    const trafficOffset = (this.starPulse * 20) % 200;
    ctx.fillStyle = '#EF4444';
    ctx.fillRect(360 + (trafficOffset % 190), 158, 4, 2);
    ctx.fillStyle = '#FEF08A';
    ctx.fillRect(550 - (trafficOffset % 190), 148, 4, 2);
    ctx.restore();

    // 4. Galata Kulesi (Galata Tower) at Europe side (x: 230, y: 190)
    ctx.save();
    ctx.translate(230, 190);
    // Base shadow
    ctx.beginPath(); ctx.ellipse(0, 30, 22, 10, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.15)'; ctx.fill();
    // Stone Tower Shaft
    ctx.fillStyle = '#D6D3D1';
    ctx.fillRect(-16, -25, 32, 55);
    // Masonry accent rings
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 2;
    ctx.strokeRect(-16, -25, 32, 55);
    ctx.beginPath(); ctx.moveTo(-16, -5); ctx.lineTo(16, -5); ctx.stroke();
    ctx.beginPath(); ctx.moveTo(-16, 12); ctx.lineTo(16, 12); ctx.stroke();
    // Arched windows
    ctx.fillStyle = '#1E293B';
    ctx.beginPath(); ctx.arc(-6, 2, 3, Math.PI, 0); ctx.rect(-9, 2, 6, 6); ctx.fill();
    ctx.beginPath(); ctx.arc(6, 2, 3, Math.PI, 0); ctx.rect(3, 2, 6, 6); ctx.fill();
    // Observation Balcony (360 viewing terrace)
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-20, -32, 40, 7);
    ctx.strokeStyle = '#44403C'; ctx.lineWidth = 1.5;
    ctx.strokeRect(-20, -32, 40, 7);
    // Upper stone turret
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-13, -46, 26, 14);
    // Conical lead/copper Roof
    ctx.beginPath();
    ctx.moveTo(-18, -46); ctx.lineTo(18, -46); ctx.lineTo(0, -78); ctx.closePath();
    ctx.fillStyle = '#475569'; ctx.fill();
    ctx.strokeStyle = '#334155'; ctx.lineWidth = 2; ctx.stroke();
    // Golden Spire & Crescent
    ctx.fillStyle = '#F59E0B';
    ctx.fillRect(-1.5, -88, 3, 10);
    ctx.beginPath(); ctx.arc(0, -90, 4, 0.4 * Math.PI, 1.6 * Math.PI); ctx.stroke();
    // Title label
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Galata Kulesi', 0, 44);
    ctx.restore();

    // 5. Ayasofya & Sultanahmet (Historic Peninsula) at x: 670, y: 200
    ctx.save();
    ctx.translate(670, 200);
    // Monumental terracotta facade
    ctx.fillStyle = '#B45309';
    ctx.fillRect(-35, -15, 70, 45);
    // Central Grand Dome
    ctx.beginPath();
    ctx.arc(0, -15, 24, Math.PI, 0);
    ctx.fillStyle = '#78716C'; ctx.fill();
    ctx.strokeStyle = '#44403C'; ctx.lineWidth = 2; ctx.stroke();
    // Half-domes
    ctx.beginPath(); ctx.arc(-22, -10, 14, Math.PI, 0); ctx.fillStyle = '#A8A29E'; ctx.fill();
    ctx.beginPath(); ctx.arc(22, -10, 14, Math.PI, 0); ctx.fillStyle = '#A8A29E'; ctx.fill();
    // Dome Spire
    ctx.fillStyle = '#F59E0B';
    ctx.fillRect(-1, -44, 2, 8);
    // Four Soaring Pencil Minarets
    const minaretOffsets = [-44, -36, 36, 44];
    minaretOffsets.forEach(mx => {
      ctx.fillStyle = '#E7E5E4';
      ctx.fillRect(mx - 3, -48, 6, 78);
      // Minaret Balconies (Şerefe)
      ctx.fillStyle = '#78716C';
      ctx.fillRect(mx - 5, -28, 10, 4);
      ctx.fillRect(mx - 5, -42, 10, 4);
      // Conical pointed spire
      ctx.beginPath();
      ctx.moveTo(mx - 3, -48); ctx.lineTo(mx + 3, -48); ctx.lineTo(mx, -64); ctx.closePath();
      ctx.fillStyle = '#475569'; ctx.fill();
    });
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Ayasofya-i Kebir', 0, 42);
    ctx.restore();

    // 6. Kız Kulesi (Maiden's Tower) on its own islet at (740, 390)
    ctx.save();
    ctx.translate(740, 390);
    ctx.fillStyle = '#78716C';
    ctx.beginPath(); ctx.ellipse(0, 16, 26, 12, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-12, -10, 24, 24);
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 1.5; ctx.strokeRect(-12, -10, 24, 24);
    ctx.fillStyle = '#D6D3D1'; ctx.fillRect(-8, -22, 16, 12);
    ctx.beginPath(); ctx.arc(0, -22, 8, Math.PI, 0); ctx.fillStyle = '#DC2626'; ctx.fill();
    // Sweeping Lighthouse Beam
    const ltAngle = this.starPulse * 1.2;
    const ltLen = 130;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(0, -22);
    ctx.lineTo(Math.cos(ltAngle - 0.25) * ltLen, -22 + Math.sin(ltAngle - 0.25) * ltLen * 0.5);
    ctx.lineTo(Math.cos(ltAngle + 0.25) * ltLen, -22 + Math.sin(ltAngle + 0.25) * ltLen * 0.5);
    ctx.closePath();
    const ltGrad = ctx.createRadialGradient(0, -22, 4, 0, -22, ltLen);
    ltGrad.addColorStop(0, 'rgba(254, 240, 138, 0.8)');
    ltGrad.addColorStop(1, 'rgba(254, 240, 138, 0)');
    ctx.fillStyle = ltGrad;
    ctx.fill();
    ctx.restore();
    ctx.font = 'bold 8px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Kız Kulesi', 0, 34);
    ctx.restore();

    // 7. Taksim Nostaljik Kırmızı Tramvay on Rails
    ctx.save();
    const tramX = 180 + Math.sin(this.starPulse * 0.8) * 20;
    const tramY = 360;
    ctx.translate(tramX, tramY);
    ctx.strokeStyle = '#64748B'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(-45, 14); ctx.lineTo(45, 14); ctx.moveTo(-45, 18); ctx.lineTo(45, 18); ctx.stroke();
    ctx.fillStyle = '#DC2626'; ctx.fillRect(-18, -12, 36, 22);
    ctx.fillStyle = '#FEF08A'; ctx.fillRect(-18, 0, 36, 4);
    ctx.fillStyle = '#38BDF8';
    ctx.fillRect(-14, -8, 7, 7); ctx.fillRect(-4, -8, 8, 7); ctx.fillRect(7, -8, 7, 7);
    ctx.fillStyle = '#1E293B'; ctx.fillRect(-14, 10, 6, 4); ctx.fillRect(8, 10, 6, 4);
    ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5;
    ctx.beginPath(); ctx.moveTo(0, -12); ctx.lineTo(4, -24); ctx.lineTo(12, -24); ctx.stroke();
    ctx.font = 'bold 7px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Nostaljik Tramvay', 0, 28);
    ctx.restore();

    // 8. Flying Seagulls (Martılar) in the sky
    ctx.strokeStyle = '#1E293B';
    ctx.lineWidth = 1.5;
    const gulls = [
      { x: 310, y: 120 }, { x: 340, y: 105 }, { x: 620, y: 110 }, { x: 580, y: 90 }
    ];
    gulls.forEach((g, idx) => {
      const wingBob = Math.sin(this.starPulse * 3 + idx) * 3;
      ctx.beginPath();
      ctx.moveTo(g.x - 7, g.y + wingBob);
      ctx.quadraticCurveTo(g.x - 3, g.y - 4, g.x, g.y);
      ctx.quadraticCurveTo(g.x + 3, g.y - 4, g.x + 7, g.y + wingBob);
      ctx.stroke();
    });

    ctx.restore();
  }

  /* ----------------------------------------------------
     2. TOKYO ENVIRONMENT (Neon, Skytree, Tokyo Kulesi, Torii)
     ---------------------------------------------------- */
  drawTokyoEnvironment(ctx) {
    ctx.save();
    // 1. Cyber Grid Flooring
    ctx.strokeStyle = 'rgba(6, 182, 212, 0.12)';
    ctx.lineWidth = 1;
    for (let x = 80; x < 1020; x += 45) {
      ctx.beginPath(); ctx.moveTo(x, 60); ctx.lineTo(x, 700); ctx.stroke();
    }
    for (let y = 60; y < 700; y += 45) {
      ctx.beginPath(); ctx.moveTo(80, y); ctx.lineTo(1020, y); ctx.stroke();
    }

    // 2. Tokyo Tower (Red and White Steel Truss Pyramid) at x: 250, y: 200
    ctx.save();
    ctx.translate(250, 200);
    // Red/White Truss Body
    ctx.lineWidth = 3;
    ctx.strokeStyle = '#EF4444';
    ctx.beginPath();
    ctx.moveTo(-28, 50); ctx.lineTo(-12, -10); ctx.lineTo(12, -10); ctx.lineTo(28, 50);
    ctx.stroke();
    // Lattice crosses
    ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(-24, 40); ctx.lineTo(24, 20); ctx.moveTo(24, 40); ctx.lineTo(-24, 20);
    ctx.stroke();
    // Observation Deck
    ctx.fillStyle = '#FFFFFF'; ctx.fillRect(-18, -10, 36, 12);
    ctx.fillStyle = '#EF4444'; ctx.fillRect(-14, -22, 28, 12);
    // Needle mast
    ctx.strokeStyle = '#EF4444'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(0, -22); ctx.lineTo(0, -68); ctx.stroke();
    // Top beacon light
    ctx.fillStyle = '#FEF08A';
    ctx.beginPath(); ctx.arc(0, -68, 4, 0, Math.PI * 2); ctx.fill();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#E2E8F0'; ctx.textAlign = 'center';
    ctx.fillText('Tokyo Kulesi', 0, 64);
    ctx.restore();

    // 3. Tokyo Skytree (Futuristic Mast) at x: 720, y: 170
    ctx.save();
    ctx.translate(720, 170);
    ctx.strokeStyle = '#E2E8F0'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(-16, 60); ctx.lineTo(-6, -30); ctx.lineTo(6, -30); ctx.lineTo(16, 60); ctx.stroke();
    // Skytree observation spheres
    ctx.fillStyle = '#06B6D4';
    ctx.beginPath(); ctx.ellipse(0, 10, 20, 7, 0, 0, Math.PI * 2); ctx.fill();
    ctx.beginPath(); ctx.ellipse(0, -20, 14, 5, 0, 0, Math.PI * 2); ctx.fill();
    // Antenna & Pulsing Cyan Beacon
    ctx.strokeStyle = '#38BDF8'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(0, -30); ctx.lineTo(0, -80); ctx.stroke();
    const beaconAlpha = 0.5 + Math.sin(this.starPulse * 4) * 0.5;
    ctx.fillStyle = `rgba(34, 211, 238, ${beaconAlpha})`;
    ctx.beginPath(); ctx.arc(0, -80, 6, 0, Math.PI * 2); ctx.fill();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#E2E8F0'; ctx.textAlign = 'center';
    ctx.fillText('Tokyo Skytree', 0, 74);
    ctx.restore();

    // 4. Shinto Torii Gate at x: 450, y: 460
    ctx.save();
    ctx.translate(450, 460);
    ctx.fillStyle = '#DC2626';
    // Twin vertical columns
    ctx.fillRect(-22, -26, 6, 36); ctx.fillRect(16, -26, 6, 36);
    // Main top crossbar (Kasagi)
    ctx.fillRect(-32, -34, 64, 7);
    ctx.fillStyle = '#0F172A'; ctx.fillRect(-34, -36, 68, 3);
    // Tie-beam (Nuki)
    ctx.fillStyle = '#DC2626'; ctx.fillRect(-25, -20, 50, 4);
    // Hanging glowing chōchin paper lanterns
    ctx.fillStyle = '#F59E0B';
    ctx.beginPath(); ctx.arc(-10, -12, 4, 0, Math.PI * 2); ctx.arc(10, -12, 4, 0, Math.PI * 2); ctx.fill();
    ctx.restore();

    // 5. Holographic Shibuya / Shinjuku Neon Billboards
    const neonSigns = [
      { x: 380, y: 220, text: '渋谷 NEON', color: '#06B6D4' },
      { x: 570, y: 220, text: 'ラーメン 🍜', color: '#F59E0B' },
      { x: 840, y: 340, text: 'ゲーム 🎮', color: '#EC4899' }
    ];
    neonSigns.forEach(s => {
      ctx.save();
      ctx.translate(s.x, s.y);
      ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
      ctx.roundRect(-36, -14, 72, 28, 6);
      ctx.fill();
      ctx.strokeStyle = s.color; ctx.lineWidth = 2;
      ctx.shadowColor = s.color; ctx.shadowBlur = 10;
      ctx.stroke();
      ctx.font = 'bold 10px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(s.text, 0, 0);
      ctx.restore();
    });

    // 6. Sakura Blossom Tree with drifting petals
    ctx.save();
    ctx.translate(850, 450);
    ctx.fillStyle = '#78350F'; ctx.fillRect(-4, 0, 8, 22);
    ctx.fillStyle = '#F472B6';
    ctx.beginPath();
    ctx.arc(-10, -12, 14, 0, Math.PI * 2);
    ctx.arc(10, -14, 16, 0, Math.PI * 2);
    ctx.arc(0, -22, 18, 0, Math.PI * 2);
    ctx.fill();
    ctx.restore();

    ctx.restore();
  }

  /* ----------------------------------------------------
     3. PARİS ENVIRONMENT (Eyfel, Zafer Takı, Sen Nehri, Louvre)
     ---------------------------------------------------- */
  drawParisEnvironment(ctx) {
    ctx.save();

    // 1. River Seine (Sen Nehri) with Deep Water Flow
    ctx.beginPath();
    ctx.moveTo(350, 60); ctx.bezierCurveTo(440, 220, 360, 440, 600, 720);
    ctx.lineTo(720, 720); ctx.bezierCurveTo(480, 440, 560, 220, 470, 60);
    ctx.closePath();
    const seineGrad = ctx.createLinearGradient(350, 60, 650, 720);
    seineGrad.addColorStop(0, '#0284C7');
    seineGrad.addColorStop(0.5, '#0369A1');
    seineGrad.addColorStop(1, '#075985');
    ctx.fillStyle = seineGrad;
    ctx.fill();

    // Seine Water Ripples
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.25)';
    ctx.lineWidth = 1.5;
    for (let r = 0; r < 5; r++) {
      const rx = 440 + Math.sin(this.starPulse + r * 1.4) * 30;
      const ry = 180 + r * 95;
      ctx.beginPath();
      ctx.arc(rx, ry, 20 + r * 3, 0.2 * Math.PI, 0.8 * Math.PI);
      ctx.stroke();
    }

    // Cruise Boat (Bateau-Mouche) on Seine
    const boatX = 490 + Math.sin(this.starPulse * 0.8) * 22;
    const boatY = 460 + Math.cos(this.starPulse * 0.8) * 18;
    ctx.save();
    ctx.translate(boatX, boatY);
    ctx.rotate(0.32);
    ctx.fillStyle = '#FFFFFF';
    ctx.beginPath();
    ctx.moveTo(-16, 5); ctx.lineTo(16, 5); ctx.lineTo(12, 13); ctx.lineTo(-12, 13); ctx.closePath();
    ctx.fill();
    ctx.fillStyle = '#0284C7';
    ctx.fillRect(-10, 1, 20, 4);
    ctx.fillStyle = '#FEF08A';
    ctx.fillRect(-7, 2, 3, 2); ctx.fillRect(-1, 2, 3, 2); ctx.fillRect(5, 2, 3, 2);
    ctx.restore();

    // 2. Arched Pont Neuf Stone Bridge
    ctx.fillStyle = '#E2E8F0';
    ctx.fillRect(400, 280, 110, 22);
    ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 2;
    ctx.strokeRect(400, 280, 110, 22);
    // Bridge Arch cutouts
    ctx.fillStyle = '#0369A1';
    ctx.beginPath(); ctx.arc(430, 302, 12, Math.PI, 0); ctx.arc(480, 302, 12, Math.PI, 0); ctx.fill();
    // Bridge balustrade details
    ctx.strokeStyle = '#64748B'; ctx.lineWidth = 1.2;
    for (let bx = 410; bx <= 500; bx += 10) {
      ctx.beginPath(); ctx.moveTo(bx, 280); ctx.lineTo(bx, 286); ctx.stroke();
    }

    // 3. 3D Parisian Haussmann Buildings (Living city quarters)
    const buildings = [
      { x: 330, y: 350, w: 32, h: 26 },
      { x: 375, y: 385, w: 28, h: 24 },
      { x: 740, y: 330, w: 34, h: 28 },
      { x: 785, y: 365, w: 30, h: 26 },
      { x: 670, y: 380, w: 26, h: 24 }
    ];
    buildings.forEach(b => {
      ctx.save();
      ctx.translate(b.x, b.y);
      // Drop Shadow
      ctx.fillStyle = 'rgba(0,0,0,0.3)';
      ctx.fillRect(-b.w / 2 + 2, b.h / 2, b.w, 4);
      // Stone facade
      ctx.fillStyle = '#E2E8F0';
      ctx.fillRect(-b.w / 2, -b.h / 2, b.w, b.h);
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1;
      ctx.strokeRect(-b.w / 2, -b.h / 2, b.w, b.h);
      // Blue-grey zinc mansard roof
      ctx.beginPath();
      ctx.moveTo(-b.w / 2 - 2, -b.h / 2);
      ctx.lineTo(-b.w / 2 + 4, -b.h / 2 - 8);
      ctx.lineTo(b.w / 2 - 4, -b.h / 2 - 8);
      ctx.lineTo(b.w / 2 + 2, -b.h / 2);
      ctx.closePath();
      ctx.fillStyle = '#475569';
      ctx.fill();
      // Lit warm windows
      ctx.fillStyle = '#FEF08A';
      for (let wx = -b.w / 2 + 5; wx <= b.w / 2 - 8; wx += 8) {
        for (let wy = -b.h / 2 + 4; wy <= b.h / 2 - 6; wy += 8) {
          ctx.fillRect(wx, wy, 4, 5);
        }
      }
      ctx.restore();
    });

    // 4. French Formal Garden Parterres (Champs de Mars) & Fountain
    ctx.save();
    ctx.translate(290, 275);
    // Green hedge borders
    ctx.strokeStyle = '#15803D'; ctx.lineWidth = 3;
    ctx.strokeRect(-24, -16, 48, 32);
    ctx.fillStyle = '#14532D'; ctx.fillRect(-22, -14, 44, 28);
    // Center Fountain
    ctx.beginPath(); ctx.arc(0, 0, 7, 0, Math.PI * 2); ctx.fillStyle = '#38BDF8'; ctx.fill();
    ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.stroke();
    // Fountain water spray
    const sprayH = 4 + Math.sin(this.starPulse * 4) * 2;
    ctx.fillStyle = 'rgba(255, 255, 255, 0.85)';
    ctx.beginPath(); ctx.arc(0, -sprayH, 2, 0, Math.PI * 2); ctx.fill();
    ctx.restore();

    // 5. Tour Eiffel (Eiffel Tower) with Rotating Golden Spotlight at x: 240, y: 200
    ctx.save();
    ctx.translate(240, 200);
    // Grand Ground Arch
    ctx.strokeStyle = '#78716C'; ctx.lineWidth = 3.5;
    ctx.beginPath(); ctx.moveTo(-32, 50); ctx.lineTo(-14, 10); ctx.lineTo(14, 10); ctx.lineTo(32, 50); ctx.stroke();
    ctx.beginPath(); ctx.arc(0, 50, 18, Math.PI, 0); ctx.stroke();
    // 1st and 2nd Level Decks
    ctx.fillStyle = '#57534E';
    ctx.fillRect(-22, 10, 44, 7);
    ctx.fillRect(-15, -15, 30, 6);
    // Middle tapered shaft
    ctx.beginPath(); ctx.moveTo(-13, 10); ctx.lineTo(-7, -15); ctx.lineTo(7, -15); ctx.lineTo(13, 10); ctx.stroke();
    // Top Spire
    ctx.beginPath(); ctx.moveTo(-6, -15); ctx.lineTo(0, -68); ctx.lineTo(6, -15); ctx.stroke();
    // Golden Twinkle Lights
    ctx.fillStyle = '#FDE047';
    for (let i = 0; i < 6; i++) {
      if (Math.sin(this.starPulse * 3 + i) > 0) {
        ctx.fillRect(-8 + (i % 3) * 8, 4 - i * 8, 2, 2);
      }
    }
    // Rotating Sweeping Beacon Spotlight Beam
    const beaconAngle = this.starPulse * 0.7;
    const beamLength = 160;
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(0, -68);
    ctx.lineTo(Math.cos(beaconAngle - 0.2) * beamLength, -68 + Math.sin(beaconAngle - 0.2) * beamLength * 0.4);
    ctx.lineTo(Math.cos(beaconAngle + 0.2) * beamLength, -68 + Math.sin(beaconAngle + 0.2) * beamLength * 0.4);
    ctx.closePath();
    const beamGrad = ctx.createRadialGradient(0, -68, 5, 0, -68, beamLength);
    beamGrad.addColorStop(0, 'rgba(253, 224, 71, 0.75)');
    beamGrad.addColorStop(1, 'rgba(253, 224, 71, 0)');
    ctx.fillStyle = beamGrad;
    ctx.fill();
    ctx.restore();

    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Eyfel Kulesi', 0, 65);
    ctx.restore();

    // 6. Arc de Triomphe (Zafer Takı) at x: 700, y: 210
    ctx.save();
    ctx.translate(700, 210);
    ctx.fillStyle = '#E7E5E4';
    ctx.fillRect(-26, -20, 52, 45);
    ctx.strokeStyle = '#A8A29E'; ctx.lineWidth = 2;
    ctx.strokeRect(-26, -20, 52, 45);
    // Center Arch Vault
    ctx.fillStyle = '#1E293B';
    ctx.beginPath();
    ctx.arc(0, 5, 12, Math.PI, 0); ctx.rect(-12, 5, 24, 20); ctx.fill();
    ctx.stroke();
    // Attic cornice frieze
    ctx.fillStyle = '#D6D3D1'; ctx.fillRect(-30, -28, 60, 8);
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Zafer Takı', 0, 40);
    ctx.restore();

    // 7. Louvre Glass Pyramid (Louvre Piramidi) at x: 460, y: 460
    ctx.save();
    ctx.translate(460, 460);
    ctx.beginPath();
    ctx.moveTo(-28, 20); ctx.lineTo(28, 20); ctx.lineTo(0, -18); ctx.closePath();
    ctx.fillStyle = 'rgba(217, 249, 157, 0.55)'; ctx.fill();
    ctx.strokeStyle = '#CA8A04'; ctx.lineWidth = 2; ctx.stroke();
    // Diamond facets
    ctx.beginPath();
    ctx.moveTo(-14, 20); ctx.lineTo(0, -18); ctx.lineTo(14, 20);
    ctx.moveTo(-28, 20); ctx.lineTo(0, 2); ctx.lineTo(28, 20);
    ctx.stroke();
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#FFFFFF'; ctx.textAlign = 'center';
    ctx.fillText('Louvre Piramidi', 0, 32);
    ctx.restore();

    ctx.restore();
  }

  /* ----------------------------------------------------
     4. NEW YORK ENVIRONMENT (Empire State, Özgürlük Heykeli, Köprü)
     ---------------------------------------------------- */
  drawNewYorkEnvironment(ctx) {
    ctx.save();

    // 1. Empire State Building at x: 260, y: 190
    ctx.save();
    ctx.translate(260, 190);
    // Stepped Art Deco Towers
    ctx.fillStyle = '#CBD5E1';
    ctx.fillRect(-22, 10, 44, 45);
    ctx.fillRect(-16, -15, 32, 25);
    ctx.fillRect(-10, -40, 20, 25);
    // Mooring Mast & Needle
    ctx.fillStyle = '#94A3B8'; ctx.fillRect(-4, -60, 8, 20);
    ctx.strokeStyle = '#EF4444'; ctx.lineWidth = 2;
    ctx.beginPath(); ctx.moveTo(0, -60); ctx.lineTo(0, -82); ctx.stroke();
    // Glowing top beacon
    ctx.fillStyle = '#EF4444';
    ctx.beginPath(); ctx.arc(0, -82, 4, 0, Math.PI * 2); ctx.fill();
    // Office Windows
    ctx.fillStyle = '#FEF08A';
    for (let wy = -35; wy < 40; wy += 8) {
      ctx.fillRect(-7, wy, 3, 4);
      ctx.fillRect(4, wy, 3, 4);
    }
    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Empire State', 0, 68);
    ctx.restore();

    // 2. Chrysler Building Spire at x: 420, y: 180
    ctx.save();
    ctx.translate(420, 180);
    ctx.fillStyle = '#94A3B8';
    ctx.fillRect(-14, 0, 28, 50);
    // Sunburst crown arcs
    ctx.fillStyle = '#E2E8F0';
    ctx.beginPath(); ctx.arc(0, 0, 12, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(0, -10, 8, Math.PI, 0); ctx.fill();
    // Stainless Needle
    ctx.strokeStyle = '#E2E8F0'; ctx.lineWidth = 2.5;
    ctx.beginPath(); ctx.moveTo(0, -10); ctx.lineTo(0, -55); ctx.stroke();
    ctx.restore();

    // 3. Statue of Liberty (Özgürlük Heykeli) at x: 740, y: 200
    ctx.save();
    ctx.translate(740, 200);
    // Star Fort Pedestal
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-20, 15, 40, 30);
    ctx.fillStyle = '#A8A29E';
    ctx.fillRect(-14, -5, 28, 20);
    // Copper Statue (Oxidized Green)
    ctx.fillStyle = '#34D399';
    // Draped Toga Robe
    ctx.beginPath();
    ctx.moveTo(-10, -5); ctx.lineTo(-14, 20); ctx.lineTo(14, 20); ctx.lineTo(10, -5); ctx.closePath();
    ctx.fill();
    // Head & 7-spike Crown
    ctx.beginPath(); ctx.arc(0, -12, 6, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#059669'; ctx.lineWidth = 1.5;
    for (let s = -3; s <= 3; s++) {
      ctx.beginPath(); ctx.moveTo(s * 2, -16); ctx.lineTo(s * 3.5, -22); ctx.stroke();
    }
    // Raised Right Arm & Golden Torch
    ctx.strokeStyle = '#34D399'; ctx.lineWidth = 3;
    ctx.beginPath(); ctx.moveTo(6, -8); ctx.lineTo(16, -26); ctx.stroke();
    // Golden Flame Beacon
    ctx.fillStyle = '#F59E0B';
    ctx.beginPath(); ctx.ellipse(16, -29, 4, 6, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = 'rgba(251, 191, 36, 0.4)';
    ctx.beginPath(); ctx.arc(16, -29, 9, 0, Math.PI * 2); ctx.fill();

    ctx.font = 'bold 9px sans-serif'; ctx.fillStyle = '#1E293B'; ctx.textAlign = 'center';
    ctx.fillText('Özgürlük Heykeli', 0, 58);
    ctx.restore();

    // 4. Brooklyn Bridge at x: 530, y: 470
    ctx.save();
    ctx.translate(530, 470);
    // Gothic Twin Arches
    ctx.fillStyle = '#78716C';
    ctx.fillRect(-22, -26, 44, 38);
    ctx.fillStyle = '#FEF08A';
    ctx.beginPath();
    ctx.arc(-10, -8, 6, Math.PI, 0); ctx.rect(-16, -8, 12, 20); ctx.fill();
    ctx.beginPath();
    ctx.arc(10, -8, 6, Math.PI, 0); ctx.rect(4, -8, 12, 20); ctx.fill();
    // Suspension Cables
    ctx.strokeStyle = '#475569'; ctx.lineWidth = 1.5;
    ctx.beginPath();
    ctx.moveTo(-50, -10); ctx.quadraticCurveTo(-20, 4, 0, -26);
    ctx.quadraticCurveTo(20, 4, 50, -10);
    ctx.stroke();
    ctx.restore();

    // 5. Yellow New York Cabs
    const cabPos = (this.starPulse * 30) % 300;
    ctx.fillStyle = '#FACC15';
    ctx.fillRect( cabPos + 320, 680, 24, 12);
    ctx.fillStyle = '#1E293B';
    ctx.fillRect( cabPos + 323, 689, 5, 5);
    ctx.fillRect( cabPos + 336, 689, 5, 5);
    ctx.fillStyle = '#EF4444';
    ctx.fillRect( cabPos + 342, 682, 2, 4);

    ctx.restore();
  }

  /* ----------------------------------------------------
     AMBIENT WORLD DETAILS (Street Lamps, Light Cones, Trees, Foliage)
     ---------------------------------------------------- */
  drawAmbientWorldDetails(ctx, theme) {
    ctx.save();

    // 1. Street Lamps with Warm Glowing Cones along the track
    const lampCoords = [
      { x: 267, y: 560 }, { x: 533, y: 655 }, { x: 813, y: 615 }, { x: 990, y: 500 },
      { x: 878, y: 175 }, { x: 524, y: 145 }, { x: 241, y: 165 }, { x: 118, y: 295 }
    ];

    lampCoords.forEach(lamp => {
      // Warm ground light circle
      ctx.fillStyle = 'rgba(254, 240, 138, 0.18)';
      ctx.beginPath();
      ctx.ellipse(lamp.x, lamp.y + 6, 22, 10, 0, 0, Math.PI * 2);
      ctx.fill();

      // Lamp post & glowing head
      ctx.fillStyle = '#1E293B';
      ctx.fillRect(lamp.x - 1.5, lamp.y - 18, 3, 18);
      ctx.fillStyle = '#FEF08A';
      ctx.beginPath();
      ctx.arc(lamp.x, lamp.y - 19, 4, 0, Math.PI * 2);
      ctx.fill();
    });

    // 2. Isometric 3D Puffy Cartoon Trees (Fills empty spaces with greenery)
    const treeCoords = [
      { x: 270, y: 350, s: 1.1 }, { x: 310, y: 380, s: 0.9 }, { x: 280, y: 420, s: 1.2 },
      { x: 820, y: 280, s: 1.0 }, { x: 860, y: 310, s: 1.2 }, { x: 790, y: 340, s: 0.9 },
      { x: 410, y: 530, s: 1.1 }, { x: 670, y: 530, s: 1.0 }, { x: 490, y: 260, s: 1.2 },
      { x: 580, y: 260, s: 1.1 }, { x: 370, y: 280, s: 0.9 }, { x: 720, y: 270, s: 1.0 }
    ];

    treeCoords.forEach(t => {
      ctx.save();
      ctx.translate(t.x, t.y);
      ctx.scale(t.s, t.s);

      // Shadow
      ctx.fillStyle = 'rgba(15, 23, 42, 0.12)';
      ctx.beginPath();
      ctx.ellipse(0, 12, 16, 7, 0, 0, Math.PI * 2);
      ctx.fill();

      // Trunk
      ctx.fillStyle = '#78350F';
      ctx.fillRect(-3, 0, 6, 12);

      // Foliage Layers (Puffy Cartoon Tree)
      ctx.fillStyle = '#15803D';
      ctx.beginPath();
      ctx.arc(0, -6, 16, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#22C55E';
      ctx.beginPath();
      ctx.arc(-2, -10, 13, 0, Math.PI * 2);
      ctx.fill();

      ctx.fillStyle = '#86EFAC';
      ctx.beginPath();
      ctx.arc(-5, -14, 6, 0, Math.PI * 2);
      ctx.fill();

      ctx.restore();
    });

    ctx.restore();
  }

  drawPathways(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.istanbul;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Connector drop shadow
    ctx.beginPath();
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      if (i === 0) ctx.moveTo(p1.x, p1.y + 12);
      ctx.lineTo(p2.x, p2.y + 12);
    }
    ctx.strokeStyle = 'rgba(15, 23, 42, 0.12)';
    ctx.lineWidth = 26;
    ctx.stroke();

    // Road Base
    ctx.beginPath();
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      if (i === 0) ctx.moveTo(p1.x, p1.y);
      ctx.lineTo(p2.x, p2.y);
    }
    ctx.strokeStyle = theme.roadBase;
    ctx.lineWidth = 22;
    ctx.stroke();

    // Dotted center line
    ctx.strokeStyle = theme.roadBorder;
    ctx.lineWidth = 3.5;
    ctx.setLineDash([8, 12]);
    ctx.stroke();
    ctx.setLineDash([]);

    // Forward Direction Chevrons (Kutucuklar üzerinde gidişat akışı)
    for (let i = 0; i < this.tileCoords.length; i++) {
      const p1 = this.tileCoords[i];
      const p2 = this.tileCoords[(i + 1) % this.tileCoords.length];
      const mx = (p1.x + p2.x) / 2;
      const my = (p1.y + p2.y) / 2;
      const angle = Math.atan2(p2.y - p1.y, p2.x - p1.x);

      // Gentle forward pulse shift
      const pulseShift = (Math.sin(this.starPulse * 2.5 + i * 0.45) + 1) * 0.5;

      ctx.save();
      ctx.translate(mx, my);
      ctx.rotate(angle);
      ctx.fillStyle = theme.roadBorder;
      ctx.globalAlpha = 0.45 + pulseShift * 0.45;

      // Draw forward-pointing chevron
      ctx.beginPath();
      ctx.moveTo(-6, -6);
      ctx.lineTo(4, 0);
      ctx.lineTo(-6, 6);
      ctx.lineTo(-3, 0);
      ctx.closePath();
      ctx.fill();
      ctx.restore();
    }

    ctx.restore();
  }

  /* ----------------------------------------------------
     STEP TRAJECTORY PREVIEW (Gidişat Yolu ve Hedef Karosu)
     ---------------------------------------------------- */
  drawStepTrajectory(ctx) {
    const roomState = window.CURRENT_ROOM_STATE;
    if (!roomState || !roomState.players || roomState.players.length === 0) return;

    const activePlayer = roomState.players[roomState.turnIndex];
    if (!activePlayer) return;

    const startIdx = activePlayer.position;
    const previewSteps = 5;

    ctx.save();
    // Glowing connecting line along future steps
    ctx.setLineDash([6, 8]);
    ctx.lineDashOffset = -this.starPulse * 24;
    ctx.strokeStyle = 'rgba(251, 191, 36, 0.75)';
    ctx.lineWidth = 3.5;
    ctx.beginPath();

    const startPos = this.tileCoords[startIdx];
    if (startPos) {
      ctx.moveTo(startPos.x, startPos.y);

      for (let step = 1; step <= previewSteps; step++) {
        const nextIdx = (startIdx + step) % this.tileCoords.length;
        const nextPos = this.tileCoords[nextIdx];
        ctx.lineTo(nextPos.x, nextPos.y);
      }
      ctx.stroke();
    }
    ctx.setLineDash([]);

    // Floating step progression tags (+1, +2, +3... Target)
    for (let step = 1; step <= previewSteps; step++) {
      const nextIdx = (startIdx + step) % this.tileCoords.length;
      const p = this.tileCoords[nextIdx];
      if (!p) continue;
      const isTarget = (step === previewSteps);

      ctx.save();
      ctx.translate(p.x, p.y - 28);
      if (isTarget) {
        const bounce = Math.sin(this.starPulse * 3.5) * 4;
        ctx.translate(0, bounce);
        ctx.beginPath();
        ctx.arc(0, 0, 14, 0, Math.PI * 2);
        ctx.fillStyle = '#EF4444';
        ctx.shadowColor = '#EF4444';
        ctx.shadowBlur = 10;
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.fillStyle = '#FFFFFF';
        ctx.font = 'bold 10px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText('🎯', 0, 0);
      } else {
        ctx.beginPath();
        ctx.arc(0, 0, 9, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
        ctx.fill();
        ctx.strokeStyle = '#FBBF24';
        ctx.lineWidth = 1.5;
        ctx.stroke();
        ctx.fillStyle = '#FBBF24';
        ctx.font = 'bold 8px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(`+${step}`, 0, 0);
      }
      ctx.restore();
    }

    ctx.restore();
  }

  /* ----------------------------------------------------
     DRAMATIC TURN SPOTLIGHT (Sıranın Kimde Olduğu Apaydın)
     ---------------------------------------------------- */
  drawTurnSpotlight(ctx) {
    const roomState = window.CURRENT_ROOM_STATE;
    if (!roomState || !roomState.players || roomState.players.length === 0) return;

    const activePlayer = roomState.players[roomState.turnIndex];
    if (!activePlayer) return;

    const anim = this.playerHopState && this.playerHopState[activePlayer.id];
    const px = anim ? anim.displayX : (this.tileCoords[activePlayer.position] ? this.tileCoords[activePlayer.position].x : 550);
    const py = anim ? (anim.displayY + anim.hopArcY) : (this.tileCoords[activePlayer.position] ? this.tileCoords[activePlayer.position].y : 380);

    ctx.save();

    // 1. Conical Volumetric Spotlight Beam from Sky
    const spotGrad = ctx.createLinearGradient(px, 0, px, py + 15);
    spotGrad.addColorStop(0, 'rgba(251, 191, 36, 0.03)');
    spotGrad.addColorStop(0.5, 'rgba(251, 191, 36, 0.12)');
    spotGrad.addColorStop(1, 'rgba(251, 191, 36, 0.32)');

    ctx.beginPath();
    ctx.moveTo(px - 18, 0);
    ctx.lineTo(px + 18, 0);
    ctx.lineTo(px + 48, py + 14);
    ctx.lineTo(px - 48, py + 14);
    ctx.closePath();
    ctx.fillStyle = spotGrad;
    ctx.fill();

    // 2. Pulsing Golden Halo Ring on the Floor
    const haloRadius = 26 + Math.sin(this.starPulse * 3.5) * 6;
    ctx.beginPath();
    ctx.ellipse(px, py + 10, haloRadius, haloRadius * 0.45, 0, 0, Math.PI * 2);
    ctx.strokeStyle = '#FBBF24';
    ctx.lineWidth = 3;
    ctx.shadowColor = '#FBBF24';
    ctx.shadowBlur = 14;
    ctx.stroke();

    // 3. Elegant Floating 3D Pointer Diamond (Unobtrusive & Never collides with name tags)
    const isMe = (window.GameClient && window.GameClient.player && window.GameClient.player.sessionId === activePlayer.sessionId);
    const bob = Math.sin(this.starPulse * 4) * 4;
    ctx.translate(px, py - 64 + bob);

    // Glowing 3D Diamond Indicator
    const diamondColor = isMe ? '#10B981' : '#F59E0B';
    const diamondBorder = isMe ? '#6EE7B7' : '#FDE047';

    ctx.save();
    ctx.shadowColor = diamondColor;
    ctx.shadowBlur = 12;

    // Diamond shape
    ctx.beginPath();
    ctx.moveTo(0, -9);
    ctx.lineTo(8, 0);
    ctx.lineTo(0, 9);
    ctx.lineTo(-8, 0);
    ctx.closePath();
    ctx.fillStyle = diamondColor;
    ctx.fill();
    ctx.strokeStyle = diamondBorder;
    ctx.lineWidth = 2;
    ctx.stroke();

    // Diamond facet highlight
    ctx.beginPath();
    ctx.moveTo(0, -9);
    ctx.lineTo(8, 0);
    ctx.lineTo(0, 0);
    ctx.closePath();
    ctx.fillStyle = 'rgba(255, 255, 255, 0.45)';
    ctx.fill();

    // Downward pointer tip
    ctx.beginPath();
    ctx.moveTo(-4, 7);
    ctx.lineTo(4, 7);
    ctx.lineTo(0, 13);
    ctx.closePath();
    ctx.fillStyle = diamondBorder;
    ctx.fill();
    ctx.restore();

    ctx.restore();
  }

  drawPlatformTile(ctx, index, tile, pos, isStarTile, isSelected, hasTrap) {
    const w = 68;
    const h = 50;
    const x = pos.x - w / 2;
    const y = pos.y - h / 2;

    const isHazard = (tile.type === 'trap' || tile.type === 'tax' || tile.type === 'jail' || tile.type === 'slip' || tile.type === 'freeze');
    const isBank = (tile.type === 'bank');
    const isStart = (tile.type === 'start');

    ctx.save();

    // 1. Selected Tile Pulsing Ring
    if (isSelected) {
      ctx.beginPath();
      ctx.roundRect(x - 6, y - 6, w + 12, h + 12, 20);
      ctx.strokeStyle = '#38BDF8';
      ctx.lineWidth = 3.5;
      ctx.stroke();
    }

    // 2. Star Pedestal Volumetric Aura
    if (isStarTile) {
      const pulseSize = 14 + Math.sin(this.starPulse * 1.5) * 6;
      ctx.beginPath();
      ctx.ellipse(pos.x, pos.y + 6, (w / 2) + pulseSize, (h / 2) + pulseSize * 0.7, 0, 0, Math.PI * 2);
      const starGrad = ctx.createRadialGradient(pos.x, pos.y, 8, pos.x, pos.y, (w / 2) + pulseSize);
      starGrad.addColorStop(0, 'rgba(251, 191, 36, 0.85)');
      starGrad.addColorStop(0.5, 'rgba(245, 158, 11, 0.35)');
      starGrad.addColorStop(1, 'rgba(245, 158, 11, 0)');
      ctx.fillStyle = starGrad;
      ctx.fill();
    }

    // 3. Hazard Tile Danger Aura
    if (isHazard) {
      const hazardPulse = 6 + Math.sin(this.starPulse * 2.5) * 4;
      ctx.beginPath();
      ctx.ellipse(pos.x, pos.y + 8, (w / 2) + hazardPulse, (h / 2) + hazardPulse * 0.6, 0, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(239, 68, 68, 0.16)';
      ctx.fill();
    }

    // 4. Soft Ground Shadow
    ctx.beginPath();
    ctx.ellipse(pos.x, pos.y + (h / 2) + 6, w * 0.48, 10, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.22)';
    ctx.fill();

    // 5. 3D Extruded Bevel Base (Side Skirt)
    ctx.beginPath();
    ctx.roundRect(x, y + 6, w, h, 14);
    if (isHazard) {
      ctx.fillStyle = '#1A0C0E';
    } else if (isBank) {
      ctx.fillStyle = '#064E3B';
    } else {
      ctx.fillStyle = this.darkenColor(tile.color || '#3B82F6', 0.4);
    }
    ctx.fill();

    // 6. Top Island Platform Surface
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, 14);
    if (isHazard) {
      const lavaGrad = ctx.createLinearGradient(x, y, x, y + h);
      lavaGrad.addColorStop(0, '#261115');
      lavaGrad.addColorStop(0.6, '#38161D');
      lavaGrad.addColorStop(1, '#1A0A0D');
      ctx.fillStyle = lavaGrad;
    } else if (isBank) {
      const bankGrad = ctx.createLinearGradient(x, y, x, y + h);
      bankGrad.addColorStop(0, '#059669');
      bankGrad.addColorStop(1, '#047857');
      ctx.fillStyle = bankGrad;
    } else {
      const surfaceGrad = ctx.createLinearGradient(x, y, x, y + h);
      surfaceGrad.addColorStop(0, tile.color || '#3B82F6');
      surfaceGrad.addColorStop(1, this.darkenColor(tile.color || '#3B82F6', 0.18));
      ctx.fillStyle = surfaceGrad;
    }
    ctx.fill();

    // 7. Bevel Border Stroke
    if (isHazard) {
      ctx.strokeStyle = '#EF4444';
      ctx.lineWidth = 2.5;
    } else if (isStarTile) {
      ctx.strokeStyle = '#FDE047';
      ctx.lineWidth = 3;
    } else {
      ctx.strokeStyle = '#FFFFFF';
      ctx.lineWidth = 2.2;
    }
    ctx.stroke();

    // 8. Hazard Visual Details (Glowing Molten Lava Cracks & Hazard Stripes)
    if (isHazard) {
      ctx.save();
      // Glowing lava crack veins
      ctx.strokeStyle = '#F97316';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.moveTo(x + 10, y + 14);
      ctx.lineTo(x + 24, y + 26);
      ctx.lineTo(x + 32, y + 18);
      ctx.lineTo(x + 52, y + 36);
      ctx.stroke();

      ctx.strokeStyle = '#FEF08A';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(x + 12, y + 15);
      ctx.lineTo(x + 24, y + 26);
      ctx.stroke();

      // Caution corner tags
      ctx.fillStyle = '#EF4444';
      ctx.font = 'bold 8px sans-serif';
      ctx.fillText('⚠️', x + w - 12, y + 12);
      ctx.restore();
    }

    // 9. Center Emblem Badge
    ctx.beginPath();
    ctx.arc(pos.x, pos.y, 14, 0, Math.PI * 2);
    ctx.fillStyle = isHazard ? '#1E1B2E' : '#FFFFFF';
    ctx.fill();
    ctx.strokeStyle = isHazard ? '#EF4444' : (isBank ? '#F59E0B' : this.darkenColor(tile.color || '#3B82F6', 0.2));
    ctx.lineWidth = 2;
    ctx.stroke();

    // Center Icon
    let iconSymbol = '🪙';
    if (tile.type === 'start') iconSymbol = '🏁';
    else if (tile.type === 'star') iconSymbol = '⭐';
    else if (tile.type === 'bank') iconSymbol = '🏦';
    else if (tile.type === 'trap') iconSymbol = '💥';
    else if (tile.type === 'mystery') iconSymbol = '🎁';
    else if (tile.type === 'shop') iconSymbol = '🛍️';
    else if (tile.type === 'warp') iconSymbol = '🌀';
    else if (tile.type === 'slip') iconSymbol = '🍌';
    else if (tile.type === 'duel') iconSymbol = '⚔️';
    else if (tile.type === 'jail') iconSymbol = '⛓️';
    else if (tile.type === 'tax') iconSymbol = '💸';
    else if (tile.type === 'freeze') iconSymbol = '🧊';
    else if (tile.type === 'reverse') iconSymbol = '🔄';

    ctx.font = 'bold 13px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(iconSymbol, pos.x, pos.y + 1);

    // 10. Tile Number Badge
    ctx.beginPath();
    ctx.arc(x + 10, y + 8, 8, 0, Math.PI * 2);
    ctx.fillStyle = isHazard ? '#EF4444' : '#1E293B';
    ctx.fill();
    ctx.fillStyle = '#FFFFFF';
    ctx.font = 'bold 8px sans-serif';
    ctx.fillText(index.toString(), x + 10, y + 9);

    // 11. Floating Golden Star Flag
    if (isStarTile) {
      const starBob = Math.sin(this.starPulse * 1.5) * 8;
      ctx.save();
      ctx.translate(pos.x, y - 32 + starBob);
      ctx.font = '32px sans-serif';
      ctx.textAlign = 'center';
      ctx.shadowColor = '#F59E0B';
      ctx.shadowBlur = 14;
      ctx.fillText('⭐', 0, 0);
      ctx.restore();
    }

    // 12. Trap Marker
    if (hasTrap) {
      ctx.save();
      ctx.translate(pos.x, y - 16);
      ctx.font = '22px sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('🍌', 0, 0);
      ctx.restore();
    }

    ctx.restore();
  }

  drawLandmark(ctx) {
    const theme = MAP_THEMES[this.activeTheme] || MAP_THEMES.tropics;
    const lm = theme.landmark;
    ctx.save();
    ctx.translate(lm.x, lm.y);

    // Landmark Shadow
    ctx.beginPath();
    ctx.ellipse(0, 16, 28, 12, 0, 0, Math.PI * 2);
    ctx.fillStyle = 'rgba(15, 23, 42, 0.15)';
    ctx.fill();

    // Bobbing Landmark icon
    const bob = Math.sin(this.starPulse) * 4;
    ctx.font = '38px sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(lm.icon, 0, bob);

    // Interactive Callout Tag
    ctx.font = 'bold 10px sans-serif';
    ctx.fillStyle = 'rgba(15, 23, 42, 0.8)';
    ctx.fillText(lm.name, 0, 32);

    ctx.restore();
  }

  drawParticles(ctx) {
    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.life -= 0.035;

      if (p.life <= 0) {
        this.particles.splice(i, 1);
        continue;
      }

      ctx.beginPath();
      ctx.arc(p.x, p.y, 4 * p.life, 0, Math.PI * 2);
      ctx.fillStyle = p.color;
      ctx.globalAlpha = p.life;
      ctx.fill();
      ctx.globalAlpha = 1.0;
    }
  }

  /* --------------------------------------------------------------------------
     MONOPOLY-STYLE TILE-BY-TILE HOPPING & CHIBI CHARACTER TOKEN ENGINE
     -------------------------------------------------------------------------- */
  drawPlayerTokens(ctx, players) {
    if (!this.playerHopState) this.playerHopState = {};

    // 1. Group players by target/current tile position for cluster spacing
    const positionGroups = {};
    players.forEach(p => {
      positionGroups[p.position] = positionGroups[p.position] || [];
      positionGroups[p.position].push(p);
    });

    const tokensToRender = [];

    players.forEach((player) => {
      const tilePos = this.tileCoords[player.position] || { x: 110, y: 620 };
      const group = positionGroups[player.position];
      const count = group.length;
      const indexInGroup = group.indexOf(player);

      let clusterDx = 0;
      let clusterDy = 0;
      if (count === 2) {
        clusterDx = (indexInGroup === 0 ? -24 : 24);
      } else if (count >= 3) {
        const angle = (indexInGroup / count) * Math.PI * 2 - Math.PI / 2;
        clusterDx = Math.cos(angle) * 32;
        clusterDy = Math.sin(angle) * 22;
      }

      // Initialize state if first time seeing player
      if (!this.playerHopState[player.id]) {
        this.playerHopState[player.id] = {
          currentTile: player.position,
          targetTile: player.position,
          lastServerPos: player.position,
          hopQueue: [],
          hopProgress: 0,
          displayX: tilePos.x + clusterDx,
          displayY: tilePos.y + clusterDy,
          hopArcY: 0,
          scaleX: 1,
          scaleY: 1,
          isHopping: false
        };
      }

      const pState = this.playerHopState[player.id];

      // Detect server position change -> Enqueue intermediate tiles for Monopoly hop!
      if (player.position !== pState.lastServerPos) {
        const from = pState.currentTile;
        const to = player.position;
        pState.lastServerPos = player.position;

        if (from !== to) {
          const forwardSteps = (to - from + 32) % 32;
          const backwardSteps = (from - to + 32) % 32;
          const isReverse = !!player.reverseNext || (backwardSteps < forwardSteps && backwardSteps <= 6);

          if (isReverse) {
            for (let s = 1; s <= backwardSteps; s++) {
              pState.hopQueue.push((from - s + 32) % 32);
            }
          } else {
            for (let s = 1; s <= forwardSteps; s++) {
              pState.hopQueue.push((from + s) % 32);
            }
          }
          pState.isHopping = true;
        }
      }

      // Animate Hopping or Idle Breathing
      if (pState.hopQueue.length > 0) {
        const targetTile = pState.hopQueue[0];
        const fromPos = this.tileCoords[pState.currentTile] || { x: 110, y: 620 };
        const toPos = this.tileCoords[targetTile] || { x: 110, y: 620 };

        // Advance hop progress smoothly (accelerated by turbo speed & bot multiplier)
        const speedMultiplier = (window.GAME_SPEED || 1) * (player.isBot ? 1.35 : 1);
        pState.hopProgress += 0.042 * speedMultiplier;
        const p = Math.min(1, pState.hopProgress);

        // Linear ground position interpolation
        pState.displayX = fromPos.x + (toPos.x - fromPos.x) * p;
        pState.displayY = fromPos.y + (toPos.y - fromPos.y) * p;

        // Parabolic vertical jump arc (height = 32px)
        pState.hopArcY = -Math.sin(p * Math.PI) * 32;

        // Squash & Stretch physics
        if (p < 0.2) {
          const sub = p / 0.2;
          pState.scaleX = 1 + 0.15 * (1 - sub);
          pState.scaleY = 0.85 + 0.15 * sub;
        } else if (p < 0.8) {
          pState.scaleX = 0.88;
          pState.scaleY = 1.18;
        } else {
          const sub = (p - 0.8) / 0.2;
          pState.scaleX = 0.88 + 0.32 * sub;
          pState.scaleY = 1.18 - 0.38 * sub;
        }

        // Check if step completed
        if (pState.hopProgress >= 1) {
          pState.currentTile = pState.hopQueue.shift();
          pState.hopProgress = 0;
          pState.hopArcY = 0;
          pState.scaleX = 1;
          pState.scaleY = 1;

          // Sound effect on each hop landing
          if (window.AudioPlayer && window.AudioPlayer.playStep) {
            window.AudioPlayer.playStep();
          }

          // Emit dust puff particles at landing tile
          for (let d = 0; d < 4; d++) {
            this.particles.push({
              x: toPos.x + (Math.random() - 0.5) * 18,
              y: toPos.y + 12 + (Math.random() - 0.5) * 6,
              vx: (Math.random() - 0.5) * 2.2,
              vy: -Math.random() * 1.8 - 0.4,
              size: Math.random() * 3 + 2,
              color: 'rgba(226, 232, 240, 0.85)',
              life: 1,
              decay: 0.05
            });
          }

          // Destination reached!
          if (pState.hopQueue.length === 0) {
            pState.isHopping = false;
            // Celebratory star particles burst on final arrival
            for (let s = 0; s < 10; s++) {
              const ang = Math.random() * Math.PI * 2;
              const spd = Math.random() * 3.5 + 2;
              this.particles.push({
                x: toPos.x,
                y: toPos.y - 12,
                vx: Math.cos(ang) * spd,
                vy: Math.sin(ang) * spd - 2,
                size: Math.random() * 4 + 2,
                color: ['#FBBF24', '#38BDF8', '#F472B6', '#10B981'][Math.floor(Math.random() * 4)],
                life: 1,
                decay: 0.035
              });
            }
          }
        }
      } else {
        // Idle state: smooth slide to cluster offset & gentle breathing bob
        const targetX = tilePos.x + clusterDx;
        const targetY = tilePos.y + clusterDy;
        pState.displayX += (targetX - pState.displayX) * 0.2;
        pState.displayY += (targetY - pState.displayY) * 0.2;

        const idleBob = Math.sin(this.starPulse * 2.5 + player.name.charCodeAt(0)) * 1.8;
        pState.hopArcY = idleBob;
        pState.scaleX = 1;
        pState.scaleY = 1;
      }

      tokensToRender.push({
        player,
        floorX: pState.displayX,
        floorY: pState.displayY,
        px: pState.displayX,
        py: pState.displayY + pState.hopArcY,
        scaleX: pState.scaleX,
        scaleY: pState.scaleY,
        hopArcY: pState.hopArcY,
        clusterCount: count
      });
    });

    // 3. DEPTH SORTING (Y-Sort): Render tokens from top to bottom
    tokensToRender.sort((a, b) => a.floorY - b.floorY);

    // 4. Render each Chibi Token cleanly
    tokensToRender.forEach(item => {
      this.renderMinifigToken(
        ctx,
        item.player,
        item.px,
        item.py,
        item.floorY,
        item.clusterCount > 1,
        item.scaleX,
        item.scaleY,
        item.hopArcY
      );
    });
  }

  /* --------------------------------------------------------------------------
     PREMIUM 3D CHIBI CHARACTER TOKEN RENDERER
     -------------------------------------------------------------------------- */
  renderMinifigToken(ctx, player, px, py, floorY, isClustered, scaleX = 1, scaleY = 1, hopArcY = 0) {
    const isCurrent = window.CURRENT_ROOM_STATE && 
                      window.CURRENT_ROOM_STATE.players[window.CURRENT_ROOM_STATE.turnIndex] &&
                      window.CURRENT_ROOM_STATE.players[window.CURRENT_ROOM_STATE.turnIndex].id === player.id;

    ctx.save();

    // 1. Dynamic Ground Shadow (Stays on floor, shrinks & fades as token hops higher)
    const jumpHeight = Math.max(0, -hopArcY);
    const shadowScale = Math.max(0.35, 1 - (jumpHeight / 50));
    const shadowAlpha = Math.max(0.08, 0.28 * (1 - (jumpHeight / 45)));

    ctx.beginPath();
    ctx.ellipse(px, floorY + 8, 18 * shadowScale, 9 * shadowScale, 0, 0, Math.PI * 2);
    ctx.fillStyle = `rgba(15, 23, 42, ${shadowAlpha})`;
    ctx.fill();

    // 2. Base Colored Ring on Floor
    ctx.beginPath();
    ctx.ellipse(px, floorY + 7, 15 * shadowScale, 7 * shadowScale, 0, 0, Math.PI * 2);
    ctx.strokeStyle = player.color || '#3B82F6';
    ctx.lineWidth = 2.5 * shadowScale;
    ctx.stroke();

    // Apply Token Squash & Stretch Transform
    ctx.translate(px, py);
    ctx.scale(scaleX, scaleY);

    // 3. Shield Bubble if active
    if (player.shieldActive > 0) {
      ctx.beginPath();
      ctx.arc(0, -18, 28, 0, Math.PI * 2);
      ctx.strokeStyle = '#38BDF8';
      ctx.lineWidth = 2.5;
      ctx.fillStyle = 'rgba(56, 189, 248, 0.2)';
      ctx.fill();
      ctx.stroke();
    }

    // 3.1 Ghost Cloak Ethereal Aura if active
    if (player.ghostCloakActive > 0) {
      ctx.save();
      ctx.beginPath();
      ctx.arc(0, -18, 30, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(6, 182, 212, 0.25)';
      ctx.shadowColor = '#22D3EE';
      ctx.shadowBlur = 12;
      ctx.fill();
      ctx.strokeStyle = '#67E8F9';
      ctx.lineWidth = 2;
      ctx.setLineDash([4, 3]);
      ctx.stroke();
      ctx.restore();
    }

    // 4. Ice Block if frozen
    if (player.frozenTurns > 0) {
      ctx.beginPath();
      ctx.roundRect(-20, -42, 40, 48, 8);
      ctx.fillStyle = 'rgba(186, 230, 253, 0.55)';
      ctx.strokeStyle = '#0284C7';
      ctx.lineWidth = 2;
      ctx.fill();
      ctx.stroke();
    }

    const avatarId = player.avatar || 'builder';
    const mainColor = player.color || '#3B82F6';

    // 5. Draw Archetype-Specific Chibi Silhouette & Features
    if (avatarId === 'dino') {
      // --- CUTE DINO CHIBI ---
      // Dorsal crest spikes
      ctx.fillStyle = '#F59E0B';
      ctx.beginPath(); ctx.moveTo(-6, -34); ctx.lineTo(-12, -40); ctx.lineTo(-2, -37); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(0, -38); ctx.lineTo(0, -46); ctx.lineTo(6, -38); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(6, -34); ctx.lineTo(12, -40); ctx.lineTo(2, -37); ctx.closePath(); ctx.fill();

      // Green body & chubby head
      ctx.beginPath(); ctx.roundRect(-14, -20, 28, 22, 6); ctx.fillStyle = '#059669'; ctx.fill();
      ctx.beginPath(); ctx.roundRect(-13, -37, 26, 20, 9); ctx.fillStyle = '#10B981'; ctx.fill();
      ctx.strokeStyle = '#047857'; ctx.lineWidth = 1.5; ctx.stroke();

      // Light belly
      ctx.beginPath(); ctx.ellipse(0, -11, 7, 7, 0, 0, Math.PI * 2); ctx.fillStyle = '#A7F3D0'; ctx.fill();

      // Cute snout
      ctx.beginPath(); ctx.ellipse(0, -26, 8, 5, 0, 0, Math.PI * 2); ctx.fillStyle = '#34D399'; ctx.fill();
      ctx.fillStyle = '#065F46';
      ctx.beginPath(); ctx.arc(-2.5, -26.5, 1, 0, Math.PI * 2); ctx.arc(2.5, -26.5, 1, 0, Math.PI * 2); ctx.fill();

      // Big anime eyes
      ctx.fillStyle = '#064E3B';
      ctx.beginPath(); ctx.arc(-5.5, -31, 2.5, 0, Math.PI * 2); ctx.arc(5.5, -31, 2.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-6.2, -32, 1, 0, Math.PI * 2); ctx.arc(4.8, -32, 1, 0, Math.PI * 2); ctx.fill();

      // Rosy cheeks
      ctx.fillStyle = '#F472B6';
      ctx.beginPath(); ctx.ellipse(-9, -27, 2.5, 1.3, 0, 0, Math.PI * 2); ctx.ellipse(9, -27, 2.5, 1.3, 0, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'cat') {
      // --- SIBER NEKO (CAT) CHIBI ---
      // Curled kitty tail behind
      ctx.strokeStyle = mainColor; ctx.lineWidth = 3.5; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(10, -12); ctx.quadraticCurveTo(22, -18, 18, -30); ctx.stroke();

      // Kitty ears with pink inner fluff
      ctx.fillStyle = mainColor;
      ctx.beginPath(); ctx.moveTo(-11, -33); ctx.lineTo(-14, -46); ctx.lineTo(-4, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(11, -33); ctx.lineTo(14, -46); ctx.lineTo(4, -36); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDF2F8';
      ctx.beginPath(); ctx.moveTo(-10, -34); ctx.lineTo(-12, -42); ctx.lineTo(-5, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(10, -34); ctx.lineTo(12, -42); ctx.lineTo(5, -36); ctx.closePath(); ctx.fill();

      // Body & chubby face
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 6); ctx.fillStyle = mainColor; ctx.fill();
      ctx.beginPath(); ctx.roundRect(-12, -36, 24, 19, 8); ctx.fillStyle = '#F472B6'; ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.stroke();

      // Cyber visor band
      ctx.fillStyle = '#0F172A'; ctx.fillRect(-12, -34, 24, 4);
      ctx.fillStyle = '#06B6D4'; ctx.fillRect(-10, -33, 20, 2);

      // Cute big cat eyes
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.ellipse(-5, -27, 2.4, 2.8, 0, 0, Math.PI * 2); ctx.ellipse(5, -27, 2.4, 2.8, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-5.8, -28, 1, 0, Math.PI * 2); ctx.arc(4.2, -28, 1, 0, Math.PI * 2); ctx.fill();

      // Cute nose & whiskers
      ctx.fillStyle = '#9D174D'; ctx.beginPath(); ctx.moveTo(0, -24.5); ctx.lineTo(-1.5, -23); ctx.lineTo(1.5, -23); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1;
      ctx.beginPath(); ctx.moveTo(-9, -24); ctx.lineTo(-15, -25); ctx.moveTo(-9, -22); ctx.lineTo(-15, -21); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(9, -24); ctx.lineTo(15, -25); ctx.moveTo(9, -22); ctx.lineTo(15, -21); ctx.stroke();

      // Gold collar bell
      ctx.fillStyle = '#FBBF24'; ctx.beginPath(); ctx.arc(0, -18, 3, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'astro') {
      // --- SPACE EXPLORER CHIBI ---
      // Spacesuit torso
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 21, 5); ctx.fillStyle = '#F1F5F9'; ctx.fill();
      ctx.strokeStyle = '#CBD5E1'; ctx.lineWidth = 2; ctx.stroke();
      ctx.fillStyle = mainColor; ctx.beginPath(); ctx.roundRect(-8, -16, 16, 12, 3); ctx.fill();
      ctx.fillStyle = '#22C55E'; ctx.beginPath(); ctx.arc(-3, -10, 1.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(3, -10, 1.5, 0, Math.PI * 2); ctx.fill();

      // Big Round Space Helmet
      ctx.beginPath(); ctx.arc(0, -32, 13, 0, Math.PI * 2); ctx.fillStyle = '#E2E8F0'; ctx.fill();
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1.5; ctx.stroke();

      // Iridescent Cyan/Gold Visor
      ctx.beginPath(); ctx.ellipse(0, -32, 9, 7.5, 0, 0, Math.PI * 2); ctx.fillStyle = '#0891B2'; ctx.fill();
      ctx.strokeStyle = '#67E8F9'; ctx.lineWidth = 1.5; ctx.stroke();
      // Glint
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.arc(0, -32, 6, 1.1 * Math.PI, 1.6 * Math.PI); ctx.stroke();

    } else if (avatarId === 'robot') {
      // --- MECHA ROBOT CHIBI ---
      // Top Antenna with glowing bulb
      ctx.strokeStyle = '#06B6D4'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(0, -37); ctx.lineTo(0, -45); ctx.stroke();
      ctx.fillStyle = '#FACC15'; ctx.beginPath(); ctx.arc(0, -46, 3, 0, Math.PI * 2); ctx.fill();

      // Metallic Chassis Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 4); ctx.fillStyle = '#475569'; ctx.fill();
      ctx.strokeStyle = '#94A3B8'; ctx.lineWidth = 1.5; ctx.stroke();
      ctx.fillStyle = '#1E293B'; ctx.fillRect(-7, -15, 14, 9);

      // Square Monitor Head
      ctx.beginPath(); ctx.roundRect(-12, -37, 24, 18, 4); ctx.fillStyle = '#64748B'; ctx.fill();
      ctx.strokeStyle = '#334155'; ctx.lineWidth = 1.5; ctx.stroke();

      // CRT Face Screen
      ctx.fillStyle = '#0F172A'; ctx.beginPath(); ctx.roundRect(-9, -34, 18, 12, 2.5); ctx.fill();

      // Cyan Glowing Anime Eyes ( ^ ◡ ^ )
      ctx.strokeStyle = '#38BDF8'; ctx.lineWidth = 1.8; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(-6, -29); ctx.lineTo(-4, -31); ctx.lineTo(-2, -29); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(2, -29); ctx.lineTo(4, -31); ctx.lineTo(6, -29); ctx.stroke();
      ctx.beginPath(); ctx.arc(0, -26, 2, 0.1 * Math.PI, 0.9 * Math.PI); ctx.stroke();

    } else if (avatarId === 'wizard') {
      // --- WIZARD MERLIN CHIBI ---
      // Robes
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 5); ctx.fillStyle = '#6D28D9'; ctx.fill();
      ctx.strokeStyle = '#FDE047'; ctx.lineWidth = 1.5; ctx.stroke();

      // Head & White Cloud Beard
      ctx.beginPath(); ctx.arc(0, -28, 9, 0, Math.PI * 2); ctx.fillStyle = '#FED7AA'; ctx.fill();
      ctx.fillStyle = '#F8FAFC';
      ctx.beginPath(); ctx.moveTo(-8, -26); ctx.quadraticCurveTo(0, -14, 8, -26); ctx.closePath(); ctx.fill();

      // Wizard Hat
      ctx.fillStyle = '#7C3AED';
      ctx.beginPath(); ctx.ellipse(0, -35, 13, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.moveTo(-9, -35); ctx.quadraticCurveTo(-1, -50, 4, -58); ctx.lineTo(7, -35); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDE047'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();

      // Eyes
      ctx.fillStyle = '#1E1B4B';
      ctx.beginPath(); ctx.arc(-3.5, -28, 1.4, 0, Math.PI * 2); ctx.arc(3.5, -28, 1.4, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'ninja') {
      // --- SHINOBI NINJA CHIBI ---
      // Headband tails flying
      ctx.strokeStyle = '#DC2626'; ctx.lineWidth = 3; ctx.lineCap = 'round';
      ctx.beginPath(); ctx.moveTo(11, -33); ctx.quadraticCurveTo(20, -32, 18, -22); ctx.stroke();

      // Ninja Gi Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 21, 5); ctx.fillStyle = '#1E293B'; ctx.fill();
      ctx.strokeStyle = '#DC2626'; ctx.lineWidth = 2;
      ctx.beginPath(); ctx.moveTo(-10, -19); ctx.lineTo(10, 0); ctx.stroke();

      // Dark Mask Cowl
      ctx.beginPath(); ctx.roundRect(-11, -37, 22, 19, 7); ctx.fillStyle = '#0F172A'; ctx.fill();

      // Red Forehead Band
      ctx.fillStyle = '#DC2626'; ctx.fillRect(-11, -35, 22, 4);

      // Eye Slit & Intense Eyes
      ctx.fillStyle = '#FED7AA'; ctx.fillRect(-7, -29, 14, 5);
      ctx.fillStyle = '#DC2626';
      ctx.beginPath(); ctx.arc(-3.5, -26.5, 1.5, 0, Math.PI * 2); ctx.arc(3.5, -26.5, 1.5, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'detective') {
      // --- DETECTIVE CHIBI ---
      // Trenchcoat
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 5); ctx.fillStyle = '#B45309'; ctx.fill();
      ctx.strokeStyle = '#78350F'; ctx.lineWidth = 1.5; ctx.stroke();

      // Chibi Head
      ctx.beginPath(); ctx.roundRect(-11, -36, 22, 18, 6); ctx.fillStyle = '#FED7AA'; ctx.fill();

      // Deerstalker Cap
      ctx.fillStyle = '#78350F';
      ctx.beginPath(); ctx.ellipse(0, -36, 14, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.roundRect(-9, -46, 18, 11, [5, 5, 1, 1]); ctx.fillStyle = '#92400E'; ctx.fill();

      // Monocle Right Eye
      ctx.strokeStyle = '#F59E0B'; ctx.lineWidth = 1.8;
      ctx.beginPath(); ctx.arc(4, -27, 3, 0, Math.PI * 2); ctx.stroke();
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -27, 1.8, 0, Math.PI * 2); ctx.arc(4, -27, 1.8, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'panda') {
      // --- CHIBI PANDA ---
      // Black Ears
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.arc(-9, -38, 4.5, 0, Math.PI * 2); ctx.arc(9, -38, 4.5, 0, Math.PI * 2); ctx.fill();

      // Torso & Red Bowtie
      ctx.beginPath(); ctx.roundRect(-14, -19, 28, 22, 6); ctx.fillStyle = '#EA580C'; ctx.fill();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(-3, -17, 2.5, 0, Math.PI * 2); ctx.arc(3, -17, 2.5, 0, Math.PI * 2); ctx.fill();

      // Round White Panda Head
      ctx.beginPath(); ctx.arc(0, -28, 12, 0, Math.PI * 2); ctx.fillStyle = '#F8FAFC'; ctx.fill();
      ctx.strokeStyle = '#0F172A'; ctx.lineWidth = 1.2; ctx.stroke();

      // Black Eye Patches
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.ellipse(-5, -29, 3, 3.8, 0, 0, Math.PI * 2); ctx.ellipse(5, -29, 3, 3.8, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-5.8, -30, 1.2, 0, Math.PI * 2); ctx.arc(4.2, -30, 1.2, 0, Math.PI * 2); ctx.fill();

      // Nose & Blush
      ctx.fillStyle = '#0F172A'; ctx.beginPath(); ctx.arc(0, -25, 1.5, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FDA4AF'; ctx.beginPath(); ctx.arc(-8, -26, 2, 0, Math.PI * 2); ctx.arc(8, -26, 2, 0, Math.PI * 2); ctx.fill();

    } else if (avatarId === 'king') {
      // --- ROYAL KING CHIBI ---
      // Royal Cape
      ctx.beginPath(); ctx.roundRect(-15, -19, 30, 22, 5); ctx.fillStyle = '#9333EA'; ctx.fill();
      ctx.fillStyle = '#F8FAFC'; ctx.fillRect(-6, -19, 12, 22);

      // Head & Golden Crown
      ctx.beginPath(); ctx.roundRect(-11, -35, 22, 17, 5); ctx.fillStyle = '#FDE047'; ctx.fill();
      ctx.fillStyle = '#FACC15'; ctx.strokeStyle = '#CA8A04'; ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(-11, -35); ctx.lineTo(-12, -47); ctx.lineTo(-5, -41); ctx.lineTo(0, -49); ctx.lineTo(5, -41); ctx.lineTo(12, -47); ctx.lineTo(11, -35);
      ctx.closePath(); ctx.fill(); ctx.stroke();

      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();

      // Eyes & Smile
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -28, 1.8, 0, Math.PI * 2); ctx.arc(4, -28, 1.8, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.arc(0, -24, 3, 0.1 * Math.PI, 0.9 * Math.PI); ctx.stroke();

    } else {
      // --- BUILDER (MINIFIG BOB) DEFAULT ---
      // Blue Dungarees Torso
      ctx.beginPath(); ctx.roundRect(-13, -19, 26, 22, 5); ctx.fillStyle = mainColor; ctx.fill();
      ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 2; ctx.stroke();

      // Yellow Head
      ctx.beginPath(); ctx.roundRect(-10, -35, 20, 17, 5); ctx.fillStyle = '#FCD34D'; ctx.fill();
      ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5; ctx.stroke();

      // Hardhat / Stud
      ctx.beginPath(); ctx.ellipse(0, -35, 12, 3.5, 0, 0, Math.PI * 2); ctx.fillStyle = '#EA580C'; ctx.fill();
      ctx.beginPath(); ctx.arc(0, -37, 7, Math.PI, 0); ctx.fillStyle = '#F97316'; ctx.fill();

      // Cute Face
      ctx.fillStyle = '#1E293B';
      ctx.beginPath(); ctx.arc(-4, -28, 1.8, 0, Math.PI * 2); ctx.arc(4, -28, 1.8, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#FFFFFF';
      ctx.beginPath(); ctx.arc(-4.6, -28.8, 0.8, 0, Math.PI * 2); ctx.arc(3.4, -28.8, 0.8, 0, Math.PI * 2); ctx.fill();

      ctx.strokeStyle = '#1E293B'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.arc(0, -24, 3.5, 0.1 * Math.PI, 0.9 * Math.PI, false); ctx.stroke();

      ctx.fillStyle = '#FDA4AF';
      ctx.beginPath(); ctx.ellipse(-7.5, -25.5, 2, 1.2, 0, 0, Math.PI * 2); ctx.ellipse(7.5, -25.5, 2, 1.2, 0, 0, Math.PI * 2); ctx.fill();
    }

    // 6. Dynamic Headwear / Hats (if equipped)
    const hat = player.hat;
    if (hat === 'crown') {
      ctx.fillStyle = '#FBBF24'; ctx.strokeStyle = '#D97706'; ctx.lineWidth = 1.2;
      ctx.beginPath();
      ctx.moveTo(-9, -37); ctx.lineTo(-9, -48); ctx.lineTo(-4, -42); ctx.lineTo(0, -50); ctx.lineTo(4, -42); ctx.lineTo(9, -48); ctx.lineTo(9, -37);
      ctx.closePath(); ctx.fill(); ctx.stroke();
      ctx.fillStyle = '#EF4444'; ctx.beginPath(); ctx.arc(0, -44, 2, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'headphones') {
      // DJ Headphones
      ctx.strokeStyle = '#0F172A'; ctx.lineWidth = 3;
      ctx.beginPath(); ctx.arc(0, -36, 13, Math.PI, 0); ctx.stroke();
      ctx.fillStyle = '#06B6D4'; ctx.strokeStyle = '#FFFFFF'; ctx.lineWidth = 1.5;
      ctx.beginPath(); ctx.roundRect(-16, -34, 5, 11, 2); ctx.fill(); ctx.stroke();
      ctx.beginPath(); ctx.roundRect(11, -34, 5, 11, 2); ctx.fill(); ctx.stroke();
    } else if (hat === 'flower_crown') {
      // Flower Crown
      const flowers = ['#F43F5E', '#FBBF24', '#38BDF8', '#A855F7'];
      for (let f = 0; f < 4; f++) {
        ctx.fillStyle = flowers[f];
        ctx.beginPath(); ctx.arc(-9 + f * 6, -36, 2.5, 0, Math.PI * 2); ctx.fill();
      }
    } else if (hat === 'halo') {
      // Angel Halo
      ctx.strokeStyle = '#FDE047'; ctx.lineWidth = 2.5;
      ctx.shadowColor = '#FACC15'; ctx.shadowBlur = 8;
      ctx.beginPath(); ctx.ellipse(0, -47, 10, 3.5, 0, 0, Math.PI * 2); ctx.stroke();
      ctx.shadowBlur = 0;
    } else if (hat === 'pirate') {
      ctx.fillStyle = '#0F172A';
      ctx.beginPath(); ctx.moveTo(-15, -36); ctx.quadraticCurveTo(0, -54, 15, -36); ctx.quadraticCurveTo(0, -43, -15, -36); ctx.closePath(); ctx.fill();
      ctx.strokeStyle = '#F59E0B'; ctx.lineWidth = 1.2; ctx.stroke();
      ctx.fillStyle = '#FFFFFF'; ctx.beginPath(); ctx.arc(0, -43, 2.5, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'wizard') {
      ctx.fillStyle = '#7C3AED'; ctx.beginPath(); ctx.ellipse(0, -36, 13, 4, 0, 0, Math.PI * 2); ctx.fill();
      ctx.beginPath(); ctx.moveTo(-10, -36); ctx.quadraticCurveTo(-2, -54, 5, -62); ctx.lineTo(8, -36); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#FDE047'; ctx.beginPath(); ctx.arc(0, -45, 2, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'cat_ears') {
      ctx.fillStyle = mainColor;
      ctx.beginPath(); ctx.moveTo(-9, -36); ctx.lineTo(-6, -46); ctx.lineTo(-2, -36); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(2, -36); ctx.lineTo(6, -46); ctx.lineTo(9, -36); ctx.closePath(); ctx.fill();
    } else if (hat === 'party_hat') {
      ctx.fillStyle = '#EF4444';
      ctx.beginPath(); ctx.moveTo(-8, -37); ctx.lineTo(8, -37); ctx.lineTo(0, -56); ctx.closePath(); ctx.fill();
      ctx.fillStyle = '#38BDF8'; ctx.beginPath(); ctx.arc(0, -57, 3, 0, Math.PI * 2); ctx.fill();
    } else if (hat === 'visor') {
      ctx.fillStyle = '#06B6D4'; ctx.shadowColor = '#06B6D4'; ctx.shadowBlur = 8;
      ctx.fillRect(-10, -30, 20, 5); ctx.shadowBlur = 0;
      ctx.fillStyle = '#FFFFFF'; ctx.fillRect(-8, -29, 16, 1.5);
    } else if (hat === 'detective') {
      ctx.fillStyle = '#78350F'; ctx.beginPath(); ctx.ellipse(0, -37, 14, 3.5, 0, 0, Math.PI * 2); ctx.fill();
      ctx.fillStyle = '#92400E'; ctx.beginPath(); ctx.roundRect(-8, -47, 16, 11, [4, 4, 1, 1]); ctx.fill();
    } else if (hat === 'viking') {
      ctx.fillStyle = '#64748B'; ctx.beginPath(); ctx.arc(0, -36, 10, Math.PI, 0); ctx.fill();
      ctx.fillStyle = '#FEF08A';
      ctx.beginPath(); ctx.moveTo(-9, -37); ctx.quadraticCurveTo(-16, -43, -15, -49); ctx.quadraticCurveTo(-11, -43, -6, -39); ctx.closePath(); ctx.fill();
      ctx.beginPath(); ctx.moveTo(9, -37); ctx.quadraticCurveTo(16, -43, 15, -49); ctx.quadraticCurveTo(11, -43, 6, -39); ctx.closePath(); ctx.fill();
    }

    // 7. Dynamic Auto-Sizing Name Tag Pill (Guarantees zero text overflow!)
    if (isCurrent || !isClustered) {
      ctx.font = 'bold 9.5px sans-serif';
      const displayName = player.name.length > 10 ? player.name.substr(0, 9) + '..' : player.name;
      const tagText = `${displayName} ⭐${player.stars}`;
      const textWidth = ctx.measureText(tagText).width;
      const pillWidth = Math.max(76, textWidth + 18);
      const pillHeight = 18;

      ctx.beginPath();
      ctx.roundRect(-pillWidth / 2, -56, pillWidth, pillHeight, 9);
      ctx.fillStyle = isCurrent ? '#FEF08A' : 'rgba(15, 23, 42, 0.9)';
      ctx.fill();
      ctx.strokeStyle = player.color || '#38BDF8';
      ctx.lineWidth = isCurrent ? 2 : 1.2;
      ctx.stroke();

      ctx.fillStyle = isCurrent ? '#854D0E' : '#FFFFFF';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(tagText, 0, -47);

      // Active Buff Badges (Double Dice, Lucky Die, Ghost)
      let buffOffset = pillWidth / 2 + 10;
      if (player.doubleDiceActive) {
        ctx.beginPath();
        ctx.arc(buffOffset, -47, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#F59E0B';
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 1.2;
        ctx.stroke();
        ctx.font = '9px sans-serif';
        ctx.fillText('🎲', buffOffset, -46);
        buffOffset += 18;
      }
      if (player.fixedRollNext) {
        ctx.beginPath();
        ctx.arc(buffOffset, -47, 8, 0, Math.PI * 2);
        ctx.fillStyle = '#10B981';
        ctx.fill();
        ctx.strokeStyle = '#FFFFFF';
        ctx.lineWidth = 1.2;
        ctx.stroke();
        ctx.font = 'bold 9px sans-serif';
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText(player.fixedRollNext, buffOffset, -47);
        buffOffset += 18;
      }
    } else {
      ctx.beginPath();
      ctx.arc(0, -45, 10, 0, Math.PI * 2);
      ctx.fillStyle = player.color;
      ctx.fill();
      ctx.strokeStyle = '#FFFFFF';
      ctx.lineWidth = 1.5;
      ctx.stroke();

      ctx.fillStyle = '#FFFFFF';
      ctx.font = 'bold 9px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(player.name.charAt(0).toUpperCase(), 0, -45);
    }

    ctx.restore();
  }

  darkenColor(hex, factor = 0.2) {
    if (!hex || hex[0] !== '#') return '#1E293B';
    let r = parseInt(hex.substr(1, 2), 16);
    let g = parseInt(hex.substr(3, 2), 16);
    let b = parseInt(hex.substr(5, 2), 16);
    r = Math.floor(Math.max(0, r * (1 - factor)));
    g = Math.floor(Math.max(0, g * (1 - factor)));
    b = Math.floor(Math.max(0, b * (1 - factor)));
    return `rgb(${r}, ${g}, ${b})`;
  }
}

window.BoardRenderer = BoardRenderer;
