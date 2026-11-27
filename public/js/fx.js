/**
 * FX layer — "game feel" for Parti Dünyası.
 *
 * Wraps a few UI hooks instead of rewriting them:
 *  - 3D lacquered dice (GSAP) instead of the flat 2D die
 *  - floating +/- coin and star numbers over player cards
 *  - a short board shake on penalties
 *  - confetti for stars, mini-game wins and the final podium (canvas-confetti)
 *  - one orchestrated "your turn" sweep
 * Everything degrades gracefully: without GSAP / confetti, or with
 * prefers-reduced-motion, the game keeps working with simpler feedback.
 */
(function () {
  'use strict';

  const hasGsap = typeof window.gsap !== 'undefined';
  const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const TEXT = {
    tr: { rolling: (n) => `${n} zar atıyor`, steps: (n, s) => `${n}: ${s} adım`, yourTurn: 'Sıra sende', yourTurnSub: 'Zar atmak için Boşluk tuşuna bas', you: 'Sen' },
    en: { rolling: (n) => `${n} is rolling`, steps: (n, s) => `${n}: ${s} steps`, yourTurn: 'Your turn', yourTurnSub: 'Press Space to roll the dice', you: 'You' },
    de: { rolling: (n) => `${n} würfelt`, steps: (n, s) => `${n}: ${s} Felder`, yourTurn: 'Du bist dran', yourTurnSub: 'Drücke die Leertaste zum Würfeln', you: 'Du' },
    es: { rolling: (n) => `${n} tira los dados`, steps: (n, s) => `${n}: ${s} casillas`, yourTurn: 'Tu turno', yourTurnSub: 'Pulsa Espacio para tirar el dado', you: 'Tú' },
    fr: { rolling: (n) => `${n} lance les dés`, steps: (n, s) => `${n} : ${s} cases`, yourTurn: 'À toi de jouer', yourTurnSub: 'Appuie sur Espace pour lancer le dé', you: 'Toi' }
  };
  const t = () => {
    const lang = (window.i18n && window.i18n.getLang && window.i18n.getLang()) || 'tr';
    return TEXT[lang] || TEXT.tr;
  };

  // Pip layout on a 3x3 grid (cells 1..9, row-major)
  const PIP_CELLS = {
    1: [5],
    2: [1, 9],
    3: [1, 5, 9],
    4: [1, 3, 7, 9],
    5: [1, 3, 5, 7, 9],
    6: [1, 3, 4, 6, 7, 9]
  };
  // Cube rotation that brings each face to the front
  const FACE_ROTATION = {
    1: { x: 0, y: 0 },
    6: { x: 0, y: 180 },
    3: { x: 0, y: -90 },
    4: { x: 0, y: 90 },
    2: { x: -90, y: 0 },
    5: { x: 90, y: 0 }
  };

  function buildDie(isBrass) {
    const wrap = document.createElement('div');
    wrap.className = 'dice3d-shadow-wrap';
    const shadow = document.createElement('div');
    shadow.className = 'dice3d-shadow';
    const cube = document.createElement('div');
    cube.className = 'dice3d' + (isBrass ? ' is-brass' : '');
    for (let face = 1; face <= 6; face++) {
      const f = document.createElement('div');
      f.className = 'dice3d-face';
      f.dataset.face = String(face);
      for (let cell = 1; cell <= 9; cell++) {
        const slot = document.createElement('span');
        if (PIP_CELLS[face].includes(cell)) slot.className = 'pip3d';
        slot.style.gridArea = `${Math.ceil(cell / 3)} / ${((cell - 1) % 3) + 1}`;
        f.appendChild(slot);
      }
      cube.appendChild(f);
    }
    wrap.appendChild(shadow);
    wrap.appendChild(cube);
    return { wrap, cube, shadow };
  }

  // ---------------------------------------------------------------------------
  // 3D dice roll
  // ---------------------------------------------------------------------------
  function show3dDice(data) {
    const overlay = document.getElementById('dice-roll-overlay');
    const stage = document.getElementById('dice3d-stage');
    const legacy = document.getElementById('dice-legacy-wrapper');
    const titleBox = document.getElementById('dice-roller-name');
    const resultBox = document.getElementById('dice-result-value');
    if (!overlay || !stage) return false;

    const speed = window.GAME_SPEED || 1;
    const name = data.playerName || t().you;
    const values = [data.die1 || data.totalRoll, data.die2].filter(v => v >= 1 && v <= 6);
    if (values.length === 0) values.push(Math.min(6, Math.max(1, data.totalRoll || 1)));

    if (window.AudioPlayer) AudioPlayer.playDiceRoll();
    overlay.classList.remove('hidden');
    if (legacy) legacy.hidden = true;
    stage.hidden = false;
    stage.innerHTML = '';
    if (titleBox) titleBox.textContent = t().rolling(name);
    if (resultBox) resultBox.textContent = '…';

    const dice = values.map((v, i) => {
      const d = buildDie(i === 1); // second die (double dice) is brass
      stage.appendChild(d.wrap);
      return { ...d, value: v };
    });

    const tumble = Math.max(0.35, 0.95 / speed);
    const hold = Math.max(0.25, 0.55 / speed);
    let closeTimer = null;

    const finish = () => {
      if (resultBox) resultBox.textContent = t().steps(name, data.totalRoll);
      if (window.AudioPlayer) AudioPlayer.playStep();
      if (hasGsap && !reduceMotion && resultBox) {
        gsap.fromTo(resultBox, { scale: 0.85, opacity: 0 }, { scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2)' });
      }
      closeTimer = setTimeout(close, hold * 1000);
    };
    const close = () => {
      clearTimeout(closeTimer);
      overlay.classList.add('hidden');
      overlay.onclick = null;
      if (hasGsap) dice.forEach(d => gsap.killTweensOf([d.cube, d.wrap, d.shadow]));
    };
    overlay.onclick = close;

    if (!hasGsap || reduceMotion) {
      dice.forEach(d => {
        const r = FACE_ROTATION[d.value];
        d.cube.style.transform = `rotateX(${r.x}deg) rotateY(${r.y}deg)`;
      });
      setTimeout(finish, 150);
      return true;
    }

    dice.forEach((d, i) => {
      const r = FACE_ROTATION[d.value];
      // Several full turns plus the landing face; slight tilt so it reads as 3D
      const spinsX = 360 * (2 + i);
      const spinsY = 360 * (3 - i);
      gsap.set(d.cube, { rotationX: gsap.utils.random(-40, 40), rotationY: gsap.utils.random(-40, 40), transformPerspective: 900 });
      const tl = gsap.timeline({ onComplete: i === dice.length - 1 ? finish : null });
      tl.fromTo(d.wrap, { y: -160, x: i === 0 ? -60 : 60, scale: 0.7 },
        { y: 0, x: 0, scale: 1, duration: tumble, ease: 'bounce.out' }, 0)
        .to(d.cube, { rotationX: spinsX + r.x - 12, rotationY: spinsY + r.y + 10, duration: tumble, ease: 'power3.out' }, 0)
        .to(d.cube, { rotationX: spinsX + r.x, rotationY: spinsY + r.y, duration: 0.25, ease: 'back.out(3)' })
        .fromTo(d.shadow, { scale: 0.3, opacity: 0.2 }, { scale: 1, opacity: 1, duration: tumble, ease: 'bounce.out' }, 0);
    });
    return true;
  }

  // ---------------------------------------------------------------------------
  // Floating numbers over player cards
  // ---------------------------------------------------------------------------
  function floatText(anchor, text, kind, dx) {
    if (!anchor) return;
    const rect = anchor.getBoundingClientRect();
    if (!rect.width) return;
    const el = document.createElement('div');
    el.className = 'fx-float' + (kind ? ` is-${kind}` : '');
    el.textContent = text;
    el.style.left = `${rect.left + rect.width / 2 + (dx || 0)}px`;
    el.style.top = `${rect.top + rect.height / 2}px`;
    document.body.appendChild(el);
    if (!hasGsap || reduceMotion) {
      el.style.transform = 'translate(-50%, 24px)';
      setTimeout(() => el.remove(), 1200);
      return;
    }
    gsap.fromTo(el,
      { xPercent: -50, yPercent: -50, y: 0, scale: 0.6, opacity: 0 },
      { y: 46, scale: 1, opacity: 1, duration: 0.35, ease: 'back.out(2.5)' });
    gsap.to(el, { y: 96, opacity: 0, duration: 0.6, delay: 0.9, ease: 'power2.in', onComplete: () => el.remove() });
  }

  // ---------------------------------------------------------------------------
  // Confetti in the game palette
  // ---------------------------------------------------------------------------
  let confettiFire = null;
  function getConfetti() {
    if (confettiFire || typeof window.confetti !== 'function') return confettiFire;
    const canvas = document.createElement('canvas');
    canvas.className = 'fx-confetti-canvas';
    document.body.appendChild(canvas);
    confettiFire = window.confetti.create(canvas, { resize: true, useWorker: true, disableForReducedMotion: true });
    return confettiFire;
  }
  const PALETTE = ['#E8B44A', '#F6DA9A', '#E5484D', '#2BB3A3', '#FFF4DE'];
  function burst(origin, power) {
    const fire = getConfetti();
    if (!fire || reduceMotion) return;
    const p = power || 1;
    fire({ particleCount: Math.round(60 * p), spread: 70, startVelocity: 38 * Math.sqrt(p), origin, colors: PALETTE, scalar: 1.05, ticks: 220 });
  }
  function grandFinale() {
    const fire = getConfetti();
    if (!fire || reduceMotion) return;
    const end = Date.now() + 1600;
    (function frame() {
      fire({ particleCount: 6, angle: 60, spread: 60, origin: { x: 0, y: 0.75 }, colors: PALETTE });
      fire({ particleCount: 6, angle: 120, spread: 60, origin: { x: 1, y: 0.75 }, colors: PALETTE });
      if (Date.now() < end) requestAnimationFrame(frame);
    })();
  }
  function originOf(el) {
    if (!el) return { x: 0.5, y: 0.4 };
    const r = el.getBoundingClientRect();
    return { x: (r.left + r.width / 2) / window.innerWidth, y: (r.top + r.height / 2) / window.innerHeight };
  }

  // ---------------------------------------------------------------------------
  // Board shake (penalties)
  // ---------------------------------------------------------------------------
  function shakeBoard(strength) {
    const target = document.querySelector('.board-arena-container');
    if (!target || !hasGsap || reduceMotion) return;
    const s = strength || 8;
    gsap.killTweensOf(target);
    gsap.fromTo(target, { x: 0 }, {
      keyframes: [{ x: -s }, { x: s * 0.8 }, { x: -s * 0.5 }, { x: s * 0.3 }, { x: 0 }],
      duration: 0.32,
      ease: 'power1.out',
      clearProps: 'transform'
    });
  }

  // ---------------------------------------------------------------------------
  // "Your turn" sweep — the one orchestrated moment per turn
  // ---------------------------------------------------------------------------
  function yourTurnSweep() {
    const old = document.querySelector('.fx-turn-sweep');
    if (old) old.remove();
    const wrap = document.createElement('div');
    wrap.className = 'fx-turn-sweep';
    wrap.setAttribute('role', 'status');
    const band = document.createElement('div');
    band.className = 'fx-turn-sweep-band';
    band.textContent = t().yourTurn;
    const sub = document.createElement('small');
    sub.textContent = t().yourTurnSub;
    band.appendChild(sub);
    wrap.appendChild(band);
    document.body.appendChild(wrap);
    if (!hasGsap || reduceMotion) {
      setTimeout(() => wrap.remove(), 1400);
      return;
    }
    gsap.timeline({ onComplete: () => wrap.remove() })
      .fromTo(band, { xPercent: -30, opacity: 0, scaleY: 0.6 }, { xPercent: 0, opacity: 1, scaleY: 1, duration: 0.38, ease: 'power3.out' })
      .to(band, { xPercent: 30, opacity: 0, duration: 0.32, ease: 'power2.in' }, '+=0.75');
  }

  // ---------------------------------------------------------------------------
  // City intro card — shown once when a match starts on a map
  // ---------------------------------------------------------------------------
  let introShownFor = null;
  let introUntil = 0;
  function cityIntro(themeId) {
    if (!window.WorldMaps || introShownFor === themeId) return;
    introShownFor = themeId;
    const info = window.WorldMaps.info(themeId);
    const wrap = document.createElement('div');
    wrap.className = 'fx-city-intro';
    wrap.setAttribute('role', 'status');
    wrap.innerHTML = '<div class="fx-city-intro-card"><div class="fx-city-intro-kicker"></div><div class="fx-city-intro-title"></div><div class="fx-city-intro-rule"></div></div>';
    wrap.querySelector('.fx-city-intro-kicker').textContent = info.subtitle;
    wrap.querySelector('.fx-city-intro-title').textContent = info.title;
    document.body.appendChild(wrap);
    introUntil = Date.now() + 2300;
    const card = wrap.querySelector('.fx-city-intro-card');
    if (!hasGsap || reduceMotion) {
      setTimeout(() => wrap.remove(), 1800);
      return;
    }
    gsap.timeline({ onComplete: () => wrap.remove() })
      .fromTo(wrap, { opacity: 0 }, { opacity: 1, duration: 0.25 })
      .fromTo(card.querySelector('.fx-city-intro-title'), { y: 30, opacity: 0, letterSpacing: '0.2em' }, { y: 0, opacity: 1, letterSpacing: '-0.03em', duration: 0.7, ease: 'power3.out' }, 0.05)
      .fromTo(card.querySelector('.fx-city-intro-kicker'), { y: -10, opacity: 0 }, { y: 0, opacity: 1, duration: 0.45, ease: 'power2.out' }, 0.25)
      .fromTo(card.querySelector('.fx-city-intro-rule'), { scaleX: 0 }, { scaleX: 1, duration: 0.6, ease: 'power3.inOut' }, 0.3)
      .to(wrap, { opacity: 0, duration: 0.4, ease: 'power2.in' }, '+=0.9');
  }

  // ---------------------------------------------------------------------------
  // HUD enrichment: rank medals, star meters, round beads
  // ---------------------------------------------------------------------------
  const STAR_PRICE = 20;
  function enrichHud(state) {
    if (!state || !Array.isArray(state.players)) return;
    const ranked = [...state.players].sort((a, b) => (b.stars - a.stars) || (b.coins - a.coins));
    ranked.forEach((p, i) => {
      const card = document.getElementById(`player-card-${p.id}`);
      if (!card || card.querySelector('.hud-rank')) return;
      const rank = document.createElement('span');
      rank.className = `hud-rank rank-${i + 1}`;
      rank.textContent = String(i + 1);
      rank.title = `${i + 1}.`;
      card.appendChild(rank);
      const stats = card.querySelector('.hud-player-stats');
      if (stats) {
        const meter = document.createElement('div');
        meter.className = 'hud-star-meter';
        meter.title = `${Math.min(p.coins, STAR_PRICE)} / ${STAR_PRICE} 🪙 → ⭐`;
        const fill = document.createElement('span');
        fill.style.width = `${Math.min(100, Math.round((p.coins / STAR_PRICE) * 100))}%`;
        if (p.coins >= STAR_PRICE) meter.classList.add('is-ready');
        meter.appendChild(fill);
        stats.appendChild(meter);
      }
    });

    // Round beads under the round card
    const roundCard = document.getElementById('hud-round-card');
    if (roundCard && state.totalRounds) {
      let beads = roundCard.querySelector('.hud-round-beads');
      if (!beads) {
        beads = document.createElement('div');
        beads.className = 'hud-round-beads';
        roundCard.appendChild(beads);
      }
      const total = Math.min(30, state.totalRounds);
      if (beads.childElementCount !== total) {
        beads.innerHTML = '';
        for (let i = 1; i <= total; i++) beads.appendChild(document.createElement('i'));
      }
      Array.from(beads.children).forEach((b, i) => {
        const r = i + 1;
        b.className = r < state.currentRound ? 'done' : (r === state.currentRound ? 'now' : '');
        if (r === total) b.classList.add('final');
      });
    }
  }

  // ---------------------------------------------------------------------------
  // Map previews on the lobby and create-room city buttons
  // ---------------------------------------------------------------------------
  function decorateMapButtons() {
    if (!window.WorldMaps) return;
    document.querySelectorAll('.lobby-map-btn[data-theme]').forEach(btn => {
      if (btn.querySelector('canvas')) return;
      const c = window.WorldMaps.preview(btn.dataset.theme, 132);
      c.className = 'map-preview-thumb';
      btn.prepend(c);
      btn.classList.add('has-preview');
    });
    document.querySelectorAll('.map-card-label input[name="map-theme-select"]').forEach(input => {
      const box = input.parentElement && input.parentElement.querySelector('.map-card-box');
      if (!box || box.querySelector('canvas')) return;
      const c = window.WorldMaps.preview(input.value, 150);
      c.className = 'map-preview-thumb';
      box.prepend(c);
      box.classList.add('has-preview');
    });
  }

  // ---------------------------------------------------------------------------
  // State diffing: who gained/lost coins or stars, whose turn it is now
  // ---------------------------------------------------------------------------
  let lastPlayers = null;
  let lastTurnKey = null;

  function isMe(p) {
    const me = window.GameClient && window.GameClient.player;
    return !!(me && ((p.sessionId && p.sessionId === me.sessionId) || (me.id && p.id === me.id)));
  }

  function onState(state) {
    if (!state || !Array.isArray(state.players)) return;
    const prev = lastPlayers;
    lastPlayers = {};
    state.players.forEach(p => { lastPlayers[p.id] = { coins: p.coins, stars: p.stars }; });

    if (prev && state.state === 'playing') {
      state.players.forEach(p => {
        const before = prev[p.id];
        if (!before) return;
        const card = document.getElementById(`player-card-${p.id}`);
        const dStars = (p.stars || 0) - (before.stars || 0);
        const dCoins = (p.coins || 0) - (before.coins || 0);
        if (dStars > 0) {
          floatText(card, `+${dStars} ⭐`, 'star', -38);
          burst(originOf(card), isMe(p) ? 1.4 : 0.7);
        }
        if (dCoins !== 0) {
          // When a star was bought the price shows next to it, slightly later
          setTimeout(() => floatText(card, `${dCoins > 0 ? '+' : ''}${dCoins} 🪙`, dCoins < 0 ? 'loss' : null, dStars > 0 ? 42 : 0), dStars > 0 ? 300 : 0);
        }
      });
    }

    if (state.state === 'playing' || state.state === 'minigame') {
      enrichHud(state);
    }

    if (state.state === 'playing') {
      cityIntro(state.mapTheme || 'istanbul');
      const current = state.players[state.turnIndex];
      const key = `${state.currentRound}:${state.turnIndex}`;
      if (key !== lastTurnKey) {
        lastTurnKey = key;
        if (current && isMe(current) && !current.isBot) {
          // Let the dice / event cards (and the city intro) finish before announcing the turn,
          // then re-check: the game may have ended or the turn moved on in the meantime
          setTimeout(() => {
            const live = window.CURRENT_ROOM_STATE;
            const gameOverOpen = document.getElementById('modal-gameover') && !document.getElementById('modal-gameover').classList.contains('hidden');
            if (!live || live.state !== 'playing' || gameOverOpen) return;
            const now = live.players && live.players[live.turnIndex];
            if (now && isMe(now) && `${live.currentRound}:${live.turnIndex}` === key) yourTurnSweep();
          }, Math.max(450, introUntil - Date.now()));
        }
      }
    } else {
      lastTurnKey = null;
      if (state.state === 'lobby') {
        lastPlayers = null;
        introShownFor = null;
        decorateMapButtons();
      }
    }
  }

  // ---------------------------------------------------------------------------
  // Hook into the existing UI once it exists
  // ---------------------------------------------------------------------------
  function install() {
    const UIRef = window.UI;
    if (!UIRef || UIRef.__fxInstalled) return;
    UIRef.__fxInstalled = true;

    const origDice = UIRef.showDiceRollAnimation.bind(UIRef);
    UIRef.showDiceRollAnimation = function (data) {
      if (!show3dDice(data || {})) origDice(data);
    };

    const origState = UIRef.updateGameState.bind(UIRef);
    UIRef.updateGameState = function (state) {
      origState(state);
      try { onState(state); } catch (e) { console.warn('fx state hook failed', e); }
    };

    const origTile = UIRef.showTileEventCard.bind(UIRef);
    UIRef.showTileEventCard = function (data) {
      origTile(data);
      if (data && data.category === 'penalty') shakeBoard(data.sound === 'jail' ? 10 : 7);
      const card = document.getElementById('tile-event-card');
      if (card && hasGsap && !reduceMotion) {
        gsap.fromTo(card, { y: 30, rotation: -3, scale: 0.92, opacity: 0 }, { y: 0, rotation: 0, scale: 1, opacity: 1, duration: 0.42, ease: 'back.out(1.8)' });
      }
    };

    // Replace the old DOM confetti pieces with palette-matched canvas confetti
    if (UIRef.triggerConfetti && typeof window.confetti === 'function') {
      UIRef.triggerConfetti = function () { burst({ x: 0.5, y: 0.35 }, 1.5); };
    }

    if (UIRef.showMiniGameResults) {
      const origMg = UIRef.showMiniGameResults.bind(UIRef);
      UIRef.showMiniGameResults = function (data) {
        origMg(data);
        const winner = data && data.ranked && data.ranked[0];
        const me = window.GameClient && window.GameClient.player;
        if (winner && me && winner.id === me.id) burst({ x: 0.5, y: 0.45 }, 1.6);
      };
    }

    if (UIRef.showVictoryPodium) {
      const origPodium = UIRef.showVictoryPodium.bind(UIRef);
      UIRef.showVictoryPodium = function (data) {
        origPodium(data);
        grandFinale();
      };
    }

    // City thumbnails in the create-room modal (rendered once the page is ready)
    setTimeout(decorateMapButtons, 0);

    // Keyboard access for the div-based action cards on the home screen
    document.addEventListener('keydown', (e) => {
      const el = e.target;
      if (!el || el.getAttribute('role') !== 'button') return;
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        el.click();
      }
    });
  }

  // ui.js creates window.UI synchronously; install right after it is defined
  if (window.UI) install();
  else window.addEventListener('DOMContentLoaded', install);

  window.FX = { burst, grandFinale, shakeBoard, floatText, yourTurnSweep, cityIntro, decorateMapButtons };
})();
