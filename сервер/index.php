<?php
/* ═══════════════════════════════════════════════════════════════════
   КИНОМЕТР — сайт оценок фильмов · ВЕСЬ САЙТ В ОДНОМ ФАЙЛЕ (index.php)

   ДЕПЛОЙ НА AWARDSPACE:
   1) Загрузите этот файл в папку htdocs вашего сайта (просто index.php)
   2) Пароль БД уже вписан (DB_PASS ниже). Таблицы MySQL и главный
      админ создадутся автоматически при первом открытии.
   3) Вход в админку: ссылка «Админ» в шапке (или #/admin),
      логин admin, пароль kinometr — сразу смените в разделе «Админы»!

   Если пароль БД не вписан — сайт сам работает в демо-режиме
   (данные хранятся в браузере), никаких ошибок не будет.

   Как устроен файл:
   · верх (PHP) — база данных и API (?api)
   · низ (после закрывающего тега PHP) — сам сайт: чистые HTML, CSS
     и JavaScript. Они НЕ внутри PHP-строк, поэтому стили не могут
     «сброситься» или потеряться при обработке сервером.
   ═══════════════════════════════════════════════════════════════════ */

error_reporting(0);
ini_set('display_errors', '0');

/* ─────────────── НАСТРОЙКИ БАЗЫ (AwardSpace) ─────────────── */
const DB_HOST = 'fdb1029.awardspace.net';
const DB_PORT = 3306;
const DB_NAME = '4772808_base';
const DB_USER = '4772808_base';
const DB_PASS = '66677712A';   /* пароль БД AwardSpace */
const SECRET  = 'kinometr_4772808_awardspace_secret_2024';

