import { useEffect, useMemo, useRef, useState, type CSSProperties, type ReactNode } from "react";

/* =====================================================================
   КИНОМЕТР — сайт оценок фильмов (весь фронтенд в одном файле)
   Лендинг: последние добавленные фильмы, поиск, жанры, сортировки.
   Админка (#/admin): CRUD фильмов, импорт JSON/CSV, администраторы.
   Данные: MySQL через api.php (AwardSpace) либо localStorage (демо).
   ===================================================================== */

/* ----------------------------- типы ----------------------------- */

type Movie = {
  id: number | string;
  title: string;
  originalTitle: string;
  year: number;
  country: string;
  director: string;
  duration: number;
  genres: string[];
  description: string;
  coverUrl: string;
  adminScore: number;
  imdbScore: number;
  createdAt: string;
};

type AdminT = { id: number; login: string; role: string; created_at?: string };
type LocalAdmin = AdminT & { pass: string };
type ToastT = { id: number; type: "ok" | "err" | "info"; text: string };
type Mode = "detect" | "server" | "local";
type Route = "home" | "admin";
type Auth = { login: string; role: string; token: string };

/* --------------------------- константы --------------------------- */

const API = "api.php";
const LS = {
  movies: "km_movies_v1",
  admins: "km_admins_v1",
  token: "km_token",
  login: "km_login",
  role: "km_role",
};
const POSTER = "https://image.tmdb.org/t/p/w500";

const SEED: Movie[] = [
  {
    id: "s1", title: "Дюна: Часть вторая", originalTitle: "Dune: Part Two", year: 2024,
    country: "США", director: "Дени Вильнёв", duration: 166,
    genres: ["фантастика", "приключения", "драма"],
    description: "Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один. Эпическая экранизация романа Фрэнка Герберта.",
    coverUrl: POSTER + "/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg", adminScore: 8.4, imdbScore: 8.5, createdAt: "2026-02-04T19:30:00",
  },
  {
    id: "s2", title: "Оппенгеймер", originalTitle: "Oppenheimer", year: 2023,
    country: "США, Великобритания", director: "Кристофер Нолан", duration: 180,
    genres: ["биография", "драма", "триллер"],
    description: "История «отца атомной бомбы» Роберта Оппенгеймера: проект «Манхэттен», триумф науки и моральная пропасть, разверзшаяся под ногами её творцов.",
    coverUrl: POSTER + "/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg", adminScore: 8.6, imdbScore: 8.3, createdAt: "2026-01-21T18:00:00",
  },
  {
    id: "s3", title: "Тёмный рыцарь", originalTitle: "The Dark Knight", year: 2008,
    country: "США", director: "Кристофер Нолан", duration: 152,
    genres: ["боевик", "триллер", "криминал"],
    description: "Бэтмен сталкивается с Джокером — хаосом во плоти, который хочет доказать, что любой идеал рушится от одного толчка. Хит Леджер в роли всей жизни.",
    coverUrl: POSTER + "/qJ2tW6WMUDux911r6m7haRef0WH.jpg", adminScore: 9.1, imdbScore: 9.0, createdAt: "2026-01-13T20:15:00",
  },
  {
    id: "s4", title: "Паразиты", originalTitle: "Gisaengchung", year: 2019,
    country: "Южная Корея", director: "Пон Джун-хо", duration: 132,
    genres: ["триллер", "драма", "комедия"],
    description: "Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, которая оборачивается кровавой трагикомедией. «Оскар» за лучший фильм.",
    coverUrl: POSTER + "/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg", adminScore: 8.9, imdbScore: 8.5, createdAt: "2026-01-06T17:45:00",
  },
  {
    id: "s5", title: "Ла-Ла Ленд", originalTitle: "La La Land", year: 2016,
    country: "США", director: "Дэмьен Шазелл", duration: 128,
    genres: ["мюзикл", "мелодрама", "драма"],
    description: "Джазовый пианист и начинающая актриса влюбляются в Лос-Анджелесе, но амбиции разводят их мечты по разным берегам. Шесть «Оскаров».",
    coverUrl: POSTER + "/uDO8zWDhfWwoFdKS4fzkUJt0Rf0.jpg", adminScore: 7.8, imdbScore: 8.0, createdAt: "2025-12-30T21:00:00",
  },
  {
    id: "s6", title: "Интерстеллар", originalTitle: "Interstellar", year: 2014,
    country: "США, Великобритания", director: "Кристофер Нолан", duration: 169,
    genres: ["фантастика", "драма", "приключения"],
    description: "Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома. Любовь и гравитация — единственное, что способно пересечь измерения.",
    coverUrl: POSTER + "/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", adminScore: 9.2, imdbScore: 8.7, createdAt: "2025-12-23T19:20:00",
  },
  {
    id: "s7", title: "Начало", originalTitle: "Inception", year: 2010,
    country: "США, Великобритания", director: "Кристофер Нолан", duration: 148,
    genres: ["фантастика", "боевик", "триллер"],
    description: "Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.",
    coverUrl: POSTER + "/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg", adminScore: 9.0, imdbScore: 8.8, createdAt: "2025-12-16T18:40:00",
  },
  {
    id: "s8", title: "Зелёная миля", originalTitle: "The Green Mile", year: 1999,
    country: "США", director: "Фрэнк Дарабонт", duration: 189,
    genres: ["драма", "фэнтези", "криминал"],
    description: "Надзиратель блока смертников знакомится с заключённым Джоном Коффи — великаном с необъяснимым даром и сердцем ребёнка. По роману Стивена Кинга.",
    coverUrl: POSTER + "/velWPhVMQeQKcxggNEU8YmIo52R.jpg", adminScore: 9.3, imdbScore: 8.6, createdAt: "2025-12-09T20:10:00",
  },
  {
    id: "s9", title: "Бойцовский клуб", originalTitle: "Fight Club", year: 1999,
    country: "США", director: "Дэвид Финчер", duration: 139,
    genres: ["триллер", "драма"],
    description: "Бессонный клерк и харизматичный торговец мылом основывают подпольный клуб, правила которого лучше не нарушать. Первое правило клуба вы знаете.",
    coverUrl: POSTER + "/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg", adminScore: 8.7, imdbScore: 8.8, createdAt: "2025-12-02T19:00:00",
  },
  {
    id: "s10", title: "Матрица", originalTitle: "The Matrix", year: 1999,
    country: "США", director: "Лана и Лилли Вачовски", duration: 136,
    genres: ["фантастика", "боевик"],
    description: "Хакер Нео узнаёт, что привычный мир — симуляция, созданная машинами. Синяя или красная таблетка? Выбора на самом деле нет.",
    coverUrl: POSTER + "/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg", adminScore: 8.8, imdbScore: 8.7, createdAt: "2025-11-25T21:30:00",
  },
  {
    id: "s11", title: "Крёстный отец", originalTitle: "The Godfather", year: 1972,
    country: "США", director: "Фрэнсис Форд Коппола", duration: 175,
    genres: ["драма", "криминал"],
    description: "Стареющий дон Корлеоне передаёт власть младшему сыну Майклу, который не хотел иметь с семьёй ничего общего. Предложение, от которого невозможно отказаться.",
    coverUrl: POSTER + "/3bhkrj58Vtu7enYsRolD1fZdja1.jpg", adminScore: 9.6, imdbScore: 9.2, createdAt: "2025-11-18T18:25:00",
  },
  {
    id: "s12", title: "Побег из Шоушенка", originalTitle: "The Shawshank Redemption", year: 1994,
    country: "США", director: "Фрэнк Дарабонт", duration: 142,
    genres: ["драма"],
    description: "Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных. Лучший фильм по версии зрителей IMDb.",
    coverUrl: POSTER + "/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg", adminScore: 9.5, imdbScore: 9.1, createdAt: "2025-11-12T19:45:00",
  },
];

/* ---------------------------- утилиты ---------------------------- */

const cn = (...a: (string | false | undefined)[]) => a.filter(Boolean).join(" ");
const uid = () =>
  typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : Math.random().toString(36).slice(2) + Date.now().toString(36);
const clampScore = (n: number) => Math.max(0, Math.min(10, Math.round(n * 10) / 10));
const fmtDate = (iso: string) => {
  const d = new Date(iso);
  return isNaN(+d) ? "—" : d.toLocaleDateString("ru-RU", { day: "2-digit", month: "2-digit", year: "numeric" });
};
const scoreColor = (v: number) => (v >= 8 ? "#86ba8f" : v >= 6.5 ? "#e2a64b" : v >= 5 ? "#e08a4a" : "#d9503f");
const scoreWord = (v: number) =>
  v >= 8.5 ? "Шедевр" : v >= 7.5 ? "Отлично" : v >= 6 ? "Хорошо" : v >= 4.5 ? "Средне" : "Слабо";
const fmtDuration = (min: number) => (min ? `${Math.floor(min / 60)} ч ${min % 60} мин` : "");

