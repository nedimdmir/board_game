/**
 * Player Progression, XP, Level, and Unlockables System
 */
const AVATAR_DEFS = {
  builder: {
    id: 'builder',
    name: 'Minifig Bob',
    requiredLevel: 1,
    desc: 'Neşeli usta lego inşaatçısı.',
    color: '#3B82F6',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="bobHead" cx="40%" cy="35%" r="60%">
          <stop offset="0%" stop-color="#FEF08A"/>
          <stop offset="70%" stop-color="#FACC15"/>
          <stop offset="100%" stop-color="#EAB308"/>
        </radialGradient>
        <linearGradient id="bobHat" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="#F97316"/>
          <stop offset="100%" stop-color="#EA580C"/>
        </linearGradient>
      </defs>
      <path d="M12 15 C12 6, 36 6, 36 15 Z" fill="url(#bobHat)"/>
      <ellipse cx="24" cy="15" rx="14" ry="3.5" fill="#C2410C"/>
      <path d="M18 9 Q24 7 30 9" stroke="#FED7AA" stroke-width="1.5" stroke-linecap="round" fill="none"/>
      <rect x="15" y="15" width="18" height="15" rx="4" fill="url(#bobHead)"/>
      <circle cx="20" cy="21" r="2.2" fill="#0F172A"/>
      <circle cx="19.3" cy="20.3" r="0.8" fill="#FFFFFF"/>
      <circle cx="28" cy="21" r="2.2" fill="#0F172A"/>
      <circle cx="27.3" cy="20.3" r="0.8" fill="#FFFFFF"/>
      <ellipse cx="18" cy="24" rx="2" ry="1.2" fill="#F87171" opacity="0.5"/>
      <ellipse cx="30" cy="24" rx="2" ry="1.2" fill="#F87171" opacity="0.5"/>
      <path d="M21 24 Q24 28 27 24" stroke="#0F172A" stroke-width="1.8" stroke-linecap="round" fill="none"/>
      <path d="M11 31 L37 31 L34 45 L14 45 Z" fill="#2563EB"/>
      <rect x="19" y="31" width="10" height="14" fill="#1D4ED8"/>
      <circle cx="16" cy="33" r="1.3" fill="#FBBF24"/>
      <circle cx="32" cy="33" r="1.3" fill="#FBBF24"/>
      <path d="M22 36 L26 40" stroke="#CBD5E1" stroke-width="1.5" stroke-linecap="round"/>
    </svg>`
  },
  dino: {
    id: 'dino',
    name: 'Şapkalı Dino',
    requiredLevel: 1,
    desc: 'Partilere bayılan sevimli yeşil dinozor.',
    color: '#10B981',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="dinoSkin" cx="35%" cy="35%" r="65%">
          <stop offset="0%" stop-color="#6EE7B7"/>
          <stop offset="60%" stop-color="#10B981"/>
          <stop offset="100%" stop-color="#047857"/>
        </radialGradient>
      </defs>
      <polygon points="12,12 8,5 16,10" fill="#F59E0B"/>
      <polygon points="19,8 20,1 23,8" fill="#EF4444"/>
      <polygon points="26,10 32,5 28,12" fill="#3B82F6"/>
      <rect x="12" y="11" width="24" height="20" rx="9" fill="url(#dinoSkin)"/>
      <ellipse cx="24" cy="23" rx="7" ry="4.5" fill="#34D399"/>
      <circle cx="22" cy="22" r="1" fill="#065F46"/>
      <circle cx="26" cy="22" r="1" fill="#065F46"/>
      <circle cx="18" cy="18" r="2.8" fill="#064E3B"/>
      <circle cx="17.2" cy="17" r="1.1" fill="#FFFFFF"/>
      <circle cx="30" cy="18" r="2.8" fill="#064E3B"/>
      <circle cx="29.2" cy="17" r="1.1" fill="#FFFFFF"/>
      <ellipse cx="15" cy="22" rx="2.5" ry="1.5" fill="#F472B6" opacity="0.65"/>
      <ellipse cx="33" cy="22" rx="2.5" ry="1.5" fill="#F472B6" opacity="0.65"/>
      <polygon points="24,2 19,12 29,12" fill="#F43F5E"/>
      <circle cx="24" cy="2" r="1.8" fill="#FDE047"/>
      <path d="M14 31 Q24 29 34 31 L32 45 L16 45 Z" fill="#059669"/>
      <ellipse cx="24" cy="38" rx="6" ry="5" fill="#A7F3D0"/>
    </svg>`
  },
  cat: {
    id: 'cat',
    name: 'Siber Neko',
    requiredLevel: 1,
    desc: 'Hızlı ve şanslı neon siber kedi.',
    color: '#EC4899',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="catSkin" cx="40%" cy="35%" r="60%">
          <stop offset="0%" stop-color="#FBCFE8"/>
          <stop offset="70%" stop-color="#F472B6"/>
          <stop offset="100%" stop-color="#DB2777"/>
        </radialGradient>
      </defs>
      <polygon points="11,18 15,4 23,15" fill="#DB2777"/>
      <polygon points="14,16 16,7 21,14" fill="#FDF2F8"/>
      <polygon points="37,18 33,4 25,15" fill="#DB2777"/>
      <polygon points="34,16 32,7 27,14" fill="#FDF2F8"/>
      <rect x="13" y="13" width="22" height="18" rx="8" fill="url(#catSkin)"/>
      <rect x="11" y="16" width="26" height="5" rx="2.5" fill="#0F172A"/>
      <rect x="13" y="17.5" width="22" height="2" rx="1" fill="#06B6D4"/>
      <ellipse cx="19" cy="22" rx="2.6" ry="3" fill="#0F172A"/>
      <circle cx="18.2" cy="21" r="1.1" fill="#FFFFFF"/>
      <ellipse cx="29" cy="22" rx="2.6" ry="3" fill="#0F172A"/>
      <circle cx="28.2" cy="21" r="1.1" fill="#FFFFFF"/>
      <polygon points="24,24 23,25.5 25,25.5" fill="#9D174D"/>
      <path d="M22 26 Q24 27.5 26 26" stroke="#9D174D" stroke-width="1.3" fill="none"/>
      <line x1="12" y1="23" x2="6" y2="22" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="12" y1="25" x2="5" y2="26" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="36" y1="23" x2="42" y2="22" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <line x1="36" y1="25" x2="43" y2="26" stroke="#FCE7F3" stroke-width="1.5" stroke-linecap="round"/>
      <rect x="16" y="31" width="16" height="13" rx="4" fill="#BE185D"/>
      <circle cx="24" cy="33" r="2.8" fill="#FBBF24"/>
      <circle cx="24" cy="33.8" r="0.8" fill="#78350F"/>
    </svg>`
  },
  astro: {
    id: 'astro',
    name: 'Kozmik Kaşif',
    requiredLevel: 2,
    desc: 'Uzayın derinliklerinden gelen zar ustası.',
    color: '#06B6D4',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <radialGradient id="visorGrad" cx="30%" cy="30%" r="70%">
          <stop offset="0%" stop-color="#67E8F9"/>
          <stop offset="50%" stop-color="#0891B2"/>
          <stop offset="100%" stop-color="#083344"/>
        </radialGradient>
      </defs>
      <line x1="24" y1="2" x2="24" y2="8" stroke="#94A3B8" stroke-width="2"/>
      <circle cx="24" cy="2" r="2.2" fill="#EF4444"/>
      <circle cx="24" cy="18" r="13" fill="#E2E8F0"/>
      <ellipse cx="24" cy="18" rx="9" ry="7" fill="url(#visorGrad)"/>
      <path d="M19 14 Q24 12 28 15" stroke="#FFFFFF" stroke-width="1.8" stroke-linecap="round" fill="none" opacity="0.8"/>
      <circle cx="27" cy="19" r="1.2" fill="#FFFFFF" opacity="0.9"/>
      <path d="M13 31 Q24 29 35 31 L33 45 L15 45 Z" fill="#F1F5F9"/>
      <rect x="18" y="33" width="12" height="11" rx="2" fill="#0891B2"/>
      <circle cx="21" cy="37" r="1.2" fill="#22C55E"/>
      <circle cx="27" cy="37" r="1.2" fill="#F59E0B"/>
    </svg>`
  },
  wizard: {
    id: 'wizard',
    name: 'Büyücü Merlin',
    requiredLevel: 2,
    desc: 'Zarları sihirle manipüle eden usta.',
    color: '#8B5CF6',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <defs>
        <linearGradient id="wizHat" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#7C3AED"/>
          <stop offset="100%" stop-color="#4C1D95"/>
        </linearGradient>
      </defs>
      <path d="M24 1 Q34 6 28 17 L12 17 Q18 7 24 1 Z" fill="url(#wizHat)"/>
      <ellipse cx="24" cy="17" rx="14" ry="3.5" fill="#5B21B6"/>
      <circle cx="24" cy="8" r="1.5" fill="#FDE047"/>
      <polygon points="21,12 22,14 24,14 22.5,15 23,17 21,15.5 19,17 19.5,15 18,14 20,14" fill="#FDE047"/>
      <circle cx="24" cy="21" r="7.5" fill="#FED7AA"/>
      <circle cx="21" cy="20" r="1.5" fill="#1E1B4B"/>
      <circle cx="27" cy="20" r="1.5" fill="#1E1B4B"/>
      <path d="M17 23 C14 27, 18 36, 24 36 C30 36, 34 27, 31 23 Z" fill="#F8FAFC"/>
      <path d="M13 34 L35 34 L33 46 L15 46 Z" fill="#6D28D9"/>
      <path d="M24 34 L24 46" stroke="#FDE047" stroke-width="1.8"/>
    </svg>`
  },
  ninja: {
    id: 'ninja',
    name: 'Siber Ninja',
    requiredLevel: 3,
    desc: 'Tuzakları sezen sessiz parti gölgesi.',
    color: '#1E293B',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <path d="M34 16 Q44 14 41 24" stroke="#DC2626" stroke-width="3" fill="none" stroke-linecap="round"/>
      <path d="M34 18 Q42 20 38 28" stroke="#EF4444" stroke-width="2.5" fill="none" stroke-linecap="round"/>
      <rect x="14" y="10" width="20" height="19" rx="7" fill="#0F172A"/>
      <rect x="14" y="12" width="20" height="5" fill="#DC2626"/>
      <rect x="21" y="12.5" width="6" height="4" rx="1" fill="#94A3B8"/>
      <rect x="17" y="18" width="14" height="6" rx="2" fill="#FED7AA"/>
      <circle cx="20" cy="20.5" r="1.6" fill="#0F172A"/>
      <circle cx="19.4" cy="19.8" r="0.6" fill="#EF4444"/>
      <circle cx="28" cy="20.5" r="1.6" fill="#0F172A"/>
      <circle cx="27.4" cy="19.8" r="0.6" fill="#EF4444"/>
      <path d="M13 30 L35 30 L32 45 L16 45 Z" fill="#1E293B"/>
      <line x1="16" y1="31" x2="31" y2="44" stroke="#DC2626" stroke-width="2.5"/>
      <line x1="32" y1="31" x2="17" y2="44" stroke="#DC2626" stroke-width="2.5"/>
    </svg>`
  },
  robot: {
    id: 'robot',
    name: 'Mecha Bot-7',
    requiredLevel: 1,
    desc: 'Hesaplama gücü yüksek tatlı robot dost.',
    color: '#06B6D4',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <line x1="24" y1="2" x2="24" y2="9" stroke="#06B6D4" stroke-width="2.5"/>
      <circle cx="24" cy="3" r="3" fill="#FACC15"/>
      <rect x="11" y="17" width="3" height="6" rx="1" fill="#94A3B8"/>
      <rect x="34" y="17" width="3" height="6" rx="1" fill="#94A3B8"/>
      <rect x="13" y="10" width="22" height="19" rx="5" fill="#64748B"/>
      <rect x="16" y="13" width="16" height="13" rx="3" fill="#0F172A"/>
      <path d="M18 19 Q20 16 22 19" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none"/>
      <path d="M26 19 Q28 16 30 19" stroke="#38BDF8" stroke-width="2" stroke-linecap="round" fill="none"/>
      <line x1="21" y1="23" x2="27" y2="23" stroke="#22D3EE" stroke-width="1.5" stroke-linecap="round"/>
      <path d="M14 30 L34 30 L32 45 L16 45 Z" fill="#475569"/>
      <rect x="19" y="33" width="10" height="9" rx="2" fill="#1E293B"/>
      <circle cx="22" cy="37" r="1.5" fill="#EF4444"/>
      <circle cx="26" cy="37" r="1.5" fill="#10B981"/>
    </svg>`
  },
  detective: {
    id: 'detective',
    name: 'Dedektif Holmes',
    requiredLevel: 2,
    desc: 'Hileleri ve gizemleri anında çözen dedektif.',
    color: '#78350F',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <path d="M12 16 Q24 5 36 16 Z" fill="#92400E"/>
      <ellipse cx="24" cy="16" rx="15" ry="3.5" fill="#78350F"/>
      <path d="M23 7 Q24 5 25 7" stroke="#FEF3C7" stroke-width="2"/>
      <rect x="15" y="16" width="18" height="15" rx="5" fill="#FED7AA"/>
      <circle cx="19" cy="22" r="2" fill="#1E293B"/>
      <circle cx="18.3" cy="21.3" r="0.7" fill="#FFFFFF"/>
      <circle cx="28" cy="22" r="3.2" fill="none" stroke="#F59E0B" stroke-width="1.8"/>
      <circle cx="28" cy="22" r="1.8" fill="#1E293B"/>
      <line x1="31" y1="23" x2="35" y2="29" stroke="#F59E0B" stroke-width="1.2"/>
      <path d="M21 27 Q24 29 26 26" stroke="#78350F" stroke-width="1.5" fill="none" stroke-linecap="round"/>
      <path d="M12 32 L36 32 L33 46 L15 46 Z" fill="#B45309"/>
      <polygon points="20,32 24,37 28,32" fill="#78350F"/>
    </svg>`
  },
  panda: {
    id: 'panda',
    name: 'Parti Pandası',
    requiredLevel: 3,
    desc: 'Altın toplamayı seven sevimli dev panda.',
    color: '#F97316',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <circle cx="14" cy="11" r="5" fill="#0F172A"/>
      <circle cx="34" cy="11" r="5" fill="#0F172A"/>
      <circle cx="24" cy="21" r="12" fill="#F8FAFC"/>
      <ellipse cx="18" cy="20" rx="3.5" ry="4" fill="#0F172A"/>
      <circle cx="17.5" cy="19.2" r="1.3" fill="#FFFFFF"/>
      <ellipse cx="30" cy="20" rx="3.5" ry="4" fill="#0F172A"/>
      <circle cx="29.5" cy="19.2" r="1.3" fill="#FFFFFF"/>
      <ellipse cx="24" cy="25" rx="2" ry="1.4" fill="#0F172A"/>
      <path d="M22 27 Q24 29 26 27" stroke="#0F172A" stroke-width="1.5" fill="none"/>
      <ellipse cx="13" cy="24" rx="2" ry="1.2" fill="#FDA4AF" opacity="0.6"/>
      <ellipse cx="35" cy="24" rx="2" ry="1.2" fill="#FDA4AF" opacity="0.6"/>
      <polygon points="21,32 27,32 24,35" fill="#EF4444"/>
      <polygon points="21,38 27,38 24,35" fill="#EF4444"/>
      <circle cx="24" cy="35" r="1.5" fill="#FDE047"/>
      <path d="M14 34 L34 34 L32 46 L16 46 Z" fill="#EA580C"/>
    </svg>`
  },
  king: {
    id: 'king',
    name: 'Altın Lego Kralı',
    requiredLevel: 4,
    desc: 'Parti Dünyasının mutlak altın hükümdarı!',
    color: '#EAB308',
    svg: `<svg viewBox="0 0 48 48" width="100%" height="100%">
      <polygon points="12,14 10,4 17,9 24,2 31,9 38,4 36,14" fill="#FACC15" stroke="#CA8A04" stroke-width="1.2"/>
      <circle cx="24" cy="3" r="2" fill="#EF4444"/>
      <circle cx="10" cy="5" r="1.5" fill="#3B82F6"/>
      <circle cx="38" cy="5" r="1.5" fill="#10B981"/>
      <rect x="15" y="14" width="18" height="15" rx="5" fill="#FDE047"/>
      <circle cx="20" cy="20" r="2" fill="#1E293B"/>
      <circle cx="19.3" cy="19.3" r="0.8" fill="#FFFFFF"/>
      <circle cx="28" cy="20" r="2" fill="#1E293B"/>
      <circle cx="27.3" cy="19.3" r="0.8" fill="#FFFFFF"/>
      <path d="M21 24 Q24 28 27 24" stroke="#1E293B" stroke-width="1.8" stroke-linecap="round" fill="none"/>
      <path d="M11 30 L37 30 L34 46 L14 46 Z" fill="#9333EA"/>
      <rect x="17" y="30" width="14" height="16" fill="#F8FAFC"/>
      <circle cx="24" cy="34" r="1.5" fill="#0F172A"/>
      <circle cx="24" cy="40" r="1.5" fill="#0F172A"/>
    </svg>`
  }
};

const HAT_DEFS = {
  none: { id: 'none', name: 'Şapkasız / Sade', requiredLevel: 1, icon: '🙂' },
  party_hat: { id: 'party_hat', name: 'Parti Külahı', requiredLevel: 1, icon: '🎉' },
  crown: { id: 'crown', name: 'Kraliyet Tacı', requiredLevel: 1, icon: '👑' },
  headphones: { id: 'headphones', name: 'DJ Kulaklığı', requiredLevel: 1, icon: '🎧' },
  flower_crown: { id: 'flower_crown', name: 'Çiçek Tacı', requiredLevel: 1, icon: '🌸' },
  pirate: { id: 'pirate', name: 'Korsan Kaptanı', requiredLevel: 2, icon: '🏴‍☠️' },
  wizard: { id: 'wizard', name: 'Büyücü Külahı', requiredLevel: 2, icon: '🧙' },
  cat_ears: { id: 'cat_ears', name: 'Kedi Kulakları', requiredLevel: 2, icon: '🐱' },
  visor: { id: 'visor', name: 'Siber Vizör', requiredLevel: 3, icon: '🕶️' },
  detective: { id: 'detective', name: 'Dedektif Fötrü', requiredLevel: 3, icon: '🕵️' },
  viking: { id: 'viking', name: 'Viking Miğferi', requiredLevel: 4, icon: '⚔️' },
  halo: { id: 'halo', name: 'Melek Halesi', requiredLevel: 4, icon: '😇' }
};

const COLOR_PRESETS = [
  { id: 'blue', hex: '#3B82F6', name: 'Kobalt Mavi' },
  { id: 'red', hex: '#EF4444', name: 'Alev Kırmızı' },
  { id: 'green', hex: '#10B981', name: 'Zümrüt Yeşil' },
  { id: 'amber', hex: '#F59E0B', name: 'Güneş Sarısı' },
  { id: 'purple', hex: '#8B5CF6', name: 'Mistik Mor' },
  { id: 'pink', hex: '#EC4899', name: 'Neon Pembe' },
  { id: 'cyan', hex: '#06B6D4', name: 'Siber Turkuaz' },
  { id: 'dark', hex: '#1E293B', name: 'Gece Grisi' }
];

const DICE_SKINS = {
  default: { id: 'default', name: 'Klasik Beyaz', requiredLevel: 1, color: '#FFFFFF', dotColor: '#1E293B', border: '#CBD5E1' },
  emerald: { id: 'emerald', name: 'Zümrüt Yeşil', requiredLevel: 3, color: '#10B981', dotColor: '#FFFFFF', border: '#059669' },
  ruby: { id: 'ruby', name: 'Alev Kırmızı', requiredLevel: 6, color: '#EF4444', dotColor: '#FEF08A', border: '#B91C1C' },
  neon: { id: 'neon', name: 'Siber Neon', requiredLevel: 10, color: '#06B6D4', dotColor: '#082F49', border: '#0E7490' },
  galaxy: { id: 'galaxy', name: 'Kozmik Galaksi', requiredLevel: 16, color: '#8B5CF6', dotColor: '#FDF4FF', border: '#6D28D9' },
  gold: { id: 'gold', name: 'Saf Altın Zar', requiredLevel: 22, color: '#FACC15', dotColor: '#78350F', border: '#CA8A04' }
};

const TITLES = [
  { id: 't1', title: 'Acemi Gezgin', title_en: 'Novice Explorer', title_de: 'Neuer Entdecker', title_es: 'Explorador Novato', title_fr: 'Explorateur Novice', level: 1 },
  { id: 't2', title: 'Şanslı Tilki', title_en: 'Lucky Fox', title_de: 'Glücksfuchs', title_es: 'Zorro Afortunado', title_fr: 'Renard Chanceux', level: 3 },
  { id: 't3', title: 'Zar Cambazı', title_en: 'Dice Acrobat', title_de: 'Würfel-Akrobat', title_es: 'Acróbata del Dado', title_fr: 'Acrobate du Dé', level: 6 },
  { id: 't4', title: 'Tuzak Bükücü', title_en: 'Trap Bender', title_de: 'Fallenmeister', title_es: 'Domador de Trampas', title_fr: 'Maître des Pièges', level: 10 },
  { id: 't5', title: 'Yıldız Avcısı', title_en: 'Star Hunter', title_de: 'Sternenjäger', title_es: 'Cazador de Estrellas', title_fr: 'Chasseur d\'Étoiles', level: 15 },
  { id: 't6', title: 'Parti Efsanesi', title_en: 'Party Legend', title_de: 'Party-Legende', title_es: 'Leyenda de la Fiesta', title_fr: 'Légende de la Fête', level: 22 }
];

const EMOTE_DEFS = {
  laugh: { id: 'laugh', char: '😂', name: 'Kahkaha', level: 1 },
  cool: { id: 'cool', char: '😎', name: 'Havalı', level: 1 },
  shock: { id: 'shock', char: '😱', name: 'Şok', level: 1 },
  party: { id: 'party', char: '🥳', name: 'Parti', level: 2 },
  fire: { id: 'fire', char: '🔥', name: 'Alev', level: 3 },
  skull: { id: 'skull', char: '💀', name: 'GG', level: 5 },
  rocket: { id: 'rocket', char: '🚀', name: 'Roket', level: 8 },
  crown: { id: 'crown', char: '👑', name: 'Kral', level: 12 }
};

class ProgressionManager {
  constructor() {
    this.storageKey = 'partycraft_player_profile';
    this.profile = this.loadProfile();
  }

  loadProfile() {
    try {
      const data = localStorage.getItem(this.storageKey);
      if (data) {
        const parsed = JSON.parse(data);
        if (!parsed.hat) parsed.hat = 'none';
        if (!parsed.color) parsed.color = '#3B82F6';
        return parsed;
      }
    } catch (e) {
      console.warn('Storage read error:', e);
    }

    // Default Profile
    const defaultProfile = {
      sessionId: 'sess_' + Math.random().toString(36).substr(2, 9),
      name: 'Oyuncu_' + Math.floor(Math.random() * 899 + 100),
      level: 1,
      xp: 0,
      avatar: 'builder',
      hat: 'none',
      color: '#3B82F6',
      diceSkin: 'default',
      title: 'Acemi Gezgin',
      stats: {
        gamesPlayed: 0,
        gamesWon: 0,
        starsCollected: 0,
        coinsEarned: 0,
        minigamesWon: 0
      }
    };
    this.saveProfile(defaultProfile);
    return defaultProfile;
  }

  saveProfile(profile = this.profile) {
    try {
      localStorage.setItem(this.storageKey, JSON.stringify(profile));
    } catch (e) {
      console.warn('Storage save error:', e);
    }
  }

  getXpRequired(level = this.profile.level) {
    return level * 140;
  }

  addXp(amount) {
    this.profile.xp += amount;
    let leveledUp = false;
    let oldLevel = this.profile.level;

    while (this.profile.xp >= this.getXpRequired(this.profile.level)) {
      this.profile.xp -= this.getXpRequired(this.profile.level);
      this.profile.level++;
      leveledUp = true;
    }

    this.saveProfile();
    return {
      leveledUp,
      oldLevel,
      newLevel: this.profile.level,
      currentXp: this.profile.xp,
      neededXp: this.getXpRequired(this.profile.level)
    };
  }

  setAvatar(avatarId) {
    const def = AVATAR_DEFS[avatarId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.avatar = avatarId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setHat(hatId) {
    const def = HAT_DEFS[hatId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.hat = hatId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setColor(hexColor) {
    if (hexColor && /^#[0-9A-Fa-f]{6}$/.test(hexColor)) {
      this.profile.color = hexColor;
      this.saveProfile();
      return true;
    }
    return false;
  }

  getUnlockedHats() {
    return Object.values(HAT_DEFS).filter(h => this.profile.level >= h.requiredLevel);
  }

  setDiceSkin(skinId) {
    const def = DICE_SKINS[skinId];
    if (def && this.profile.level >= def.requiredLevel) {
      this.profile.diceSkin = skinId;
      this.saveProfile();
      return true;
    }
    return false;
  }

  setTitle(titleStr) {
    this.profile.title = titleStr;
    this.saveProfile();
  }

  setName(newName) {
    if (newName && newName.trim().length > 0) {
      this.profile.name = newName.trim().substr(0, 16);
      this.saveProfile();
    }
  }

  getUnlockedAvatars() {
    return Object.values(AVATAR_DEFS).filter(a => this.profile.level >= a.requiredLevel);
  }

  getUnlockedDiceSkins() {
    return Object.values(DICE_SKINS).filter(d => this.profile.level >= d.requiredLevel);
  }

  getUnlockedTitles() {
    return TITLES.filter(t => this.profile.level >= t.level);
  }

  getDisplayTitle(titleStr) {
    const tStr = titleStr || this.profile.title || 'Acemi Gezgin';
    const lang = (window.i18n && window.i18n.getLang()) ? window.i18n.getLang() : 'tr';
    const match = TITLES.find(t => t.title === tStr || t.title_en === tStr || t.title_de === tStr || t.title_es === tStr || t.title_fr === tStr);
    if (match) {
      if (lang === 'en') return match.title_en;
      if (lang === 'de') return match.title_de;
      if (lang === 'es') return match.title_es;
      if (lang === 'fr') return match.title_fr;
      return match.title;
    }
    return tStr;
  }

  getUnlockedEmotes() {
    return Object.values(EMOTE_DEFS).filter(e => this.profile.level >= e.level);
  }
}

const PlayerProgression = new ProgressionManager();