/* ─────────────── служебное ─────────────── */
function jout($x) {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($x, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function db() {
    static $c = null;
    if ($c !== null) return $c === false ? null : $c;
    $c = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if (!$c || $c->connect_errno) { $c = false; return null; }
    $c->set_charset('utf8mb4');
    return $c;
}

function sign($data) {
    $p = rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    return $p . '.' . hash_hmac('sha256', $p, SECRET);
}
function verify_token($tok) {
    if (!is_string($tok)) return null;
    $a = explode('.', $tok);
    if (count($a) !== 2) return null;
    if (hash_hmac('sha256', $a[0], SECRET) !== $a[1]) return null;
    $json = base64_decode(strtr($a[0], '-_', '+/'));
    $d = json_decode($json, true);
    if (!is_array($d) || !isset($d['exp']) || $d['exp'] < time()) return null;
    return $d;
}

const SEED_MOVIES = [
    ['Дюна: Часть вторая','Dune: Part Two',2024,'США','Дени Вильнёв',166,'["фантастика","приключения","драма"]','Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один.','https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg',8.4,8.5],
    ['Оппенгеймер','Oppenheimer',2023,'США · Великобритания','Кристофер Нолан',180,'["биография","драма","триллер"]','История «отца атомной бомбы»: проект «Манхэттен», триумф науки и моральная пропасть под ногами её творцов.','https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',8.6,8.3],
    ['Интерстеллар','Interstellar',2014,'США · Великобритания','Кристофер Нолан',169,'["фантастика","драма","приключения"]','Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома для человечества.','https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',9.2,8.7],
    ['Начало','Inception',2010,'США · Великобритания','Кристофер Нолан',148,'["фантастика","боевик","триллер"]','Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.','https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',9.0,8.8],
    ['Паразиты','Gisaengchung',2019,'Южная Корея','Пон Джун-хо',132,'["триллер","драма","комедия"]','Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, оборачивающаяся кровавой трагикомедией.','https://image.tmdb.org/t/p/w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg',8.9,8.5],
    ['Побег из Шоушенка','The Shawshank Redemption',1994,'США','Фрэнк Дарабонт',142,'["драма"]','Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных.','https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg',9.5,9.1],
    ['Бойцовский клуб','Fight Club',1999,'США · Германия','Дэвид Финчер',139,'["триллер","драма"]','Страдающий бессонницей клерк и харизматичный продавец мыла Тайлер Дёрден основывают подпольный бойцовский клуб, который перерастает в нечто гораздо большее.','https://image.tmdb.org/t/p/w500/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg',8.8,8.8],
    ['Матрица','The Matrix',1999,'США','Лана и Лилли Вачовски',136,'["фантастика","боевик"]','Хакер Нео узнаёт, что привычный мир — симуляция, созданная машинами, и присоединяется к повстанцам, сражающимся за свободу людей.','https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',8.7,8.7],
];

function ensure_schema() {
    $c = db();
    if (!$c) return false;
    $c->query("CREATE TABLE IF NOT EXISTS `movies` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `original_title` VARCHAR(255) NOT NULL DEFAULT '',
        `year` SMALLINT NULL,
        `country` VARCHAR(150) NOT NULL DEFAULT '',
        `director` VARCHAR(200) NOT NULL DEFAULT '',
        `duration` INT NOT NULL DEFAULT 0,
        `genres` TEXT,
        `description` TEXT,
        `cover_url` TEXT,
        `admin_score` DECIMAL(3,1) NOT NULL DEFAULT 0,
        `imdb_score` DECIMAL(3,1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $c->query("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `login` VARCHAR(64) NOT NULL,
        `pass` VARCHAR(255) NOT NULL,
        `role` VARCHAR(16) NOT NULL DEFAULT 'admin',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_login` (`login`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $r = $c->query("SELECT COUNT(*) AS n FROM `admins`");
    if ($r && (int)$r->fetch_assoc()['n'] === 0) {
        $st = $c->prepare("INSERT INTO `admins` (`login`,`pass`,`role`) VALUES (?,?,?)");
        $login = 'admin'; $role = 'root'; $hash = password_hash('kinometr', PASSWORD_BCRYPT);
        $st->bind_param('sss', $login, $hash, $role); $st->execute(); $st->close();
    }
    $r = $c->query("SELECT COUNT(*) AS n FROM `movies`");
    if ($r && (int)$r->fetch_assoc()['n'] === 0) {
        $st = $c->prepare("INSERT INTO `movies`
            (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        foreach (SEED_MOVIES as $m) {
            $st->bind_param('ssisssissdd', $m[0],$m[1],$m[2],$m[3],$m[4],$m[5],$m[6],$m[7],$m[8],$m[9],$m[10]);
            $st->execute();
        }
        $st->close();
    }
    return true;
}

function normalize_movie($d) {
    if (!is_array($d)) return null;
    $title = trim((string)($d['title'] ?? ''));
    if ($title === '') return null;
    $g = isset($d['genres']) ? $d['genres'] : [];
    if (is_string($g)) {
        $dec = json_decode($g, true);
        if (is_array($dec)) $g = $dec;
        else $g = preg_split('/[,;]/u', $g);
    }
    if (!is_array($g)) $g = [];
    $genres = [];
    foreach ($g as $x) { $x = trim((string)$x); if ($x !== '') $genres[] = mb_substr($x, 0, 40); }
    $genres = array_slice(array_values(array_unique($genres)), 0, 8);
    $clamp = function ($v) { $v = (float)$v; if ($v < 0) $v = 0.0; if ($v > 10) $v = 10.0; return round($v, 1); };
    return [
        'title'          => mb_substr($title, 0, 255),
        'original_title' => mb_substr(trim((string)($d['original_title'] ?? '')), 0, 255),
        'year'           => ($d['year'] ?? null) !== '' && ($d['year'] ?? null) !== null ? (int)$d['year'] : null,
        'country'        => mb_substr(trim((string)($d['country'] ?? '')), 0, 150),
        'director'       => mb_substr(trim((string)($d['director'] ?? '')), 0, 200),
        'duration'       => max(0, (int)($d['duration'] ?? 0)),
        'genres'         => json_encode($genres, JSON_UNESCAPED_UNICODE),
        'description'    => trim((string)($d['description'] ?? '')),
        'cover_url'      => trim((string)($d['cover_url'] ?? '')),
        'admin_score'    => $clamp($d['admin_score'] ?? 0),
        'imdb_score'     => $clamp($d['imdb_score'] ?? 0),
    ];
}

function fetch_all_movies() {
    $c = db();
    $res = $c->query("SELECT * FROM `movies` ORDER BY `created_at` DESC, `id` DESC");
    $rows = [];
    while ($m = $res->fetch_assoc()) {
        $m['genres'] = json_decode((string)$m['genres'], true);
        if (!is_array($m['genres'])) $m['genres'] = [];
        $m['admin_score'] = (float)$m['admin_score'];
        $m['imdb_score']  = (float)$m['imdb_score'];
        $m['year']        = $m['year'] !== null ? (int)$m['year'] : null;
        $m['duration']    = (int)$m['duration'];
        $rows[] = $m;
    }
    return $rows;
}

/* ─────────────── API (один файл — один эндпоинт) ─────────────── */
if (isset($_GET['api'])) {
    $raw = file_get_contents('php://input');
    $in  = json_decode((string)$raw, true);
    if (!is_array($in)) $in = [];
    $act = isset($in['action']) ? (string)$in['action'] : '';

    if ($act === 'ping') {
        $ok = ensure_schema();
        jout(['ok' => true, 'db' => (bool)$ok]);
    }

    if ($act === 'list') {
        if (!ensure_schema()) jout(['ok' => false, 'db' => false]);
        jout(['ok' => true, 'db' => true, 'movies' => fetch_all_movies()]);
    }

    if ($act === 'login') {
        if (!ensure_schema()) jout(['ok' => false, 'error' => 'База данных недоступна']);
        $login = strtolower(trim((string)($in['login'] ?? '')));
        $pass  = (string)($in['pass'] ?? '');
        $st = db()->prepare("SELECT `id`,`login`,`pass`,`role` FROM `admins` WHERE LOWER(`login`)=? LIMIT 1");
        $st->bind_param('s', $login); $st->execute();
        $u = $st->get_result()->fetch_assoc(); $st->close();
        if (!$u || !password_verify($pass, $u['pass'])) jout(['ok' => false, 'error' => 'Неверный логин или пароль']);
        jout(['ok' => true, 'token' => sign(['uid' => (int)$u['id'], 'login' => $u['login'], 'role' => $u['role'], 'exp' => time() + 86400 * 7]),
              'user' => ['login' => $u['login'], 'role' => $u['role']]]);
    }

    /* --- дальше только для авторизованных --- */
    $me = verify_token(isset($in['token']) ? $in['token'] : null);
    if (!$me) jout(['ok' => false, 'auth' => false, 'error' => 'Требуется вход в админку']);
    if (!ensure_schema()) jout(['ok' => false, 'error' => 'База данных недоступна']);
    $c = db();

    if ($act === 'save') {
        $m = normalize_movie($in);
        if (!$m) jout(['ok' => false, 'error' => 'Название фильма обязательно']);
        if (!empty($in['id'])) {
            $st = $c->prepare("UPDATE `movies` SET `title`=?,`original_title`=?,`year`=?,`country`=?,`director`=?,`duration`=?,`genres`=?,`description`=?,`cover_url`=?,`admin_score`=?,`imdb_score`=? WHERE `id`=?");
            $id = (int)$in['id'];
            $st->bind_param('ssisssissddi', $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score'],$id);
            $st->execute(); $st->close();
            jout(['ok' => true, 'movies' => fetch_all_movies()]);
        }
        $st = $c->prepare("INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('ssisssissdd', $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score']);
        $st->execute(); $st->close();
        jout(['ok' => true, 'movies' => fetch_all_movies()]);
    }

    if ($act === 'delete') {
        $st = $c->prepare("DELETE FROM `movies` WHERE `id`=?");
        $id = (int)($in['id'] ?? 0);
        $st->bind_param('i', $id); $st->execute(); $st->close();
        jout(['ok' => true, 'movies' => fetch_all_movies()]);
    }

    if ($act === 'import') {
        $list = isset($in['movies']) && is_array($in['movies']) ? $in['movies'] : [];
        $added = 0;
        $st = $c->prepare("INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        foreach ($list as $d) {
            $m = normalize_movie($d);
            if (!$m) continue;
            $st->bind_param('ssisssissdd', $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score']);
            $st->execute();
            $added++;
        }
        $st->close();
        jout(['ok' => true, 'added' => $added, 'movies' => fetch_all_movies()]);
    }

    if ($act === 'admins') {
        $res = $c->query("SELECT `id`,`login`,`role`,`created_at` FROM `admins` ORDER BY `id`");
        $rows = [];
        while ($a = $res->fetch_assoc()) $rows[] = $a;
        jout(['ok' => true, 'admins' => $rows]);
    }

    if ($act === 'addAdmin') {
        if ($me['role'] !== 'root') jout(['ok' => false, 'error' => 'Добавлять админов может только главный администратор']);
        $login = strtolower(trim((string)($in['login'] ?? '')));
        $pass  = (string)($in['pass'] ?? '');
        if (!preg_match('/^[a-z0-9_\-\.]{3,32}$/u', $login)) jout(['ok' => false, 'error' => 'Логин: 3–32 символа, латиница/цифры/дефис']);
        if (mb_strlen($pass) < 6) jout(['ok' => false, 'error' => 'Пароль: минимум 6 символов']);
        $hash = password_hash($pass, PASSWORD_BCRYPT); $role = 'admin';
        $st = $c->prepare("INSERT INTO `admins` (`login`,`pass`,`role`) VALUES (?,?,?)");
        $st->bind_param('sss', $login, $hash, $role);
        if (!$st->execute()) jout(['ok' => false, 'error' => 'Такой логин уже занят']);
        $st->close();
        jout(['ok' => true]);
    }

    if ($act === 'delAdmin') {
        if ($me['role'] !== 'root') jout(['ok' => false, 'error' => 'Удалять админов может только главный администратор']);
        $id = (int)($in['id'] ?? 0);
        if ($id === (int)$me['uid']) jout(['ok' => false, 'error' => 'Нельзя удалить самого себя']);
        $st = $c->prepare("DELETE FROM `admins` WHERE `id`=? AND `role`!='root'");
        $st->bind_param('i', $id); $st->execute(); $st->close();
        jout(['ok' => true]);
    }

    jout(['ok' => false, 'error' => 'Неизвестный метод']);
}
/* если запрос не к API — отдаём сайт (всё, что ниже, это обычный HTML) */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>КИНОМЕТР — оценки фильмов: вердикт админа против IMDb</title>
<meta name="description" content="Авторский каталог фильмов: оценка админа, рейтинг IMDb, жанры, карточки с описаниями.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23e8b84b'/%3E%3Cpath d='M32 12v40M32 12L19 25M32 12l13 13' stroke='%23171310' stroke-width='7' stroke-linecap='round' stroke-linejoin='round' fill='none'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
/* ═══════════ КИНОМЕТР · дизайн «золото на угле» ═══════════ */
*{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0c0c0e; --bg2:#121216; --panel:#16161c; --panel2:#1b1b22;
  --line:#26262e; --line2:#34343e;
  --text:#eae6dc; --mut:#9a97a3;
  --gold:#e8b84b; --gold2:#f5d379; --gold3:#a8781f; --imdb:#f5c518;
  --red:#e05a4e; --green:#7fc98f;
  --disp:'Unbounded','Arial Black',sans-serif;
  --body:'Manrope','Segoe UI',sans-serif;
  --mono:'JetBrains Mono','Consolas',monospace;
}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--text);font:500 16px/1.6 var(--body);-webkit-font-smoothing:antialiased;overflow-x:hidden}
::selection{background:rgba(232,184,75,.35)}
a{color:inherit;text-decoration:none}
button{font-family:inherit}
.wrap{max-width:1180px;margin:0 auto;padding:0 24px}
.mono{font-family:var(--mono)}
.disp{font-family:var(--disp);font-weight:700;letter-spacing:-.01em}

/* атмосфера: зерно плёнки + золотые пятна света */
.grain{position:fixed;inset:0;z-index:80;pointer-events:none;opacity:.055;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2'/></filter><rect width='160' height='160' filter='url(%23n)' opacity='0.7'/></svg>")}
.glow{position:fixed;width:640px;height:640px;border-radius:50%;filter:blur(130px);pointer-events:none;z-index:0}
.g1{top:-240px;left:-180px;background:#e8b84b;opacity:.12}
.g2{bottom:-280px;right:-220px;background:#8a5a12;opacity:.09}

/* золотая «аварийная» полоса — фирменный мотив */
.stripes{background:repeating-linear-gradient(135deg,var(--gold) 0 12px,#241d0c 12px 24px)}

/* бегущая строка оценок */
#ticker{position:relative;z-index:5;background:linear-gradient(180deg,var(--gold2),var(--gold));color:#1c1508;overflow:hidden}
#ticker.off{display:none}
#ticker-track{display:flex;align-items:center;gap:28px;width:max-content;padding:8px 0;
  font:700 12px/1 var(--mono);letter-spacing:.14em;text-transform:uppercase;white-space:nowrap;
  animation:tickmove 46s linear infinite}
#ticker:hover #ticker-track{animation-play-state:paused}
.tk b{background:#1c1508;color:var(--gold2);padding:3px 8px;border-radius:5px;margin-left:7px}
.tk-sep{opacity:.55}
@keyframes tickmove{to{transform:translateX(-50%)}}

/* шапка */
#hdr{position:sticky;top:0;z-index:50;background:rgba(12,12,14,.88);backdrop-filter:blur(12px);border-bottom:1px solid var(--line)}
.hdr-in{display:flex;align-items:center;justify-content:space-between;height:66px}
.logo{display:flex;align-items:center;gap:11px;font-family:var(--disp);font-weight:900;font-size:18px;letter-spacing:.05em}
.logo b{color:var(--gold)}
.logo svg{transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.logo:hover svg{transform:rotate(180deg)}
.hdr-nav{display:flex;align-items:center;gap:20px}
.nav-link{font:700 12.5px var(--body);letter-spacing:.09em;text-transform:uppercase;color:var(--mut);transition:.2s;padding:4px 0}
.nav-link:hover{color:var(--gold)}
.nav-admin{border:1px solid var(--line2);padding:8px 15px;border-radius:9px}
.nav-admin:hover{border-color:var(--gold);color:var(--gold)}
.db-state{display:flex;align-items:center;gap:8px;font:600 11px var(--mono);color:var(--mut);letter-spacing:.04em}
.db-state em{font-style:normal}
.db-dot{width:8px;height:8px;border-radius:50%;background:#555;display:inline-block}
.db-dot.on{background:var(--green);box-shadow:0 0 10px rgba(127,201,143,.8)}
.db-dot.off{background:#e0912f;box-shadow:0 0 10px rgba(224,145,47,.7)}
.hdr-stripe{height:5px}

#view{position:relative;z-index:1;padding:44px 0 70px;min-height:62vh}

/* герой: свежее измерение */
.hero{display:grid;grid-template-columns:1.25fr .75fr;gap:48px;align-items:center;padding:30px 0 44px}
.kicker{display:inline-block;font:700 11.5px var(--mono);letter-spacing:.2em;text-transform:uppercase;color:var(--gold);
  border:1px solid rgba(232,184,75,.4);padding:7px 14px;border-radius:100px;margin-bottom:20px;background:rgba(232,184,75,.06)}
.hero h1{font-size:clamp(32px,4.8vw,60px);line-height:1.05;margin-bottom:16px}
.hero-meta{color:var(--mut);font-size:14px;margin-bottom:15px;letter-spacing:.04em}
.hero-genres{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px}
.lead{color:#c8c4bc;max-width:56ch;font-size:16.5px}
.hero-btns{display:flex;gap:14px;margin-top:26px;flex-wrap:wrap}
.hero-dial{display:flex;justify-content:center}
.dial-wrap{text-align:center}
.dial{display:block;margin:0 auto;filter:drop-shadow(0 0 28px rgba(232,184,75,.16))}
.dial-num{font:700 34px var(--mono);fill:var(--gold2)}
.dial-lab{font:600 9.5px var(--mono);fill:var(--mut);letter-spacing:.14em;text-transform:uppercase}
.imdb-big{display:inline-block;margin-top:14px;background:var(--imdb);color:#171310;font:700 14px var(--mono);padding:8px 15px;border-radius:9px;letter-spacing:.05em}

/* статистика — «билетная» лента с перфорацией */
.statbar{display:flex;margin:6px 0 56px;border:1px solid rgba(232,184,75,.35);border-radius:14px;
  background:linear-gradient(180deg,var(--panel),var(--bg2));overflow:hidden}
.stat{flex:1;padding:22px 26px;display:flex;flex-direction:column;gap:5px}
.stat + .stat{border-left:2px dashed rgba(232,184,75,.3)}
.stat b{font-size:33px;color:var(--gold2);line-height:1}
.stat span{font:700 10.5px var(--body);text-transform:uppercase;letter-spacing:.13em;color:var(--mut)}

/* каталог */
.cat-head{display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;margin-bottom:20px}
.cat-head h2{font-size:clamp(25px,3.3vw,38px)}
.cat-head h2 em{font-style:normal;color:var(--gold)}
.sub{color:var(--mut);font-size:14px;margin-top:6px}
.cat-tools{display:flex;gap:12px;flex:1;max-width:560px;min-width:280px}
#search{flex:1}
.sort{width:205px}
.chips{display:flex;flex-wrap:wrap;gap:9px;margin:6px 0 28px}
.chip{background:var(--panel);border:1px solid var(--line);color:var(--mut);padding:7px 15px;border-radius:100px;
  font:600 13px var(--body);cursor:pointer;transition:.2s}
.chip:hover{border-color:var(--gold);color:var(--gold)}
.chip.on{background:var(--gold);border-color:var(--gold);color:#171310}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(215px,1fr));gap:24px}
.empty{grid-column:1/-1;text-align:center;color:var(--mut);padding:70px 20px;border:1px dashed var(--line2);border-radius:14px}

/* карточка фильма — с золотой полосой сверху */
.card{background:var(--panel);border:1px solid var(--line);border-radius:13px;overflow:hidden;cursor:pointer;position:relative;
  transition:transform .28s cubic-bezier(.2,.7,.2,1),box-shadow .28s,border-color .28s}
.card:hover{transform:translateY(-7px);border-color:rgba(232,184,75,.55);
  box-shadow:0 18px 44px rgba(0,0,0,.5),0 10px 34px rgba(232,184,75,.13)}
.card-stripe{height:6px}
.card-cover{position:relative;aspect-ratio:2/3;overflow:hidden;background:var(--bg2)}
.card-cover img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .5s ease}
.card:hover .card-cover img{transform:scale(1.06)}
.score-badge{position:absolute;top:10px;right:10px;font:700 15px var(--mono);color:#171310;padding:5px 10px;border-radius:9px;
  box-shadow:0 6px 18px rgba(0,0,0,.45)}
.card-body{padding:15px 16px 16px}
.card-title{font:700 15.5px/1.35 var(--body);margin-bottom:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:42px}
.card-meta{color:var(--mut);font-size:12.5px;margin-bottom:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.card-genres{display:flex;flex-wrap:wrap;gap:6px;min-height:24px}
.chip-mini{font:600 11px var(--body);color:var(--gold2);background:rgba(232,184,75,.1);border:1px solid rgba(232,184,75,.25);padding:3px 9px;border-radius:100px}
.card-foot{display:flex;justify-content:space-between;align-items:center;margin-top:13px;padding-top:12px;border-top:1px dashed var(--line2)}
.imdb{font:700 11.5px var(--mono);background:var(--imdb);color:#171310;padding:3px 9px;border-radius:6px}
.more{font:700 12px var(--body);color:var(--mut);transition:.2s}
.card:hover .more{color:var(--gold)}
/* золотой блик, пробегающий по карточке при наведении */
.card::after{content:'';position:absolute;top:0;left:-80%;width:45%;height:100%;z-index:3;pointer-events:none;
  background:linear-gradient(105deg,transparent,rgba(245,211,121,.14) 45%,rgba(245,211,121,.24) 50%,rgba(245,211,121,.14) 55%,transparent);
  transform:skewX(-18deg);transition:left .75s ease}
.card:hover::after{left:135%}
.score-badge{border:1px solid rgba(23,19,16,.3);box-shadow:0 6px 18px rgba(0,0,0,.45),0 0 18px rgba(232,184,75,.28)}
/* скелетоны загрузки каталога */
.skl{position:relative;overflow:hidden;background:var(--panel);border:1px solid var(--line);border-radius:13px}
.skl::after{content:'';position:absolute;inset:0;
  background:linear-gradient(100deg,transparent 30%,rgba(232,184,75,.08) 50%,transparent 70%);
  animation:shimmer 1.5s infinite}
@keyframes shimmer{from{transform:translateX(-100%)}to{transform:translateX(100%)}}
.skl-cover{aspect-ratio:2/3;background:var(--panel2)}
.skl-line{height:12px;border-radius:6px;background:var(--panel2);margin:12px 16px 0}
.skl-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:22px;margin-top:30px}
.load-state{padding:56px 0 30px}
.load-state h1{margin:14px 0 4px}
.load-reel{display:inline-block;width:38px;height:38px;border-radius:50%;margin-top:22px;
  border:3px dashed rgba(232,184,75,.6);animation:spin 2.4s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
/* импорт: статусы строк */
.tag-dup{display:inline-block;font:700 10px var(--mono);letter-spacing:.06em;color:#f0a868;
  background:rgba(224,145,47,.12);border:1px solid rgba(224,145,47,.45);padding:2.5px 9px;border-radius:100px;white-space:nowrap}
.tag-new{display:inline-block;font:700 10px var(--mono);letter-spacing:.06em;color:var(--green);
  background:rgba(127,201,143,.1);border:1px solid rgba(127,201,143,.4);padding:2.5px 9px;border-radius:100px;white-space:nowrap}
.imp-sumrow{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:14px}
.imp-opt{display:flex;align-items:center;gap:10px;color:var(--mut);font:600 13px var(--body);cursor:pointer;user-select:none;transition:.2s}
.imp-opt:hover{color:var(--text)}
.imp-opt input{accent-color:var(--gold);width:16px;height:16px;cursor:pointer}
.preview-grid{grid-template-columns:230px}
.preview-grid .card{cursor:default}
.preview-grid .card:hover{transform:none;box-shadow:none;border-color:var(--line)}

/* появление при скролле */
.reveal{opacity:0;transform:translateY(26px);transition:opacity .65s ease,transform .65s cubic-bezier(.2,.7,.2,1)}
.reveal.in{opacity:1;transform:none}

/* кнопки и поля */
.btn{font:700 14px var(--body);padding:11px 20px;border-radius:10px;border:1px solid transparent;cursor:pointer;
  transition:.2s;display:inline-flex;align-items:center;gap:8px;background:none;color:var(--text)}
.btn-gold{background:linear-gradient(180deg,var(--gold2),var(--gold));color:#171310}
.btn-gold:hover{transform:translateY(-2px);box-shadow:0 8px 26px rgba(232,184,75,.32)}
.btn-ghost{border-color:var(--line2)}
.btn-ghost:hover{border-color:var(--gold);color:var(--gold)}
.btn-danger{border-color:rgba(224,90,78,.5);color:var(--red)}
.btn-danger:hover{background:rgba(224,90,78,.12);border-color:var(--red)}
.btn-sm{padding:7px 13px;font-size:12.5px;border-radius:8px}
.btn-wide{width:100%;justify-content:center;margin-top:8px}
.inp{width:100%;background:var(--bg2);border:1px solid var(--line);color:var(--text);padding:11px 14px;border-radius:10px;
  font:500 14px var(--body);transition:.2s}
.inp:focus{outline:none;border-color:var(--gold);box-shadow:0 0 0 3px rgba(232,184,75,.15)}
select.inp{appearance:none;cursor:pointer;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='8'><path d='M1 1l5 5 5-5' stroke='%23e8b84b' stroke-width='2' fill='none' stroke-linecap='round'/></svg>");
  background-repeat:no-repeat;background-position:right 12px center;padding-right:34px}
textarea.inp{resize:vertical;min-height:90px;line-height:1.55}
.lbl{font:700 10.5px var(--body);text-transform:uppercase;letter-spacing:.14em;color:var(--mut)}

/* модалка */
#modal{position:fixed;inset:0;z-index:100;display:none}
#modal.open{display:block}
.m-back{position:absolute;inset:0;background:rgba(5,5,7,.8);backdrop-filter:blur(6px);animation:fadein .25s ease}
.m-card{position:relative;max-width:920px;margin:4vh auto;background:var(--panel2);border:1px solid var(--line2);border-radius:18px;
  display:grid;grid-template-columns:320px 1fr;overflow:hidden;max-height:92vh;overflow-y:auto;
  animation:pop .35s cubic-bezier(.2,.7,.2,1)}
@keyframes fadein{from{opacity:0}}
@keyframes pop{from{opacity:0;transform:translateY(30px) scale(.97)}}
.m-close{position:absolute;top:14px;right:14px;z-index:5;width:38px;height:38px;border-radius:10px;
  background:rgba(12,12,14,.75);border:1px solid var(--line2);color:var(--text);font-size:15px;cursor:pointer;transition:.25s}
.m-close:hover{border-color:var(--gold);color:var(--gold);transform:rotate(90deg)}
.m-cover{position:relative}
.m-cover img{width:100%;height:100%;object-fit:cover;display:block;min-height:420px}
.m-cover .card-stripe{position:absolute;bottom:0;left:0;right:0}
.m-info{padding:32px 36px 38px}
.m-info h2{font-size:clamp(23px,3vw,32px);line-height:1.12;margin:10px 0 6px}
.orig{color:var(--mut);font-size:14px;letter-spacing:.03em}
.m-genres{display:flex;flex-wrap:wrap;gap:8px;margin:14px 0 18px}
.m-meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 16px;margin-bottom:20px}
.m-cell{background:var(--bg2);border:1px solid var(--line);border-radius:10px;padding:10px 14px;display:flex;flex-direction:column;gap:3px}
.m-cell b{font-size:14px}
.m-desc{color:#c8c4bc;font-size:15px;border-left:3px solid var(--gold);padding-left:14px;margin-bottom:24px}
.m-scores{display:flex;gap:26px;align-items:flex-start;flex-wrap:wrap}
.m-verdict{flex:1;min-width:230px}
/* вердикт — «штамп» цензора */
.verdict{font-family:var(--disp);font-weight:700;font-size:13.5px;letter-spacing:.11em;text-transform:uppercase;
  display:inline-block;border:2.5px solid currentColor;border-radius:9px;padding:9px 16px;margin:16px 0 18px;
  transform:rotate(-2.4deg);opacity:.93;transition:transform .3s ease}
.verdict:hover{transform:rotate(0deg) scale(1.02)}
.imdb-big{transition:transform .25s ease,box-shadow .25s ease}
.imdb-big:hover{transform:translateY(-2px);box-shadow:0 8px 22px rgba(245,197,24,.25)}
.bars{display:flex;flex-direction:column;gap:11px}
.bar-row{display:grid;grid-template-columns:105px 1fr 38px;gap:10px;align-items:center;font:600 12px var(--body);color:var(--mut)}
.bar{background:#26262e;border-radius:100px;height:9px;overflow:hidden}
.bar i{display:block;height:100%;border-radius:100px;transition:width 1.1s cubic-bezier(.2,.7,.2,1)}
.bar-row b{font-family:var(--mono);color:var(--text)}

/* админка */
.admin-shell{max-width:1080px;margin:0 auto}
.adm-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;margin-bottom:24px}
.adm-head h1{font-size:clamp(25px,3.2vw,36px)}
.adm-actions{display:flex;gap:10px;flex-wrap:wrap}
.atabs{display:flex;gap:6px;margin-bottom:22px;border-bottom:1px solid var(--line)}
.atab{background:none;border:none;color:var(--mut);font:700 14px var(--body);padding:12px 18px;cursor:pointer;
  border-bottom:3px solid transparent;margin-bottom:-1px;transition:.2s;letter-spacing:.03em}
.atab:hover{color:var(--text)}
.atab.on{color:var(--gold);border-bottom-color:var(--gold)}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:15px;padding:26px;margin-bottom:26px}
.panel-head{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.disp.sm{font-size:20px}
.tbl-wrap{overflow-x:auto}
.tbl{width:100%;border-collapse:collapse;min-width:660px}
.tbl th{font:700 10.5px var(--mono);text-transform:uppercase;letter-spacing:.14em;color:var(--mut);text-align:left;padding:10px 12px;border-bottom:1px solid var(--line2)}
.tbl td{padding:11px 12px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:middle}
.tbl tbody tr:hover td{background:rgba(232,184,75,.04)}
.td-cover img{width:38px;height:56px;object-fit:cover;border-radius:6px;display:block;background:var(--bg2)}
.td-sub{color:var(--mut);font-size:12px}
.td-act{white-space:nowrap}
.tbl .td-act{display:flex;gap:8px;justify-content:flex-end}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px 16px}
.f{display:flex;flex-direction:column;gap:6px}
.span2{grid-column:1/-1}
.login-card{max-width:430px;margin:6vh auto 0;background:var(--panel);border:1px solid var(--line2);border-radius:16px;overflow:hidden}
.login-body{padding:32px 34px 30px}
.login-body h2{font-size:26px;margin:4px 0 6px}
.login-body .sub{margin-bottom:22px}
.login-body form{display:flex;flex-direction:column;gap:13px}
.hint{color:var(--mut);font-size:11.5px;margin-top:18px;text-align:center;line-height:1.7}
.tag-root{background:var(--gold);color:#171310;font:700 10px var(--mono);padding:3px 8px;border-radius:5px;letter-spacing:.1em;text-transform:uppercase;margin-left:8px}
.drop{display:block;border:2px dashed var(--line2);border-radius:14px;padding:34px;text-align:center;cursor:pointer;transition:.2s;background:var(--bg2)}
.drop:hover,.drop.drag{border-color:var(--gold);background:rgba(232,184,75,.05)}
.drop-in{display:flex;flex-direction:column;align-items:center;gap:7px}
.imp-prev{margin-top:22px}
.imp-tbl{min-width:520px}
.stripe-thin{height:5px;border-radius:3px}
.preview-zone{margin-top:24px;border-top:1px dashed var(--line2);padding-top:18px}

/* тосты */
#toasts{position:fixed;right:22px;bottom:22px;z-index:200;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--panel2);border:1px solid var(--line2);border-left:4px solid var(--gold);color:var(--text);
  font:600 14px var(--body);padding:13px 18px;border-radius:11px;box-shadow:0 14px 40px rgba(0,0,0,.5);
  opacity:0;transform:translateX(30px);transition:.3s cubic-bezier(.2,.7,.2,1);max-width:340px}
.toast.err{border-left-color:var(--red)}
.toast.show{opacity:1;transform:none}

/* подвал */
footer{position:relative;z-index:1;margin-top:16px;border-top:1px solid var(--line);background:var(--bg2)}
footer .stripes{height:5px}
.foot-in{display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;padding:26px 24px;color:var(--mut);font-size:13px}

/* скроллбар */
::-webkit-scrollbar{width:11px}
::-webkit-scrollbar-track{background:var(--bg)}
::-webkit-scrollbar-thumb{background:#2c2c35;border-radius:8px;border:3px solid var(--bg)}
::-webkit-scrollbar-thumb:hover{background:var(--gold3)}

/* адаптив */
@media(max-width:940px){
  .hero{grid-template-columns:1fr;gap:30px}
  .hero-dial{order:-1}
  .statbar{flex-wrap:wrap}
  .stat{flex:1 1 46%}
  .stat + .stat{border-left:none}
  .stat:nth-child(even){border-left:2px dashed rgba(232,184,75,.3)}
  .stat:nth-child(n+3){border-top:2px dashed rgba(232,184,75,.3)}
  .m-card{grid-template-columns:1fr}
  .m-cover img{min-height:0;max-height:380px}
  .cat-tools{max-width:none;width:100%}
  .form-grid{grid-template-columns:1fr}
}
@media(max-width:560px){
  .wrap{padding:0 16px}
  .stat{flex:1 1 100%;border-left:none!important}
  .stat + .stat{border-top:2px dashed rgba(232,184,75,.3)!important}
  .grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px}
  .db-state em{display:none}
  .hdr-in{height:58px}
  .logo{font-size:15px}
}
@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{animation-duration:.01ms!important;transition-duration:.01ms!important}
}
</style>
</head>
<body>

<div class="glow g1"></div>
<div class="glow g2"></div>
<div class="grain"></div>

<div id="ticker"><div id="ticker-track"></div></div>

<header id="hdr">
  <div class="wrap hdr-in">
    <a class="logo" href="#">
      <svg viewBox="0 0 64 64" width="30" height="30" aria-hidden="true"><rect width="64" height="64" rx="14" fill="#e8b84b"/><path d="M32 12v40M32 12L19 25M32 12l13 13" stroke="#171310" stroke-width="7" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
      <span>КИНО<b>МЕТР</b></span>
    </a>
    <nav class="hdr-nav">
      <a href="#catalog" class="nav-link" data-gocat>Каталог</a>
      <a href="#/admin" class="nav-link nav-admin">Админ</a>
      <span class="db-state" id="dbstate"><i class="db-dot"></i><em>…</em></span>
    </nav>
  </div>
  <div class="hdr-stripe stripes"></div>
</header>

<main id="view" class="wrap"></main>

<footer>
  <div class="stripes"></div>
  <div class="wrap foot-in">
    <span><b style="color:var(--gold)">КИНОМЕТР</b> — авторский каталог оценок: вердикт админа против IMDb</span>
    <span class="mono">admin_score / imdb_score</span>
  </div>
</footer>

<div id="modal"></div>
<div id="toasts"></div>

<script>
/* ═══════════════ КИНОМЕТР · вся логика сайта ═══════════════ */
"use strict";

var $  = function(s, r){ return (r || document).querySelector(s); };
var $$ = function(s, r){ return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
function esc(s){
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

var API_URL = location.pathname.indexOf('.php') !== -1
  ? location.pathname.split('?')[0] + '?api'
  : 'index.php?api';

var LS = {
  get: function(k, d){ try { var v = localStorage.getItem('km_' + k); return v ? JSON.parse(v) : d; } catch(e){ return d; } },
  set: function(k, v){ try { localStorage.setItem('km_' + k, JSON.stringify(v)); } catch(e){} }
};

/* стартовый каталог (демо-режим и первая загрузка БД) */
var SEED = [
  {title:'Дюна: Часть вторая',original_title:'Dune: Part Two',year:2024,country:'США',director:'Дени Вильнёв',duration:166,genres:['фантастика','приключения','драма'],description:'Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один.',cover_url:'https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg',admin_score:8.4,imdb_score:8.5},
  {title:'Оппенгеймер',original_title:'Oppenheimer',year:2023,country:'США · Великобритания',director:'Кристофер Нолан',duration:180,genres:['биография','драма','триллер'],description:'История «отца атомной бомбы»: проект «Манхэттен», триумф науки и моральная пропасть под ногами её творцов.',cover_url:'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',admin_score:8.6,imdb_score:8.3},
  {title:'Интерстеллар',original_title:'Interstellar',year:2014,country:'США · Великобритания',director:'Кристофер Нолан',duration:169,genres:['фантастика','драма','приключения'],description:'Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома для человечества.',cover_url:'https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',admin_score:9.2,imdb_score:8.7},
  {title:'Начало',original_title:'Inception',year:2010,country:'США · Великобритания',director:'Кристофер Нолан',duration:148,genres:['фантастика','боевик','триллер'],description:'Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.',cover_url:'https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',admin_score:9.0,imdb_score:8.8},
  {title:'Паразиты',original_title:'Gisaengchung',year:2019,country:'Южная Корея',director:'Пон Джун-хо',duration:132,genres:['триллер','драма','комедия'],description:'Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, оборачивающаяся кровавой трагикомедией.',cover_url:'https://image.tmdb.org/t/p/w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg',admin_score:8.9,imdb_score:8.5},
  {title:'Побег из Шоушенка',original_title:'The Shawshank Redemption',year:1994,country:'США',director:'Фрэнк Дарабонт',duration:142,genres:['драма'],description:'Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных.',cover_url:'https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg',admin_score:9.5,imdb_score:9.1},
  {title:'Бойцовский клуб',original_title:'Fight Club',year:1999,country:'США · Германия',director:'Дэвид Финчер',duration:139,genres:['триллер','драма'],description:'Страдающий бессонницей клерк и харизматичный продавец мыла Тайлер Дёрден основывают подпольный бойцовский клуб, который перерастает в нечто большее.',cover_url:'https://image.tmdb.org/t/p/w500/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg',admin_score:8.8,imdb_score:8.8},
  {title:'Матрица',original_title:'The Matrix',year:1999,country:'США',director:'Лана и Лилли Вачовски',duration:136,genres:['фантастика','боевик'],description:'Хакер Нео узнаёт, что привычный мир — симуляция, созданная машинами, и присоединяется к повстанцам, сражающимся за свободу людей.',cover_url:'https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',admin_score:8.7,imdb_score:8.7}
];

var S = {
  db: false,
  loading: true,
  movies: [],
  q: '', genre: 'all', sort: 'new',
  view: 'site',
  admin: { user: null, token: null, tab: 'movies', editing: null, admins: [], importRows: null }
};

/* ---------- сеть ---------- */
function api(action, payload){
  return fetch(API_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(Object.assign({ action: action }, payload || {}))
  }).then(function(r){
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  });
}
function aapi(action, payload){ /* вызов от имени админа */
  return api(action, Object.assign({ token: S.admin.token }, payload || {})).then(function(r){
    if (r && r.auth === false){
      S.admin.user = null; S.admin.token = null; LS.set('session', null);
      toast('Сессия истекла — войдите снова', 'err');
      render();
      throw new Error('auth');
    }
    return r;
  });
}

/* ---------- данные ---------- */
function demoSave(){ LS.set('movies', S.movies); }
function demoAdmins(){
  var a = LS.get('admins', null);
  if (!a){ a = [{ id:1, login:'admin', pass:'kinometr', role:'root' }]; LS.set('admins', a); }
  return a;
}
function loadMovies(){
  if (S.db){
    return api('list').then(function(r){
      if (r && r.ok){ S.movies = r.movies; return; }
      S.db = false; seedDemo();
    }).catch(function(){ S.db = false; seedDemo(); });
  }
  seedDemo();
  return Promise.resolve();
}
function seedDemo(){
  var m = LS.get('movies', null);
  if (!m){
    m = SEED.map(function(x, i){
      return Object.assign({ id: i + 1, created_at: new Date(Date.now() - i * 864e5).toISOString() }, x);
    });
    LS.set('movies', m);
  }
  S.movies = m;
}
function allGenres(){
  var s = {};
  S.movies.forEach(function(m){ (m.genres || []).forEach(function(g){ s[g] = 1; }); });
  return Object.keys(s).sort();
}
function filtered(){
  var list = S.movies.slice();
  var q = S.q.trim().toLowerCase();
  if (q) list = list.filter(function(m){
    return ((m.title||'') + ' ' + (m.original_title||'') + ' ' + (m.director||'')).toLowerCase().indexOf(q) !== -1;
  });
  if (S.genre !== 'all') list = list.filter(function(m){ return (m.genres || []).indexOf(S.genre) !== -1; });
  if (S.sort === 'new')   list.sort(function(a,b){ return ((b.created_at||'') > (a.created_at||'') ? 1 : -1) || ((b.id||0) - (a.id||0)); });
  if (S.sort === 'admin') list.sort(function(a,b){ return (b.admin_score||0) - (a.admin_score||0); });
  if (S.sort === 'imdb')  list.sort(function(a,b){ return (b.imdb_score||0) - (a.imdb_score||0); });
  if (S.sort === 'year')  list.sort(function(a,b){ return (b.year||0) - (a.year||0); });
  return list;
}

/* ---------- утилиты отображения ---------- */
function scoreColor(v){ return v >= 8 ? '#e8b84b' : (v >= 6 ? '#e0912f' : '#e05a4e'); }
function verdict(v){
  return v >= 9 ? 'Обязательно к просмотру' : v >= 8 ? 'Отличное кино'
       : v >= 7 ? 'Хороший фильм' : v >= 5 ? 'Спорно, на любителя' : 'Слабое кино';
}
function fmtDur(min){
  if (!min) return '';
  var h = Math.floor(min / 60), m2 = min % 60;
  return (h ? h + ' ч ' : '') + (m2 ? m2 + ' мин' : '');
}
function parseGenres(s){
  if (Array.isArray(s)) return s;
  try { var d = JSON.parse(s); if (Array.isArray(d)) return d; } catch(e){}
  return String(s || '').split(/[,;]/).map(function(x){ return x.trim(); }).filter(Boolean);
}
function ph(t){ /* заглушка постера */
  var s = '<svg xmlns="http://www.w3.org/2000/svg" width="500" height="750">'
    + '<rect width="500" height="750" fill="#17171d"/>'
    + '<g stroke="#e8b84b" stroke-width="6" opacity="0.4"><line x1="0" y1="42" x2="500" y2="42" stroke-dasharray="18 12"/><line x1="0" y1="708" x2="500" y2="708" stroke-dasharray="18 12"/></g>'
    + '<text x="250" y="358" font-family="Arial" font-size="28" font-weight="bold" fill="#e8b84b" text-anchor="middle">' + esc(String(t || 'КИНО')).slice(0, 16) + '</text>'
    + '<text x="250" y="398" font-family="Arial" font-size="18" fill="#77747f" text-anchor="middle">КИНОМЕТР · постер</text></svg>';
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(s);
}
function dialSVG(score, size){
  var C = 2 * Math.PI * 58;
  var off = C * (1 - Math.min(10, Math.max(0, score)) / 10);
  return '<svg class="dial" viewBox="0 0 140 140" style="width:' + size + 'px;height:' + size + 'px">'
    + '<circle cx="70" cy="70" r="58" fill="none" stroke="#26262e" stroke-width="9"/>'
    + '<circle class="dial-arc" cx="70" cy="70" r="58" fill="none" stroke="' + scoreColor(score) + '" stroke-width="9" stroke-linecap="round"'
    + ' stroke-dasharray="' + C.toFixed(1) + '" data-off="' + off.toFixed(1) + '" transform="rotate(-90 70 70)"'
    + ' style="stroke-dashoffset:' + C.toFixed(1) + ';transition:stroke-dashoffset 1.1s cubic-bezier(.2,.7,.2,1)"/>'
    + '<text x="70" y="65" text-anchor="middle" class="dial-num">' + Number(score).toFixed(1) + '</text>'
    + '<text x="70" y="87" text-anchor="middle" class="dial-lab">оценка админа</text></svg>';
}
function animateDials(){
  $$('.dial-arc').forEach(function(c){ c.style.strokeDashoffset = c.getAttribute('data-off'); });
}
var revIO = null;
function revealInit(){
  var els = $$('.reveal:not(.in)');
  if (!('IntersectionObserver' in window)){ els.forEach(function(e){ e.classList.add('in'); }); return; }
  if (revIO) revIO.disconnect();
  revIO = new IntersectionObserver(function(es){
    es.forEach(function(e){ if (e.isIntersecting){ e.target.classList.add('in'); revIO.unobserve(e.target); } });
  }, { threshold: .08 });
  els.forEach(function(e){ revIO.observe(e); });
}
function toast(msg, type){
  var box = $('#toasts');
  var el = document.createElement('div');
  el.className = 'toast ' + (type || 'ok');
  el.textContent = msg;
  box.appendChild(el);
  requestAnimationFrame(function(){ el.classList.add('show'); });
  setTimeout(function(){ el.classList.remove('show'); setTimeout(function(){ el.remove(); }, 320); }, 2700);
}

/* ---------- шапка, бегущая строка ---------- */
function renderDbState(){
  var el = $('#dbstate');
  if (!el) return;
  el.innerHTML = S.db
    ? '<i class="db-dot on"></i><em>MySQL подключён</em>'
    : '<i class="db-dot off"></i><em>демо-режим</em>';
}
function renderTicker(){
  var t = $('#ticker-track');
  var top = S.movies.slice().sort(function(a,b){ return (b.admin_score||0) - (a.admin_score||0); }).slice(0, 12);
  if (!top.length){ $('#ticker').classList.add('off'); t.innerHTML = ''; return; }
  $('#ticker').classList.remove('off');
  var seg = top.map(function(m){
    return '<span class="tk">' + esc(m.title) + (m.year ? ' · ' + m.year : '') + ' <b>' + Number(m.admin_score).toFixed(1) + '</b></span>';
  }).join('<span class="tk-sep">✦</span>');
  t.innerHTML = seg + '<span class="tk-sep">✦</span>' + seg + '<span class="tk-sep">✦</span>';
}

/* ---------- главная ---------- */
function sklCards(){
  var out = '';
  for (var i = 0; i < 8; i++){
    out += '<div class="skl"><div class="skl-cover"></div>'
      + '<div class="skl-line" style="width:72%"></div>'
      + '<div class="skl-line" style="width:45%"></div>'
      + '<div class="skl-line" style="width:85%;margin-bottom:18px"></div></div>';
  }
  return out;
}
function siteHTML(){
  if (S.loading) return '<section class="load-state reveal in"><div class="kicker">✦ кинометр</div>'
    + '<h1 class="disp">Разматываем плёнку…</h1>'
    + '<p class="sub">Загружаем афишу каталога</p><div class="load-reel"></div>'
    + '<div class="skl-row">' + sklCards() + '</div></section>';
  var latest = S.movies.slice().sort(function(a,b){
    return ((b.created_at||'') > (a.created_at||'') ? 1 : -1) || ((b.id||0) - (a.id||0));
  })[0];
  return heroHTML(latest) + statsHTML() + catalogHTML();
}
function heroHTML(m){
  if (!m) return '<section class="hero"><div class="hero-txt">'
    + '<div class="kicker">✦ кинометр · авторские оценки</div>'
    + '<h1 class="disp">Каталог пока пуст</h1>'
    + '<p class="lead">Добавьте первый фильм через админ-панель — и он сразу появится здесь.</p>'
    + '<div class="hero-btns"><a class="btn btn-gold" href="#/admin">Открыть админку</a></div>'
    + '</div></section>';
  return '<section class="hero">'
    + '<div class="hero-txt">'
    + '<div class="kicker">✦ свежее измерение</div>'
    + '<h1 class="disp">' + esc(m.title) + '</h1>'
    + '<div class="hero-meta mono">' + (m.year || '—') + ' · ' + esc(m.director || 'режиссёр не указан') + (m.duration ? ' · ' + fmtDur(m.duration) : '') + '</div>'
    + '<div class="hero-genres">' + (m.genres||[]).map(function(g){ return '<span class="chip-mini">' + esc(g) + '</span>'; }).join('') + '</div>'
    + '<p class="lead">' + esc(m.description || '') + '</p>'
    + '<div class="hero-btns"><button class="btn btn-gold" data-open="' + m.id + '">Открыть карточку</button>'
    + '<a class="btn btn-ghost" href="#catalog" data-gocat>Весь каталог ↓</a></div>'
    + '</div>'
    + '<div class="hero-dial"><div class="dial-wrap">' + dialSVG(m.admin_score, 230)
    + '<div class="imdb-big">IMDb ' + Number(m.imdb_score).toFixed(1) + '</div></div></div>'
    + '</section>';
}
function statsHTML(){
  var n = S.movies.length;
  var avgA = n ? S.movies.reduce(function(s,m){ return s + (m.admin_score||0); }, 0) / n : 0;
  var avgI = n ? S.movies.reduce(function(s,m){ return s + (m.imdb_score||0); }, 0) / n : 0;
  return '<div class="statbar reveal">'
    + '<div class="stat"><b class="mono">' + n + '</b><span>фильмов в каталоге</span></div>'
    + '<div class="stat"><b class="mono">' + avgA.toFixed(1) + '</b><span>средний балл админа</span></div>'
    + '<div class="stat"><b class="mono">' + avgI.toFixed(1) + '</b><span>средний рейтинг IMDb</span></div>'
    + '<div class="stat"><b class="mono">' + allGenres().length + '</b><span>жанров</span></div>'
    + '</div>';
}
function chipsHTML(){
  return '<button class="chip' + (S.genre === 'all' ? ' on' : '') + '" data-genre="all">Все</button>'
    + allGenres().map(function(g){
      return '<button class="chip' + (S.genre === g ? ' on' : '') + '" data-genre="' + esc(g) + '">' + esc(g) + '</button>';
    }).join('');
}
function cardHTML(m, i, isStatic){
  var gs = (m.genres||[]).slice(0,3).map(function(g){ return '<span class="chip-mini">' + esc(g) + '</span>'; }).join('');
  return '<article class="card reveal" style="transition-delay:' + ((i % 8) * 55) + 'ms"' + (isStatic ? '' : ' data-open="' + m.id + '"') + '>'
    + '<div class="card-stripe stripes"></div>'
    + '<div class="card-cover"><img loading="lazy" src="' + esc(m.cover_url || '') + '" alt="' + esc(m.title) + '" data-t="' + esc(m.title) + '" onerror="this.onerror=null;this.src=ph(this.dataset.t)">'
    + '<span class="score-badge" style="background:' + scoreColor(m.admin_score||0) + '">' + Number(m.admin_score||0).toFixed(1) + '</span></div>'
    + '<div class="card-body"><h3 class="card-title">' + esc(m.title) + '</h3>'
    + '<div class="card-meta">' + (m.year || '—') + ' · ' + esc(m.director || 'реж. не указан') + '</div>'
    + '<div class="card-genres">' + gs + '</div>'
    + '<div class="card-foot"><span class="imdb">IMDb ' + Number(m.imdb_score||0).toFixed(1) + '</span>'
    + '<span class="more">' + (isStatic ? 'предпросмотр' : 'Подробнее →') + '</span></div>'
    + '</div></article>';
}
function gridHTML(){
  var list = filtered();
  if (!list.length) return '<div class="empty mono">Ничего не нашлось — попробуйте сбросить фильтры.</div>';
  return list.map(function(m, i){ return cardHTML(m, i); }).join('');
}
function catalogHTML(){
  return '<section class="catalog" id="catalog">'
    + '<div class="cat-head reveal"><div><h2 class="disp">Каталог <em>измерений</em></h2>'
    + '<p class="sub">Оценка админа против народного рейтинга IMDb · ' + S.movies.length + ' фильмов</p></div>'
    + '<div class="cat-tools"><input id="search" class="inp" type="search" placeholder="Поиск: название, режиссёр…" value="' + esc(S.q) + '">'
    + '<select id="sort" class="inp sort">'
    + '<option value="new"'   + (S.sort==='new'   ? ' selected' : '') + '>Сначала новые</option>'
    + '<option value="admin"' + (S.sort==='admin' ? ' selected' : '') + '>По оценке админа</option>'
    + '<option value="imdb"'  + (S.sort==='imdb'  ? ' selected' : '') + '>По рейтингу IMDb</option>'
    + '<option value="year"'  + (S.sort==='year'  ? ' selected' : '') + '>По году</option>'
    + '</select></div></div>'
    + '<div class="chips" id="chipsbox">' + chipsHTML() + '</div>'
    + '<div class="grid" id="gridbox">' + gridHTML() + '</div>'
    + '</section>';
}
function rerenderCatalog(){
  var ch = $('#chipsbox'), gr = $('#gridbox');
  if (ch) ch.innerHTML = chipsHTML();
  if (gr){
    gr.innerHTML = gridHTML();
    $$('.reveal', gr).forEach(function(e){ e.classList.add('in'); });
  }
}
function siteBind(){
  var se = $('#search');
  if (se) se.addEventListener('input', function(){ S.q = se.value; rerenderCatalog(); });
  var so = $('#sort');
  if (so) so.addEventListener('change', function(){ S.sort = so.value; rerenderCatalog(); });
}

/* ---------- модалка ---------- */
function metaCell(l, v){ return '<div class="m-cell"><span class="lbl">' + l + '</span><b>' + esc(v) + '</b></div>'; }
function barsHTML(m){
  return '<div class="bars">'
    + '<div class="bar-row"><span>Оценка админа</span><div class="bar"><i style="width:' + ((m.admin_score||0)*10) + '%;background:' + scoreColor(m.admin_score||0) + '"></i></div><b>' + Number(m.admin_score||0).toFixed(1) + '</b></div>'
    + '<div class="bar-row"><span>IMDb</span><div class="bar"><i style="width:' + ((m.imdb_score||0)*10) + '%;background:var(--imdb)"></i></div><b>' + Number(m.imdb_score||0).toFixed(1) + '</b></div>'
    + '</div>';
}
function openModal(id){
  var m = null;
  S.movies.forEach(function(x){ if (String(x.id) === String(id)) m = x; });
  if (!m) return;
  $('#modal').innerHTML = '<div class="m-back" data-close-modal></div>'
    + '<div class="m-card">'
    + '<button class="m-close" data-close-modal aria-label="Закрыть">✕</button>'
    + '<div class="m-cover"><img src="' + esc(m.cover_url||'') + '" alt="' + esc(m.title) + '" data-t="' + esc(m.title) + '" onerror="this.onerror=null;this.src=ph(this.dataset.t)"><div class="card-stripe stripes"></div></div>'
    + '<div class="m-info">'
    + '<div class="kicker">✦ карточка измерения</div>'
    + '<h2 class="disp">' + esc(m.title) + '</h2>'
    + (m.original_title ? '<div class="orig mono">' + esc(m.original_title) + '</div>' : '')
    + '<div class="m-genres">' + (m.genres||[]).map(function(g){ return '<span class="chip-mini">' + esc(g) + '</span>'; }).join('') + '</div>'
    + '<div class="m-meta">'
    + metaCell('Год', m.year || '—') + metaCell('Страна', m.country || '—')
    + metaCell('Режиссёр', m.director || '—') + metaCell('Хронометраж', m.duration ? fmtDur(m.duration) : '—')
    + '</div>'
    + (m.description ? '<p class="m-desc">' + esc(m.description) + '</p>' : '')
    + '<div class="m-scores">' + dialSVG(m.admin_score||0, 150)
    + '<div class="m-verdict"><div class="imdb-big">IMDb ' + Number(m.imdb_score||0).toFixed(1) + '</div>'
    + '<div class="verdict" style="color:' + scoreColor(m.admin_score||0) + '">' + verdict(m.admin_score||0) + '</div>'
    + barsHTML(m) + '</div></div></div></div>';
  $('#modal').classList.add('open');
  document.body.style.overflow = 'hidden';
  animateDials();
}
function closeModal(){
  $('#modal').classList.remove('open');
  $('#modal').innerHTML = '';
  document.body.style.overflow = '';
}

/* ---------- админка ---------- */
function adminHTML(){
  if (!S.admin.user) return loginHTML();
  return dashboardHTML();
}
function loginHTML(){
  return '<div class="admin-shell"><div class="login-card reveal in">'
    + '<div class="card-stripe stripes" style="height:8px"></div>'
    + '<div class="login-body">'
    + '<div class="kicker">✦ служебный вход</div>'
    + '<h2 class="disp">Админ-панель</h2>'
    + '<p class="sub">Доступ только для администраторов каталога</p>'
    + '<form id="login-form">'
    + '<div class="f"><label class="lbl">Логин</label><input class="inp" name="login" autocomplete="username" required></div>'
    + '<div class="f"><label class="lbl">Пароль</label><input class="inp" name="pass" type="password" autocomplete="current-password" required></div>'
    + '<button class="btn btn-gold btn-wide" type="submit">Войти в админку</button>'
    + '</form>'
    + '<p class="hint mono">' + (S.db
        ? 'Подключена база MySQL. Стандартный вход: admin / kinometr'
        : 'База не подключена — демо-режим. Вход: admin / kinometr') + '</p>'
    + '</div></div></div>';
}
function doLogin(ev){
  ev.preventDefault();
  var f = ev.target;
  var login = f.login.value.trim(), pass = f.pass.value;
  if (S.db){
    api('login', { login: login, pass: pass }).then(function(r){
      if (r && r.ok){
        S.admin.user = r.user; S.admin.token = r.token;
        LS.set('session', { user: r.user, token: r.token });
        toast('Добро пожаловать, ' + r.user.login + '!');
        render();
      } else toast((r && r.error) || 'Ошибка входа', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    var a = null;
    demoAdmins().forEach(function(x){ if (x.login === login.toLowerCase()) a = x; });
    if (a && a.pass === pass){
      S.admin.user = { login: a.login, role: a.role };
      LS.set('session', { user: S.admin.user });
      toast('Добро пожаловать (демо-режим)!');
      render();
    } else toast('Неверный логин или пароль', 'err');
  }
}
function tabBtn(t, label){
  return '<button class="atab' + (S.admin.tab === t ? ' on' : '') + '" data-tab="' + t + '">' + label + '</button>';
}
function dashboardHTML(){
  var u = S.admin.user;
  var body = S.admin.tab === 'movies' ? tabMoviesHTML()
           : S.admin.tab === 'import' ? tabImportHTML()
           : tabAdminsHTML();
  return '<div class="admin-shell">'
    + '<div class="adm-head reveal in"><div><h1 class="disp">Админ-панель</h1>'
    + '<p class="sub mono">' + esc(u.login) + ' · ' + (u.role === 'root' ? 'главный администратор' : 'администратор') + (S.db ? ' · MySQL' : ' · демо-режим') + '</p></div>'
    + '<div class="adm-actions"><a class="btn btn-ghost" href="#">← На сайт</a>'
    + '<button class="btn btn-ghost" id="logout">Выйти</button></div></div>'
    + '<div class="atabs">' + tabBtn('movies','Фильмы') + tabBtn('import','Импорт') + (u.role === 'root' ? tabBtn('admins','Админы') : '') + '</div>'
    + '<div id="tab-content">' + body + '</div>'
    + '</div>';
}
function tabMoviesHTML(){
  if (S.admin.editing !== null) return movieFormHTML();
  var rows = S.movies.map(function(m){
    return '<tr><td class="td-cover"><img src="' + esc(m.cover_url||'') + '" alt="" data-t="' + esc(m.title) + '" onerror="this.onerror=null;this.src=ph(this.dataset.t)"></td>'
      + '<td><b>' + esc(m.title) + '</b><div class="td-sub mono">' + esc(m.original_title||'') + (m.year ? ' · ' + m.year : '') + '</div></td>'
      + '<td class="mono" style="font-size:12.5px;color:var(--mut)">' + (m.genres||[]).slice(0,2).map(esc).join(', ') + '</td>'
      + '<td class="mono" style="color:' + scoreColor(m.admin_score||0) + ';font-weight:700">' + Number(m.admin_score||0).toFixed(1) + '</td>'
      + '<td class="mono">' + Number(m.imdb_score||0).toFixed(1) + '</td>'
      + '<td class="td-act"><button class="btn btn-ghost btn-sm" data-edit="' + m.id + '">Изменить</button>'
      + '<button class="btn btn-danger btn-sm" data-del="' + m.id + '">Удалить</button></td></tr>';
  }).join('');
  return '<div class="panel reveal in"><div class="panel-head">'
    + '<h3 class="disp sm">Фильмы <span class="mono" style="color:var(--mut);font-size:14px">(' + S.movies.length + ')</span></h3>'
    + '<button class="btn btn-gold" id="add-movie">+ Добавить фильм</button></div>'
    + '<div class="tbl-wrap"><table class="tbl"><thead><tr><th></th><th>Название</th><th>Жанры</th><th>Админ</th><th>IMDb</th><th style="text-align:right">Действия</th></tr></thead>'
    + '<tbody>' + (rows || '<tr><td colspan="6" class="mono" style="color:var(--mut)">Каталог пуст — добавьте первый фильм.</td></tr>') + '</tbody></table></div></div>';
}
function previewMovie(m){
  return {
    id: -1, title: m.title || 'Без названия', original_title: m.original_title || '',
    year: m.year, director: m.director || '', cover_url: m.cover_url || '',
    genres: parseGenres(m.genres),
    admin_score: parseFloat(String(m.admin_score).replace(',', '.')) || 0,
    imdb_score: parseFloat(String(m.imdb_score).replace(',', '.')) || 0
  };
}
function readForm(){
  var f = $('#movie-form');
  var num = function(name){ var v = f.elements[name].value; return v === '' ? '' : v; };
  return {
    title: f.elements['title'].value,
    original_title: f.elements['original_title'].value,
    year: num('year') === '' ? null : parseInt(num('year'), 10),
    country: f.elements['country'].value,
    director: f.elements['director'].value,
    duration: parseInt(f.elements['duration'].value, 10) || 0,
    genres: f.elements['genres'].value,
    description: f.elements['description'].value,
    cover_url: f.elements['cover_url'].value,
    admin_score: parseFloat(String(f.elements['admin_score'].value).replace(',', '.')) || 0,
    imdb_score: parseFloat(String(f.elements['imdb_score'].value).replace(',', '.')) || 0
  };
}
function fld(name, label, type, phold, val, span){
  return '<div class="f' + (span ? ' span2' : '') + '"><label class="lbl">' + label + '</label>'
    + (type === 'area'
      ? '<textarea class="inp" name="' + name + '" rows="4" placeholder="' + (phold||'') + '">' + esc(val == null ? '' : val) + '</textarea>'
      : '<input class="inp" name="' + name + '" type="' + (type||'text') + '" placeholder="' + (phold||'') + '" value="' + esc(val == null ? '' : val) + '">')
    + '</div>';
}
function movieFormHTML(){
  var isNew = S.admin.editing === 'new';
  var m = { title:'', original_title:'', year:'', country:'', director:'', duration:'', genres:'', description:'', cover_url:'', admin_score:'', imdb_score:'' };
  if (!isNew){
    S.movies.forEach(function(x){ if (String(x.id) === String(S.admin.editing)) m = x; });
    if (Array.isArray(m.genres)) m = Object.assign({}, m, { genres: m.genres.join(', ') });
  }
  return '<form id="movie-form" class="panel reveal in">'
    + '<div class="panel-head"><h3 class="disp sm">' + (isNew ? 'Новый фильм' : 'Редактирование карточки') + '</h3>'
    + '<div class="adm-actions"><button class="btn btn-ghost" type="button" id="cancel-edit">Отмена</button>'
    + '<button class="btn btn-gold" type="submit">Сохранить</button></div></div>'
    + '<div class="form-grid">'
    + fld('title','Название *','','Например: Дюна', m.title)
    + fld('original_title','Оригинальное название','','Dune: Part Two', m.original_title)
    + fld('year','Год','number','2024', m.year)
    + fld('duration','Хронометраж (мин)','number','166', m.duration)
    + fld('country','Страна','','США', m.country)
    + fld('director','Режиссёр','','Дени Вильнёв', m.director)
    + fld('genres','Жанры (через запятую)','','фантастика, драма', m.genres, true)
    + fld('admin_score','Оценка админа (0–10)','number','8.4', m.admin_score)
    + fld('imdb_score','Рейтинг IMDb (0–10)','number','8.5', m.imdb_score)
    + fld('cover_url','Ссылка на обложку (URL картинки)','url','https://…/poster.jpg', m.cover_url, true)
    + fld('description','Описание','area','О чём этот фильм и почему стоит смотреть…', m.description, true)
    + '</div>'
    + '<div class="preview-zone"><div class="lbl" style="margin-bottom:12px">Предпросмотр карточки</div>'
    + '<div class="grid preview-grid" id="live-preview">' + cardHTML(previewMovie(m), 0, true) + '</div></div>'
    + '</form>';
}
function saveMovie(ev){
  ev.preventDefault();
  var m = readForm();
  if (!m.title.trim()){ toast('Название фильма обязательно', 'err'); return; }
  var id = S.admin.editing === 'new' ? null : Number(S.admin.editing);
  function done(msg){ S.admin.editing = null; toast(msg); render(); }
  if (S.db){
    aapi('save', Object.assign({ id: id }, m)).then(function(r){
      if (r && r.ok){ S.movies = r.movies; done(id ? 'Изменения сохранены' : 'Фильм добавлен в каталог'); }
      else toast((r && r.error) || 'Ошибка сохранения', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    var rec = Object.assign({}, m, { genres: parseGenres(m.genres) });
    if (id){
      S.movies = S.movies.map(function(x){ return String(x.id) === String(id) ? Object.assign({}, x, rec) : x; });
    } else {
      var nid = S.movies.reduce(function(a,b){ return Math.max(a, b.id||0); }, 0) + 1;
      S.movies.unshift(Object.assign({ id: nid, created_at: new Date().toISOString() }, rec));
    }
    demoSave();
    done(id ? 'Сохранено (демо-режим)' : 'Добавлено (демо-режим)');
  }
}
function delMovie(id){
  var m = null;
  S.movies.forEach(function(x){ if (String(x.id) === String(id)) m = x; });
  if (!m) return;
  if (!confirm('Удалить фильм «' + m.title + '» из каталога?')) return;
  if (S.db){
    aapi('delete', { id: Number(id) }).then(function(r){
      if (r && r.ok){ S.movies = r.movies; toast('Фильм удалён'); render(); }
      else toast((r && r.error) || 'Ошибка удаления', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    S.movies = S.movies.filter(function(x){ return String(x.id) !== String(id); });
    demoSave(); toast('Удалено (демо-режим)'); render();
  }
}

/* ---------- импорт ---------- */
function csvLine(line){
  var out = [], cur = '', q = false;
  for (var i = 0; i < line.length; i++){
    var ch = line[i];
    if (q){ if (ch === '"'){ if (line[i+1] === '"'){ cur += '"'; i++; } else q = false; } else cur += ch; }
    else { if (ch === '"') q = true; else if (ch === ',' || ch === ';'){ out.push(cur); cur = ''; } else cur += ch; }
  }
  out.push(cur);
  return out;
}
function parseCSV(text){
  var lines = text.replace(/\r/g,'').split('\n').filter(function(l){ return l.trim() !== ''; });
  if (!lines.length) return [];
  var keys = ['title','original_title','year','country','director','duration','genres','description','cover_url','admin_score','imdb_score'];
  var head = csvLine(lines[0]).map(function(h){ return h.trim().toLowerCase(); });
  var start = 0, cols = keys;
  if (head.indexOf('title') !== -1){
    start = 1;
    cols = head.map(function(h){ return keys.indexOf(h) !== -1 ? h : null; });
  }
  var out = [];
  for (var i = start; i < lines.length; i++){
    var parts = csvLine(lines[i]), o = {};
    parts.forEach(function(v, j){ var k = cols[j]; if (k) o[k] = v.trim(); });
    if (o.title) out.push(o);
  }
  return out;
}
/* «всеядный» JSON: прощает висячие запятые и ключи без кавычек */
function lenientJSON(text){
  try { return JSON.parse(text); } catch(e){}
  var t2 = text.replace(/,\s*([}\]])/g, '$1');
  try { return JSON.parse(t2); } catch(e){}
  try { return JSON.parse(t2.replace(/'/g, '"')); } catch(e){ return null; }
}
/* достаёт массив фильмов из корня: [ ... ] или { "films": [ ... ] } и т.п. */
function extractFilmArray(d){
  if (Array.isArray(d)) return d;
  if (d && typeof d === 'object'){
    var keys = ['films','movies','data','items','list','results','rows'];
    for (var i = 0; i < keys.length; i++){ if (Array.isArray(d[keys[i]])) return d[keys[i]]; }
    for (var k in d){ if (Object.prototype.hasOwnProperty.call(d, k) && Array.isArray(d[k])) return d[k]; }
  }
  return null;
}
/* гибкое сопоставление чужих полей с нашими (экспорты с разных сайтов) */
function mapForeignFilm(o){
  if (!o || typeof o !== 'object') return null;
  function pick(){
    for (var i = 0; i < arguments.length; i++){
      var v = o[arguments[i]];
      if (v !== undefined && v !== null && String(v).trim() !== '') return v;
    }
    return '';
  }
  var title = pick('title','name','film','movie','ru_title');
  if (!title) return null;
  var year  = pick('year','release_year');
  var imdb  = pick('imdb_rating','imdb_score','imdb','rating_imdb');
  var admin = pick('reactor_rating','admin_rating','admin_score','site_rating','my_rating','our_rating','rating','score');
  var desc  = pick('comment','description','desc','about','annotation','text');
  var cover = pick('image_url','cover_url','poster_url','poster','image','img','cover','pic','photo');
  var orig  = pick('original_title','orig_title','en_title','title_en');
  var country  = pick('country','countries');
  var director = pick('director','directors');
  var duration = pick('duration','runtime','time_min','length');
  var genres = o.genres !== undefined ? o.genres : (o.genre !== undefined ? o.genre : []);
  if (typeof genres === 'string'){
    try { var gd = JSON.parse(genres); genres = Array.isArray(gd) ? gd : genres.split(/[,;]/); }
    catch(e){ genres = genres.split(/[,;]/); }
  }
  if (typeof country  === 'object' && country)  country  = Array.isArray(country)  ? country.join(', ')  : '';
  if (typeof director === 'object' && director) director = Array.isArray(director) ? director.join(', ') : '';
  /* чужие отзывы — добавляем к описанию */
  if (Array.isArray(o.reviews) && o.reviews.length){
    var parts = [];
    for (var i = 0; i < o.reviews.length && parts.length < 3; i++){
      var r = o.reviews[i];
      if (!r) continue;
      if (typeof r === 'object'){
        var txt = r.text || r.comment || r.body || r.content || r.review || '';
        var who = r.author || r.name || r.user || r.login || '';
        if (txt) parts.push((who ? who + ': ' : '') + txt);
      } else if (typeof r === 'string' && r.trim()) parts.push(r.trim());
    }
    if (parts.length) desc = (desc ? desc + '\n\n' : '') + parts.join('\n\n');
  }
  return {
    title: String(title).trim(),
    original_title: String(orig).trim(),
    year: year === '' ? null : (parseInt(year, 10) || null),
    country: String(country).trim(),
    director: String(director).trim(),
    duration: parseInt(duration, 10) || 0,
    genres: Array.isArray(genres) ? genres.map(function(g){ return String(g).trim(); }).filter(Boolean) : [],
    description: String(desc).trim(),
    cover_url: String(cover).trim(),
    admin_score: parseFloat(String(admin).replace(',', '.')) || 0,
    imdb_score: parseFloat(String(imdb).replace(',', '.')) || 0
  };
}
function parseImportText(text){
  text = String(text || '').trim();
  if (!text) return [];
  if (text.charAt(0) === '[' || text.charAt(0) === '{'){
    var d = lenientJSON(text);
    if (d === null){ toast('JSON не распознан — проверьте синтаксис файла', 'err'); return []; }
    var arr = extractFilmArray(d);
    if (!arr){ toast('В JSON не найден список фильмов (ожидался массив)', 'err'); return []; }
    return arr;
  }
  return parseCSV(text);
}
function normalizeRows(rawRows){
  var existing = {};
  S.movies.forEach(function(m){
    existing[String(m.title || '').trim().toLowerCase() + '|' + (m.year || '')] = 1;
  });
  var batch = {}, out = [];
  rawRows.forEach(function(r){
    var m = mapForeignFilm(r);
    if (!m) return;
    var key = m.title.toLowerCase() + '|' + (m.year || '');
    if (batch[key]) return;           /* дубль внутри самого файла */
    batch[key] = 1;
    m.dup = existing[key] ? 1 : 0;    /* уже есть в каталоге */
    out.push(m);
  });
  return out;
}
function parseImport(){
  var ta = $('#imp-text');
  var rows = normalizeRows(parseImportText(ta ? ta.value : ''));
  if (!rows.length){ toast('Не удалось распознать фильмы в файле', 'err'); return; }
  S.admin.importRows = rows;
  if (S.admin.importSkipDups === undefined) S.admin.importSkipDups = true;
  render();
}
function importEffective(){
  var rows = S.admin.importRows || [];
  var skip = S.admin.importSkipDups !== false;
  return rows.filter(function(r){ return !(r.dup && skip); }).map(function(r){
    var c = Object.assign({}, r); delete c.dup; return c;
  });
}
function updateImportSummary(){
  var rows = S.admin.importRows || [];
  var dupN = rows.filter(function(r){ return r.dup; }).length;
  var eff = importEffective();
  var sm = $('#imp-summary');
  if (sm) sm.innerHTML = 'Распознано: <b style="color:var(--gold2)">' + rows.length + '</b>'
    + (dupN ? ' · уже в каталоге: <b style="color:#e0912f">' + dupN + '</b>' : '')
    + ' · к импорту: <b style="color:var(--green)">' + eff.length + '</b>';
  var go = $('#imp-go');
  if (go){ go.textContent = 'Импортировать ' + eff.length + ' шт.'; go.disabled = !eff.length; }
}
function doImport(){
  var rows = importEffective();
  if (!rows.length){ toast('Нет новых фильмов для импорта', 'err'); return; }
  if (S.db){
    aapi('import', { movies: rows }).then(function(r){
      if (r && r.ok){
        S.movies = r.movies; S.admin.importRows = null;
        toast('Импортировано фильмов: ' + r.added); render();
      } else toast((r && r.error) || 'Ошибка импорта', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    var mx = S.movies.reduce(function(a,b){ return Math.max(a, b.id||0); }, 0);
    rows.forEach(function(rw){
      S.movies.unshift(Object.assign({ id: ++mx, created_at: new Date().toISOString() }, rw, { genres: parseGenres(rw.genres) }));
    });
    demoSave(); S.admin.importRows = null;
    toast('Импортировано (демо): ' + rows.length); render();
  }
}
function download(name, content, type){
  var blob = new Blob([content], { type: type || 'application/json;charset=utf-8' });
  var a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = name;
  document.body.appendChild(a);
  a.click();
  setTimeout(function(){ URL.revokeObjectURL(a.href); a.remove(); }, 400);
}
function tabImportHTML(){
  var rows = S.admin.importRows;
  var skip = S.admin.importSkipDups !== false;
  var effN = rows ? rows.filter(function(r){ return !(r.dup && skip); }).length : 0;
  var prev = '';
  if (rows && rows.length){
    var dupN = rows.filter(function(r){ return r.dup; }).length;
    prev = '<div class="preview-zone">'
      + '<div class="imp-sumrow">'
      + '<div class="sub mono" id="imp-summary" style="margin:0">Распознано: <b style="color:var(--gold2)">' + rows.length + '</b>'
        + (dupN ? ' · уже в каталоге: <b style="color:#e0912f">' + dupN + '</b>' : '')
        + ' · к импорту: <b style="color:var(--green)">' + effN + '</b></div>'
      + '<label class="imp-opt"><input type="checkbox" id="imp-skip-dups"' + (skip ? ' checked' : '') + '>пропускать фильмы, которые уже есть в каталоге</label>'
      + '</div>'
      + '<div class="tbl-wrap"><table class="tbl imp-tbl"><thead><tr><th>Название</th><th>Год</th><th>Админ</th><th>IMDb</th><th>Статус</th></tr></thead><tbody>'
      + rows.slice(0, 8).map(function(r){
        return '<tr><td><b>' + esc(r.title) + '</b>'
          + (r.cover_url ? ' <span class="mono" style="font-size:10.5px;color:var(--green)">постер ✓</span>' : '')
          + (r.genres && r.genres.length ? ' <span class="mono" style="font-size:10.5px;color:var(--mut)">· ' + esc(r.genres.slice(0,3).join(', ')) + '</span>' : '') + '</td>'
          + '<td class="mono">' + (r.year || '—') + '</td>'
          + '<td class="mono" style="color:var(--gold2)">' + Number(r.admin_score).toFixed(1) + '</td>'
          + '<td class="mono">' + Number(r.imdb_score).toFixed(1) + '</td>'
          + '<td>' + (r.dup ? '<span class="tag-dup">уже есть</span>' : '<span class="tag-new">новый</span>') + '</td></tr>';
      }).join('')
      + (rows.length > 8 ? '<tr><td colspan="5" class="mono" style="color:var(--mut)">… и ещё ' + (rows.length - 8) + '</td></tr>' : '')
      + '</tbody></table></div></div>';
  }
  return '<div class="panel reveal in"><div class="panel-head"><h3 class="disp sm">Импорт фильмов</h3>'
    + '<div class="adm-actions"><button class="btn btn-ghost btn-sm" id="tpl-json" type="button">Шаблон JSON</button>'
    + '<button class="btn btn-ghost btn-sm" id="tpl-csv" type="button">Шаблон CSV</button>'
    + '<button class="btn btn-ghost btn-sm" id="export-json" type="button">Экспорт каталога</button></div></div>'
    + '<label class="drop" id="drop"><input type="file" id="imp-file" accept=".json,.csv,.txt" hidden>'
    + '<div class="drop-in"><svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="#e8b84b" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15l3 3 3-3"/></svg>'
    + '<b>Перетащите файл с фильмами</b>'
    + '<span class="mono" style="color:var(--mut);font-size:12px">JSON или CSV · понимает экспорт с полями title, year, imdb_rating, reactor_rating, comment, image_url</span></div></label>'
    + '<div class="lbl" style="margin:20px 0 8px">Или вставьте содержимое файла</div>'
    + '<textarea class="inp mono" id="imp-text" rows="6" placeholder="{ &quot;films&quot;: [ { &quot;title&quot;: &quot;Прибытие&quot;, &quot;year&quot;: 2016, &quot;imdb_rating&quot;: &quot;7.9&quot;, &quot;reactor_rating&quot;: &quot;8.0&quot;, &quot;comment&quot;: &quot;…&quot;, &quot;image_url&quot;: &quot;https://…&quot; } ] }"></textarea>'
    + '<div class="adm-actions" style="margin-top:14px"><button class="btn btn-ghost" id="imp-parse" type="button">Распознать</button>'
    + (rows && rows.length
      ? '<button class="btn btn-gold" id="imp-go" type="button"' + (effN ? '' : ' disabled') + '>Импортировать ' + effN + ' шт.</button>'
        + '<button class="btn btn-ghost" id="imp-clear" type="button">Очистить</button>'
      : '') + '</div>'
    + prev + '</div>';
}

/* ---------- админы ---------- */
function tabAdminsHTML(){
  var rows = S.admin.admins.map(function(a){
    return '<tr><td class="mono" style="color:var(--mut)">#' + a.id + '</td>'
      + '<td><b>' + esc(a.login) + '</b>' + (a.role === 'root' ? '<span class="tag-root">root</span>' : '') + '</td>'
      + '<td class="mono" style="color:var(--mut);font-size:12.5px">' + esc(String(a.created_at || '').replace('T',' ').slice(0,16) || '—') + '</td>'
      + '<td class="td-act">' + (a.role === 'root'
          ? '<span class="mono" style="color:var(--mut);font-size:12px">главный — защищён</span>'
          : '<button class="btn btn-danger btn-sm" data-del-admin="' + a.id + '">Удалить</button>') + '</td></tr>';
  }).join('');
  return '<div class="panel reveal in"><div class="panel-head"><h3 class="disp sm">Администраторы</h3>'
    + '<span class="mono" style="color:var(--mut);font-size:12.5px">' + (S.db ? 'таблица admins в MySQL' : 'демо-режим') + '</span></div>'
    + '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>ID</th><th>Логин</th><th>Создан</th><th style="text-align:right">Действия</th></tr></thead><tbody>'
    + (rows || '<tr><td colspan="4" class="mono" style="color:var(--mut)">загрузка…</td></tr>') + '</tbody></table></div>'
    + '<div class="stripe-thin stripes" style="margin:24px 0"></div>'
    + '<h3 class="disp sm">Добавить администратора</h3>'
    + '<form id="add-admin" class="form-grid" style="margin-top:14px">'
    + '<div class="f"><label class="lbl">Логин (латиница, 3–32)</label><input class="inp" name="login" required></div>'
    + '<div class="f"><label class="lbl">Пароль (минимум 6 символов)</label><input class="inp" name="pass" minlength="6" required></div>'
    + '<div class="f" style="justify-content:flex-end"><button class="btn btn-gold" type="submit">+ Добавить админа</button></div>'
    + '</form></div>';
}
function loadAdmins(){
  if (S.db){
    aapi('admins').then(function(r){
      if (r && r.ok) S.admin.admins = r.admins;
      if (S.admin.tab === 'admins'){ $('#tab-content').innerHTML = tabAdminsHTML(); adminBind(); }
    }).catch(function(){});
  } else {
    S.admin.admins = demoAdmins().map(function(a){ return { id: a.id, login: a.login, role: a.role, created_at: '' }; });
    if (S.admin.tab === 'admins'){ $('#tab-content').innerHTML = tabAdminsHTML(); adminBind(); }
  }
}
function addAdmin(ev){
  ev.preventDefault();
  var f = ev.target, login = f.login.value.trim(), pass = f.pass.value;
  if (S.db){
    aapi('addAdmin', { login: login, pass: pass }).then(function(r){
      if (r && r.ok){ toast('Администратор «' + login + '» добавлен'); f.reset(); loadAdmins(); }
      else toast((r && r.error) || 'Ошибка', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    var admins = demoAdmins();
    var exists = admins.some(function(a){ return a.login === login.toLowerCase(); });
    if (exists){ toast('Такой логин уже занят', 'err'); return; }
    admins.push({ id: admins.reduce(function(x,y){ return Math.max(x, y.id); }, 0) + 1, login: login.toLowerCase(), pass: pass, role: 'admin' });
    LS.set('admins', admins);
    toast('Администратор добавлен (демо)'); f.reset(); loadAdmins();
  }
}
function delAdmin(id){
  if (!confirm('Удалить администратора #' + id + '?')) return;
  if (S.db){
    aapi('delAdmin', { id: Number(id) }).then(function(r){
      if (r && r.ok){ toast('Администратор удалён'); loadAdmins(); }
      else toast((r && r.error) || 'Ошибка', 'err');
    }).catch(function(){ toast('Сервер недоступен', 'err'); });
  } else {
    LS.set('admins', demoAdmins().filter(function(a){
      return a.role !== 'root' && String(a.id) !== String(id);
    }));
    toast('Удалено (демо)'); loadAdmins();
  }
}

/* ---------- привязка событий админки ---------- */
function adminBind(){
  var lf = $('#login-form');
  if (lf) lf.addEventListener('submit', doLogin);
  var lo = $('#logout');
  if (lo) lo.addEventListener('click', function(){
    S.admin.user = null; S.admin.token = null; S.admin.admins = [];
    LS.set('session', null);
    toast('Вы вышли из админки');
    render();
  });
  $$('.atab').forEach(function(b){
    b.addEventListener('click', function(){
      S.admin.tab = b.getAttribute('data-tab');
      render();
      if (S.admin.tab === 'admins') loadAdmins();
    });
  });
  var am = $('#add-movie');
  if (am) am.addEventListener('click', function(){ S.admin.editing = 'new'; render(); });
  $$('[data-edit]').forEach(function(b){
    b.addEventListener('click', function(){ S.admin.editing = b.getAttribute('data-edit'); render(); });
  });
  $$('[data-del]').forEach(function(b){
    b.addEventListener('click', function(){ delMovie(b.getAttribute('data-del')); });
  });
  var form = $('#movie-form');
  if (form){
    form.addEventListener('submit', saveMovie);
    form.addEventListener('input', function(){
      var pv = $('#live-preview');
      if (pv) pv.innerHTML = cardHTML(previewMovie(readForm()), 0, true);
    });
    var ce = $('#cancel-edit');
    if (ce) ce.addEventListener('click', function(){ S.admin.editing = null; render(); });
  }
  /* импорт */
  var impFile = $('#imp-file');
  if (impFile) impFile.addEventListener('change', function(){
    var file = impFile.files[0];
    if (!file) return;
    var rd = new FileReader();
    rd.onload = function(){ $('#imp-text').value = rd.result; parseImport(); };
    rd.readAsText(file);
  });
  var drop = $('#drop');
  if (drop){
    drop.addEventListener('dragover', function(e){ e.preventDefault(); drop.classList.add('drag'); });
    drop.addEventListener('dragleave', function(){ drop.classList.remove('drag'); });
    drop.addEventListener('drop', function(e){
      e.preventDefault(); drop.classList.remove('drag');
      var file = e.dataTransfer.files[0];
      if (!file) return;
      var rd = new FileReader();
      rd.onload = function(){ $('#imp-text').value = rd.result; parseImport(); };
      rd.readAsText(file);
    });
  }
  var ip = $('#imp-parse'); if (ip) ip.addEventListener('click', parseImport);
  var ig = $('#imp-go');    if (ig) ig.addEventListener('click', doImport);
  var ic = $('#imp-clear'); if (ic) ic.addEventListener('click', function(){ S.admin.importRows = null; render(); });
  var isd = $('#imp-skip-dups');
  if (isd) isd.addEventListener('change', function(){ S.admin.importSkipDups = isd.checked; updateImportSummary(); });
  var tj = $('#tpl-json');
  if (tj) tj.addEventListener('click', function(){
    download('kinometr-template.json', JSON.stringify([{
      title:'Название фильма', original_title:'Original Title', year:2024, country:'США',
      director:'Режиссёр', duration:120, genres:['драма','комедия'],
      description:'Описание фильма…', cover_url:'https://example.com/poster.jpg',
      admin_score:8.0, imdb_score:7.9
    }], null, 2));
  });
  var tc = $('#tpl-csv');
  if (tc) tc.addEventListener('click', function(){
    download('kinometr-template.csv',
      'title;original_title;year;country;director;duration;genres;description;cover_url;admin_score;imdb_score\n' +
      'Название;Original;2024;США;Режиссёр;120;"драма, комедия";Описание;https://example.com/poster.jpg;8.0;7.9',
      'text/csv;charset=utf-8');
  });
  var ej = $('#export-json');
  if (ej) ej.addEventListener('click', function(){
    download('kinometr-export.json', JSON.stringify(S.movies, null, 2));
  });
  /* админы */
  var aa = $('#add-admin');
  if (aa) aa.addEventListener('submit', addAdmin);
  $$('[data-del-admin]').forEach(function(b){
    b.addEventListener('click', function(){ delAdmin(b.getAttribute('data-del-admin')); });
  });
}

/* ---------- роутинг и инициализация ---------- */
function render(){
  renderDbState();
  renderTicker();
  var v = $('#view');
  v.innerHTML = S.view === 'admin' ? adminHTML() : siteHTML();
  if (S.view === 'admin') adminBind(); else siteBind();
  requestAnimationFrame(function(){ animateDials(); revealInit(); });
}
function syncHash(){
  S.view = location.hash === '#/admin' ? 'admin' : 'site';
  render();
  window.scrollTo({ top: 0 });
}
window.addEventListener('hashchange', syncHash);

document.addEventListener('click', function(e){
  var goCat = e.target.closest('[data-gocat]');
  if (goCat){
    e.preventDefault();
    S.view = 'site';
    if (location.hash) history.replaceState(null, '', location.pathname);
    render();
    requestAnimationFrame(function(){
      var el = $('#catalog');
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    return;
  }
  var op = e.target.closest('[data-open]');
  if (op){ openModal(op.getAttribute('data-open')); return; }
  var g = e.target.closest('[data-genre]');
  if (g){ S.genre = g.getAttribute('data-genre'); rerenderCatalog(); return; }
  if (e.target.closest('[data-close-modal]')){ closeModal(); return; }
});
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeModal(); });

function init(){
  var s = LS.get('session', null);
  if (s && s.user){ S.admin.user = s.user; S.admin.token = s.token || null; }
  syncHash();                      /* сразу показываем скелетоны загрузки */
  api('ping').then(function(p){
    S.db = !!(p && p.ok && p.db);
    return loadMovies();
  }).catch(function(){
    S.db = false;
    return loadMovies();
  }).then(function(){
    S.loading = false;
    syncHash();
  });
}
init();
</script>
</body>
</html>