function downloadFile(name: string, content: string, type = "application/json") {
  const blob = new Blob([content], { type: type + ";charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = name;
  a.click();
  setTimeout(() => URL.revokeObjectURL(url), 2000);
}

/* ------------------------- слой данных ------------------------- */

async function serverCall<T = any>(action: string, payload?: unknown, token?: string): Promise<T> {
  const res = await fetch(`${API}?action=${action}`, {
    method: payload === undefined ? "GET" : "POST",
    headers: {
      ...(payload !== undefined ? { "Content-Type": "application/json" } : {}),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: payload === undefined ? undefined : JSON.stringify(payload),
  });
  const data = await res.json().catch(() => null);
  if (!res.ok || !data || data.ok === false)
    throw new Error((data && data.error) || `Сервер ответил ошибкой (${res.status})`);
  return data as T;
}

function loadLocalMovies(): Movie[] {
  try {
    const raw = localStorage.getItem(LS.movies);
    if (raw) {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length) return arr;
    }
  } catch { /* повреждённые данные — пересеиваем */ }
  localStorage.setItem(LS.movies, JSON.stringify(SEED));
  return [...SEED];
}
function loadLocalAdmins(): LocalAdmin[] {
  try {
    const raw = localStorage.getItem(LS.admins);
    if (raw) {
      const arr = JSON.parse(raw);
      if (Array.isArray(arr) && arr.length) return arr;
    }
  } catch { /* ignore */ }
  const seed: LocalAdmin[] = [{ id: 1, login: "admin", pass: "kinometr", role: "root", created_at: new Date().toISOString() }];
  localStorage.setItem(LS.admins, JSON.stringify(seed));
  return seed;
}
function saveLocalMovies(list: Movie[]) {
  localStorage.setItem(LS.movies, JSON.stringify(list));
}

/* ------------------- нормализация и импорт ------------------- */

function normalizeMovie(p: any, i = 0): Movie {
  let genres: string[] = [];
  if (Array.isArray(p?.genres)) genres = p.genres.map((g: unknown) => String(g).trim()).filter(Boolean);
  else if (typeof p?.genres === "string")
    genres = p.genres.split(/[|,]/).map((g: string) => g.trim()).filter(Boolean);
  return {
    id: p?.id ?? uid(),
    title: String(p?.title ?? "").trim(),
    originalTitle: String(p?.originalTitle ?? "").trim(),
    year: parseInt(p?.year, 10) || 0,
    country: String(p?.country ?? "").trim(),
    director: String(p?.director ?? "").trim(),
    duration: parseInt(p?.duration, 10) || 0,
    genres,
    description: String(p?.description ?? "").trim(),
    coverUrl: String(p?.coverUrl ?? p?.cover ?? "").trim(),
    adminScore: clampScore(parseFloat(p?.adminScore) || 0),
    imdbScore: clampScore(parseFloat(p?.imdbScore) || 0),
    createdAt: p?.createdAt ? String(p.createdAt) : new Date(Date.now() - i * 1000).toISOString(),
  };
}

const KEYMAP: Record<string, string> = {
  название: "title", title: "title",
  оригинал: "originalTitle", оригинальноеназвание: "originalTitle", originaltitle: "originalTitle",
  год: "year", year: "year",
  страна: "country", country: "country",
  режиссер: "director", режиссёр: "director", director: "director",
  хронометраж: "duration", длительность: "duration", duration: "duration",
  жанры: "genres", жанр: "genres", genres: "genres",
  описание: "description", description: "description",
  обложка: "coverUrl", постер: "coverUrl", coverurl: "coverUrl", cover: "coverUrl",
  оценкаадмина: "adminScore", админ: "adminScore", админоценка: "adminScore", adminscore: "adminScore",
  imdb: "imdbScore", imdbscore: "imdbScore", imdbрейтинг: "imdbScore",
};

function mapImportRow(raw: Record<string, any>): any {
  const out: Record<string, any> = {};
  Object.keys(raw).forEach((k) => {
    const compact = k.trim().toLowerCase().replace(/[\s_\-]+/g, "");
    const mapped = KEYMAP[compact] ?? k.trim();
    out[mapped] = raw[k];
  });
  return out;
}

function parseCSV(text: string): any[] {
  const lines = text.replace(/^\uFEFF/, "").split(/\r?\n/).filter((l) => l.trim() !== "");
  if (lines.length < 2) return [];
  const delim = (lines[0].match(/;/g)?.length ?? 0) >= (lines[0].match(/,/g)?.length ?? 1) ? ";" : ",";
  const headers = lines[0].split(delim).map((h) => h.trim().replace(/^"|"$/g, ""));
  return lines.slice(1).map((line) => {
    const cells = line.split(delim).map((c) => c.trim().replace(/^"|"$/g, ""));
    const row: Record<string, any> = {};
    headers.forEach((h, i) => (row[h] = cells[i] ?? ""));
    return mapImportRow(row);
  });
}

const TEMPLATE_JSON = JSON.stringify(
  [
    {
      title: "Название фильма", originalTitle: "Original Title", year: 2024, country: "США",
      director: "Режиссёр", duration: 120, genres: ["драма", "триллер"],
      description: "Краткое описание фильма…", coverUrl: "https://image.tmdb.org/t/p/w500/xxxxx.jpg",
      adminScore: 8.2, imdbScore: 7.9,
    },
  ],
  null,
  2
);
const TEMPLATE_CSV = [
  "title;originalTitle;year;country;director;duration;genres;description;coverUrl;adminScore;imdbScore",
  "Название фильма;Original Title;2024;США;Режиссёр;120;драма|триллер;Краткое описание…;https://image.tmdb.org/t/p/w500/xxxxx.jpg;8.2;7.9",
].join("\n");

/* ------------------------------ иконки ------------------------------ */

const PATHS: Record<string, string> = {
  star: "M12 2.6l2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.4l-5.8 3.1 1.1-6.5L2.6 9.4l6.5-.9L12 2.6z",
  search: "M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z",
  plus: "M12 5v14M5 12h14",
  edit: "M4 20l4.5-1L20 7.5 16.5 4 5 15.5 4 20zM14 6.5L17.5 10",
  trash: "M4 7h16M9 7V5h6v2M6.5 7l1 13h9l1-13M10 11v6M14 11v6",
  upload: "M12 16V4m0 0L7 9m5-5l5 5M4 20h16",
  download: "M12 4v12m0 0l5-5m-5 5l-5-5M4 20h16",
  users: "M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM20 19a4 4 0 0 0-3-3.87M15.5 5.3a3 3 0 0 1 0 5.4",
  film: "M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13zM8 4v16M16 4v16M4 9h4M4 15h4M16 9h4M16 15h4",
  x: "M6 6l12 12M18 6L6 18",
  logout: "M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10",
  key: "M15.5 8.5a4.5 4.5 0 1 1-6.4 6.4 4.5 4.5 0 0 1 6.4-6.4zM12.3 11.7L4 20M7 17l2 2M10 14l2 2",
  check: "M5 13l4 4L19 7",
  alert: "M12 3.5l9.5 16.5h-19L12 3.5zM12 10v4.5M12 17.8v.4",
  clock: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3.2 2",
  globe: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM3 12h18M12 3c2.5 2.6 4 5.6 4 9s-1.5 6.4-4 9c-2.5-2.6-4-5.6-4-9s1.5-6.4 4-9z",
  clap: "M3 8.5h18V19a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V8.5zM3 8.5l2-4.5h16l-2 4.5M8.7 4L7 8.5M13.7 4L12 8.5M18.7 4L17 8.5",
  chev: "M6 9l6 6 6-6",
  db: "M12 8c4.4 0 8-1.3 8-3s-3.6-3-8-3-8 1.3-8 3 3.6 3 8 3zM4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3",
  arrow: "M4 12h16M13 5l7 7-7 7",
  lock: "M6.5 11V8a5.5 5.5 0 1 1 11 0v3M5 11h14v9H5zM12 14.5v2.5",
  eye: "M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12zM12 14.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z",
};

function Ic({ n, className = "w-4 h-4", filled = false }: { n: string; className?: string; filled?: boolean }) {
  return (
    <svg viewBox="0 0 24 24" className={cn("shrink-0", className)} fill={filled ? "currentColor" : "none"}
      stroke="currentColor" strokeWidth={filled ? 0 : 1.8} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d={PATHS[n]} />
    </svg>
  );
}

function LogoMark({ className = "w-9 h-9", animate = false }: { className?: string; animate?: boolean }) {
  return (
    <svg viewBox="0 0 32 32" className={className} fill="none" aria-hidden="true">
      <path d="M4.5 22a11.5 11.5 0 0 1 23 0" stroke="#e2a64b" strokeWidth="2.4" strokeLinecap="round" />
      <path d="M7.2 14.8l1.9 1.1M24.8 14.8l-1.9 1.1M16 9.8v2.3" stroke="#e2a64b" strokeWidth="1.6" strokeLinecap="round" opacity="0.7" />
      <line x1="16" y1="22" x2="22.3" y2="13.2" stroke="#f1e8d9" strokeWidth="2.4" strokeLinecap="round" className={animate ? "logo-needle" : ""} />
      <circle cx="16" cy="22" r="2.3" fill="#e2a64b" />
    </svg>
  );
}

/* --------------------------- мелкая атомарика --------------------------- */

function Reveal({ children, delay = 0, className }: { children: ReactNode; delay?: number; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [seen, setSeen] = useState(false);
  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      (es) => es.forEach((e) => { if (e.isIntersecting) { setSeen(true); io.disconnect(); } }),
      { threshold: 0.08, rootMargin: "0px 0px -30px 0px" }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);
  return (
    <div ref={ref} className={className}
      style={{ opacity: seen ? 1 : 0, transform: seen ? "none" : "translateY(24px)",
        transition: `opacity .7s ease ${delay}ms, transform .7s cubic-bezier(.22,1,.36,1) ${delay}ms` }}>
      {children}
    </div>
  );
}

function ScoreDial({ value, size = 92, label }: { value: number; size?: number; label?: string }) {
  const stroke = Math.max(5, Math.round(size * 0.075));
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const [prog, setProg] = useState(0);
  useEffect(() => {
    const t = setTimeout(() => setProg(Math.max(0, Math.min(10, value))), 120);
    return () => clearTimeout(t);
  }, [value]);
  const col = scoreColor(value);
  return (
    <div className="relative inline-flex items-center justify-center" style={{ width: size, height: size }} title={`Оценка: ${value.toFixed(1)} из 10`}>
      <svg width={size} height={size} className="-rotate-90">
        <circle cx={size / 2} cy={size / 2} r={r} stroke="rgba(241,231,216,0.12)" strokeWidth={stroke} fill="none" />
        <circle cx={size / 2} cy={size / 2} r={r} stroke={col} strokeWidth={stroke} fill="none" strokeLinecap="round"
          strokeDasharray={c} strokeDashoffset={c * (1 - prog / 10)}
          style={{ transition: "stroke-dashoffset 1.1s cubic-bezier(.22,1,.36,1)" }} />
      </svg>
      <div className="absolute inset-0 flex flex-col items-center justify-center">
        <span className="font-display font-bold leading-none" style={{ fontSize: size * 0.24, color: col }}>
          {value.toFixed(1)}
        </span>
        {label && <span className="font-mono uppercase mt-1 text-muted" style={{ fontSize: Math.max(8, size * 0.085), letterSpacing: "0.16em" }}>{label}</span>}
      </div>
    </div>
  );
}

function ImdbBadge({ value, big = false }: { value: number; big?: boolean }) {
  return (
    <span className={cn("inline-flex items-center gap-2 border border-line bg-coal", big ? "px-4 py-3 rounded-lg" : "px-2 py-1 rounded-md")}>
      <span className="font-display font-bold text-imdb leading-none" style={{ fontSize: big ? 17 : 11 }}>IMDb</span>
      <span className={cn("flex items-center gap-1 font-mono text-paper", big ? "text-xl" : "text-xs")}>
        <Ic n="star" filled className={big ? "w-4 h-4 text-imdb" : "w-3 h-3 text-imdb"} />
        {value.toFixed(1)}
      </span>
    </span>
  );
}

function PosterImg({ src, title, className }: { src: string; title: string; className?: string }) {
  const [err, setErr] = useState(false);
  useEffect(() => setErr(false), [src]);
  if (!src || err)
    return (
      <div className={cn("flex flex-col items-center justify-center gap-2 bg-panel2 text-muted relative overflow-hidden", className)}>
        <div className="absolute inset-0 opacity-40" style={{ background: "repeating-linear-gradient(135deg, transparent 0 12px, rgba(226,166,75,0.06) 12px 24px)" }} />
        <Ic n="film" className="w-8 h-8 opacity-50 relative" />
        <span className="font-display text-3xl text-gold/50 leading-none relative">{(title.trim().charAt(0) || "К").toUpperCase()}</span>
      </div>
    );
  return <img src={src} alt={title} loading="lazy" onError={() => setErr(true)} className={cn("object-cover", className)} />;
}

/* ------------------------------ шапка ------------------------------ */

function ModePill({ mode }: { mode: Mode }) {
  if (mode === "detect")
    return (
      <span className="hidden md:flex items-center gap-1.5 font-mono text-[11px] text-muted">
        <span className="w-1.5 h-1.5 rounded-full bg-muted animate-pulse" /> подключение…
      </span>
    );
  const server = mode === "server";
  return (
    <span title={server ? "Данные читаются из MySQL (AwardSpace) через api.php" : "Сервер БД недоступен — включён демо-режим: данные хранятся в браузере (localStorage)"}
      className={cn("hidden md:flex items-center gap-1.5 border rounded-full px-3 py-1 font-mono text-[11px]",
        server ? "border-moss/40 text-moss" : "border-gold/40 text-gold")}>
      <span className={cn("w-1.5 h-1.5 rounded-full soft-pulse", server ? "bg-moss" : "bg-gold")} />
      {server ? "MySQL подключён" : "демо-режим"}
    </span>
  );
}

function Header({ mode, authed, onNav }: { mode: Mode; authed: Auth | null; onNav: (r: Route) => void }) {
  return (
    <header className="sticky top-0 z-40 border-b border-line/80 bg-ink/90 backdrop-blur-md">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
        <button onClick={() => onNav("home")} className="flex items-center gap-3 group cursor-pointer">
          <LogoMark className="w-9 h-9 transition-transform duration-500 group-hover:rotate-12" />
          <span className="font-display font-bold text-lg sm:text-xl tracking-tight text-paper">
            КИНО<span className="text-gold">МЕТР</span>
          </span>
        </button>
        <nav className="flex items-center gap-2 sm:gap-3">
          <ModePill mode={mode} />
          <button onClick={() => onNav("home")} className="hidden sm:inline-flex font-semibold text-sm text-muted hover:text-gold transition-colors cursor-pointer px-2 py-1">
            Афиша
          </button>
          <button onClick={() => onNav("admin")} className="btn btn-gold btn-sm cursor-pointer">
            <Ic n={authed ? "clap" : "lock"} className="w-3.5 h-3.5" />
            {authed ? "Админ-панель" : "Вход для админа"}
          </button>
        </nav>
      </div>
    </header>
  );
}

/* --------------------------- бегущая строка --------------------------- */

function Ticker({ items }: { items: Movie[] }) {
  if (!items.length) return null;
  const row = (key: string) => (
    <div key={key} className="flex items-center shrink-0">
      {items.map((m) => (
        <span key={key + m.id} className="flex items-center gap-2 px-5 font-mono text-xs tracking-wide text-muted whitespace-nowrap">
          <Ic n="star" filled className="w-3 h-3 text-gold" />
          <span className="text-paper font-medium">{m.adminScore.toFixed(1)}</span>
          {m.title}
          <span className="opacity-60">({m.year || "—"})</span>
          <span className="text-line ml-5">/</span>
        </span>
      ))}
    </div>
  );
  return (
    <div className="ticker overflow-hidden border-b border-line bg-coal/70">
      <div className="ticker-track flex w-max py-2.5">{row("a")}{row("b")}</div>
    </div>
  );
}

/* ------------------------- главное «измерение» ------------------------- */

function Featured({ movie, onOpen }: { movie: Movie; onOpen: (m: Movie) => void }) {
  return (
    <section className="relative overflow-hidden">
      <div className="absolute inset-0 pointer-events-none"
        style={{ background: "radial-gradient(60% 55% at 18% 20%, rgba(226,166,75,0.13), transparent 65%), radial-gradient(45% 45% at 90% 80%, rgba(217,80,63,0.08), transparent 60%)" }} />
      <div className="absolute -bottom-6 left-0 right-0 outline-word font-display font-extrabold text-[16vw] leading-none whitespace-nowrap pointer-events-none select-none" aria-hidden="true">
        КИНОМЕТР
      </div>

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 pt-10 sm:pt-16 pb-16 grid lg:grid-cols-[1fr_330px] gap-10 lg:gap-14 items-center">
        <Reveal>
          <div>
            <div className="flex items-center gap-3 font-mono text-[11px] tracking-[0.24em] uppercase text-gold">
              <span className="h-px w-10 bg-gold/70" />
              Свежее измерение · {fmtDate(movie.createdAt)}
            </div>
            <h1 className="font-display font-extrabold text-[clamp(1.8rem,4.6vw,3.4rem)] leading-[1.06] mt-4 text-paper">
              {movie.title}
            </h1>
            <p className="font-mono text-sm text-muted mt-3">
              {movie.originalTitle}{movie.year ? ` · ${movie.year}` : ""}{movie.country ? ` · ${movie.country}` : ""}
            </p>

            <div className="flex flex-wrap gap-2 mt-4">
              {movie.director && (
                <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                  <Ic n="clap" className="w-3.5 h-3.5 text-gold" /> {movie.director}
                </span>
              )}
              {movie.duration > 0 && (
                <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                  <Ic n="clock" className="w-3.5 h-3.5 text-gold" /> {fmtDuration(movie.duration)}
                </span>
              )}
              {movie.genres.slice(0, 3).map((g) => (
                <span key={g} className="border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">{g}</span>
              ))}
            </div>

            <p className="text-paper/75 max-w-xl mt-5 leading-relaxed">{movie.description}</p>

            <div className="flex flex-wrap items-center gap-6 mt-7">
              <ScoreDial value={movie.adminScore} size={106} label="админ" />
              <div className="flex flex-col gap-2 items-start">
                <ImdbBadge value={movie.imdbScore} big />
                <span className="font-mono text-[11px] uppercase tracking-[0.18em]" style={{ color: scoreColor(movie.adminScore) }}>
                  вердикт: {scoreWord(movie.adminScore)}
                </span>
              </div>
            </div>

            <div className="flex flex-wrap gap-3 mt-8">
              <button className="btn btn-gold cursor-pointer" onClick={() => onOpen(movie)}>
                Открыть карточку <Ic n="arrow" className="w-4 h-4" />
              </button>
              <button className="btn btn-ghost cursor-pointer"
                onClick={() => document.getElementById("katalog")?.scrollIntoView({ behavior: "smooth" })}>
                Весь каталог <Ic n="chev" className="w-4 h-4" />
              </button>
            </div>
          </div>
        </Reveal>

        <Reveal delay={160}>
          <div className="relative mx-auto w-[230px] sm:w-[290px] lg:w-[320px]">
            <div className="absolute -inset-8 rounded-full bg-gold/10 blur-3xl soft-pulse" aria-hidden="true" />
            <div className="absolute inset-0 translate-x-3.5 translate-y-3.5 border border-gold/40" aria-hidden="true" />
            <div className="relative border border-line bg-panel shadow-[0_40px_80px_-30px_rgba(0,0,0,0.8)] rotate-[1.6deg] hover:rotate-0 transition-transform duration-500">
              <div className="flex justify-between px-2 pt-2" aria-hidden="true">
                {Array.from({ length: 8 }).map((_, i) => (
                  <span key={i} className="w-2 h-2 rounded-[2px] bg-ink border border-line" />
                ))}
              </div>
              <PosterImg src={movie.coverUrl} title={movie.title} className="w-full aspect-[2/3] mx-2 mt-2" />
              <div className="flex items-center justify-between px-3 py-2.5">
                <span className="font-mono text-[10px] uppercase tracking-[0.18em] text-muted">постер #{movie.id}</span>
                <span className="flex items-center gap-1 font-display font-bold text-sm" style={{ color: scoreColor(movie.adminScore) }}>
                  {movie.adminScore.toFixed(1)} <Ic n="star" filled className="w-3 h-3" />
                </span>
              </div>
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  );
}

/* ------------------------------ статистика ------------------------------ */

function StatsStrip({ movies }: { movies: Movie[] }) {
  const genres = new Set(movies.flatMap((m) => m.genres)).size;
  const avg = (fn: (m: Movie) => number) => (movies.length ? movies.reduce((s, m) => s + fn(m), 0) / movies.length : 0);
  const items = [
    { v: String(movies.length), l: "фильмов в каталоге", icon: "film" },
    { v: String(genres), l: "жанров измерено", icon: "clap" },
    { v: avg((m) => m.adminScore).toFixed(1), l: "средняя оценка админа", icon: "star" },
    { v: avg((m) => m.imdbScore).toFixed(1), l: "средний рейтинг IMDb", icon: "globe" },
  ];
  return (
    <div className="border-y border-line bg-coal/50">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 grid grid-cols-2 md:grid-cols-4">
        {items.map((it, i) => (
          <Reveal key={it.l} delay={i * 80}
            className={cn("py-6 px-2 md:px-6", i > 0 && "border-l border-line/70", i >= 2 && "border-t md:border-t-0", i === 2 && "border-l-0 md:border-l")}>
            <div className="flex items-center gap-3">
              <Ic n={it.icon} className="w-5 h-5 text-gold/70" />
              <div className="font-display font-bold text-3xl sm:text-4xl text-paper leading-none">{it.v}</div>
            </div>
            <div className="font-mono text-[10.5px] uppercase tracking-[0.16em] text-muted mt-2">{it.l}</div>
          </Reveal>
        ))}
      </div>
    </div>
  );
}

/* ------------------------------ каталог ------------------------------ */

type SortKey = "new" | "admin" | "imdb" | "year";

function MovieCard({ m, onOpen }: { m: Movie; onOpen: (m: Movie) => void }) {
  return (
    <button onClick={() => onOpen(m)} className="group text-left w-full cursor-pointer">
      <div className="relative overflow-hidden border border-line bg-panel transition-all duration-300 group-hover:border-gold/60 group-hover:-translate-y-1.5 group-hover:shadow-[0_26px_50px_-26px_rgba(226,166,75,0.4)]">
        <PosterImg src={m.coverUrl} title={m.title} className="w-full aspect-[2/3] transition-transform duration-500 group-hover:scale-[1.045]" />
        <div className="absolute inset-0 bg-gradient-to-t from-ink/85 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300" />
        <div className="absolute top-2 left-2 flex items-center gap-1.5 bg-ink/85 border border-line px-2 py-1">
          <span className="font-mono text-[9px] text-muted uppercase tracking-wider">адм</span>
          <span className="font-display font-bold text-sm leading-none" style={{ color: scoreColor(m.adminScore) }}>
            {m.adminScore.toFixed(1)}
          </span>
        </div>
        <div className="absolute top-2 right-2 flex items-center gap-1 bg-ink/85 border border-line px-1.5 py-1">
          <Ic n="star" filled className="w-3 h-3 text-imdb" />
          <span className="font-mono text-[11px] text-paper leading-none">{m.imdbScore.toFixed(1)}</span>
        </div>
        <div className="absolute inset-x-0 bottom-0 p-3 translate-y-3 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition-all duration-300">
          <span className="inline-flex items-center gap-2 bg-gold text-ink font-mono text-[11px] font-bold uppercase tracking-wider px-3 py-2">
            <Ic n="eye" className="w-3.5 h-3.5" /> карточка
          </span>
        </div>
      </div>
      <div className="pt-3 px-0.5">
        <h3 className="font-bold text-[15px] leading-snug text-paper group-hover:text-gold transition-colors truncate">{m.title}</h3>
        <p className="font-mono text-[11px] text-muted mt-1 truncate">
          {m.year || "—"}{m.genres.length ? " · " + m.genres.slice(0, 2).join(", ") : ""}
        </p>
      </div>
    </button>
  );
}

function Catalog({ movies, onOpen }: { movies: Movie[]; onOpen: (m: Movie) => void }) {
  const [q, setQ] = useState("");
  const [genre, setGenre] = useState("Все");
  const [sort, setSort] = useState<SortKey>("new");

  const genres = useMemo(
    () => ["Все", ...Array.from(new Set(movies.flatMap((m) => m.genres))).sort((a, b) => a.localeCompare(b, "ru"))],
    [movies]
  );

  const list = useMemo(() => {
    const needle = q.trim().toLowerCase();
    let arr = movies.filter((m) => {
      const okQ =
        !needle ||
        m.title.toLowerCase().includes(needle) ||
        m.originalTitle.toLowerCase().includes(needle) ||
        m.director.toLowerCase().includes(needle);
      const okG = genre === "Все" || m.genres.includes(genre);
      return okQ && okG;
    });
    arr = [...arr];
    if (sort === "new") arr.sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt));
    if (sort === "admin") arr.sort((a, b) => b.adminScore - a.adminScore);
    if (sort === "imdb") arr.sort((a, b) => b.imdbScore - a.imdbScore);
    if (sort === "year") arr.sort((a, b) => b.year - a.year);
    return arr;
  }, [movies, q, genre, sort]);

  return (
    <section id="katalog" className="mx-auto max-w-7xl px-4 sm:px-6 py-14 scroll-mt-20">
      <Reveal>
        <div className="flex flex-wrap items-end justify-between gap-4">
          <div>
            <div className="flex items-center gap-3 font-mono text-[11px] tracking-[0.24em] uppercase text-gold">
              <span className="h-px w-10 bg-gold/70" /> последние добавленные
            </div>
            <h2 className="font-display font-bold text-3xl sm:text-4xl mt-3 text-paper">
              Каталог <span className="text-gold">измерений</span>
            </h2>
          </div>
          <span className="font-mono text-xs text-muted">
            показано <span className="text-paper font-bold">{list.length}</span> из {movies.length}
          </span>
        </div>
      </Reveal>

      <Reveal delay={100}>
        <div className="flex flex-col md:flex-row gap-3 mt-7">
          <div className="relative flex-1">
            <Ic n="search" className="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input className="field !pl-10" placeholder="Поиск: название, оригинал, режиссёр…" value={q} onChange={(e) => setQ(e.target.value)} />
          </div>
          <select className="field md:w-64" value={sort} onChange={(e) => setSort(e.target.value as SortKey)}>
            <option value="new">Сначала новые</option>
            <option value="admin">По оценке админа</option>
            <option value="imdb">По рейтингу IMDb</option>
            <option value="year">По году выхода</option>
          </select>
        </div>
        <div className="flex gap-2 mt-4 overflow-x-auto pb-2">
          {genres.map((g) => (
            <button key={g} onClick={() => setGenre(g)} className={cn("chip", genre === g && "chip-on")}>
              {g}
            </button>
          ))}
        </div>
      </Reveal>

      {list.length ? (
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-x-5 gap-y-8 mt-8">
          {list.map((m, i) => (
            <Reveal key={m.id} delay={(i % 5) * 60}>
              <MovieCard m={m} onOpen={onOpen} />
            </Reveal>
          ))}
        </div>
      ) : (
        <div className="mt-10 border border-dashed border-line py-16 flex flex-col items-center gap-4 text-center">
          <Ic n="film" className="w-10 h-10 text-muted" />
          <div className="font-display font-bold text-xl text-paper">Ничего не найдено</div>
          <p className="font-mono text-sm text-muted">Попробуйте другой запрос или сбросьте фильтры</p>
          <button className="btn btn-ghost btn-sm" onClick={() => { setQ(""); setGenre("Все"); }}>Сбросить</button>
        </div>
      )}
    </section>
  );
}

