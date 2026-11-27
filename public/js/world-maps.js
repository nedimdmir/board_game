/**
 * World Maps — hand-built city dioramas for the board renderer.
 *
 * Each city has its own track shape (32 spaces sampled from a closed spline),
 * its own geography (coastlines, rivers, districts, landmarks) and its own
 * ambient motion. The static scenery is rendered once into an offscreen
 * canvas per city; only the moving details are drawn every frame.
 *
 * This file patches BoardRenderer (board-map.js) instead of rewriting it, so
 * cameras, tokens, hop animations and tile clicks keep working unchanged.
 */
(function () {
  'use strict';
  if (typeof BoardRenderer === 'undefined') return;

  const W = 1100;
  const H = 760;
  const PAD = 260;          // scenery drawn around the world rect so panning never shows a void
  // Static layer resolution. Phones render the board small, so 1x is plenty there.
  const CACHE_SCALE = (typeof window !== 'undefined' && window.innerWidth && window.innerWidth < 700) ? 1 : 1.5;
  const SPRITE_SCALE = 2;

  // ---------------------------------------------------------------------------
  // Geometry helpers
  // ---------------------------------------------------------------------------
  function seeded(seed) {
    let s = seed >>> 0;
    return () => {
      s = (Math.imul(s, 1664525) + 1013904223) >>> 0;
      return s / 4294967296;
    };
  }

  // Closed Catmull-Rom spline through control points -> dense polyline
  function spline(points, steps) {
    const out = [];
    const n = points.length;
    for (let i = 0; i < n; i++) {
      const p0 = points[(i - 1 + n) % n];
      const p1 = points[i];
      const p2 = points[(i + 1) % n];
      const p3 = points[(i + 2) % n];
      for (let s = 0; s < steps; s++) {
        const t = s / steps;
        const t2 = t * t;
        const t3 = t2 * t;
        out.push({
          x: 0.5 * ((2 * p1[0]) + (-p0[0] + p2[0]) * t + (2 * p0[0] - 5 * p1[0] + 4 * p2[0] - p3[0]) * t2 + (-p0[0] + 3 * p1[0] - 3 * p2[0] + p3[0]) * t3),
          y: 0.5 * ((2 * p1[1]) + (-p0[1] + p2[1]) * t + (2 * p0[1] - 5 * p1[1] + 4 * p2[1] - p3[1]) * t2 + (-p0[1] + 3 * p1[1] - 3 * p2[1] + p3[1]) * t3)
        });
      }
    }
    return out;
  }

  // n points at equal arc length along a closed polyline, starting at its first point
  function resample(poly, n) {
    const lens = [0];
    for (let i = 1; i <= poly.length; i++) {
      const a = poly[i - 1];
      const b = poly[i % poly.length];
      lens.push(lens[i - 1] + Math.hypot(b.x - a.x, b.y - a.y));
    }
    const total = lens[lens.length - 1];
    const out = [];
    let seg = 0;
    for (let k = 0; k < n; k++) {
      const target = (total * k) / n;
      while (lens[seg + 1] < target) seg++;
      const a = poly[seg];
      const b = poly[(seg + 1) % poly.length];
      const f = (target - lens[seg]) / Math.max(1e-6, lens[seg + 1] - lens[seg]);
      out.push({ x: Math.round(a.x + (b.x - a.x) * f), y: Math.round(a.y + (b.y - a.y) * f) });
    }
    return { points: out, length: total };
  }

  function distToPolyline(x, y, poly) {
    let best = Infinity;
    for (let i = 0; i < poly.length; i++) {
      const a = poly[i];
      const b = poly[(i + 1) % poly.length];
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      const l2 = dx * dx + dy * dy || 1;
      let t = ((x - a.x) * dx + (y - a.y) * dy) / l2;
      t = Math.max(0, Math.min(1, t));
      const d = Math.hypot(x - (a.x + t * dx), y - (a.y + t * dy));
      if (d < best) best = d;
    }
    return best;
  }

  function inPoly(x, y, poly) {
    let inside = false;
    for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
      const xi = poly[i][0], yi = poly[i][1], xj = poly[j][0], yj = poly[j][1];
      if (((yi > y) !== (yj > y)) && (x < ((xj - xi) * (y - yi)) / (yj - yi) + xi)) inside = !inside;
    }
    return inside;
  }

  function smoothShape(ctx, pts, steps) {
    const dense = spline(pts, steps || 10);
    ctx.beginPath();
    dense.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y)));
    ctx.closePath();
    return dense;
  }

  // Open band (river) along control points
  function bandPath(ctx, pts) {
    ctx.beginPath();
    pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
  }

  function polyFill(ctx, pts, fill) {
    ctx.beginPath();
    pts.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
    ctx.closePath();
    ctx.fillStyle = fill;
    ctx.fill();
  }

  // Oblique extruded block: (x, y) is the front-bottom-left corner
  function block(ctx, x, y, w, h, depth, c, rand, windows) {
    const dx = depth * 0.55;
    const dy = -depth * 0.42;
    polyFill(ctx, [[x + w, y], [x + w + dx, y + dy], [x + w + dx, y + dy - h], [x + w, y - h]], c.side);
    ctx.fillStyle = c.front;
    ctx.fillRect(x, y - h, w, h);
    polyFill(ctx, [[x, y - h], [x + dx, y - h + dy], [x + w + dx, y - h + dy], [x + w, y - h]], c.top);
    if (windows && h > 14) {
      const cols = Math.max(1, Math.floor((w - 4) / 6));
      const rows = Math.max(1, Math.floor((h - 6) / 7));
      for (let r = 0; r < rows; r++) {
        for (let k = 0; k < cols; k++) {
          const lit = rand() < windows.lit;
          ctx.fillStyle = lit ? windows.on : windows.off;
          ctx.fillRect(x + 3 + k * 6, y - h + 4 + r * 7, 3, 4);
        }
      }
    }
  }

  // Scatter items over land, away from the track and from keep-out circles
  function scatter(rand, count, opts) {
    const out = [];
    let guard = count * 30;
    while (out.length < count && guard-- > 0) {
      const x = opts.x0 + rand() * (opts.x1 - opts.x0);
      const y = opts.y0 + rand() * (opts.y1 - opts.y0);
      if (opts.isLand && !opts.isLand(x, y)) continue;
      if (opts.track && distToPolyline(x, y, opts.track) < (opts.clear || 52)) continue;
      if (opts.keepOut && opts.keepOut.some(k => Math.hypot(x - k[0], y - k[1]) < k[2])) continue;
      if (out.some(o => Math.hypot(o.x - x, o.y - y) < (opts.spacing || 14))) continue;
      out.push({ x, y, r: rand() });
    }
    return out.sort((a, b) => a.y - b.y);
  }

  // Neighbourhood placement: a jittered street grid, so cities read as blocks and streets
  function gridScatter(rand, opts) {
    const out = [];
    const [sx, sy] = opts.step;
    const every = opts.streetEvery || 0;
    let row = 0;
    for (let y = opts.y0; y < opts.y1; y += sy, row++) {
      let col = 0;
      for (let x = opts.x0; x < opts.x1; x += sx, col++) {
        if (every && (col % every === every - 1 || row % every === every - 1)) continue;
        if (rand() < (opts.skip || 0)) continue;
        let px = x + (rand() - 0.5) * (opts.jitter || 0);
        let py = y + (rand() - 0.5) * (opts.jitter || 0);
        if (opts.transform) [px, py] = opts.transform(px, py);
        if (opts.isLand && !opts.isLand(px, py)) continue;
        if (opts.track && distToPolyline(px, py, opts.track) < (opts.clear || 52)) continue;
        if (opts.keepOut && opts.keepOut.some(k => Math.hypot(px - k[0], py - k[1]) < k[2])) continue;
        out.push({ x: px, y: py, r: rand() });
      }
    }
    return out.sort((a, b) => a.y - b.y);
  }

  function tree(ctx, x, y, r, c1, c2) {
    ctx.fillStyle = 'rgba(0,0,0,0.22)';
    ctx.beginPath();
    ctx.ellipse(x + 3, y + 3, r, r * 0.55, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = c1;
    ctx.beginPath();
    ctx.arc(x, y - r * 0.4, r, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = c2;
    ctx.beginPath();
    ctx.arc(x - r * 0.3, y - r * 0.7, r * 0.5, 0, Math.PI * 2);
    ctx.fill();
  }

  function waterTexture(ctx, rand, x0, y0, x1, y1, color, count) {
    ctx.strokeStyle = color;
    ctx.lineWidth = 1.4;
    for (let i = 0; i < count; i++) {
      const x = x0 + rand() * (x1 - x0);
      const y = y0 + rand() * (y1 - y0);
      const w = 8 + rand() * 18;
      ctx.beginPath();
      ctx.moveTo(x, y);
      ctx.quadraticCurveTo(x + w / 2, y - 3, x + w, y);
      ctx.stroke();
    }
  }

  function label(ctx, text, x, y, opts) {
    const o = opts || {};
    ctx.save();
    ctx.font = `${o.weight || 700} ${o.size || 13}px 'Bricolage Grotesque', 'Figtree', sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    const w = ctx.measureText(text).width + 18;
    ctx.fillStyle = o.bg || 'rgba(30, 21, 48, 0.82)';
    ctx.beginPath();
    ctx.roundRect(x - w / 2, y - 11, w, 22, 11);
    ctx.fill();
    ctx.strokeStyle = o.border || 'rgba(232, 180, 74, 0.55)';
    ctx.lineWidth = 1;
    ctx.stroke();
    ctx.fillStyle = o.color || '#FFF4DE';
    ctx.fillText(text, x, y + 0.5);
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // City definitions
  // ---------------------------------------------------------------------------
  const WORLDS = {};

  /* ============================== İSTANBUL ============================== */
  (function () {
    const europe = [[-PAD, -PAD], [520, -PAD], [518, 40], [505, 150], [480, 260], [455, 360], [470, 470], [520, 560], [505, 660], [430, H + PAD], [-PAD, H + PAD]];
    const asia = [[640, -PAD], [W + PAD, -PAD], [W + PAD, H + PAD], [770, H + PAD], [700, 650], [660, 545], [640, 440], [655, 300], [640, 160], [628, 40]];
    // Golden Horn reaches far enough west that the track crosses it on the Galata Bridge
    const goldenHorn = [[478, 392], [400, 404], [300, 418], [180, 432], [62, 446], [90, 464], [180, 460], [310, 450], [404, 442], [484, 440]];
    const isLand = (x, y) => (inPoly(x, y, europe) || inPoly(x, y, asia)) && !inPoly(x, y, goldenHorn);

    WORLDS.istanbul = {
      title: 'İstanbul',
      subtitle: 'İki kıta, bir Boğaz',
      outside: '#0D3F5D',
      road: { base: '#CDB79A', edge: '#7D6249', line: '#FFF4DE', dash: [3, 9], glow: null },
      control: [
        [455, 590], [330, 622], [200, 598], [118, 520], [112, 420], [140, 316], [210, 226], [318, 168], [440, 140],
        [560, 132], [690, 140], [810, 168], [918, 222], [986, 318], [1002, 430], [960, 540], [868, 616], [740, 646], [620, 628]
      ],
      landmark: { x: 590, y: 700, name: 'Kız Kulesi', icon: '🏰' },
      labels: [['Avrupa Yakası', 250, 300], ['Asya Yakası', 860, 400], ['Haliç', 300, 470], ['Boğaz', 575, 360]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1453);
        // Sea
        const sea = ctx.createLinearGradient(0, -PAD, 0, H + PAD);
        sea.addColorStop(0, '#0F4A6B');
        sea.addColorStop(1, '#0A3049');
        ctx.fillStyle = sea;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        waterTexture(ctx, rand, -PAD, -PAD, W + PAD, H + PAD, 'rgba(160, 220, 255, 0.10)', 520);

        // Shallow water halo + land
        [europe, asia].forEach(shape => {
          ctx.save();
          ctx.beginPath();
          shape.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
          ctx.closePath();
          ctx.strokeStyle = 'rgba(120, 210, 220, 0.30)';
          ctx.lineWidth = 26;
          ctx.stroke();
          const land = ctx.createLinearGradient(0, 0, W, H);
          land.addColorStop(0, '#B9A07C');
          land.addColorStop(1, '#957A5B');
          ctx.fillStyle = land;
          ctx.fill();
          ctx.strokeStyle = '#E9D4AE';
          ctx.lineWidth = 3;
          ctx.stroke();
          ctx.restore();
        });

        // Golden Horn inlet
        ctx.save();
        ctx.beginPath();
        goldenHorn.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.fillStyle = '#0F4A6B';
        ctx.fill();
        ctx.strokeStyle = '#E9D4AE';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();

        // Meadows wherever no neighbourhood stands
        const districtField = (x, y) => Math.sin(x * 0.017) + Math.sin(y * 0.022 + 1.3) + Math.sin((x + y) * 0.009 + 0.4);
        ctx.fillStyle = 'rgba(126, 150, 92, 0.55)';
        for (let gy = -PAD; gy < H + PAD; gy += 22) {
          for (let gx = -PAD; gx < W + PAD; gx += 22) {
            if (districtField(gx, gy) <= -0.45 && isLand(gx, gy)) {
              ctx.beginPath(); ctx.arc(gx, gy, 17, 0, Math.PI * 2); ctx.fill();
            }
          }
        }

        // Parks (Gülhane, Yıldız, Validebağ)
        [[250, 560, 55, 32], [760, 250, 60, 38], [850, 470, 48, 30]].forEach(([x, y, rx, ry]) => {
          ctx.fillStyle = '#4E7A3E';
          ctx.beginPath();
          ctx.ellipse(x, y, rx, ry, 0.2, 0, Math.PI * 2);
          ctx.fill();
        });

        // Houses with terracotta roofs, in clustered neighbourhoods with green
        // gaps between them (low-frequency "district" field)
        const walls = ['#EADBC0', '#E7C9A3', '#D9B48F', '#C9D2B8', '#EFE3CF', '#E3B9A0'];
        const district = (x, y) => Math.sin(x * 0.017) + Math.sin(y * 0.022 + 1.3) + Math.sin((x + y) * 0.009 + 0.4);
        const houses = gridScatter(rand, {
          x0: -PAD + 30, x1: W + PAD - 30, y0: -PAD + 30, y1: H + PAD - 10,
          step: [22, 18], jitter: 3, streetEvery: 4, skip: 0.42,
          isLand: (x, y) => isLand(x, y) && district(x, y) > -0.35, track, clear: 48,
          keepOut: [[330, 500, 70], [300, 335, 40], [850, 380, 60], [590, 132, 80], [250, 560, 58], [760, 250, 62], [850, 470, 50]]
        });
        houses.forEach(h => {
          const w = 10 + h.r * 8;
          const hh = 7 + h.r * 6;
          ctx.fillStyle = 'rgba(0,0,0,0.25)';
          ctx.fillRect(h.x + 2, h.y - 1, w, 4);
          ctx.fillStyle = walls[Math.floor(h.r * walls.length)];
          ctx.fillRect(h.x, h.y - hh, w, hh);
          ctx.fillStyle = h.r > 0.5 ? '#B5533C' : '#C96A4A';
          polyFill(ctx, [[h.x - 2, h.y - hh], [h.x + w / 2, h.y - hh - 6], [h.x + w + 2, h.y - hh]], ctx.fillStyle);
          ctx.fillStyle = 'rgba(255, 220, 140, 0.85)';
          if (h.r > 0.35) ctx.fillRect(h.x + 3, h.y - hh + 3, 2, 2);
        });

        // Green between the neighbourhoods
        scatter(rand, 260, {
          x0: -PAD, x1: W + PAD, y0: -PAD, y1: H + PAD,
          isLand: (x, y) => isLand(x, y) && district(x, y) <= -0.35, track, clear: 44, spacing: 16
        }).forEach(t => tree(ctx, t.x, t.y, 5 + t.r * 3, '#5B7F45', '#77A05A'));

        // Ayasofya: dome, half-domes, four minarets
        drawMosque(ctx, 330, 512, 1.25, '#D99C8B');
        // Sultanahmet-style mosque in Asia (Çamlıca)
        drawMosque(ctx, 850, 392, 1.05, '#E9E1D2');
        // Galata Tower
        drawGalata(ctx, 300, 345);

        // Bosphorus Bridge towers + cables around the northern crossing
        drawSuspension(ctx, 520, 646, 132);
        // Maiden's Tower islet
        ctx.fillStyle = '#E9D4AE';
        ctx.beginPath();
        ctx.ellipse(590, 708, 26, 12, 0, 0, Math.PI * 2);
        ctx.fill();
        ctx.fillStyle = '#F4EBDD';
        ctx.fillRect(582, 672, 16, 32);
        polyFill(ctx, [[578, 672], [590, 652], [602, 672]], '#6C8C9C');
        ctx.fillStyle = '#B5533C';
        ctx.fillRect(588, 640, 4, 14);
      },
      drawDynamic(ctx, t) {
        // Ferries crossing the Bosphorus
        for (let i = 0; i < 3; i++) {
          const phase = ((t * 0.018) + i * 0.33) % 1;
          const y = -40 + phase * (H + 80);
          const x = 575 + Math.sin(phase * 6 + i) * 18;
          drawFerry(ctx, x, y, i % 2 === 0);
        }
        // Gulls
        ctx.strokeStyle = 'rgba(255,255,255,0.85)';
        ctx.lineWidth = 1.6;
        for (let i = 0; i < 6; i++) {
          const gx = ((t * (12 + i * 3)) + i * 190) % (W + 200) - 100;
          const gy = 90 + i * 95 + Math.sin(t * 0.8 + i) * 14;
          const flap = Math.sin(t * 6 + i) * 3;
          ctx.beginPath();
          ctx.moveTo(gx - 6, gy - flap);
          ctx.quadraticCurveTo(gx - 3, gy - 4, gx, gy);
          ctx.quadraticCurveTo(gx + 3, gy - 4, gx + 6, gy - flap);
          ctx.stroke();
        }
      }
    };
  })();

  function drawMosque(ctx, x, y, s, wall) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(s, s);
    ctx.fillStyle = 'rgba(0,0,0,0.25)';
    ctx.beginPath();
    ctx.ellipse(4, 4, 40, 10, 0, 0, Math.PI * 2);
    ctx.fill();
    ctx.fillStyle = wall;
    ctx.fillRect(-30, -22, 60, 22);
    ctx.fillStyle = '#7E9AA8';
    ctx.beginPath(); ctx.arc(0, -22, 18, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(-20, -20, 9, Math.PI, 0); ctx.fill();
    ctx.beginPath(); ctx.arc(20, -20, 9, Math.PI, 0); ctx.fill();
    ctx.fillStyle = '#E8B44A';
    ctx.fillRect(-1, -46, 2, 6);
    [-38, 38].forEach(mx => {
      ctx.fillStyle = '#F4EBDD';
      ctx.fillRect(mx - 2.5, -54, 5, 54);
      polyFill(ctx, [[mx - 3.5, -54], [mx, -66], [mx + 3.5, -54]], '#7E9AA8');
      ctx.fillStyle = '#D8CBB4';
      ctx.fillRect(mx - 4, -36, 8, 2);
    });
    ctx.fillStyle = 'rgba(255, 220, 140, 0.9)';
    for (let i = -24; i <= 20; i += 8) ctx.fillRect(i, -14, 3, 5);
    ctx.restore();
  }

  function drawGalata(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.25)';
    ctx.beginPath(); ctx.ellipse(3, 3, 16, 6, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#D9C3A0';
    ctx.fillRect(-10, -52, 20, 52);
    ctx.fillStyle = '#C9AE86';
    ctx.fillRect(-10, -52, 6, 52);
    ctx.fillStyle = '#3E3A3A';
    for (let i = -6; i <= 4; i += 5) ctx.fillRect(i, -46, 2, 4);
    ctx.fillStyle = '#E9D4AE';
    ctx.fillRect(-13, -56, 26, 5);
    polyFill(ctx, [[-12, -56], [0, -80], [12, -56]], '#596C7A');
    ctx.restore();
  }

  function drawSuspension(ctx, x0, x1, y) {
    ctx.save();
    ctx.strokeStyle = 'rgba(240, 230, 210, 0.9)';
    ctx.lineWidth = 1.5;
    [-16, 16].forEach(off => {
      ctx.beginPath();
      ctx.moveTo(x0 - 60, y + off);
      ctx.quadraticCurveTo((x0 + x1) / 2, y + off + 2, x1 + 60, y + off);
      ctx.stroke();
    });
    [x0, x1].forEach(tx => {
      ctx.fillStyle = '#C04A3C';
      ctx.fillRect(tx - 4, y - 24, 8, 48);
      ctx.fillStyle = '#8E2F25';
      ctx.fillRect(tx - 4, y - 24, 3, 48);
    });
    ctx.restore();
  }

  function drawFerry(ctx, x, y, north) {
    ctx.save();
    ctx.translate(x, y);
    if (!north) ctx.rotate(Math.PI);
    ctx.fillStyle = 'rgba(255,255,255,0.35)';
    ctx.beginPath(); ctx.moveTo(-5, 16); ctx.lineTo(0, 30); ctx.lineTo(5, 16); ctx.fill();
    ctx.fillStyle = '#F4EBDD';
    ctx.beginPath(); ctx.roundRect(-8, -16, 16, 32, 6); ctx.fill();
    ctx.fillStyle = '#26394A';
    ctx.fillRect(-5, -8, 10, 14);
    ctx.fillStyle = '#C04A3C';
    ctx.fillRect(-2, -3, 4, 4);
    ctx.restore();
  }

  /* ============================== TOKYO ============================== */
  (function () {
    const bay = [[700, H - 30], [880, 640], [1010, 600], [W + PAD, 560], [W + PAD, H + PAD], [640, H + PAD]];
    const river = [[760, -PAD], [742, 60], [720, 150], [736, 260], [768, 380], [792, 480], [800, 560], [820, 640]];
    const riverW = 44;
    const onRiver = (x, y) => {
      for (let i = 0; i < river.length - 1; i++) {
        const a = river[i], b = river[i + 1];
        const dx = b[0] - a[0], dy = b[1] - a[1];
        let t = ((x - a[0]) * dx + (y - a[1]) * dy) / (dx * dx + dy * dy);
        t = Math.max(0, Math.min(1, t));
        if (Math.hypot(x - (a[0] + t * dx), y - (a[1] + t * dy)) < riverW / 2) return true;
      }
      return false;
    };
    const isLand = (x, y) => !inPoly(x, y, bay) && !onRiver(x, y);

    WORLDS.tokyo = {
      title: 'Tokyo',
      subtitle: 'Neon körfezi',
      outside: '#12152A',
      road: { base: '#262A44', edge: '#0B0D1C', line: '#5EEAD4', dash: [10, 8], glow: 'rgba(94, 234, 212, 0.55)' },
      control: [
        [140, 610], [128, 470], [130, 330], [140, 190], [260, 150], [380, 158], [440, 230], [470, 330], [550, 365],
        [630, 330], [660, 230], [720, 158], [850, 150], [968, 190], [980, 330], [970, 470], [930, 600], [790, 650],
        [640, 660], [480, 662], [300, 652]
      ],
      landmark: { x: 550, y: 250, name: 'Tokyo Kulesi', icon: '🗼' },
      labels: [['Shibuya', 150, 690], ['Ueno Parkı', 300, 420], ['Tokyo Körfezi', 960, 690], ['Sumida', 735, 95]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1964);
        // Night ground
        ctx.fillStyle = '#12152A';
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        // Street grid
        ctx.strokeStyle = 'rgba(120, 130, 200, 0.14)';
        ctx.lineWidth = 6;
        for (let gx = -PAD; gx < W + PAD; gx += 64) { ctx.beginPath(); ctx.moveTo(gx, -PAD); ctx.lineTo(gx, H + PAD); ctx.stroke(); }
        for (let gy = -PAD; gy < H + PAD; gy += 58) { ctx.beginPath(); ctx.moveTo(-PAD, gy); ctx.lineTo(W + PAD, gy); ctx.stroke(); }

        // Bay
        ctx.save();
        ctx.beginPath();
        bay.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        const bayG = ctx.createLinearGradient(700, 560, 1100, 900);
        bayG.addColorStop(0, '#0D2A4A');
        bayG.addColorStop(1, '#06162A');
        ctx.fillStyle = bayG;
        ctx.fill();
        ctx.strokeStyle = 'rgba(94, 234, 212, 0.45)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
        // River
        ctx.save();
        bandPath(ctx, river);
        ctx.strokeStyle = '#0D2A4A';
        ctx.lineWidth = riverW;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        ctx.strokeStyle = 'rgba(94, 234, 212, 0.25)';
        ctx.lineWidth = 2;
        ctx.stroke();
        ctx.restore();
        waterTexture(ctx, rand, 700, 600, W + PAD, H + PAD, 'rgba(140, 200, 255, 0.12)', 120);

        // Ueno park with sakura
        ctx.fillStyle = '#1C2E2A';
        ctx.beginPath(); ctx.roundRect(220, 360, 170, 120, 26); ctx.fill();
        scatter(rand, 26, { x0: 230, x1: 380, y0: 370, y1: 470, spacing: 18 })
          .forEach(t => tree(ctx, t.x, t.y, 7 + t.r * 3, '#F4A6C6', '#FFD1E3'));

        // Shibuya scramble crossing (under the track corner)
        ctx.save();
        ctx.translate(140, 640);
        ctx.fillStyle = 'rgba(255,255,255,0.75)';
        for (let i = -3; i <= 3; i++) {
          ctx.fillRect(-60, i * 9 - 2, 30, 5);
          ctx.fillRect(30, i * 9 - 2, 30, 5);
        }
        ctx.rotate(Math.PI / 4);
        for (let i = -4; i <= 4; i++) ctx.fillRect(i * 9 - 2, -50, 5, 100);
        ctx.restore();

        // Skyscrapers with lit windows and neon signs
        const neon = ['#FF4FA3', '#5EEAD4', '#FDE047', '#A78BFA', '#38BDF8'];
        const towers = scatter(rand, 150, {
          x0: -PAD + 20, x1: W + PAD - 20, y0: -PAD + 60, y1: H + PAD,
          isLand, track, clear: 56, spacing: 26,
          keepOut: [[550, 250, 70], [305, 420, 100], [880, 330, 50], [140, 640, 70]]
        });
        towers.forEach(b => {
          const w = 16 + b.r * 14;
          const h = 22 + Math.pow(b.r, 2) * 70;
          const shade = 18 + Math.floor(b.r * 16);
          block(ctx, b.x - w / 2, b.y, w, h, 12, {
            front: `rgb(${shade + 8}, ${shade + 10}, ${shade + 34})`,
            side: `rgb(${shade}, ${shade + 2}, ${shade + 22})`,
            top: `rgb(${shade + 22}, ${shade + 24}, ${shade + 52})`
          }, rand, { lit: 0.35, on: b.r > 0.6 ? '#FDE68A' : '#9BE7FF', off: 'rgba(255,255,255,0.05)' });
          if (b.r > 0.78) {
            const c = neon[Math.floor(b.r * 97) % neon.length];
            ctx.save();
            ctx.shadowColor = c;
            ctx.shadowBlur = 10;
            ctx.fillStyle = c;
            ctx.fillRect(b.x - w / 2 + 2, b.y - h * 0.55, 3, Math.min(26, h * 0.4));
            ctx.restore();
          }
        });

        // Tokyo Tower in the notch of the track, Skytree on the east side
        drawTokyoTower(ctx, 550, 290, 1.25);
        drawSkytree(ctx, 880, 330);

        // Shinkansen line
        ctx.strokeStyle = '#3A3F63';
        ctx.lineWidth = 8;
        ctx.beginPath(); ctx.moveTo(-PAD, 718); ctx.lineTo(W + PAD, 718); ctx.stroke();
        ctx.strokeStyle = '#7C82B8';
        ctx.lineWidth = 1.5;
        [-3, 3].forEach(o => { ctx.beginPath(); ctx.moveTo(-PAD, 718 + o); ctx.lineTo(W + PAD, 718 + o); ctx.stroke(); });

        // Torii gate (Meiji) near the western track
        drawTorii(ctx, 60, 360);
      },
      drawDynamic(ctx, t) {
        // Shinkansen
        const tx = ((t * 160) % (W + PAD * 2 + 400)) - PAD - 200;
        ctx.save();
        ctx.fillStyle = '#F4F6FB';
        ctx.beginPath(); ctx.roundRect(tx, 710, 180, 16, 8); ctx.fill();
        ctx.fillStyle = '#1D4ED8';
        ctx.fillRect(tx + 10, 722, 160, 2);
        ctx.fillStyle = '#93C5FD';
        for (let k = 0; k < 9; k++) ctx.fillRect(tx + 16 + k * 18, 714, 10, 4);
        ctx.restore();
        // Sakura petals drifting over Ueno
        ctx.fillStyle = 'rgba(255, 190, 215, 0.9)';
        for (let i = 0; i < 18; i++) {
          const px = 210 + ((i * 53 + t * 18) % 220);
          const py = 330 + ((i * 37 + t * 26) % 190);
          ctx.beginPath();
          ctx.ellipse(px + Math.sin(t * 2 + i) * 6, py, 2.4, 1.4, t + i, 0, Math.PI * 2);
          ctx.fill();
        }
        // Tower beacon
        const a = (Math.sin(t * 3) + 1) / 2;
        ctx.fillStyle = `rgba(255, 80, 80, ${0.4 + a * 0.6})`;
        ctx.beginPath(); ctx.arc(550, 290 - 150, 3.5, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(880, 330 - 205, 3, 0, Math.PI * 2); ctx.fill();
      }
    };
  })();

  function drawTokyoTower(ctx, x, y, s) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(s, s);
    ctx.fillStyle = 'rgba(0,0,0,0.35)';
    ctx.beginPath(); ctx.ellipse(0, 2, 34, 9, 0, 0, Math.PI * 2); ctx.fill();
    const bands = ['#E5484D', '#F4F6FB'];
    for (let i = 0; i < 8; i++) {
      const y0 = -i * 14;
      const y1 = y0 - 14;
      const w0 = 30 - i * 3.4;
      const w1 = 30 - (i + 1) * 3.4;
      polyFill(ctx, [[-w0, y0], [-w1, y1], [w1, y1], [w0, y0]], bands[i % 2]);
      ctx.strokeStyle = 'rgba(0,0,0,0.25)';
      ctx.lineWidth = 0.8;
      ctx.beginPath(); ctx.moveTo(-w0, y0); ctx.lineTo(w1, y1); ctx.moveTo(w0, y0); ctx.lineTo(-w1, y1); ctx.stroke();
    }
    ctx.fillStyle = '#F4F6FB';
    ctx.fillRect(-9, -58, 18, 6);
    ctx.fillRect(-6, -96, 12, 5);
    ctx.fillStyle = '#E5484D';
    ctx.fillRect(-1.5, -122, 3, 12);
    ctx.restore();
  }

  function drawSkytree(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.35)';
    ctx.beginPath(); ctx.ellipse(0, 2, 20, 6, 0, 0, Math.PI * 2); ctx.fill();
    polyFill(ctx, [[-14, 0], [-4, -200], [4, -200], [14, 0]], '#C9D4E8');
    polyFill(ctx, [[-14, 0], [-4, -200], [0, -200], [-2, 0]], '#9FB0CC');
    ctx.fillStyle = '#E8EEF8';
    ctx.fillRect(-10, -120, 20, 8);
    ctx.fillRect(-7, -160, 14, 6);
    ctx.fillStyle = '#5EEAD4';
    ctx.fillRect(-10, -114, 20, 2);
    ctx.restore();
  }

  function drawTorii(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = '#E5484D';
    ctx.fillRect(-20, -30, 5, 30);
    ctx.fillRect(15, -30, 5, 30);
    ctx.fillRect(-26, -34, 52, 5);
    ctx.fillRect(-22, -24, 44, 3);
    ctx.fillStyle = '#1F1A1A';
    ctx.fillRect(-28, -37, 56, 3);
    ctx.restore();
  }

  /* ============================== PARIS ============================== */
  (function () {
    const seine = [[-PAD, 300], [60, 330], [190, 410], [330, 450], [470, 418], [610, 380], [740, 420], [870, 470], [1000, 440], [W + PAD, 390]];
    const seineW = 50;
    const onSeine = (x, y) => {
      for (let i = 0; i < seine.length - 1; i++) {
        const a = seine[i], b = seine[i + 1];
        const dx = b[0] - a[0], dy = b[1] - a[1];
        let t = ((x - a[0]) * dx + (y - a[1]) * dy) / (dx * dx + dy * dy);
        t = Math.max(0, Math.min(1, t));
        if (Math.hypot(x - (a[0] + t * dx), y - (a[1] + t * dy)) < seineW / 2 + 6) return true;
      }
      return false;
    };
    const center = [550, 375];
    // Track: a rosette around the Étoile
    const control = [];
    for (let i = 0; i < 20; i++) {
      const a = Math.PI / 2 + (i / 20) * Math.PI * 2;
      const r = 1 + 0.11 * Math.cos(a * 5);
      control.push([Math.round(center[0] + Math.cos(a) * 440 * r), Math.round(center[1] + Math.sin(a) * 268 * r)]);
    }
    const isLand = (x, y) => !onSeine(x, y);

    WORLDS.paris = {
      title: 'Paris',
      subtitle: 'Işık bulvarları',
      outside: '#8C7357',
      veil: 'rgba(40, 26, 18, 0.24)',
      road: { base: '#E9DCC0', edge: '#9C8660', line: '#B8862B', dash: [2, 7], glow: null },
      control,
      landmark: { x: 360, y: 300, name: 'Eyfel Kulesi', icon: '🗼' },
      labels: [['Sen Nehri', 120, 345], ['Louvre', 770, 318], ['Zafer Takı', 550, 412]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1889);
        // Warm evening limestone city
        const g = ctx.createRadialGradient(center[0], center[1], 80, center[0], center[1], 900);
        g.addColorStop(0, '#CDB48C');
        g.addColorStop(1, '#8C7357');
        ctx.fillStyle = g;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);

        // Radial avenues from the Étoile
        ctx.strokeStyle = 'rgba(250, 236, 205, 0.55)';
        ctx.lineWidth = 9;
        for (let i = 0; i < 12; i++) {
          const a = (i / 12) * Math.PI * 2;
          ctx.beginPath();
          ctx.moveTo(center[0] + Math.cos(a) * 46, center[1] + Math.sin(a) * 30);
          ctx.lineTo(center[0] + Math.cos(a) * 1200, center[1] + Math.sin(a) * 800);
          ctx.stroke();
        }
        // Ring boulevard
        ctx.lineWidth = 7;
        ctx.beginPath(); ctx.ellipse(center[0], center[1], 250, 150, 0, 0, Math.PI * 2); ctx.stroke();

        // Seine
        ctx.save();
        bandPath(ctx, seine);
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = '#E3D2AE';
        ctx.lineWidth = seineW + 12;
        ctx.stroke();
        ctx.strokeStyle = '#3E6E8C';
        ctx.lineWidth = seineW;
        ctx.stroke();
        ctx.strokeStyle = 'rgba(255, 230, 170, 0.22)';
        ctx.lineWidth = 2;
        ctx.setLineDash([12, 18]);
        ctx.stroke();
        ctx.restore();

        // Champ de Mars + Tuileries gardens
        ctx.fillStyle = '#6C8A4A';
        ctx.beginPath(); ctx.roundRect(300, 300, 120, 44, 10); ctx.fill();
        ctx.beginPath(); ctx.roundRect(700, 250, 140, 46, 10); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.35)';
        ctx.lineWidth = 1;
        ctx.strokeRect(312, 310, 96, 24);

        // Plane trees lining the Seine
        for (let i = 0; i < seine.length - 1; i++) {
          const a = seine[i], b = seine[i + 1];
          for (let k = 0; k < 6; k++) {
            const f = k / 6;
            const x = a[0] + (b[0] - a[0]) * f;
            const y = a[1] + (b[1] - a[1]) * f;
            [-1, 1].forEach(side => {
              const ty = y + side * (seineW / 2 + 14);
              if (distToPolyline(x, ty, track) > 42) tree(ctx, x, ty, 5.5, '#4F6E36', '#6B8C48');
            });
          }
        }

        // Haussmann blocks: cream stone, slate mansard roofs, ironwork balconies
        // Haussmann blocks follow the radial avenues: place on a grid, then skip
        // anything sitting on an avenue so the star pattern stays readable
        const onAvenue = (x, y) => {
          const a = Math.atan2((y - center[1]) / 0.66, x - center[0]);
          const step = (Math.PI * 2) / 12;
          const off = Math.abs(((a % step) + step) % step - step / 2);
          const d = Math.hypot(x - center[0], (y - center[1]) / 0.66);
          return (step / 2 - off) * d < 14;
        };
        const blocks = gridScatter(rand, {
          x0: -PAD + 20, x1: W + PAD - 20, y0: -PAD + 30, y1: H + PAD,
          step: [32, 26], jitter: 4, skip: 0.45,
          isLand: (x, y) => isLand(x, y) && !onAvenue(x, y), track, clear: 50,
          keepOut: [[360, 250, 90], [550, 360, 62], [770, 290, 82], [360, 320, 70]]
        });
        blocks.forEach(b => {
          const w = 22 + b.r * 6;
          const h = 15 + b.r * 9;
          block(ctx, b.x - w / 2, b.y, w, h, 15, { front: '#EFE3C8', side: '#D6C6A3', top: '#5E6B7D' }, rand,
            { lit: 0.5, on: '#FFD58A', off: '#8F9AA8' });
          // Wrought-iron balcony line
          ctx.fillStyle = '#3A4250';
          ctx.fillRect(b.x - w / 2, b.y - h + 7, w, 1.2);
        });

        drawArc(ctx, center[0], center[1] + 8);
        drawPyramid(ctx, 770, 300);
        drawEiffel(ctx, 360, 300);
      },
      drawDynamic(ctx, t) {
        // Eiffel sparkle on the hour (here: in waves)
        const wave = (Math.sin(t * 0.7) + 1) / 2;
        if (wave > 0.55) {
          ctx.fillStyle = 'rgba(255, 252, 230, 0.95)';
          for (let i = 0; i < 26; i++) {
            const k = (i * 7919 + Math.floor(t * 12)) % 100 / 100;
            const yy = 300 - k * 180;
            const half = 26 * (1 - k) + 3;
            const xx = 360 + (((i * 31 + Math.floor(t * 20)) % 100) / 50 - 1) * half;
            ctx.fillRect(xx, yy, 2, 2);
          }
        }
        // Bateaux-mouches on the Seine
        for (let i = 0; i < 2; i++) {
          const f = ((t * 0.02) + i * 0.5) % 1;
          const seg = Math.min(seineDense.length - 1, Math.floor(f * seineDense.length));
          const p = seineDense[seg];
          ctx.save();
          ctx.translate(p.x, p.y + (i ? -8 : 8));
          ctx.fillStyle = '#F4EBDD';
          ctx.beginPath(); ctx.roundRect(-16, -5, 32, 10, 5); ctx.fill();
          ctx.fillStyle = '#FFD58A';
          for (let k = -12; k <= 10; k += 5) ctx.fillRect(k, -2, 3, 3);
          ctx.restore();
        }
      }
    };
    // Dense Seine path for the boats
    const seineDense = [];
    for (let i = 0; i < seine.length - 1; i++) {
      for (let k = 0; k < 20; k++) {
        const f = k / 20;
        seineDense.push({ x: seine[i][0] + (seine[i + 1][0] - seine[i][0]) * f, y: seine[i][1] + (seine[i + 1][1] - seine[i][1]) * f });
      }
    }
  })();

  function drawEiffel(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    ctx.beginPath(); ctx.ellipse(0, 4, 44, 10, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = '#6B4F34';
    ctx.fillStyle = '#7A5B3C';
    ctx.lineWidth = 2.2;
    // Legs with arch
    ctx.beginPath();
    ctx.moveTo(-34, 0); ctx.quadraticCurveTo(-14, -70, -6, -180); ctx.lineTo(6, -180); ctx.quadraticCurveTo(14, -70, 34, 0);
    ctx.lineTo(22, 0); ctx.quadraticCurveTo(0, -36, -22, 0); ctx.closePath();
    ctx.fill();
    // Lattice
    ctx.strokeStyle = 'rgba(255, 220, 160, 0.35)';
    ctx.lineWidth = 0.8;
    for (let k = 0; k < 14; k++) {
      const yy = -k * 12;
      const half = 30 * (1 - k / 15) + 4;
      ctx.beginPath(); ctx.moveTo(-half, yy); ctx.lineTo(half, yy - 12); ctx.moveTo(half, yy); ctx.lineTo(-half, yy - 12); ctx.stroke();
    }
    ctx.fillStyle = '#5A412A';
    ctx.fillRect(-24, -44, 48, 5);
    ctx.fillRect(-13, -104, 26, 4);
    ctx.fillRect(-2, -196, 4, 18);
    ctx.fillStyle = '#FFD58A';
    ctx.beginPath(); ctx.arc(0, -198, 2.5, 0, Math.PI * 2); ctx.fill();
    ctx.restore();
  }

  function drawArc(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(0,0,0,0.3)';
    ctx.beginPath(); ctx.ellipse(4, 2, 34, 9, 0, 0, Math.PI * 2); ctx.fill();
    block(ctx, -26, 0, 52, 44, 14, { front: '#EFE3C8', side: '#D2BE97', top: '#E6D6B4' });
    ctx.fillStyle = '#5B4A3A';
    ctx.beginPath(); ctx.moveTo(-9, 0); ctx.lineTo(-9, -22); ctx.arc(0, -22, 9, Math.PI, 0); ctx.lineTo(9, 0); ctx.closePath(); ctx.fill();
    ctx.fillStyle = '#D2BE97';
    ctx.fillRect(-26, -38, 52, 3);
    ctx.restore();
  }

  function drawPyramid(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    polyFill(ctx, [[-30, 0], [0, -34], [30, 0]], 'rgba(170, 215, 235, 0.85)');
    polyFill(ctx, [[0, -34], [30, 0], [10, 6]], 'rgba(120, 170, 200, 0.85)');
    ctx.strokeStyle = 'rgba(255,255,255,0.6)';
    ctx.lineWidth = 0.7;
    for (let i = 1; i < 5; i++) {
      ctx.beginPath(); ctx.moveTo(-30 + i * 6, -i * 6.8); ctx.lineTo(30 - i * 6, -i * 6.8); ctx.stroke();
    }
    ctx.restore();
  }

  /* ============================== NEW YORK ============================== */
  (function () {
    const ANG = -0.22; // Manhattan's grid tilt
    const cx = 560, cy = 380;
    const rot = (x, y) => {
      const c = Math.cos(ANG), s = Math.sin(ANG);
      return [Math.round(cx + x * c - y * s), Math.round(cy + x * s + y * c)];
    };
    const manhattan = [rot(-600, -200), rot(520, -250), rot(620, -150), rot(600, 170), rot(480, 260), rot(-420, 280), rot(-520, 240), rot(-640, 60)];
    const brooklyn = [[860, 700], [1000, 610], [W + PAD, 560], [W + PAD, H + PAD], [760, H + PAD]];
    const liberty = [[110, 690], [150, 668], [190, 684], [168, 712], [124, 714]];
    const park = [rot(-170, -95), rot(250, -95), rot(250, 70), rot(-170, 70)];
    const isLand = (x, y) => inPoly(x, y, manhattan) || inPoly(x, y, brooklyn);

    // Track: avenues around Central Park, down to the financial district and back
    const control = [
      rot(-470, 190), rot(-500, 40), rot(-430, -120), rot(-250, -175), rot(-40, -185), rot(170, -185), rot(380, -178),
      rot(470, -90), rot(480, 60), rot(420, 175), rot(250, 205), rot(40, 210), rot(-150, 215), rot(-320, 225)
    ];

    WORLDS.newyork = {
      title: 'New York',
      subtitle: 'Manhattan ızgarası',
      outside: '#2F6FA0',
      road: { base: '#3A3F47', edge: '#1D2026', line: '#FBBF24', dash: [12, 10], glow: null },
      control,
      landmark: { x: 150, y: 680, name: 'Özgürlük Heykeli', icon: '🗽' },
      labels: [['Central Park', ...rot(40, -10)], ['Brooklyn', 960, 690], ['Hudson', 120, 120]],
      isLand,
      drawStatic(ctx, track) {
        const rand = seeded(1776);
        // Harbour
        const sea = ctx.createLinearGradient(0, -PAD, W, H + PAD);
        sea.addColorStop(0, '#3C86B8');
        sea.addColorStop(1, '#245E8A');
        ctx.fillStyle = sea;
        ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);
        waterTexture(ctx, rand, -PAD, -PAD, W + PAD, H + PAD, 'rgba(255,255,255,0.16)', 420);

        [manhattan, brooklyn, liberty].forEach(shape => {
          ctx.beginPath();
          shape.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
          ctx.closePath();
          ctx.strokeStyle = 'rgba(255,255,255,0.55)';
          ctx.lineWidth = 3;
          ctx.stroke();
          ctx.fillStyle = '#B9B5AA';
          ctx.fill();
        });

        // Street grid, clipped to Manhattan
        ctx.save();
        ctx.beginPath();
        manhattan.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.clip();
        ctx.translate(cx, cy);
        ctx.rotate(ANG);
        ctx.strokeStyle = '#8F8B82';
        ctx.lineWidth = 7;
        for (let gx = -700; gx < 700; gx += 90) { ctx.beginPath(); ctx.moveTo(gx, -400); ctx.lineTo(gx, 400); ctx.stroke(); }
        ctx.lineWidth = 3;
        for (let gy = -400; gy < 400; gy += 26) { ctx.beginPath(); ctx.moveTo(-700, gy); ctx.lineTo(700, gy); ctx.stroke(); }
        ctx.restore();

        // Central Park with lake and paths
        polyFill(ctx, park, '#5F9447');
        const lake = rot(60, -20);
        ctx.fillStyle = '#3C86B8';
        ctx.beginPath(); ctx.ellipse(lake[0], lake[1], 46, 24, ANG, 0, Math.PI * 2); ctx.fill();
        scatter(rand, 70, {
          x0: 330, x1: 860, y0: 200, y1: 520, isLand: (x, y) => inPoly(x, y, park),
          spacing: 14, keepOut: [[lake[0], lake[1], 52]]
        }).forEach(t => tree(ctx, t.x, t.y, 5 + t.r * 3, '#3F7A34', '#5E9E48'));

        // Skyscrapers with sharp afternoon shadows
        const towers = scatter(rand, 150, {
          x0: -PAD, x1: W + PAD, y0: -PAD, y1: H + PAD,
          isLand: (x, y) => isLand(x, y) && !inPoly(x, y, park), track, clear: 56, spacing: 24,
          keepOut: [[...rot(-330, 120), 50]]
        });
        towers.forEach(b => {
          const w = 16 + b.r * 12;
          const h = 26 + Math.pow(b.r, 2.2) * 90;
          ctx.fillStyle = 'rgba(30, 40, 60, 0.28)';
          polyFill(ctx, [[b.x - w / 2, b.y], [b.x + w / 2, b.y], [b.x + w / 2 + h * 0.5, b.y + h * 0.18], [b.x - w / 2 + h * 0.5, b.y + h * 0.18]], 'rgba(30, 40, 60, 0.25)');
          const glass = b.r > 0.5;
          block(ctx, b.x - w / 2, b.y, w, h, 11, glass
            ? { front: '#7FA7C9', side: '#5D84A8', top: '#C7D9E8' }
            : { front: '#C9B9A0', side: '#A7967D', top: '#E1D5C1' }, rand,
          { lit: 0.25, on: glass ? '#E6F3FF' : '#FFF1C9', off: glass ? '#6A92B6' : '#9C8C74' });
        });

        // Landmarks
        const es = rot(-330, 120);
        drawEmpireState(ctx, es[0], es[1]);
        drawLiberty(ctx, 150, 690);
        drawBrooklynBridge(ctx);
      },
      drawDynamic(ctx, t) {
        // Yellow cabs on the avenues (only on the island)
        ctx.save();
        ctx.beginPath();
        manhattan.forEach((p, i) => (i ? ctx.lineTo(p[0], p[1]) : ctx.moveTo(p[0], p[1])));
        ctx.closePath();
        ctx.clip();
        ctx.translate(cx, cy);
        ctx.rotate(ANG);
        for (let i = 0; i < 12; i++) {
          const lane = -630 + (i % 6) * 90 * 2 + 45 * 0;
          const dir = i % 2 ? 1 : -1;
          const yy = ((t * (40 + i * 6) * dir + i * 137) % 760 + 760) % 760 - 380;
          const xx = lane + (i > 5 ? 90 : 0);
          ctx.fillStyle = '#FBBF24';
          ctx.fillRect(xx - 3, yy - 6, 6, 12);
          ctx.fillStyle = '#1F2937';
          ctx.fillRect(xx - 2, yy - 2, 4, 3);
        }
        ctx.restore();
        // Liberty torch glow
        const a = (Math.sin(t * 2.5) + 1) / 2;
        ctx.fillStyle = `rgba(255, 200, 80, ${0.35 + a * 0.5})`;
        ctx.beginPath(); ctx.arc(141, 618, 5 + a * 2, 0, Math.PI * 2); ctx.fill();
        // Ferry to Liberty Island
        const f = (t * 0.03) % 1;
        const fx = 220 + f * 300;
        const fy = 690 - f * 120;
        ctx.fillStyle = '#F4EBDD';
        ctx.beginPath(); ctx.roundRect(fx - 10, fy - 4, 20, 8, 4); ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,0.5)';
        ctx.fillRect(fx - 22, fy - 1, 10, 2);
      }
    };
  })();

  function drawEmpireState(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.fillStyle = 'rgba(30, 40, 60, 0.3)';
    polyFill(ctx, [[-14, 0], [14, 0], [110, 34], [80, 34]], 'rgba(30, 40, 60, 0.3)');
    block(ctx, -16, 0, 32, 70, 14, { front: '#D9CDB6', side: '#B4A68C', top: '#ECE3D2' });
    block(ctx, -11, -70, 22, 50, 10, { front: '#D9CDB6', side: '#B4A68C', top: '#ECE3D2' });
    block(ctx, -7, -120, 14, 30, 8, { front: '#E3D8C3', side: '#BBAE95', top: '#F1EADD' });
    ctx.fillStyle = '#C0C7D1';
    ctx.fillRect(-2, -176, 4, 30);
    ctx.fillStyle = '#8B95A3';
    for (let k = -12; k <= 10; k += 5) ctx.fillRect(k, -66, 2, 60);
    ctx.restore();
  }

  function drawLiberty(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    block(ctx, -9, 0, 18, 16, 8, { front: '#B9A98D', side: '#9C8C70', top: '#CFC2A8' });
    ctx.fillStyle = '#6FB5A4';
    polyFill(ctx, [[-8, -16], [-5, -58], [5, -58], [8, -16]], '#6FB5A4');
    ctx.beginPath(); ctx.arc(0, -62, 5, 0, Math.PI * 2); ctx.fill();
    ctx.fillRect(-10, -82, 3, 22);
    ctx.strokeStyle = '#6FB5A4';
    ctx.lineWidth = 1.2;
    for (let k = -3; k <= 3; k++) { ctx.beginPath(); ctx.moveTo(0, -66); ctx.lineTo(k * 3, -72); ctx.stroke(); }
    ctx.restore();
  }

  function drawBrooklynBridge(ctx) {
    const a = [790, 610], b = [930, 655];
    ctx.save();
    ctx.strokeStyle = '#6B5E50';
    ctx.lineWidth = 8;
    ctx.beginPath(); ctx.moveTo(a[0] - 30, a[1] - 10); ctx.lineTo(b[0] + 30, b[1] + 10); ctx.stroke();
    ctx.strokeStyle = 'rgba(255,255,255,0.7)';
    ctx.lineWidth = 1;
    ctx.beginPath(); ctx.moveTo(a[0] - 30, a[1] - 20); ctx.quadraticCurveTo((a[0] + b[0]) / 2, (a[1] + b[1]) / 2 + 4, b[0] + 30, b[1]); ctx.stroke();
    [a, b].forEach(p => {
      ctx.fillStyle = '#8C7B66';
      ctx.fillRect(p[0] - 5, p[1] - 30, 10, 32);
      ctx.fillStyle = '#5B4E40';
      ctx.fillRect(p[0] - 2, p[1] - 24, 4, 10);
    });
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // Precompute tracks
  // ---------------------------------------------------------------------------
  Object.keys(WORLDS).forEach(id => {
    const world = WORLDS[id];
    const dense = spline(world.control, 24);
    const sampled = resample(dense, 32);
    world.dense = dense;
    world.tiles = sampled.points;
    world.trackLength = sampled.length;
  });
  WORLDS.tropics = WORLDS.istanbul;
  WORLDS.cyber = WORLDS.tokyo;
  WORLDS.candy = WORLDS.paris;

  // Keep MAP_THEMES landmark data in sync (used for clicks and toasts)
  Object.keys(WORLDS).forEach(id => {
    if (MAP_THEMES[id]) MAP_THEMES[id].landmark = WORLDS[id].landmark;
  });

  function worldFor(themeId) {
    return WORLDS[themeId] || WORLDS.istanbul;
  }

  // ---------------------------------------------------------------------------
  // Static scenery cache
  // ---------------------------------------------------------------------------
  // The cache holds everything that never moves: scenery, a dimming veil that
  // pushes the scenery back behind the track, the road body and the soft edge
  // fade. Once built it is converted to an ImageBitmap (GPU-resident), and each
  // frame only the visible part of it is drawn.
  const cache = {};
  const bitmaps = {};
  function worldKey(world) {
    return Object.keys(WORLDS).find(k => WORLDS[k] === world);
  }

  function traceDense(ctx, dense, dy) {
    ctx.beginPath();
    dense.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y + (dy || 0)) : ctx.moveTo(p.x, p.y + (dy || 0))));
    ctx.closePath();
  }

  function sceneryFor(themeId) {
    const world = worldFor(themeId);
    const key = worldKey(world);
    if (cache[key]) return bitmaps[key] || cache[key];
    const canvas = document.createElement('canvas');
    canvas.width = Math.round((W + PAD * 2) * CACHE_SCALE);
    canvas.height = Math.round((H + PAD * 2) * CACHE_SCALE);
    const ctx = canvas.getContext('2d');
    ctx.scale(CACHE_SCALE, CACHE_SCALE);
    ctx.translate(PAD, PAD);
    world.drawStatic(ctx, world.dense);

    // Veil: quieter scenery so the track, tiles and pawns read first
    ctx.fillStyle = world.veil || 'rgba(24, 16, 38, 0.22)';
    ctx.fillRect(-PAD, -PAD, W + PAD * 2, H + PAD * 2);

    // Bridge piers wherever the track runs over water
    ctx.fillStyle = 'rgba(40, 30, 25, 0.55)';
    world.dense.forEach((p, i) => {
      if (i % 3 === 0 && !world.isLand(p.x, p.y)) ctx.fillRect(p.x - 3, p.y + 10, 6, 14);
    });

    // Road body (the flowing centre line is drawn live)
    const road = world.road;
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    traceDense(ctx, world.dense, 7);
    ctx.strokeStyle = 'rgba(0, 0, 0, 0.35)';
    ctx.lineWidth = 34;
    ctx.stroke();
    traceDense(ctx, world.dense, 3);
    ctx.strokeStyle = road.edge;
    ctx.lineWidth = 32;
    ctx.stroke();
    if (road.glow) {
      ctx.shadowColor = road.glow;
      ctx.shadowBlur = 16;
      traceDense(ctx, world.dense);
      ctx.strokeStyle = road.glow;
      ctx.lineWidth = 31;
      ctx.stroke();
      ctx.shadowBlur = 0;
    }
    traceDense(ctx, world.dense);
    ctx.strokeStyle = road.base;
    ctx.lineWidth = 28;
    ctx.stroke();
    ctx.restore();

    // Soft fade into the surrounding colour at the cache edges
    const fade = 160;
    const x0 = -PAD, y0 = -PAD, x1 = W + PAD, y1 = H + PAD;
    [
      [x0, y0, x0 + fade, y0, x0, y0, fade, y1 - y0],
      [x1, y0, x1 - fade, y0, x1 - fade, y0, fade, y1 - y0],
      [x0, y0, x0, y0 + fade, x0, y0, x1 - x0, fade],
      [x0, y1, x0, y1 - fade, x0, y1 - fade, x1 - x0, fade]
    ].forEach(([gx0, gy0, gx1, gy1, rx, ry, rw, rh]) => {
      const g = ctx.createLinearGradient(gx0, gy0, gx1, gy1);
      g.addColorStop(0, world.outside);
      g.addColorStop(1, 'rgba(0,0,0,0)');
      ctx.fillStyle = g;
      ctx.fillRect(rx, ry, rw, rh);
    });

    cache[key] = canvas;
    if (typeof createImageBitmap === 'function') {
      createImageBitmap(canvas).then(bmp => { bitmaps[key] = bmp; }).catch(() => {});
    }
    return canvas;
  }

  // Raw (un-veiled) scenery for menu thumbnails
  function rawCanvasFor(themeId) {
    const world = worldFor(themeId);
    sceneryFor(themeId);
    return cache[worldKey(world)];
  }

  // ---------------------------------------------------------------------------
  // Tile icons (vector, so every platform renders them identically)
  // ---------------------------------------------------------------------------
  const TYPE_STYLE = {
    start: { color: '#2BB3A3' },
    coin: { color: '#3E7BD8' },
    mystery: { color: '#8A5CD1' },
    trap: { color: '#C2373C', hazard: true },
    shop: { color: '#E0962E' },
    star: { color: '#E8B44A' },
    warp: { color: '#1FA0C2' },
    duel: { color: '#D2457E' },
    tax: { color: '#B23A5A', hazard: true },
    bank: { color: '#2F9D6A' },
    jail: { color: '#7A3036', hazard: true },
    freeze: { color: '#4B8FD6', hazard: true },
    reverse: { color: '#7D4BC2', hazard: true },
    slip: { color: '#C99A1E', hazard: true }
  };

  function starPath(ctx, x, y, r, inner) {
    ctx.beginPath();
    for (let i = 0; i < 10; i++) {
      const a = -Math.PI / 2 + (i * Math.PI) / 5;
      const rr = i % 2 ? r * (inner || 0.45) : r;
      const px = x + Math.cos(a) * rr;
      const py = y + Math.sin(a) * rr;
      if (i) ctx.lineTo(px, py); else ctx.moveTo(px, py);
    }
    ctx.closePath();
  }

  function drawIcon(ctx, type, x, y, tile) {
    const ink = '#2B1D12';
    ctx.save();
    ctx.translate(x, y);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = ink;
    ctx.fillStyle = ink;
    ctx.lineWidth = 2;
    switch (type) {
      case 'coin':
      case 'bank': {
        ctx.fillStyle = '#E8B44A';
        ctx.beginPath(); ctx.arc(0, 0, 9, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#A97A22'; ctx.lineWidth = 1.6; ctx.stroke();
        ctx.fillStyle = ink;
        ctx.font = "800 9px 'Figtree', sans-serif";
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(type === 'bank' ? '$' : `+${tile.amount || ''}`, 0, 0.5);
        break;
      }
      case 'star':
        starPath(ctx, 0, 0, 10);
        ctx.fillStyle = '#E8B44A'; ctx.fill();
        ctx.strokeStyle = '#A97A22'; ctx.lineWidth = 1.4; ctx.stroke();
        break;
      case 'start':
        ctx.strokeStyle = ink; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(-6, 9); ctx.lineTo(-6, -9); ctx.stroke();
        for (let r = 0; r < 2; r++) for (let c = 0; c < 3; c++) {
          ctx.fillStyle = (r + c) % 2 ? '#FFF4DE' : ink;
          ctx.fillRect(-5 + c * 4, -9 + r * 4, 4, 4);
        }
        break;
      case 'mystery':
        ctx.fillStyle = '#8A5CD1';
        ctx.fillRect(-8, -3, 16, 11);
        ctx.fillStyle = '#B88CF0';
        ctx.fillRect(-9, -7, 18, 5);
        ctx.fillStyle = '#E8B44A';
        ctx.fillRect(-1.5, -7, 3, 15);
        ctx.beginPath(); ctx.ellipse(-3, -9, 3, 2, -0.5, 0, Math.PI * 2); ctx.ellipse(3, -9, 3, 2, 0.5, 0, Math.PI * 2); ctx.fill();
        break;
      case 'shop':
        ctx.fillStyle = '#E0962E';
        ctx.beginPath(); ctx.roundRect(-8, -4, 16, 13, 2); ctx.fill();
        ctx.strokeStyle = ink; ctx.lineWidth = 1.6;
        ctx.beginPath(); ctx.arc(0, -4, 4.5, Math.PI, 0); ctx.stroke();
        break;
      case 'trap':
        ctx.fillStyle = '#C2373C';
        for (let i = -1; i <= 1; i++) {
          ctx.beginPath(); ctx.moveTo(i * 6 - 4, 7); ctx.lineTo(i * 6, -8); ctx.lineTo(i * 6 + 4, 7); ctx.closePath(); ctx.fill();
        }
        break;
      case 'warp':
        ctx.strokeStyle = '#1FA0C2'; ctx.lineWidth = 2.2;
        ctx.beginPath();
        for (let a = 0; a < Math.PI * 5; a += 0.2) {
          const r = 1 + a * 0.55;
          const px = Math.cos(a) * r, py = Math.sin(a) * r;
          if (a) ctx.lineTo(px, py); else ctx.moveTo(px, py);
        }
        ctx.stroke();
        break;
      case 'duel':
        ctx.strokeStyle = ink; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.moveTo(-8, 8); ctx.lineTo(8, -8); ctx.moveTo(8, 8); ctx.lineTo(-8, -8); ctx.stroke();
        ctx.strokeStyle = '#D2457E'; ctx.lineWidth = 2;
        ctx.beginPath(); ctx.moveTo(-9, 3); ctx.lineTo(-3, 9); ctx.moveTo(9, 3); ctx.lineTo(3, 9); ctx.stroke();
        break;
      case 'jail':
        ctx.strokeStyle = ink; ctx.lineWidth = 2;
        ctx.strokeRect(-8, -8, 16, 16);
        for (let i = -4; i <= 4; i += 4) { ctx.beginPath(); ctx.moveTo(i, -8); ctx.lineTo(i, 8); ctx.stroke(); }
        break;
      case 'tax':
        ctx.fillStyle = '#E8B44A';
        ctx.beginPath(); ctx.arc(0, 0, 9, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = '#B23A5A'; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.moveTo(-4, 0); ctx.lineTo(4, 0); ctx.stroke();
        break;
      case 'freeze':
        ctx.strokeStyle = '#2F6FB8'; ctx.lineWidth = 2;
        for (let i = 0; i < 3; i++) {
          ctx.save(); ctx.rotate((i * Math.PI) / 3);
          ctx.beginPath(); ctx.moveTo(0, -10); ctx.lineTo(0, 10); ctx.moveTo(-3, -7); ctx.lineTo(0, -4); ctx.lineTo(3, -7); ctx.stroke();
          ctx.restore();
        }
        break;
      case 'reverse':
      case 'slip':
        ctx.strokeStyle = '#7D4BC2'; ctx.lineWidth = 2.4;
        ctx.beginPath(); ctx.arc(0, 0, 7, -Math.PI * 0.2, Math.PI * 1.3); ctx.stroke();
        ctx.fillStyle = '#7D4BC2';
        ctx.beginPath(); ctx.moveTo(6, -9); ctx.lineTo(10, -1); ctx.lineTo(1, -3); ctx.closePath(); ctx.fill();
        break;
      default:
        ctx.beginPath(); ctx.arc(0, 0, 4, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
  }

  // ---------------------------------------------------------------------------
  // Patch BoardRenderer
  // ---------------------------------------------------------------------------
  const P = BoardRenderer.prototype;
  const origSetTheme = P.setTheme;

  P.calculateTilePositions = function () {
    return worldFor(this.activeTheme || 'istanbul').tiles.map(p => ({ x: p.x, y: p.y }));
  };

  P.setTheme = function (themeId) {
    const before = this.activeTheme;
    origSetTheme.call(this, themeId);
    if (this.activeTheme !== before || !this.__worldReady) {
      this.__worldReady = true;
      this.tileCoords = this.calculateTilePositions();
      sceneryFor(this.activeTheme); // warm the cache
    }
  };

  P.drawEnvironment = function (ctx) {
    const world = worldFor(this.activeTheme);
    if (!this.__worldReady) {
      this.__worldReady = true;
      this.tileCoords = this.calculateTilePositions();
    }
    const img = sceneryFor(this.activeTheme);
    ctx.save();
    ctx.fillStyle = world.outside;
    ctx.fillRect(-3000, -3000, 7100, 6800);

    // Draw only the part of the cached scenery that is on screen
    const m = ctx.getTransform();
    const inv = m.inverse();
    const cw = ctx.canvas.width;
    const ch = ctx.canvas.height;
    const corners = [[0, 0], [cw, 0], [0, ch], [cw, ch]].map(([x, y]) => ({
      x: inv.a * x + inv.c * y + inv.e,
      y: inv.b * x + inv.d * y + inv.f
    }));
    let vx0 = Math.min(...corners.map(c => c.x)) - 4;
    let vy0 = Math.min(...corners.map(c => c.y)) - 4;
    let vx1 = Math.max(...corners.map(c => c.x)) + 4;
    let vy1 = Math.max(...corners.map(c => c.y)) + 4;
    vx0 = Math.max(vx0, -PAD); vy0 = Math.max(vy0, -PAD);
    vx1 = Math.min(vx1, W + PAD); vy1 = Math.min(vy1, H + PAD);
    if (vx1 > vx0 && vy1 > vy0) {
      ctx.drawImage(img,
        (vx0 + PAD) * CACHE_SCALE, (vy0 + PAD) * CACHE_SCALE, (vx1 - vx0) * CACHE_SCALE, (vy1 - vy0) * CACHE_SCALE,
        vx0, vy0, vx1 - vx0, vy1 - vy0);
    }

    if (world.drawDynamic) world.drawDynamic(ctx, performance.now() / 1000);
    ctx.restore();
  };

  // Only the flowing centre line is live; the road body is in the cache
  P.drawPathways = function (ctx) {
    const world = worldFor(this.activeTheme);
    ctx.save();
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    traceDense(ctx, world.dense);
    ctx.setLineDash(world.road.dash);
    ctx.lineDashOffset = -(performance.now() / 60) % 100;
    ctx.strokeStyle = world.road.line;
    ctx.lineWidth = 2.5;
    ctx.stroke();
    ctx.restore();
  };

  // ---------------------------------------------------------------------------
  // Tile sprites: each kind of space is drawn once, then blitted every frame
  // ---------------------------------------------------------------------------
  const TILE_R = 25;
  const TILE_DEPTH = 7;
  const SPRITE_W = 64;
  const SPRITE_H = 56;
  const spriteCache = {};

  function darken(hex, f) {
    const n = parseInt(hex.slice(1), 16);
    const r = Math.round(((n >> 16) & 255) * (1 - f));
    const g = Math.round(((n >> 8) & 255) * (1 - f));
    const b = Math.round((n & 255) * (1 - f));
    return `rgb(${r}, ${g}, ${b})`;
  }

  function tileSprite(tile) {
    const key = `${tile.type}|${tile.amount || ''}`;
    if (spriteCache[key]) return spriteCache[key];
    const style = TYPE_STYLE[tile.type] || { color: '#3E7BD8' };
    const c = document.createElement('canvas');
    c.width = SPRITE_W * SPRITE_SCALE;
    c.height = SPRITE_H * SPRITE_SCALE;
    const ctx = c.getContext('2d');
    ctx.scale(SPRITE_SCALE, SPRITE_SCALE);
    const x = SPRITE_W / 2;
    const y = SPRITE_H / 2 - 4;
    const r = TILE_R;

    ctx.fillStyle = 'rgba(0, 0, 0, 0.35)';
    ctx.beginPath(); ctx.ellipse(x + 2, y + TILE_DEPTH + 5, r + 2, r * 0.62, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = darken(style.color, 0.45);
    ctx.beginPath(); ctx.ellipse(x, y + TILE_DEPTH, r, r * 0.66, 0, 0, Math.PI * 2); ctx.fill();
    ctx.fillRect(x - r, y, r * 2, TILE_DEPTH);

    const face = ctx.createLinearGradient(x, y - r, x, y + r);
    face.addColorStop(0, style.color);
    face.addColorStop(1, darken(style.color, 0.22));
    ctx.fillStyle = face;
    ctx.beginPath(); ctx.ellipse(x, y, r, r * 0.66, 0, 0, Math.PI * 2); ctx.fill();
    ctx.strokeStyle = style.hazard ? 'rgba(255, 160, 150, 0.9)' : 'rgba(255, 244, 222, 0.85)';
    ctx.lineWidth = 1.8;
    ctx.stroke();

    if (style.hazard) {
      ctx.setLineDash([4, 4]);
      ctx.strokeStyle = 'rgba(255, 220, 120, 0.85)';
      ctx.lineWidth = 1.4;
      ctx.beginPath(); ctx.ellipse(x, y, r - 5, r * 0.66 - 4, 0, 0, Math.PI * 2); ctx.stroke();
      ctx.setLineDash([]);
    }

    ctx.fillStyle = '#FFF4DE';
    ctx.beginPath(); ctx.ellipse(x, y - 1, 13.5, 11.5, 0, 0, Math.PI * 2); ctx.fill();
    drawIcon(ctx, tile.type, x, y - 1, tile);

    spriteCache[key] = c;
    return c;
  }

  // Pre-rendered halo for the active star pedestal
  let haloSprite = null;
  function starHalo() {
    if (haloSprite) return haloSprite;
    const size = 150;
    const c = document.createElement('canvas');
    c.width = size;
    c.height = size;
    const ctx = c.getContext('2d');
    const g = ctx.createRadialGradient(size / 2, size / 2, 10, size / 2, size / 2, size / 2);
    g.addColorStop(0, 'rgba(255, 214, 120, 0.75)');
    g.addColorStop(1, 'rgba(255, 214, 120, 0)');
    ctx.fillStyle = g;
    ctx.fillRect(0, 0, size, size);
    haloSprite = c;
    return c;
  }

  P.drawPlatformTile = function (ctx, index, tile, pos, isStarTile, isSelected, hasTrap) {
    const pulse = this.starPulse || 0;
    if (isStarTile) {
      const s = 132 + Math.sin(pulse * 1.5) * 10;
      ctx.drawImage(starHalo(), pos.x - s / 2, pos.y - s / 2, s, s);
    }
    ctx.drawImage(tileSprite(tile), pos.x - SPRITE_W / 2, pos.y - SPRITE_H / 2 + 4, SPRITE_W, SPRITE_H);

    if (isStarTile) {
      ctx.save();
      ctx.strokeStyle = '#FFE08A';
      ctx.lineWidth = 3;
      ctx.beginPath(); ctx.ellipse(pos.x, pos.y, TILE_R, TILE_R * 0.66, 0, 0, Math.PI * 2); ctx.stroke();
      const bob = Math.sin(pulse * 1.5) * 5;
      starPath(ctx, pos.x, pos.y - 44 + bob, 15);
      ctx.fillStyle = '#E8B44A';
      ctx.fill();
      ctx.strokeStyle = '#FFF4DE';
      ctx.lineWidth = 2;
      ctx.stroke();
      ctx.restore();
    }

    if (isSelected) {
      ctx.save();
      ctx.strokeStyle = '#FFF4DE';
      ctx.lineWidth = 2.5;
      ctx.setLineDash([5, 4]);
      ctx.beginPath(); ctx.ellipse(pos.x, pos.y + 2, TILE_R + 8, TILE_R * 0.66 + 7, 0, 0, Math.PI * 2); ctx.stroke();
      ctx.restore();
    }

    if (hasTrap) {
      ctx.save();
      ctx.fillStyle = '#F6D24A';
      ctx.strokeStyle = '#6B4A12';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.moveTo(pos.x + 8, pos.y - 26);
      ctx.quadraticCurveTo(pos.x + 22, pos.y - 20, pos.x + 20, pos.y - 6);
      ctx.quadraticCurveTo(pos.x + 14, pos.y - 16, pos.x + 8, pos.y - 26);
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    }
  };

  // Landmarks are part of the illustration; no floating name plates
  P.drawLandmark = function () {};
  // No spotlight beam: the active pawn's name tag already marks whose turn it is
  P.drawTurnSpotlight = function () {};

  // Possible landing spots for your own roll: quiet brass rings on the next 6 spaces
  P.drawStepTrajectory = function (ctx) {
    const state = window.CURRENT_ROOM_STATE;
    if (!state || state.state !== 'playing' || !state.players) return;
    const active = state.players[state.turnIndex];
    const me = window.GameClient && window.GameClient.player;
    if (!active || !me || active.sessionId !== me.sessionId) return;
    const n = this.tileCoords.length;
    const a = 0.35 + (Math.sin((this.starPulse || 0) * 2) + 1) * 0.15;
    ctx.save();
    ctx.strokeStyle = `rgba(255, 224, 138, ${a})`;
    ctx.lineWidth = 2;
    for (let s = 1; s <= 6; s++) {
      const p = this.tileCoords[(active.position + s) % n];
      ctx.beginPath(); ctx.ellipse(p.x, p.y, TILE_R + 5, TILE_R * 0.66 + 4, 0, 0, Math.PI * 2); ctx.stroke();
    }
    ctx.restore();
  };

  // Phones in portrait fit the whole world to the narrow width, which makes the
  // spaces tiny; start closer there (the camera still follows the active pawn)
  const origResetCamera = P.resetCamera;
  P.resetCamera = function () {
    origResetCamera.call(this);
    const w = this.canvas ? this.canvas.width / (this.dpr || 1) : window.innerWidth;
    if (w < 700) this.camera.zoom = 1.75;
  };

  // Do no drawing work while the board is not on screen
  const origDraw = P.draw;
  P.draw = function () {
    if (document.hidden || !this.canvas || this.canvas.offsetParent === null) return;
    origDraw.call(this);
  };

  // Public: map metadata for the UI (title cards, previews)
  window.WorldMaps = {
    info(themeId) {
      const w = worldFor(themeId);
      return { title: w.title, subtitle: w.subtitle };
    },
    // Small preview image of a city for menus (track included in the cache)
    preview(themeId, width) {
      const src = rawCanvasFor(themeId);
      const cw = width || 240;
      const ch = Math.round(cw * (H / W));
      const c = document.createElement('canvas');
      c.width = cw * 2;
      c.height = ch * 2;
      const ctx = c.getContext('2d');
      ctx.drawImage(src, PAD * CACHE_SCALE, PAD * CACHE_SCALE, W * CACHE_SCALE, H * CACHE_SCALE, 0, 0, c.width, c.height);
      c.style.width = `${cw}px`;
      c.style.height = `${ch}px`;
      return c;
    },
    _worlds: WORLDS
  };
})();