/* ------------------------------ модалка ------------------------------ */

function MovieModal({ m, onClose }: { m: Movie; onClose: () => void }) {
  useEffect(() => {
    const h = (e: KeyboardEvent) => e.key === "Escape" && onClose();
    window.addEventListener("keydown", h);
    document.body.style.overflow = "hidden";
    return () => { window.removeEventListener("keydown", h); document.body.style.overflow = ""; };
  }, [onClose]);

  const bars = [
    { label: "Оценка админа", value: m.adminScore, color: scoreColor(m.adminScore) },
    { label: "Рейтинг IMDb", value: m.imdbScore, color: "#f5c518" },
  ];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label={m.title}>
      <div className="absolute inset-0 bg-ink/85 backdrop-blur-sm" onClick={onClose} />
      <div className="modal-pop relative w-full max-w-3xl max-h-[92vh] overflow-y-auto border border-line bg-panel shadow-2xl grid sm:grid-cols-[250px_1fr]">
        <div className="relative hidden sm:block">
          <PosterImg src={m.coverUrl} title={m.title} className="w-full h-full min-h-[420px] object-cover" />
          <div className="absolute top-3 left-3 flex items-center gap-1.5 bg-ink/85 border border-line px-2 py-1">
            <Ic n="star" filled className="w-3 h-3" />
            <span className="font-display font-bold text-sm" style={{ color: scoreColor(m.adminScore) }}>{m.adminScore.toFixed(1)}</span>
          </div>
        </div>
        <div className="p-6 sm:p-8 relative">
          <button onClick={onClose} className="absolute top-4 right-4 w-9 h-9 flex items-center justify-center border border-line text-muted hover:text-flame hover:border-flame transition-colors cursor-pointer" aria-label="Закрыть">
            <Ic n="x" className="w-4 h-4" />
          </button>

          <div className="font-mono text-[10.5px] uppercase tracking-[0.2em] text-muted">
            карточка фильма · добавлен {fmtDate(m.createdAt)}
          </div>
          <h3 className="font-display font-bold text-2xl sm:text-3xl leading-tight text-paper mt-2 pr-8">{m.title}</h3>
          <p className="font-mono text-sm text-muted mt-1.5">
            {m.originalTitle}{m.year ? ` · ${m.year}` : ""}
          </p>

          <div className="flex flex-wrap gap-2 mt-4">
            {m.year > 0 && (
              <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                <Ic n="clap" className="w-3.5 h-3.5 text-gold" /> {m.year}
              </span>
            )}
            {m.country && (
              <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                <Ic n="globe" className="w-3.5 h-3.5 text-gold" /> {m.country}
              </span>
            )}
            {m.duration > 0 && (
              <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                <Ic n="clock" className="w-3.5 h-3.5 text-gold" /> {fmtDuration(m.duration)}
              </span>
            )}
            {m.director && (
              <span className="flex items-center gap-1.5 border border-line rounded-md px-2.5 py-1 font-mono text-xs text-muted">
                реж. {m.director}
              </span>
            )}
          </div>

          {m.genres.length > 0 && (
            <div className="flex flex-wrap gap-2 mt-3">
              {m.genres.map((g) => (
                <span key={g} className="font-mono text-[11px] uppercase tracking-wider text-gold border border-gold/35 rounded-full px-2.5 py-1">{g}</span>
              ))}
            </div>
          )}

          <p className="text-paper/75 leading-relaxed mt-5">{m.description || "Описание пока не добавлено."}</p>

          <div className="border-t border-line mt-6 pt-6 flex flex-wrap items-center gap-7">
            <ScoreDial value={m.adminScore} size={96} label="админ" />
            <div className="flex-1 min-w-[220px] space-y-3">
              {bars.map((b) => (
                <div key={b.label}>
                  <div className="flex justify-between font-mono text-[11px] uppercase tracking-wider text-muted mb-1.5">
                    <span>{b.label}</span>
                    <span className="text-paper font-bold">{b.value.toFixed(1)} / 10</span>
                  </div>
                  <div className="h-1.5 bg-ink rounded-full overflow-hidden">
                    <div className="h-full rounded-full" style={{ width: `${b.value * 10}%`, background: b.color, transition: "width 1s cubic-bezier(.22,1,.36,1)" }} />
                  </div>
                </div>
              ))}
              <div className="font-mono text-[11px] uppercase tracking-[0.18em] pt-1" style={{ color: scoreColor(m.adminScore) }}>
                вердикт админа: {scoreWord(m.adminScore)}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

/* ------------------------------ футер ------------------------------ */

function Footer({ mode }: { mode: Mode }) {
  return (
    <footer className="relative border-t border-line mt-6 overflow-hidden">
      <div className="absolute -top-8 left-0 right-0 outline-word font-display font-extrabold text-[11vw] leading-none whitespace-nowrap pointer-events-none" aria-hidden="true">
        КИНОМЕТР
      </div>
      <div className="relative mx-auto max-w-7xl px-4 sm:px-6 py-12 grid gap-10 md:grid-cols-3">
        <div>
          <div className="flex items-center gap-3">
            <LogoMark className="w-8 h-8" />
            <span className="font-display font-bold text-lg">КИНО<span className="text-gold">МЕТР</span></span>
          </div>
          <p className="text-sm text-muted leading-relaxed mt-4 max-w-xs">
            Каталог фильмов с двумя системами координат: личной оценкой админа и народным рейтингом IMDb. Каждый фильм — как на ладони измерительного прибора.
          </p>
        </div>
        <div>
          <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-gold mb-4">Разделы</div>
          <ul className="space-y-2.5 text-sm">
            <li><a className="text-paper/80 hover:text-gold transition-colors" href="#/" onClick={() => setTimeout(() => document.getElementById("katalog")?.scrollIntoView({ behavior: "smooth" }), 60)}>Каталог измерений</a></li>
            <li><a className="text-paper/80 hover:text-gold transition-colors" href="#/admin">Админ-панель</a></li>
            <li><a className="text-paper/80 hover:text-gold transition-colors" href="api.php?action=ping" target="_blank" rel="noreferrer">Проверка API (api.php)</a></li>
          </ul>
        </div>
        <div>
          <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-gold mb-4">Технологии</div>
          <ul className="space-y-2.5 text-sm text-muted font-mono text-xs">
            <li className="flex items-center gap-2"><Ic n="db" className="w-4 h-4 text-gold/70" /> MySQL 8 · fdb1029.awardspace.net</li>
            <li className="flex items-center gap-2"><Ic n="film" className="w-4 h-4 text-gold/70" /> PHP API — один файл api.php</li>
            <li className="flex items-center gap-2"><Ic n="clap" className="w-4 h-4 text-gold/70" /> Режим: {mode === "server" ? "сервер БД" : "локальное демо"}</li>
          </ul>
        </div>
      </div>
      <div className="relative border-t border-line/60">
        <div className="mx-auto max-w-7xl px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-2 font-mono text-[11px] text-muted">
          <span>© 2026 КИНОМЕТР · сделано для AwardSpace</span>
          <span className="flex items-center gap-1.5"><Ic n="star" filled className="w-3 h-3 text-gold" /> {SEED.length}+ фильмов в стартовом наборе</span>
        </div>
      </div>
    </footer>
  );
}

/* ------------------------------ лендинг ------------------------------ */

function SkeletonLanding() {
  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 py-14 animate-pulse">
      <div className="grid lg:grid-cols-[1fr_330px] gap-12 items-center">
        <div className="space-y-5">
          <div className="h-3 w-52 bg-panel rounded" />
          <div className="h-12 w-4/5 bg-panel rounded" />
          <div className="h-4 w-64 bg-panel rounded" />
          <div className="h-24 w-full bg-panel rounded" />
        </div>
        <div className="h-[420px] bg-panel rounded mx-auto w-[290px]" />
      </div>
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-5 mt-16">
        {Array.from({ length: 5 }).map((_, i) => (
          <div key={i} className="aspect-[2/3] bg-panel rounded" />
        ))}
      </div>
    </div>
  );
}

function Landing({ movies, loading, onOpen }: { movies: Movie[]; loading: boolean; onOpen: (m: Movie) => void }) {
  const byDate = useMemo(() => [...movies].sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt)), [movies]);
  const featured = byDate[0];
  return (
    <>
      <Ticker items={byDate.slice(0, 8)} />
      {loading ? (
        <SkeletonLanding />
      ) : featured ? (
        <>
          <Featured movie={featured} onOpen={onOpen} />
          <StatsStrip movies={movies} />
          <Catalog movies={movies} onOpen={onOpen} />
        </>
      ) : (
        <div className="mx-auto max-w-3xl px-4 py-24 text-center">
          <Ic n="film" className="w-12 h-12 text-gold mx-auto" />
          <h2 className="font-display font-bold text-3xl mt-6">Каталог пока пуст</h2>
          <p className="text-muted mt-3">Загляните в админ-панель и добавьте первый фильм — вручную или импортом из файла.</p>
          <a href="#/admin" className="btn btn-gold mt-7 inline-flex">Добавить фильм <Ic n="plus" className="w-4 h-4" /></a>
        </div>
      )}
    </>
  );
}

/* =========================== АДМИН-ПАНЕЛЬ =========================== */

function LoginCard({ onLogin, busy }: { onLogin: (l: string, p: string) => Promise<void>; busy: boolean }) {
  const [login, setLogin] = useState("");
  const [pass, setPass] = useState("");
  const [err, setErr] = useState("");
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr("");
    try { await onLogin(login.trim(), pass); } catch (ex: any) { setErr(ex.message || "Не удалось войти"); }
  };
  return (
    <div className="mx-auto max-w-md px-4 py-16">
      <Reveal>
        <div className="border border-line bg-panel p-8 relative overflow-hidden">
          <div className="absolute inset-x-0 top-0 h-1 bg-gold" />
          <div className="flex items-center gap-3">
            <LogoMark className="w-10 h-10" animate />
            <div>
              <div className="font-display font-bold text-xl text-paper">Вход в рубку</div>
              <div className="font-mono text-[11px] text-muted uppercase tracking-widest mt-0.5">панель администратора</div>
            </div>
          </div>
          <form onSubmit={submit} className="mt-7 space-y-4">
            <div>
              <label className="lbl" htmlFor="adm-login">Логин</label>
              <input id="adm-login" className="field" autoComplete="username" value={login} onChange={(e) => setLogin(e.target.value)} placeholder="admin" />
            </div>
            <div>
              <label className="lbl" htmlFor="adm-pass">Пароль</label>
              <input id="adm-pass" type="password" className="field" autoComplete="current-password" value={pass} onChange={(e) => setPass(e.target.value)} placeholder="••••••••" />
            </div>
            {err && (
              <div className="flex items-center gap-2 text-sm text-flame font-mono">
                <Ic n="alert" className="w-4 h-4" /> {err}
              </div>
            )}
            <button className="btn btn-gold w-full" disabled={busy || !login || !pass}>
              {busy ? "Проверяем…" : "Войти"} {!busy && <Ic n="key" className="w-4 h-4" />}
            </button>
          </form>
          <div className="mt-6 border border-line/70 bg-coal/60 p-3.5 font-mono text-[11px] text-muted leading-relaxed">
            Первый вход: <span className="text-paper">admin</span> / <span className="text-paper">kinometr</span> —
            смените пароль и добавьте свою команду во вкладке «Админы».
          </div>
        </div>
        <a href="#/" className="btn btn-ghost btn-sm mt-5 w-full"><Ic n="arrow" className="w-4 h-4 rotate-180" /> Вернуться на сайт</a>
      </Reveal>
    </div>
  );
}

/* -------- редактор фильма -------- */

type EditorFields = {
  title: string; originalTitle: string; year: string; country: string; director: string;
  duration: string; genres: string; description: string; coverUrl: string;
  adminScore: number; imdbScore: number;
};

function MovieEditor({ initial, busy, onSave, onCancel }: {
  initial: Movie | null; busy: boolean;
  onSave: (m: Movie) => Promise<void>; onCancel: () => void;
}) {
  const [f, setF] = useState<EditorFields>(() => ({
    title: initial?.title ?? "", originalTitle: initial?.originalTitle ?? "",
    year: initial?.year ? String(initial.year) : "", country: initial?.country ?? "",
    director: initial?.director ?? "", duration: initial?.duration ? String(initial.duration) : "",
    genres: initial?.genres.join(", ") ?? "", description: initial?.description ?? "",
    coverUrl: initial?.coverUrl ?? "",
    adminScore: initial?.adminScore ?? 7.5, imdbScore: initial?.imdbScore ?? 7.0,
  }));
  const [err, setErr] = useState("");
  const set = (k: keyof EditorFields) => (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) =>
    setF((p) => ({ ...p, [k]: e.target.value }));

  const submit = async () => {
    if (!f.title.trim()) { setErr("Название фильма обязательно"); return; }
    setErr("");
    const m: Movie = {
      id: initial?.id ?? "",
      title: f.title.trim(), originalTitle: f.originalTitle.trim(),
      year: parseInt(f.year, 10) || 0, country: f.country.trim(), director: f.director.trim(),
      duration: parseInt(f.duration, 10) || 0,
      genres: f.genres.split(",").map((s) => s.trim()).filter(Boolean),
      description: f.description.trim(), coverUrl: f.coverUrl.trim(),
      adminScore: clampScore(f.adminScore), imdbScore: clampScore(f.imdbScore),
      createdAt: initial?.createdAt ?? "",
    };
    await onSave(m);
  };

  const scoreField = (key: "adminScore" | "imdbScore", label: string, hint: string) => (
    <div>
      <label className="lbl">{label} — <span className="text-paper">{f[key].toFixed(1)}</span> {hint}</label>
      <div className="flex items-center gap-3">
        <input type="range" min={0} max={10} step={0.1} value={f[key]} className="score-range flex-1"
          style={{ "--fill": `${f[key] * 10}%` } as CSSProperties}
          onChange={(e) => setF((p) => ({ ...p, [key]: clampScore(parseFloat(e.target.value)) }))} />
        <input type="number" min={0} max={10} step={0.1} className="field !w-20 !px-2 text-center"
          value={f[key]} onChange={(e) => setF((p) => ({ ...p, [key]: clampScore(parseFloat(e.target.value) || 0) }))} />
      </div>
    </div>
  );

  return (
    <div className="border border-line bg-panel">
      <div className="flex items-center justify-between border-b border-line px-5 py-4">
        <div className="font-display font-bold text-lg text-paper flex items-center gap-2.5">
          <Ic n={initial ? "edit" : "plus"} className="w-5 h-5 text-gold" />
          {initial ? "Редактирование" : "Новый фильм"}
        </div>
        <button onClick={onCancel} className="btn btn-ghost btn-sm cursor-pointer"><Ic n="x" className="w-3.5 h-3.5" /> Отмена</button>
      </div>

      <div className="grid lg:grid-cols-[1fr_220px] gap-7 p-5 sm:p-6">
        <div className="grid sm:grid-cols-2 gap-4 content-start">
          <div className="sm:col-span-2">
            <label className="lbl">Название (рус.) *</label>
            <input className="field" value={f.title} onChange={set("title")} placeholder="Например: Интерстеллар" />
          </div>
          <div>
            <label className="lbl">Оригинальное название</label>
            <input className="field" value={f.originalTitle} onChange={set("originalTitle")} placeholder="Interstellar" />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="lbl">Год</label>
              <input className="field" type="number" value={f.year} onChange={set("year")} placeholder="2014" />
            </div>
            <div>
              <label className="lbl">Мин.</label>
              <input className="field" type="number" value={f.duration} onChange={set("duration")} placeholder="169" />
            </div>
          </div>
          <div>
            <label className="lbl">Страна</label>
            <input className="field" value={f.country} onChange={set("country")} placeholder="США" />
          </div>
          <div>
            <label className="lbl">Режиссёр</label>
            <input className="field" value={f.director} onChange={set("director")} placeholder="Кристофер Нолан" />
          </div>
          <div className="sm:col-span-2">
            <label className="lbl">Жанры (через запятую)</label>
            <input className="field" value={f.genres} onChange={set("genres")} placeholder="фантастика, драма, приключения" />
          </div>
          <div className="sm:col-span-2">
            <label className="lbl">Ссылка на обложку (URL картинки)</label>
            <input className="field" value={f.coverUrl} onChange={set("coverUrl")} placeholder="https://image.tmdb.org/t/p/w500/….jpg" />
            <p className="font-mono text-[10.5px] text-muted mt-1.5">Подойдёт прямая ссылка на JPG/PNG: TMDB, Кинопоиск-CDN, свой хостинг. Если картинка не откроется — карточка покажет заглушку.</p>
          </div>
          <div className="sm:col-span-2">
            <label className="lbl">Описание</label>
            <textarea className="field" rows={4} value={f.description} onChange={set("description")} placeholder="Пара предложений о фильме…" />
          </div>
          {scoreField("adminScore", "Оценка админа", "· ваша личная")}
          {scoreField("imdbScore", "Рейтинг IMDb", "· с imdb.com")}
        </div>

        <div>
          <div className="lbl">Предпросмотр карточки</div>
          <div className="border border-line bg-coal p-3">
            <div className="relative overflow-hidden border border-line">
              <PosterImg src={f.coverUrl} title={f.title || "К"} className="w-full aspect-[2/3]" />
              <div className="absolute top-2 left-2 flex items-center gap-1.5 bg-ink/85 border border-line px-2 py-1">
                <span className="font-mono text-[9px] text-muted uppercase">адм</span>
                <span className="font-display font-bold text-sm leading-none" style={{ color: scoreColor(f.adminScore) }}>{f.adminScore.toFixed(1)}</span>
              </div>
              <div className="absolute top-2 right-2 flex items-center gap-1 bg-ink/85 border border-line px-1.5 py-1">
                <Ic n="star" filled className="w-3 h-3 text-imdb" />
                <span className="font-mono text-[11px] text-paper leading-none">{f.imdbScore.toFixed(1)}</span>
              </div>
            </div>
            <div className="pt-2.5">
              <div className="font-bold text-sm text-paper truncate">{f.title || "Название фильма"}</div>
              <div className="font-mono text-[11px] text-muted mt-0.5 truncate">
                {f.year || "год"}{f.genres ? " · " + f.genres.split(",")[0].trim() : ""}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line px-5 py-4">
        <div className="font-mono text-xs text-flame min-h-[16px]">{err && <span className="flex items-center gap-1.5"><Ic n="alert" className="w-3.5 h-3.5" />{err}</span>}</div>
        <div className="flex gap-2.5">
          <button className="btn btn-ghost cursor-pointer" onClick={onCancel} disabled={busy}>Отмена</button>
          <button className="btn btn-gold cursor-pointer" onClick={submit} disabled={busy}>
            {busy ? "Сохраняем…" : "Сохранить"} <Ic n="check" className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}

/* -------- вкладка «Импорт» -------- */

function ImportAdmin({ busy, onImport, onExport, total }: {
  busy: boolean; onImport: (rows: any[]) => Promise<number>; onExport: () => void; total: number;
}) {
  const [rows, setRows] = useState<any[]>([]);
  const [fileName, setFileName] = useState("");
  const [err, setErr] = useState("");
  const [parsing, setParsing] = useState(false);
  const [drag, setDrag] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  const handleFile = async (file: File) => {
    setParsing(true); setErr("");
    try {
      const text = await file.text();
      const trimmed = text.trim();
      let data: any[];
      if (/\.json$/i.test(file.name) || trimmed.startsWith("[") || trimmed.startsWith("{")) {
        const parsed = JSON.parse(trimmed);
        const arr = Array.isArray(parsed) ? parsed : parsed?.movies;
        if (!Array.isArray(arr)) throw new Error("JSON должен содержать массив фильмов (или поле movies с массивом)");
        data = arr.map((r: any) => mapImportRow(r));
      } else {
        data = parseCSV(text);
      }
      data = data.filter((r) => r && String(r.title ?? "").trim());
      if (!data.length) throw new Error("Не найдено ни одной строки с названием фильма");
      setRows(data);
      setFileName(file.name);
    } catch (e: any) {
      setErr(e?.message || "Не удалось разобрать файл");
      setRows([]);
    } finally { setParsing(false); }
  };

  return (
    <div className="grid lg:grid-cols-[1fr_300px] gap-6 items-start">
      <div>
        <div
          onDragOver={(e) => { e.preventDefault(); setDrag(true); }}
          onDragLeave={() => setDrag(false)}
          onDrop={(e) => { e.preventDefault(); setDrag(false); const f = e.dataTransfer.files?.[0]; if (f) handleFile(f); }}
          className={cn("border border-dashed rounded-lg p-10 text-center transition-all",
            drag ? "border-gold bg-gold/5 scale-[1.01]" : "border-line bg-panel hover:border-gold/50")}>
          <input ref={fileRef} type="file" accept=".json,.csv,application/json,text/csv" className="hidden"
            onChange={(e) => { const f = e.target.files?.[0]; if (f) handleFile(f); e.target.value = ""; }} />
          <Ic n="upload" className={cn("w-10 h-10 mx-auto", drag ? "text-gold" : "text-muted")} />
          <div className="font-display font-bold text-lg mt-4 text-paper">
            {parsing ? "Разбираем файл…" : "Перетащите JSON или CSV"}
          </div>
          <p className="font-mono text-xs text-muted mt-2">Массовое добавление фильмов одним файлом</p>
          <button className="btn btn-ghost btn-sm mt-5 cursor-pointer" onClick={() => fileRef.current?.click()} disabled={parsing}>
            <Ic n="film" className="w-3.5 h-3.5" /> Выбрать файл
          </button>
        </div>

        {err && (
          <div className="mt-4 flex items-center gap-2 border border-flame/40 bg-flame/10 text-flame px-4 py-3 font-mono text-xs">
            <Ic n="alert" className="w-4 h-4 shrink-0" /> {err}
          </div>
        )}

        {rows.length > 0 && (
          <div className="mt-5 border border-line bg-panel modal-pop">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-line px-4 py-3">
              <div className="font-mono text-xs text-muted">
                <span className="text-gold font-bold">{fileName}</span> · распознано фильмов: <span className="text-paper font-bold">{rows.length}</span>
              </div>
              <button className="btn btn-ghost btn-sm cursor-pointer" onClick={() => { setRows([]); setFileName(""); setErr(""); }}>
                <Ic n="x" className="w-3.5 h-3.5" /> Очистить
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="font-mono text-[10.5px] uppercase tracking-wider text-muted text-left">
                    <th className="px-4 py-2.5 font-medium">#</th>
                    <th className="px-4 py-2.5 font-medium">Название</th>
                    <th className="px-4 py-2.5 font-medium">Год</th>
                    <th className="px-4 py-2.5 font-medium">Жанры</th>
                    <th className="px-4 py-2.5 font-medium">Админ</th>
                    <th className="px-4 py-2.5 font-medium">IMDb</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-line/60">
                  {rows.slice(0, 8).map((r, i) => (
                    <tr key={i} className="hover:bg-coal/60">
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">{i + 1}</td>
                      <td className="px-4 py-2.5 font-semibold text-paper">{String(r.title)}</td>
                      <td className="px-4 py-2.5 font-mono text-xs text-muted">{r.year || "—"}</td>
                      <td className="px-4 py-2.5 font-mono text-xs text-muted max-w-[200px] truncate">
                        {Array.isArray(r.genres) ? r.genres.join(", ") : String(r.genres || "—")}
                      </td>
                      <td className="px-4 py-2.5 font-display font-bold" style={{ color: scoreColor(parseFloat(r.adminScore) || 0) }}>
                        {(parseFloat(r.adminScore) || 0).toFixed(1)}
                      </td>
                      <td className="px-4 py-2.5 font-mono text-xs text-paper">{(parseFloat(r.imdbScore) || 0).toFixed(1)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {rows.length > 8 && (
              <div className="px-4 py-2 font-mono text-[11px] text-muted border-t border-line/60">…и ещё {rows.length - 8}</div>
            )}
            <div className="flex items-center justify-between gap-3 border-t border-line px-4 py-3.5">
              <span className="font-mono text-xs text-muted">Проверьте данные перед записью в базу</span>
              <button className="btn btn-gold cursor-pointer" disabled={busy}
                onClick={async () => { try { await onImport(rows); setRows([]); setFileName(""); } catch { /* тост покажет App */ } }}>
                {busy ? "Импортируем…" : `Импортировать ${rows.length}`} <Ic n="check" className="w-4 h-4" />
              </button>
            </div>
          </div>
        )}
      </div>

      <div className="space-y-4">
        <div className="border border-line bg-panel p-5">
          <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-gold mb-3">Шаблоны файлов</div>
          <p className="text-xs text-muted leading-relaxed">
            Скачайте заготовку, заполните строками и загрузите обратно. Поддерживаются русские и английские заголовки колонок.
          </p>
          <div className="flex flex-col gap-2 mt-4">
            <button className="btn btn-ghost btn-sm cursor-pointer" onClick={() => downloadFile("kinometr-template.json", TEMPLATE_JSON)}>
              <Ic n="download" className="w-3.5 h-3.5" /> Шаблон JSON
            </button>
            <button className="btn btn-ghost btn-sm cursor-pointer" onClick={() => downloadFile("kinometr-template.csv", TEMPLATE_CSV, "text/csv")}>
              <Ic n="download" className="w-3.5 h-3.5" /> Шаблон CSV
            </button>
          </div>
        </div>
        <div className="border border-line bg-panel p-5">
          <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-gold mb-3">Резервная копия</div>
          <p className="text-xs text-muted leading-relaxed">Выгрузить весь текущий каталог ({total} шт.) в JSON-файл.</p>
          <button className="btn btn-gold btn-sm w-full mt-4 cursor-pointer" onClick={onExport} disabled={!total}>
            <Ic n="download" className="w-3.5 h-3.5" /> Экспорт каталога
          </button>
        </div>
        <div className="border border-line/70 bg-coal/60 p-4 font-mono text-[11px] text-muted leading-relaxed">
          Колонки: title, originalTitle, year, country, director, duration, genres (через «|»), description, coverUrl, adminScore, imdbScore. Разделитель CSV — «;» или «,».
        </div>
      </div>
    </div>
  );
}

/* -------- вкладка «Админы» -------- */

function AdminsAdmin({ mode, selfLogin, fetchAdmins, onAdd, onDelete, askConfirm, toast }: {
  mode: Mode; selfLogin: string;
  fetchAdmins: () => Promise<AdminT[]>;
  onAdd: (l: string, p: string) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
  askConfirm: (title: string, text: string, action: () => Promise<void>) => void;
  toast: (type: ToastT["type"], text: string) => void;
}) {
  const [admins, setAdmins] = useState<AdminT[] | null>(null);
  const [login, setLogin] = useState("");
  const [pass, setPass] = useState("");
  const [busy, setBusy] = useState(false);

  const refresh = async () => {
    try { setAdmins(await fetchAdmins()); }
    catch (e: any) { toast("err", e.message || "Не удалось получить список"); }
  };
  useEffect(() => { refresh(); /* eslint-disable-next-line */ }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      await onAdd(login.trim(), pass);
      setLogin(""); setPass("");
      await refresh();
    } catch (ex: any) { toast("err", ex.message || "Не удалось добавить"); }
    finally { setBusy(false); }
  };

  return (
    <div className="grid lg:grid-cols-[1fr_320px] gap-6 items-start">
      <div className="border border-line bg-panel divide-y divide-line/70">
        <div className="px-5 py-3.5 font-mono text-[11px] uppercase tracking-[0.2em] text-muted flex items-center gap-2">
          <Ic n="users" className="w-4 h-4 text-gold" /> Команда: {admins ? admins.length : "…"}
        </div>
        {admins === null && <div className="py-12 text-center font-mono text-sm text-muted animate-pulse">загружаем…</div>}
        {admins?.map((a) => (
          <div key={a.id} className="flex items-center gap-4 px-5 py-3.5">
            <span className={cn("w-9 h-9 flex items-center justify-center border font-display font-bold text-sm",
              a.role === "root" ? "border-gold/60 text-gold bg-gold/10" : "border-line text-muted bg-coal")}>
              {a.login.charAt(0).toUpperCase()}
            </span>
            <div className="flex-1 min-w-0">
              <div className="font-bold text-paper flex items-center gap-2">
                {a.login}
                {a.login === selfLogin && <span className="font-mono text-[10px] text-muted uppercase border border-line rounded px-1.5 py-0.5">это вы</span>}
              </div>
              <div className="font-mono text-[11px] text-muted mt-0.5">
                {a.role === "root" ? "главный администратор" : "администратор"}
                {a.created_at ? " · с " + fmtDate(a.created_at) : ""}
              </div>
            </div>
            {a.role === "root" ? (
              <span className="font-mono text-[10.5px] uppercase tracking-wider text-gold border border-gold/40 rounded-full px-2.5 py-1">root</span>
            ) : (
              <button className="btn btn-danger btn-sm cursor-pointer"
                onClick={() =>
                  askConfirm("Удалить администратора?", `«${a.login}» потеряет доступ к панели.`, async () => {
                    await onDelete(a.id); await refresh();
                  })}>
                <Ic n="trash" className="w-3.5 h-3.5" /> Удалить
              </button>
            )}
          </div>
        ))}
      </div>

      <form onSubmit={submit} className="border border-line bg-panel p-5">
        <div className="font-mono text-[11px] uppercase tracking-[0.2em] text-gold mb-4">Новый администратор</div>
        <label className="lbl">Логин</label>
        <input className="field" value={login} onChange={(e) => setLogin(e.target.value)} placeholder="maria" autoComplete="off" />
        <label className="lbl mt-4">Пароль</label>
        <input className="field" type="password" value={pass} onChange={(e) => setPass(e.target.value)} placeholder="минимум 6 символов" autoComplete="new-password" />
        <button className="btn btn-gold w-full mt-5 cursor-pointer" disabled={busy || login.trim().length < 3 || pass.length < 6}>
          {busy ? "Добавляем…" : "Добавить"} <Ic n="plus" className="w-4 h-4" />
        </button>
        <div className="mt-5 border-t border-line pt-4 font-mono text-[11px] text-muted leading-relaxed">
          {mode === "local"
            ? "Демо-режим: аккаунты хранятся в браузере. На сервере пароли хешируются (bcrypt)."
            : "Пароли хранятся в MySQL в виде bcrypt-хешей. Root-аккаунт удалить нельзя."}
        </div>
      </form>
    </div>
  );
}

/* -------- каркас админки -------- */

type AdminTab = "movies" | "import" | "admins";

function AdminShell({ mode, movies, authed, busy, onSave, onDelete, onImport, onExport,
  fetchAdmins, onAddAdmin, onDeleteAdmin, onLogout, askConfirm, toast }: {
  mode: Mode; movies: Movie[]; authed: Auth; busy: boolean;
  onSave: (m: Movie) => Promise<void>; onDelete: (id: number | string) => Promise<void>;
  onImport: (rows: any[]) => Promise<number>; onExport: () => void;
  fetchAdmins: () => Promise<AdminT[]>;
  onAddAdmin: (l: string, p: string) => Promise<void>;
  onDeleteAdmin: (id: number) => Promise<void>;
  onLogout: () => void;
  askConfirm: (t: string, x: string, a: () => Promise<void>) => void;
  toast: (type: ToastT["type"], text: string) => void;
}) {
  const [tab, setTab] = useState<AdminTab>("movies");
  const tabs: { id: AdminTab; label: string; icon: string }[] = [
    { id: "movies", label: "Фильмы", icon: "film" },
    { id: "import", label: "Импорт", icon: "upload" },
    { id: "admins", label: "Админы", icon: "users" },
  ];

  return (
    <div className="mx-auto max-w-7xl px-4 sm:px-6 py-8">
      <Reveal>
        <div className="flex flex-wrap items-center justify-between gap-4 mb-7">
          <div>
            <div className="flex items-center gap-3 font-mono text-[11px] tracking-[0.24em] uppercase text-gold">
              <span className="h-px w-10 bg-gold/70" /> служебный вход
            </div>
            <h1 className="font-display font-bold text-3xl sm:text-4xl mt-2">Админ-панель</h1>
          </div>
          <div className="flex items-center gap-3">
            <span className="hidden sm:flex items-center gap-2 border border-line rounded-full pl-1.5 pr-3.5 py-1.5">
              <span className="w-7 h-7 rounded-full bg-gold/15 border border-gold/40 flex items-center justify-center font-display font-bold text-xs text-gold">
                {authed.login.charAt(0).toUpperCase()}
              </span>
              <span className="font-mono text-xs text-paper">{authed.login}</span>
              <span className="font-mono text-[10px] text-muted uppercase">· {authed.role === "root" ? "root" : "admin"}</span>
            </span>
            <button className="btn btn-ghost btn-sm cursor-pointer" onClick={onLogout}>
              <Ic n="logout" className="w-3.5 h-3.5" /> Выйти
            </button>
          </div>
        </div>
      </Reveal>

      <div className="grid lg:grid-cols-[210px_1fr] gap-7 items-start">
        <aside className="flex lg:flex-col gap-2 overflow-x-auto pb-1">
          {tabs.map((t) => (
            <button key={t.id} onClick={() => setTab(t.id)}
              className={cn("flex items-center gap-3 px-4 py-3 border text-sm font-bold transition-all whitespace-nowrap cursor-pointer",
                tab === t.id ? "border-gold bg-gold/10 text-gold" : "border-line bg-panel text-muted hover:text-paper hover:border-gold/50")}>
              <Ic n={t.icon} className="w-4 h-4" /> {t.label}
              {t.id === "movies" && <span className="ml-auto font-mono text-[11px] opacity-70">{movies.length}</span>}
            </button>
          ))}
          <a href="#/" className="flex items-center gap-3 px-4 py-3 border border-line bg-panel text-muted hover:text-gold hover:border-gold/50 transition-all text-sm font-bold whitespace-nowrap">
            <Ic n="arrow" className="w-4 h-4 rotate-180" /> На сайт
          </a>
          <div className="hidden lg:block mt-3 border border-line/70 bg-coal/60 p-3.5 font-mono text-[11px] text-muted leading-relaxed">
            <span className="flex items-center gap-1.5 mb-1.5">
              <Ic n="db" className="w-3.5 h-3.5 text-gold/70" />
              {mode === "server" ? "MySQL · AwardSpace" : "localStorage браузера"}
            </span>
            {mode === "server" ? "Все изменения пишутся в базу 4772808_base." : "Сервер БД не найден — изменения сохраняются локально."}
          </div>
        </aside>

        <main className="min-w-0">
          {tab === "movies" && (
            <MoviesAdminList movies={movies} busy={busy} onSave={onSave} onDelete={onDelete} askConfirm={askConfirm} />
          )}
          {tab === "import" && <ImportAdmin busy={busy} onImport={onImport} onExport={onExport} total={movies.length} />}
          {tab === "admins" && (
            <AdminsAdmin mode={mode} selfLogin={authed.login} fetchAdmins={fetchAdmins}
              onAdd={onAddAdmin} onDelete={onDeleteAdmin} askConfirm={askConfirm} toast={toast} />
          )}
        </main>
      </div>
    </div>
  );
}

function MoviesAdminList({ movies, busy, onSave, onDelete, askConfirm }: {
  movies: Movie[]; busy: boolean;
  onSave: (m: Movie) => Promise<void>;
  onDelete: (id: number | string) => Promise<void>;
  askConfirm: (t: string, x: string, a: () => Promise<void>) => void;
}) {
  const [editing, setEditing] = useState<Movie | null | "new">(null);
  const [q, setQ] = useState("");
  const byDate = useMemo(
    () => [...movies].filter((m) => !q.trim() || m.title.toLowerCase().includes(q.trim().toLowerCase()))
      .sort((a, b) => +new Date(b.createdAt) - +new Date(a.createdAt)),
    [movies, q]
  );

  if (editing)
    return (
      <MovieEditor initial={editing === "new" ? null : editing} busy={busy}
        onSave={async (m) => { await onSave(m); setEditing(null); }}
        onCancel={() => setEditing(null)} />
    );

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div className="relative flex-1 min-w-[220px]">
          <Ic n="search" className="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input className="field !pl-10" placeholder="Фильтр по названию…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <button className="btn btn-gold cursor-pointer" onClick={() => setEditing("new")}>
          <Ic n="plus" className="w-4 h-4" /> Добавить фильм
        </button>
      </div>

      <div className="border border-line bg-panel divide-y divide-line/70">
        {byDate.length === 0 && <div className="py-14 text-center text-muted font-mono text-sm">Фильмы не найдены</div>}
        {byDate.map((m) => (
          <div key={m.id} className="flex items-center gap-4 px-4 py-3 hover:bg-coal/60 transition-colors group">
            <PosterImg src={m.coverUrl} title={m.title} className="w-11 h-16 shrink-0 object-cover border border-line" />
            <div className="flex-1 min-w-0">
              <div className="font-bold text-[15px] text-paper truncate group-hover:text-gold transition-colors">{m.title}</div>
              <div className="font-mono text-[11px] text-muted truncate mt-0.5">
                {m.year || "—"} · добавлен {fmtDate(m.createdAt)}{m.genres.length ? " · " + m.genres.slice(0, 3).join(", ") : ""}
              </div>
            </div>
            <div className="hidden sm:flex items-center gap-3 shrink-0">
              <span className="font-display font-bold text-base" style={{ color: scoreColor(m.adminScore) }}>{m.adminScore.toFixed(1)}</span>
              <span className="flex items-center gap-1 font-mono text-xs text-muted">
                <Ic n="star" filled className="w-3 h-3 text-imdb" /> {m.imdbScore.toFixed(1)}
              </span>
            </div>
            <div className="flex gap-2 shrink-0">
              <button className="btn btn-ghost btn-sm cursor-pointer" onClick={() => setEditing(m)}>
                <Ic n="edit" className="w-3.5 h-3.5" /> <span className="hidden md:inline">Изменить</span>
              </button>
              <button className="btn btn-danger btn-sm cursor-pointer" title="Удалить фильм"
                onClick={() =>
                  askConfirm("Удалить фильм?", `«${m.title}» будет удалён из каталога безвозвратно.`, async () => { await onDelete(m.id); })}>
                <Ic n="trash" className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* -------- тосты и подтверждения -------- */

function Toasts({ items, onClose }: { items: ToastT[]; onClose: (id: number) => void }) {
  return (
    <div className="fixed bottom-5 right-5 z-[90] flex flex-col gap-2.5 w-[min(340px,calc(100vw-40px))]">
      {items.map((t) => (
        <div key={t.id} className={cn("toast-in flex items-start gap-3 border bg-panel px-4 py-3.5 shadow-xl",
          t.type === "ok" ? "border-moss/50" : t.type === "err" ? "border-flame/50" : "border-gold/50")}>
          <Ic n={t.type === "ok" ? "check" : t.type === "err" ? "alert" : "film"}
            className={cn("w-4 h-4 mt-0.5 shrink-0", t.type === "ok" ? "text-moss" : t.type === "err" ? "text-flame" : "text-gold")} />
          <div className="text-sm text-paper leading-snug flex-1">{t.text}</div>
          <button onClick={() => onClose(t.id)} className="text-muted hover:text-paper transition-colors cursor-pointer" aria-label="Закрыть">
            <Ic n="x" className="w-3.5 h-3.5" />
          </button>
        </div>
      ))}
    </div>
  );
}

function Confirm({ data, busy, onClose }: {
  data: { title: string; text: string; action: () => Promise<void> } | null;
  busy: boolean; onClose: () => void;
}) {
  if (!data) return null;
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center p-4" role="alertdialog" aria-modal="true">
      <div className="absolute inset-0 bg-ink/80 backdrop-blur-sm" onClick={onClose} />
      <div className="modal-pop relative w-full max-w-sm border border-line bg-panel p-6">
        <div className="flex items-center gap-3">
          <span className="w-10 h-10 flex items-center justify-center border border-flame/50 bg-flame/10 text-flame">
            <Ic n="alert" className="w-5 h-5" />
          </span>
          <div className="font-display font-bold text-lg text-paper">{data.title}</div>
        </div>
        <p className="text-sm text-muted mt-3.5 leading-relaxed">{data.text}</p>
        <div className="flex gap-2.5 mt-6">
          <button className="btn btn-ghost flex-1 cursor-pointer" onClick={onClose} disabled={busy}>Отмена</button>
          <button className="btn btn-danger flex-1 cursor-pointer" disabled={busy}
            onClick={async () => { await data.action(); onClose(); }}>
            {busy ? "…" : "Удалить"} <Ic n="trash" className="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>
  );
}

/* ============================== APP ============================== */

export default function App() {
  const [mode, setMode] = useState<Mode>("detect");
  const [movies, setMovies] = useState<Movie[]>([]);
  const [loading, setLoading] = useState(true);
  const [route, setRoute] = useState<Route>(() => (window.location.hash.startsWith("#/admin") ? "admin" : "home"));
  const [authed, setAuthed] = useState<Auth | null>(() => {
    const token = localStorage.getItem(LS.token);
    const login = localStorage.getItem(LS.login);
    return token && login ? { login, role: localStorage.getItem(LS.role) || "admin", token } : null;
  });
  const [toasts, setToasts] = useState<ToastT[]>([]);
  const [modal, setModal] = useState<Movie | null>(null);
  const [busy, setBusy] = useState(false);
  const [confirm, setConfirm] = useState<{ title: string; text: string; action: () => Promise<void> } | null>(null);
  const toastId = useRef(1);

  const toast = (type: ToastT["type"], text: string) => {
    const id = toastId.current++;
    setToasts((p) => [...p.slice(-3), { id, type, text }]);
    setTimeout(() => setToasts((p) => p.filter((t) => t.id !== id)), 4200);
  };

  const refresh = async (m: Mode) => {
    try {
      if (m === "server") {
        const d = await serverCall<{ movies: Movie[] }>("movies");
        setMovies(d.movies || []);
      } else {
        setMovies(loadLocalMovies());
      }
    } catch (e: any) {
      toast("err", e.message || "Не удалось загрузить каталог");
      setMovies(loadLocalMovies());
    } finally {
      setLoading(false);
    }
  };

  /* определение режима: api.php жив — работаем с MySQL, иначе демо */
  useEffect(() => {
    let cancelled = false;
    (async () => {
      let m: Mode = "local";
      try {
        const d = await serverCall<{ ok: boolean }>("ping");
        if (d?.ok) m = "server";
      } catch { m = "local"; }
      if (cancelled) return;
      setMode(m);
      await refresh(m);
    })();
    return () => { cancelled = true; };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /* hash-маршрутизация */
  useEffect(() => {
    const h = () => {
      setRoute(window.location.hash.startsWith("#/admin") ? "admin" : "home");
      window.scrollTo(0, 0);
    };
    window.addEventListener("hashchange", h);
    return () => window.removeEventListener("hashchange", h);
  }, []);

  const onNav = (r: Route) => {
    window.location.hash = r === "admin" ? "#/admin" : "#/";
  };

  const guardAuth = (e: unknown) => {
    const msg = e instanceof Error ? e.message : String(e);
    if (/войдите|токен|авторизац/i.test(msg)) {
      localStorage.removeItem(LS.token); localStorage.removeItem(LS.login); localStorage.removeItem(LS.role);
      setAuthed(null);
      toast("info", "Сессия истекла — войдите заново");
      return true;
    }
    return false;
  };

  /* ---------- фильмы ---------- */
  const saveMovie = async (data: Movie) => {
    setBusy(true);
    try {
      if (mode === "server" && authed) {
        await serverCall("movie_save", data, authed.token);
        await refresh(mode);
      } else {
        const ls = loadLocalMovies();
        const i = ls.findIndex((x) => String(x.id) === String(data.id));
        if (data.id && i >= 0) ls[i] = { ...data, createdAt: ls[i].createdAt };
        else ls.unshift({ ...data, id: uid(), createdAt: new Date().toISOString() });
        saveLocalMovies(ls);
        setMovies(ls);
      }
      toast("ok", data.id ? "Изменения сохранены" : `«${data.title}» добавлен в каталог`);
    } catch (e) {
      if (!guardAuth(e)) toast("err", e instanceof Error ? e.message : "Не удалось сохранить");
      throw e;
    } finally { setBusy(false); }
  };

  const deleteMovie = async (id: number | string) => {
    setBusy(true);
    try {
      if (mode === "server" && authed) {
        await serverCall("movie_delete", { id }, authed.token);
        await refresh(mode);
      } else {
        const ls = loadLocalMovies().filter((m) => String(m.id) !== String(id));
        saveLocalMovies(ls);
        setMovies(ls);
      }
      toast("ok", "Фильм удалён");
    } catch (e) {
      if (!guardAuth(e)) toast("err", e instanceof Error ? e.message : "Не удалось удалить");
    } finally { setBusy(false); }
  };

  const importMovies = async (rows: any[]): Promise<number> => {
    const clean = rows.filter((r) => r && String(r.title ?? "").trim());
    if (!clean.length) throw new Error("Нет валидных строк для импорта");
    setBusy(true);
    try {
      if (mode === "server" && authed) {
        const d = await serverCall<{ imported: number }>("import", { movies: clean }, authed.token);
        await refresh(mode);
        toast("ok", `Импортировано фильмов: ${d.imported}`);
        return d.imported;
      }
      const ls = loadLocalMovies();
      clean.forEach((r, i) => ls.unshift(normalizeMovie(r, i)));
      saveLocalMovies(ls);
      setMovies(ls);
      toast("ok", `Импортировано фильмов: ${clean.length}`);
      return clean.length;
    } catch (e) {
      if (!guardAuth(e)) toast("err", e instanceof Error ? e.message : "Импорт не удался");
      throw e;
    } finally { setBusy(false); }
  };

  const exportJson = () => {
    downloadFile("kinometr-catalog.json", JSON.stringify(movies, null, 2));
    toast("ok", `Экспортировано фильмов: ${movies.length}`);
  };

  /* ---------- админы ---------- */
  const login = async (l: string, p: string) => {
    setBusy(true);
    try {
      if (mode === "server") {
        const d = await serverCall<{ token: string; login: string; role: string }>("login", { login: l, password: p });
        localStorage.setItem(LS.token, d.token);
        localStorage.setItem(LS.login, d.login);
        localStorage.setItem(LS.role, d.role);
        setAuthed({ login: d.login, role: d.role, token: d.token });
      } else {
        const admins = loadLocalAdmins();
        const found = admins.find((x) => x.login === l && x.pass === p);
        if (!found) throw new Error("Неверный логин или пароль");
        localStorage.setItem(LS.token, "local");
        localStorage.setItem(LS.login, found.login);
        localStorage.setItem(LS.role, found.role);
        setAuthed({ login: found.login, role: found.role, token: "local" });
      }
      toast("ok", `Добро пожаловать, ${l}!`);
    } finally { setBusy(false); }
  };

  const logout = () => {
    localStorage.removeItem(LS.token); localStorage.removeItem(LS.login); localStorage.removeItem(LS.role);
    setAuthed(null);
    toast("info", "Вы вышли из панели");
  };

  const fetchAdmins = async (): Promise<AdminT[]> => {
    if (mode === "server" && authed) {
      const d = await serverCall<{ admins: AdminT[] }>("admins", undefined, authed.token);
      return d.admins || [];
    }
    return loadLocalAdmins().map(({ pass: _p, ...rest }) => rest);
  };

  const addAdmin = async (l: string, p: string) => {
    if (mode === "server" && authed) {
      await serverCall("admin_add", { login: l, password: p }, authed.token);
    } else {
      const admins = loadLocalAdmins();
      if (admins.some((x) => x.login === l)) throw new Error("Такой логин уже занят");
      admins.push({ id: Math.max(0, ...admins.map((x) => x.id)) + 1, login: l, pass: p, role: "admin", created_at: new Date().toISOString() });
      localStorage.setItem(LS.admins, JSON.stringify(admins));
    }
    toast("ok", `Администратор «${l}» добавлен`);
  };

  const deleteAdmin = async (id: number) => {
    if (mode === "server" && authed) {
      await serverCall("admin_delete", { id }, authed.token);
    } else {
      const admins = loadLocalAdmins().filter((x) => x.id !== id);
      localStorage.setItem(LS.admins, JSON.stringify(admins));
    }
    toast("ok", "Администратор удалён");
  };

  const askConfirm = (title: string, text: string, action: () => Promise<void>) =>
    setConfirm({ title, text, action });

  return (
    <div className="min-h-screen font-body text-paper relative">
      {/* атмосферный фон */}
      <div className="fixed inset-0 pointer-events-none z-0" aria-hidden="true"
        style={{
          background:
            "radial-gradient(55% 40% at 80% -5%, rgba(226,166,75,0.07), transparent 60%), radial-gradient(45% 35% at -5% 60%, rgba(217,80,63,0.05), transparent 60%), linear-gradient(180deg, #15110e 0%, #13100d 40%, #100d0b 100%)",
        }} />
      <div className="grain" aria-hidden="true" />

      <div className="relative z-10">
        <Header mode={mode} authed={authed} onNav={onNav} />
        {route === "home" ? (
          <Landing movies={movies} loading={loading} onOpen={setModal} />
        ) : authed ? (
          <AdminShell mode={mode} movies={movies} authed={authed} busy={busy}
            onSave={saveMovie} onDelete={deleteMovie} onImport={importMovies} onExport={exportJson}
            fetchAdmins={fetchAdmins} onAddAdmin={addAdmin} onDeleteAdmin={deleteAdmin}
            onLogout={logout} askConfirm={askConfirm} toast={toast} />
        ) : (
          <LoginCard onLogin={login} busy={busy} />
        )}
        <Footer mode={mode} />
      </div>

      {modal && <MovieModal m={modal} onClose={() => setModal(null)} />}
      <Confirm data={confirm} busy={busy} onClose={() => setConfirm(null)} />
      <Toasts items={toasts} onClose={(id) => setToasts((p) => p.filter((t) => t.id !== id))} />
    </div>
  );
}
