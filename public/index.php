<?php
/* ============================================================================
   КИНОМЕТР — ОДИН ФАЙЛ: ВЕСЬ САЙТ + ВЕСЬ API + MySQL
   ----------------------------------------------------------------------------
   Деплой на AwardSpace: загрузите ЭТОТ ФАЙЛ в htdocs (как index.php). Всё.

   1) Впишите пароль базы данных в DB_PASS ниже.
      (хост, логин и имя базы уже подставлены: 4772808_base @ fdb1029.awardspace.net)
   2) Таблицы movies и admins создадутся автоматически при первом открытии.
   3) Вход в админку: #/admin · admin / kinometr  (смените пароль после входа!)

   Устройство файла:
   — если в URL есть ?api=... → файл работает как API (JSON, MySQL) и завершается;
   — иначе → файл отдаёт сам сайт (HTML + CSS + JS ниже в этом же файле).
   Пока пароль БД не вписан/неверен, сайт автоматически работает в демо-режиме
   (данные хранятся в браузере) — индикатор режима всегда виден в шапке.
   ============================================================================ */
error_reporting(E_ALL);
ini_set('display_errors', '0');

const DB_HOST = 'fdb1029.awardspace.net';
const DB_PORT = 3306;
const DB_NAME = '4772808_base';
const DB_USER = '4772808_base';
const DB_PASS = '';                 // <-- ВПИШИТЕ ПАРОЛЬ БАЗЫ ДАННЫХ

const AUTH_SECRET   = 'kinometr-4772808-2026';   // можно заменить на свою строку
const TOKEN_TTL     = 60 * 60 * 12;              // токен живёт 12 часов
const ROOT_LOGIN    = 'admin';
const ROOT_PASSWORD = 'kinometr';                // пароль первого входа — смените!

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

function ensure_schema(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS `movies` (
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
    db()->exec("CREATE TABLE IF NOT EXISTS `admins` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `login` VARCHAR(64) NOT NULL,
        `pass` VARCHAR(255) NOT NULL,
        `role` VARCHAR(16) NOT NULL DEFAULT 'admin',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`), UNIQUE KEY `uq_login` (`login`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $n = (int) db()->query("SELECT COUNT(*) FROM `admins`")->fetchColumn();
    if ($n === 0) {
        $st = db()->prepare("INSERT INTO `admins` (`login`,`pass`,`role`) VALUES (?,?,?)");
        $st->execute([ROOT_LOGIN, password_hash(ROOT_PASSWORD, PASSWORD_BCRYPT), 'root']);
    }
}

function json_out($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sign(string $payload): string {
    return hash_hmac('sha256', $payload, AUTH_SECRET);
}
function make_token(int $id, string $login): string {
    $exp = time() + TOKEN_TTL;
    return $id . '.' . $exp . '.' . sign($id . '|' . $login . '|' . $exp);
}
function require_auth(): array {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_GET['token'] ?? '');
    $token = substr($token, 0, 7) === 'Bearer ' ? substr($token, 7) : $token;
    $p = explode('.', (string) $token);
    if (count($p) === 3 && (int) $p[1] > time()) {
        $st = db()->prepare("SELECT `id`,`login`,`role` FROM `admins` WHERE `id` = ?");
        $st->execute([(int) $p[0]]);
        $admin = $st->fetch();
        if ($admin && hash_equals(sign($admin['id'] . '|' . $admin['login'] . '|' . $p[1]), $p[2])) {
            return $admin;
        }
    }
    json_out(['ok' => false, 'error' => 'Требуется авторизация'], 401);
}

function norm_genres($g): array {
    $a = [];
    if (is_array($g)) $a = $g;
    elseif (is_string($g) && trim($g) !== '') {
        $t = trim($g);
        if ($t[0] === '[') { $d = json_decode($t, true); if (is_array($d)) $a = $d; }
        if (!$a) $a = preg_split('/\s*,\s*/', $t);
    }
    $out = [];
    foreach ($a as $x) {
        $x = mb_strtolower(trim((string) $x));
        if ($x !== '' && !in_array($x, $out, true)) $out[] = $x;
    }
    return array_slice($out, 0, 8);
}
function clamp_score($v): float { return round(max(0, min(10, (float) $v)), 1); }
function pick_row($r): array {
    if (!is_array($r)) return [];
    $g = $r['genres'] ?? null;
    if (is_string($g) && trim($g) !== '' && trim($g)[0] === '[') $g = json_decode($g, true);
    if (!is_array($g)) $g = is_string($g) ? $g : [];
    return [
        'title'          => trim((string) ($r['title'] ?? $r['название'] ?? $r['Title'] ?? '')),
        'original_title' => trim((string) ($r['original_title'] ?? $r['оригинальное_название'] ?? '')),
        'year'           => isset($r['year']) && $r['year'] !== '' ? (int) $r['year'] : null,
        'country'        => trim((string) ($r['country'] ?? $r['страна'] ?? '')),
        'director'       => trim((string) ($r['director'] ?? $r['режиссёр'] ?? $r['режиссер'] ?? '')),
        'duration'       => isset($r['duration']) && $r['duration'] !== '' ? (int) $r['duration'] : 0,
        'genres'         => norm_genres($g),
        'description'    => trim((string) ($r['description'] ?? $r['описание'] ?? '')),
        'cover_url'      => trim((string) ($r['cover_url'] ?? $r['cover'] ?? $r['обложка'] ?? '')),
        'admin_score'    => clamp_score($r['admin_score'] ?? $r['admin'] ?? 0),
        'imdb_score'     => clamp_score($r['imdb_score'] ?? $r['imdb'] ?? 0),
    ];
}

/* ================================ API ================================ */
$apiAction = isset($_GET['api']) ? (string) $_GET['api'] : (isset($_GET['action']) ? (string) $_GET['action'] : '');
if ($apiAction !== '') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        ensure_schema();
        switch ($apiAction) {
            case 'ping':
                json_out(['ok' => true, 'db' => true, 'server' => 'php']);
                break;

            case 'list': {
                $st = db()->query("SELECT * FROM `movies` ORDER BY `created_at` DESC, `id` DESC");
                json_out(['ok' => true, 'movies' => $st->fetchAll()]);
                break;
            }

            case 'admins': {
                require_auth();
                $st = db()->query("SELECT `id`,`login`,`role`,`created_at` FROM `admins` ORDER BY `id`");
                json_out(['ok' => true, 'admins' => $st->fetchAll()]);
                break;
            }

            case 'login': {
                $b = json_decode(file_get_contents('php://input') ?: 'null', true) ?: [];
                $login = trim((string) ($b['login'] ?? ''));
                $pass  = (string) ($b['password'] ?? '');
                $st = db()->prepare("SELECT * FROM `admins` WHERE `login` = ?");
                $st->execute([$login]);
                $a = $st->fetch();
                if (!$a || !password_verify($pass, $a['pass'])) json_out(['ok' => false, 'error' => 'Неверный логин или пароль'], 401);
                json_out(['ok' => true, 'token' => make_token((int) $a['id'], $a['login']),
                          'login' => $a['login'], 'role' => $a['role']]);
                break;
            }

            case 'save': {
                require_auth();
                $r = pick_row(json_decode(file_get_contents('php://input') ?: 'null', true));
                if ($r['title'] === '') json_out(['ok' => false, 'error' => 'Название обязательно'], 422);
                $sql = "INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)";
                $st = db()->prepare($sql);
                $st->execute([$r['title'], $r['original_title'], $r['year'], $r['country'], $r['director'],
                    $r['duration'], json_encode($r['genres'], JSON_UNESCAPED_UNICODE), $r['description'],
                    $r['cover_url'], $r['admin_score'], $r['imdb_score']]);
                $id = (int) db()->lastInsertId();
                json_out(['ok' => true, 'id' => $id]);
                break;
            }

            case 'update': {
                require_auth();
                $b = json_decode(file_get_contents('php://input') ?: 'null', true) ?: [];
                $id = (int) ($b['id'] ?? 0);
                if ($id <= 0) json_out(['ok' => false, 'error' => 'Нет id'], 422);
                $r = pick_row($b);
                if ($r['title'] === '') json_out(['ok' => false, 'error' => 'Название обязательно'], 422);
                $st = db()->prepare("UPDATE `movies` SET `title`=?,`original_title`=?,`year`=?,`country`=?,`director`=?,`duration`=?,`genres`=?,`description`=?,`cover_url`=?,`admin_score`=?,`imdb_score`=? WHERE `id`=?");
                $st->execute([$r['title'], $r['original_title'], $r['year'], $r['country'], $r['director'],
                    $r['duration'], json_encode($r['genres'], JSON_UNESCAPED_UNICODE), $r['description'],
                    $r['cover_url'], $r['admin_score'], $r['imdb_score'], $id]);
                json_out(['ok' => true, 'id' => $id]);
                break;
            }

            case 'remove': {
                require_auth();
                $id = (int) ($_GET['id'] ?? 0);
                if ($id <= 0) json_out(['ok' => false, 'error' => 'Нет id'], 422);
                db()->prepare("DELETE FROM `movies` WHERE `id` = ?")->execute([$id]);
                json_out(['ok' => true]);
                break;
            }

            case 'import': {
                require_auth();
                $b = json_decode(file_get_contents('php://input') ?: 'null', true) ?: [];
                $rows = is_array($b['rows'] ?? null) ? $b['rows'] : [];
                if (!$rows) json_out(['ok' => false, 'error' => 'Нет строк для импорта'], 422);
                $st = db()->prepare("INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`)
                                     VALUES (?,?,?,?,?,?,?,?,?,?,?)");
                $added = 0;
                db()->beginTransaction();
                foreach ($rows as $raw) {
                    $r = pick_row($raw);
                    if ($r['title'] === '') continue;
                    $st->execute([$r['title'], $r['original_title'], $r['year'], $r['country'], $r['director'],
                        $r['duration'], json_encode($r['genres'], JSON_UNESCAPED_UNICODE), $r['description'],
                        $r['cover_url'], $r['admin_score'], $r['imdb_score']]);
                    $added++;
                }
                db()->commit();
                json_out(['ok' => true, 'added' => $added]);
                break;
            }

            case 'admin_add': {
                $me = require_auth();
                if (($me['role'] ?? '') !== 'root') json_out(['ok' => false, 'error' => 'Администраторов создаёт только главный админ'], 403);
                $b = json_decode(file_get_contents('php://input') ?: 'null', true) ?: [];
                $login = strtolower(trim((string) ($b['login'] ?? '')));
                $pass  = (string) ($b['password'] ?? '');
                if (!preg_match('/^[a-z0-9_.-]{3,24}$/i', $login)) json_out(['ok' => false, 'error' => 'Логин: 3–24 символа, латиница/цифры'], 422);
                if (mb_strlen($pass) < 6) json_out(['ok' => false, 'error' => 'Пароль: минимум 6 символов'], 422);
                $st = db()->prepare("SELECT `id` FROM `admins` WHERE `login` = ?");
                $st->execute([$login]);
                if ($st->fetch()) json_out(['ok' => false, 'error' => 'Такой логин уже занят'], 409);
                $st = db()->prepare("INSERT INTO `admins` (`login`,`pass`,`role`) VALUES (?,?,?)");
                $st->execute([$login, password_hash($pass, PASSWORD_BCRYPT), 'admin']);
                json_out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
                break;
            }

            case 'admin_remove': {
                $me = require_auth();
                if (($me['role'] ?? '') !== 'root') json_out(['ok' => false, 'error' => 'Удалять админов может только главный админ'], 403);
                $id = (int) ($_GET['id'] ?? 0);
                if ($id <= 0) json_out(['ok' => false, 'error' => 'Нет id'], 422);
                if ((int) $me['id'] === $id) json_out(['ok' => false, 'error' => 'Нельзя удалить самого себя'], 422);
                $st = db()->prepare("SELECT `role` FROM `admins` WHERE `id` = ?");
                $st->execute([$id]);
                $row = $st->fetch();
                if ($row && $row['role'] === 'root') json_out(['ok' => false, 'error' => 'Главного админа удалить нельзя'], 403);
                db()->prepare("DELETE FROM `admins` WHERE `id` = ?")->execute([$id]);
                json_out(['ok' => true]);
                break;
            }

            default:
                json_out(['ok' => false, 'error' => 'Неизвестный метод'], 404);
        }
    } catch (Throwable $e) {
        $code = ((int) ($e->getCode() ?: 500)) >= 100 && ((int) $e->getCode()) < 600 ? (int) $e->getCode() : 500;
        json_out(['ok' => false, 'error' => $e->getMessage()], $code);
    }
    exit;
}

/* Если ?api не запрошен — отдаём сам сайт (всё ниже в этом же файле). */
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>КИНОМЕТР — честные оценки фильмов</title>
<meta name="description" content="Каталог фильмов с авторскими оценками и рейтингом IMDb: описание, жанры, обложки.">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%8E%AC%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;500;600;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d0d0f; --bg2:#131316; --panel:#18181c; --panel2:#1f1f24;
  --line:#2a2a31; --line2:#3a3a43;
  --ink:#f2efe8; --mut:#a6a29a; --dim:#6f6c66;
  --gold:#e8b84b; --gold2:#f5d488; --imdb:#f5c518; --red:#e05252; --green:#7dc98f;
  --disp:'Unbounded','Arial Black',sans-serif; --body:'Manrope',system-ui,sans-serif; --mono:'JetBrains Mono',ui-monospace,monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--ink);font-family:var(--body);-webkit-font-smoothing:antialiased;overflow-x:hidden}
::selection{background:var(--gold);color:#161310}
:focus-visible{outline:2px solid var(--gold);outline-offset:2px}
button{font-family:inherit}
img{display:block}
.container{max-width:1240px;margin:0 auto;padding:0 24px}
.mono{font-family:var(--mono)}

/* атмосфера */
.noise{position:fixed;inset:0;z-index:40;pointer-events:none;opacity:.055;
 background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)'/%3E%3C/svg%3E")}
.spot{position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(90px)}
.spot-a{width:520px;height:520px;background:rgba(232,184,75,.10);top:-180px;right:-140px;animation:drift 16s ease-in-out infinite alternate}
.spot-b{width:420px;height:420px;background:rgba(96,96,140,.12);bottom:-160px;left:-120px;animation:drift 20s ease-in-out infinite alternate-reverse}
@keyframes drift{to{transform:translate(-46px,38px) scale(1.06)}}

/* появление при скролле */
.reveal{opacity:0;transform:translateY(26px);transition:opacity .7s cubic-bezier(.2,.7,.2,1),transform .7s cubic-bezier(.2,.7,.2,1)}
.reveal.in{opacity:1;transform:none}

/* шапка */
.top{position:sticky;top:0;z-index:50;background:rgba(13,13,15,.94);border-bottom:1px solid var(--line)}
.top-in{display:flex;align-items:center;justify-content:space-between;height:66px;gap:16px}
.brand{display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--ink)}
.brand-t{font-family:var(--disp);font-weight:700;font-size:14px;letter-spacing:.18em}
.brand-s{font-family:var(--mono);font-size:10px;color:var(--dim);letter-spacing:.14em;margin-top:2px}
.top-right{display:flex;align-items:center;gap:12px}
.mode{display:inline-flex;align-items:center;gap:7px;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--mut);border:1px solid var(--line);padding:7px 11px;border-radius:999px;background:var(--panel)}
.mode .dot{width:7px;height:7px;border-radius:50%;background:var(--gold);box-shadow:0 0 10px var(--gold);animation:pulse 2s infinite}
.mode.demo .dot{background:var(--dim);box-shadow:none;animation:none}
@keyframes pulse{50%{opacity:.4}}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;background:var(--gold);color:#181309;border:none;border-radius:8px;padding:12px 20px;font-weight:800;font-size:13.5px;letter-spacing:.02em;cursor:pointer;transition:transform .18s,box-shadow .18s,background .18s;text-decoration:none}
.btn:hover{background:var(--gold2);transform:translateY(-2px);box-shadow:0 10px 26px rgba(232,184,75,.22)}
.btn:active{transform:translateY(0) scale(.98)}
.btn:disabled{opacity:.55;cursor:wait}
.btn.ghost{background:transparent;color:var(--ink);border:1px solid var(--line2)}
.btn.ghost:hover{border-color:var(--gold);color:var(--gold);box-shadow:none;background:rgba(232,184,75,.05)}
.btn.danger{background:var(--red);color:#fff}
.btn.danger:hover{background:#e86767;box-shadow:0 10px 24px rgba(224,82,82,.25)}
.btn.sm{padding:9px 14px;font-size:12.5px;border-radius:7px}
.btn.block{width:100%}

/* бегущая строка */
.marquee{border-bottom:1px solid var(--line);background:var(--bg2);overflow:hidden;position:relative;z-index:1}
.marquee-track{display:flex;width:max-content;animation:mar 34s linear infinite;padding:9px 0}
.marquee:hover .marquee-track{animation-play-state:paused}
@keyframes mar{to{transform:translateX(-50%)}}
.mq{display:inline-flex;align-items:center;gap:10px;font-family:var(--mono);font-size:11.5px;color:var(--mut);white-space:nowrap;padding-right:34px;letter-spacing:.05em}
.mq b{color:var(--ink);font-weight:500}
.mq i{color:var(--gold);font-style:normal;font-weight:700}
.mq em{color:var(--line2);font-style:normal}

/* свежее измерение */
.fresh{padding:72px 0 56px;position:relative;z-index:1}
.fresh-grid{display:grid;grid-template-columns:300px 1fr;gap:52px;align-items:center}
.eyebrow{font-family:var(--mono);font-size:11px;letter-spacing:.22em;text-transform:uppercase;color:var(--gold);display:flex;align-items:center;gap:10px;margin-bottom:16px}
.eyebrow::before{content:"";width:26px;height:1px;background:var(--gold)}
.fresh-title{font-family:var(--disp);font-weight:700;font-size:clamp(28px,4vw,52px);line-height:1.05;letter-spacing:-.01em}
.fresh-orig{font-family:var(--mono);color:var(--dim);font-size:13px;margin-top:12px;letter-spacing:.06em}
.fresh-meta{display:flex;flex-wrap:wrap;gap:8px;margin:20px 0}
.fresh-desc{color:var(--mut);font-size:15.5px;line-height:1.65;max-width:560px;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden}
.fresh-actions{display:flex;gap:12px;margin-top:26px;flex-wrap:wrap}
.score-col{display:flex;flex-direction:column;align-items:center;gap:14px;position:relative}
.sweep{position:absolute;inset:-30px;background:conic-gradient(from 0deg,rgba(232,184,75,.14),transparent 60%);border-radius:50%;animation:rot 14s linear infinite;pointer-events:none}
@keyframes rot{to{transform:rotate(360deg)}}
.dial{position:relative;width:210px;height:210px}
.dial svg{width:100%;height:100%;transform:rotate(-90deg)}
.dial-track{fill:none;stroke:var(--line);stroke-width:8}
.dial-arc{fill:none;stroke:url(#gauge);stroke-width:8;stroke-linecap:round;stroke-dasharray:503;stroke-dashoffset:503;transition:stroke-dashoffset 1.5s cubic-bezier(.2,.8,.2,1)}
.dial-num{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center}
.dial-num b{font-family:var(--disp);font-weight:700;font-size:52px;line-height:1}
.dial-num b em{font-style:normal;font-size:20px;color:var(--dim);font-weight:500}
.dial-num span{font-family:var(--mono);font-size:9.5px;letter-spacing:.24em;color:var(--mut);text-transform:uppercase;margin-top:8px}
.imdb-row{display:inline-flex;align-items:center;gap:8px;font-family:var(--mono);font-size:12.5px;color:var(--mut);border:1px solid var(--line);padding:8px 14px;border-radius:999px;background:var(--panel)}
.imdb-row b{color:var(--imdb)}
.score-cap{font-family:var(--mono);font-size:10px;letter-spacing:.18em;color:var(--dim);text-transform:uppercase}

/* статистика */
.stats{border-block:1px solid var(--line);background:var(--bg2);position:relative;z-index:1}
.stats-in{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;padding:30px 24px}
.stat b{font-family:var(--disp);font-weight:700;font-size:30px;display:block}
.stat span{font-family:var(--mono);font-size:10.5px;letter-spacing:.18em;text-transform:uppercase;color:var(--dim)}
.stat:nth-child(2) b{color:var(--gold)}

/* каталог */
.catalog{padding:72px 0 40px;position:relative;z-index:1}
.cat-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;flex-wrap:wrap;margin-bottom:26px}
.cat-head h2{font-family:var(--disp);font-weight:700;font-size:clamp(24px,3.2vw,38px)}
.cat-note{font-family:var(--mono);font-size:12px;color:var(--dim);letter-spacing:.06em}
.toolbar{display:flex;gap:14px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.search{flex:1;min-width:240px;display:flex;align-items:center;gap:10px;background:var(--panel);border:1px solid var(--line);border-radius:9px;padding:0 14px;transition:border-color .2s}
.search:focus-within{border-color:var(--gold)}
.search svg{color:var(--dim);flex:none}
.search input{flex:1;background:none;border:none;outline:none;color:var(--ink);font:500 14.5px var(--body);padding:13px 0}
.search input::placeholder{color:var(--dim)}
.sel{background:var(--panel);border:1px solid var(--line);color:var(--ink);border-radius:9px;padding:13px 14px;font:600 13.5px var(--body);cursor:pointer;transition:border-color .2s}
.sel:hover,.sel:focus{border-color:var(--gold);outline:none}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:30px}
.chip{border:1px solid var(--line);background:var(--panel);color:var(--mut);border-radius:999px;padding:8px 15px;font:600 12.5px var(--body);cursor:pointer;transition:all .18s;letter-spacing:.02em}
.chip:hover{border-color:var(--gold);color:var(--gold);transform:translateY(-1px)}
.chip.on{background:var(--gold);border-color:var(--gold);color:#181309}
.chip .n{font-family:var(--mono);font-size:10.5px;opacity:.65;margin-left:5px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:22px;padding-bottom:24px}
.card{background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;cursor:pointer;transition:transform .25s cubic-bezier(.2,.7,.2,1),border-color .25s,box-shadow .25s;opacity:0;transform:translateY(18px);animation:pop .5s forwards}
@keyframes pop{to{opacity:1;transform:none}}
.card:hover{transform:translateY(-6px);border-color:var(--line2);box-shadow:0 22px 44px rgba(0,0,0,.5)}
.poster{position:relative;aspect-ratio:2/3;background:var(--panel2);overflow:hidden}
.poster img{width:100%;height:100%;object-fit:cover;transition:transform .5s cubic-bezier(.2,.7,.2,1)}
.card:hover .poster img{transform:scale(1.06)}
.poster-ph{width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;color:var(--dim);background:repeating-linear-gradient(45deg,var(--panel2) 0 14px,#232329 14px 28px)}
.poster-ph span{font-family:var(--mono);font-size:10px;letter-spacing:.14em;padding:0 16px;text-align:center}
.score-badge{position:absolute;top:10px;left:10px;font-family:var(--disp);font-weight:700;font-size:13px;padding:6px 9px;border-radius:7px;color:#14120d;background:var(--gold)}
.score-badge.hi{background:var(--green)}
.score-badge.lo{background:var(--red);color:#fff}
.score-badge.na{background:#3a3a43;color:var(--mut)}
.score-badge.im{top:auto;left:auto;bottom:10px;right:10px;background:rgba(12,12,14,.88);color:var(--imdb);border:1px solid rgba(245,197,24,.35);font-size:11.5px;padding:5px 8px}
.card-body{padding:14px 14px 16px}
.card-year{font-family:var(--mono);font-size:10.5px;color:var(--dim);letter-spacing:.12em}
.card-title{font-family:var(--disp);font-weight:500;font-size:14.5px;line-height:1.3;margin:6px 0 8px}
.card-genres{font-family:var(--mono);font-size:11px;color:var(--mut)}
.empty{border:1px dashed var(--line2);border-radius:14px;padding:70px 24px;text-align:center;color:var(--mut)}
.empty b{font-family:var(--disp);display:block;color:var(--ink);font-size:18px;margin-bottom:10px}
.link{color:var(--gold);cursor:pointer;font-weight:700;text-decoration:underline;text-underline-offset:3px;background:none;border:none;font-size:inherit}

/* подвал */
.foot{border-top:1px solid var(--line);padding:26px 0 34px;color:var(--dim);font-family:var(--mono);font-size:11.5px;letter-spacing:.04em;position:relative;z-index:1}
.foot-in{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:center}
.foot .link{font-size:11.5px}

/* модалки */
.modal{position:fixed;inset:0;z-index:80;display:flex;align-items:center;justify-content:center;padding:22px;background:rgba(9,9,11,.78);backdrop-filter:blur(4px);animation:fade .25s}
@keyframes fade{from{opacity:0}}
.modal-box{background:var(--panel);border:1px solid var(--line2);border-radius:14px;width:100%;max-width:880px;max-height:90vh;overflow:auto;animation:rise .32s cubic-bezier(.2,.7,.2,1);position:relative}
@keyframes rise{from{opacity:0;transform:translateY(22px) scale(.98)}}
.modal-close{position:absolute;top:14px;right:14px;z-index:5;width:36px;height:36px;border-radius:9px;border:1px solid var(--line2);background:rgba(13,13,15,.75);color:var(--mut);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .18s}
.modal-close:hover{color:var(--red);border-color:var(--red);transform:rotate(90deg)}
.mv{display:grid;grid-template-columns:320px 1fr}
.mv-cover{position:relative;min-height:420px;background:var(--panel2)}
.mv-cover img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.mv-body{padding:34px 36px 38px}
.mv-genres{display:flex;flex-wrap:wrap;gap:7px;margin:14px 0 18px}
.tag{border:1px solid var(--line2);border-radius:999px;padding:5px 12px;font-family:var(--mono);font-size:11px;color:var(--mut)}
.mv-meta{display:grid;grid-template-columns:1fr 1fr;gap:12px 20px;margin:18px 0;padding:16px 0;border-block:1px solid var(--line)}
.mv-meta dt{font-family:var(--mono);font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--dim)}
.mv-meta dd{font-weight:600;font-size:14px;margin-top:4px}
.mv-desc{color:var(--mut);line-height:1.7;font-size:15px}
.mv-scores{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:22px}
.score-box{border:1px solid var(--line);border-radius:11px;padding:16px 18px;background:var(--bg2)}
.score-box label{font-family:var(--mono);font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--dim);display:block}
.score-box b{font-family:var(--disp);font-size:34px;font-weight:700;display:block;margin:6px 0 10px}
.score-box .bar{height:6px;background:var(--line);border-radius:99px;overflow:hidden}
.score-box .bar i{display:block;height:100%;border-radius:99px;background:var(--gold);transition:width 1s cubic-bezier(.2,.7,.2,1)}
.score-box.imdb b{color:var(--imdb)}
.score-box.imdb .bar i{background:var(--imdb)}
.verdict{margin-top:16px;font-family:var(--mono);font-size:13px;color:var(--gold);border:1px dashed var(--line2);border-radius:9px;padding:12px 16px}

/* админка */
.admin{min-height:calc(100vh - 67px);display:grid;grid-template-columns:240px 1fr;position:relative;z-index:1}
.side{border-right:1px solid var(--line);background:var(--bg2);padding:26px 18px;display:flex;flex-direction:column;gap:6px}
.side-user{border:1px solid var(--line);border-radius:10px;padding:12px 14px;margin-bottom:14px;background:var(--panel)}
.side-user b{display:block;font-size:14px}
.side-user span{font-family:var(--mono);font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--gold)}
.nav-item{display:flex;align-items:center;gap:11px;padding:11px 13px;border-radius:9px;color:var(--mut);font-weight:700;font-size:13.5px;cursor:pointer;border:1px solid transparent;transition:all .16s;background:none;text-align:left;width:100%}
.nav-item:hover{color:var(--ink);background:var(--panel)}
.nav-item.on{color:var(--gold);border-color:var(--line2);background:var(--panel)}
.nav-item .cnt{margin-left:auto;font-family:var(--mono);font-size:10.5px;color:var(--dim)}
.nav-item.danger{margin-top:auto;color:var(--red)}
.nav-item.danger:hover{background:rgba(224,82,82,.08)}
.main{padding:30px 34px 60px;min-width:0}
.page-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.page-head h1{font-family:var(--disp);font-weight:700;font-size:24px}
.page-head .sub{font-family:var(--mono);font-size:11.5px;color:var(--dim);margin-top:6px;letter-spacing:.04em}
.panel{background:var(--panel);border:1px solid var(--line);border-radius:12px;padding:22px;margin-bottom:20px}
.panel h3{font-family:var(--disp);font-weight:500;font-size:15px;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.panel h3 .mono{font-size:10px;color:var(--dim);letter-spacing:.16em;font-weight:400}
table.tbl{width:100%;border-collapse:collapse;font-size:13.5px}
.tbl th{font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);text-align:left;padding:10px 12px;border-bottom:1px solid var(--line2)}
.tbl td{padding:12px;border-bottom:1px solid var(--line);vertical-align:middle}
.tbl tr{transition:background .15s}
.tbl tbody tr:hover{background:var(--panel2)}
.tbl .th-cover{width:52px}
.mini-cover{width:38px;height:56px;object-fit:cover;border-radius:5px;border:1px solid var(--line2);background:var(--panel2)}
.mini-ph{width:38px;height:56px;border-radius:5px;border:1px solid var(--line2);display:flex;align-items:center;justify-content:center;color:var(--dim);background:var(--panel2)}
.tbl .title-cell b{display:block;font-weight:700}
.tbl .title-cell span{font-family:var(--mono);font-size:10.5px;color:var(--dim)}
.score-pill{font-family:var(--disp);font-weight:700;font-size:12px;border-radius:6px;padding:4px 8px;background:rgba(232,184,75,.14);color:var(--gold)}
.score-pill.im{background:rgba(245,197,24,.1);color:var(--imdb)}
.row-actions{display:flex;gap:8px;justify-content:flex-end}
.icon-btn{width:34px;height:34px;border-radius:8px;border:1px solid var(--line2);background:var(--panel);color:var(--mut);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .16s}
.icon-btn:hover{color:var(--gold);border-color:var(--gold);transform:translateY(-1px)}
.icon-btn.del:hover{color:var(--red);border-color:var(--red)}
/* формы */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{display:flex;flex-direction:column;gap:6px}
.field.full{grid-column:1/-1}
.field label{font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.field label b{color:var(--gold)}
.field input,.field textarea,.field select{background:var(--bg2);border:1px solid var(--line);border-radius:8px;color:var(--ink);padding:11px 13px;font:500 14px var(--body);outline:none;transition:border-color .18s;width:100%}
.field input:focus,.field textarea:focus,.field select:focus{border-color:var(--gold)}
.field textarea{resize:vertical;min-height:96px;line-height:1.55}
.field .hint{font-family:var(--mono);font-size:10.5px;color:var(--dim)}
.form-foot{display:flex;gap:12px;margin-top:18px;justify-content:flex-end;flex-wrap:wrap}
.edit-layout{display:grid;grid-template-columns:1fr 300px;gap:26px;align-items:start}
.preview-card{background:var(--panel);border:1px solid var(--line);border-radius:12px;overflow:hidden;position:sticky;top:90px}
.preview-card .poster{cursor:default}
.preview-label{font-family:var(--mono);font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:var(--dim);text-align:center;padding:10px}
.dropzone{border:1.5px dashed var(--line2);border-radius:12px;padding:34px 22px;text-align:center;color:var(--mut);cursor:pointer;transition:all .2s;background:var(--bg2)}
.dropzone:hover,.dropzone.over{border-color:var(--gold);color:var(--gold);background:rgba(232,184,75,.04)}
.dropzone b{display:block;font-family:var(--disp);font-weight:500;font-size:14px;color:var(--ink);margin:12px 0 6px}
.dropzone .hint{font-family:var(--mono);font-size:11px;color:var(--dim)}
.imp-row{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:10px;padding:10px 14px;margin-bottom:8px;background:var(--bg2);font-size:13px}
.imp-row .st{width:8px;height:8px;border-radius:50%;background:var(--green);flex:none}
.imp-row .t{font-weight:700}
.imp-row .m{font-family:var(--mono);font-size:11px;color:var(--dim);margin-left:auto;text-align:right}
.imp-row.warn{opacity:.55}
.imp-row.warn .st{background:var(--red)}
.login-wrap{min-height:calc(100vh - 67px);display:flex;align-items:center;justify-content:center;padding:30px;position:relative;z-index:1}
.login-box{width:100%;max-width:400px;background:var(--panel);border:1px solid var(--line2);border-radius:14px;padding:34px 32px}
.login-box h1{font-family:var(--disp);font-size:22px;font-weight:700;margin:14px 0 6px}
.login-box .sub{font-family:var(--mono);font-size:11.5px;color:var(--dim);letter-spacing:.05em;margin-bottom:24px}
.login-box .field{margin-bottom:14px}
.login-err{color:var(--red);font-size:13px;font-weight:600;min-height:18px;margin-bottom:8px}
.login-hint{margin-top:18px;font-family:var(--mono);font-size:11px;color:var(--dim);border-top:1px dashed var(--line2);padding-top:14px;line-height:1.7}
.admin-badge{font-family:var(--mono);font-size:10px;letter-spacing:.16em;text-transform:uppercase;padding:3px 9px;border-radius:99px;border:1px solid var(--line2);color:var(--mut)}
.admin-badge.root{color:var(--gold);border-color:rgba(232,184,75,.4)}
.spin{width:16px;height:16px;border:2px solid rgba(24,19,9,.3);border-top-color:#181309;border-radius:50%;animation:rot .7s linear infinite;display:inline-block}
.toast-stack{position:fixed;right:20px;bottom:20px;z-index:100;display:flex;flex-direction:column;gap:10px}
.toast{background:var(--panel);border:1px solid var(--line2);border-left:3px solid var(--gold);border-radius:10px;padding:13px 18px;font-weight:600;font-size:13.5px;box-shadow:0 16px 40px rgba(0,0,0,.5);animation:toast-in .3s cubic-bezier(.2,.7,.2,1);max-width:340px}
.toast.err{border-left-color:var(--red)}
@keyframes toast-in{from{opacity:0;transform:translateX(30px)}}
.confirm-box{max-width:420px;padding:28px 30px}
.confirm-box h3{font-family:var(--disp);font-size:17px;margin-bottom:10px}
.confirm-box p{color:var(--mut);font-size:14px;line-height:1.6}
.confirm-foot{display:flex;gap:10px;justify-content:flex-end;margin-top:22px}

@media (max-width:960px){
  .fresh-grid{grid-template-columns:1fr;gap:34px}
  .score-col{order:-1}
  .stats-in{grid-template-columns:repeat(2,1fr)}
  .mv{grid-template-columns:1fr}
  .mv-cover{min-height:340px}
  .edit-layout{grid-template-columns:1fr}
  .preview-card{position:static}
  .admin{grid-template-columns:1fr}
  .side{flex-direction:row;flex-wrap:wrap;border-right:none;border-bottom:1px solid var(--line);padding:14px}
  .side-user{display:none}
  .nav-item.danger{margin-top:0}
  .main{padding:24px 18px 60px}
  .form-grid{grid-template-columns:1fr}
  .mv-scores{grid-template-columns:1fr}
  .brand-s{display:none}
}
</style>
</head>
<body>
<div id="app"></div>
<div class="noise"></div>
<div class="spot spot-a"></div>
<div class="spot spot-b"></div>
<div id="toasts" class="toast-stack"></div>

<script>
"use strict";
/* =====================================================================
   КИНОМЕТР — фронтенд (весь JS сайта в этом же файле).
   API живёт в начале этого же index.php и вызывается через ?api=...
   ===================================================================== */

/* ---------- мини-DOM-хелперы ---------- */
function h(tag, attrs, ...kids) {
  const el = document.createElement(tag);
  if (attrs) for (const k in attrs) {
    const v = attrs[k];
    if (k === "class") el.className = v;
    else if (k === "html") el.innerHTML = v;
    else if (k.startsWith("on") && typeof v === "function") el.addEventListener(k.slice(2), v);
    else if (v !== null && v !== undefined && v !== false) el.setAttribute(k, v === true ? "" : v);
  }
  for (const kid of kids.flat(Infinity)) {
    if (kid === null || kid === undefined || kid === false) continue;
    el.append(kid.nodeType ? kid : document.createTextNode(kid));
  }
  return el;
}
const $app = document.getElementById("app");
function mount(node) { $app.innerHTML = ""; $app.append(node); bindReveals(); }
function fmtDate(s) {
  if (!s) return "—";
  try { return new Date(s.replace ? s.replace(" ", "T") : s).toLocaleDateString("ru-RU", { day: "2-digit", month: "2-digit", year: "numeric" }); }
  catch (e) { return s; }
}
function plural(n, one, few, many) {
  const m10 = n % 10, m100 = n % 100;
  if (m10 === 1 && m100 !== 11) return one;
  if (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) return few;
  return many;
}
function fmtDur(min) { if (!min) return ""; const H = Math.floor(min / 60), M = min % 60; return (H ? H + " ч " : "") + (M ? M + " мин" : ""); }
function scoreClass(v) { if (v === null || v === undefined || v <= 0) return "na"; return v >= 8 ? "hi" : v < 5 ? "lo" : ""; }
function fmtScore(v) { return (v > 0 ? Number(v).toFixed(1) : "—"); }
function verdictFor(v) {
  if (v >= 9) return "обязательно к просмотру — эталон";
  if (v >= 8) return "отличное кино, рекомендуем";
  if (v >= 7) return "крепкая работа, стоит вечера";
  if (v >= 5) return "средне — на один раз";
  return "слабо, время дороже";
}

/* ---------- SVG-иконки ---------- */
function icon(name, size) {
  size = size || 16;
  const P = {
    play:    "M8 5v14l11-7z",
    search:  "M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 1 1-13 0 6.5 6.5 0 0 1 13 0z",
    plus:    "M12 5v14M5 12h14",
    x:       "M18 6L6 18M6 6l12 12",
    edit:    "M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z",
    trash:   "M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6",
    upload:  "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12",
    down:    "M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3",
    users:   "M17 21v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 3.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8zM23 21v-2a4 4 0 0 0-3-3.87M15.5 3.63a4 4 0 0 1 0 7.75",
    film:    "M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM3 8h18M3 16h18M8 3v18M16 3v18",
    star:    "M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z",
    lock:    "M5 11h14v10H5zM7 11V7a5 5 0 0 1 10 0v4",
    gauge:   "M12 21a9 9 0 1 1 9-9M12 12l4.5-4.5",
    out:     "M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9",
    alert:   "M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z",
    check:   "M20 6L9 17l-5-5",
    file:    "M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zM14 2v6h6",
    info:    "M12 16v-4m0-4h.01M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0z"
  };
  return h("svg", { width: size, height: size, viewBox: "0 0 24 24", fill: name === "play" ? "currentColor" : "none", stroke: "currentColor", "stroke-width": "2", "stroke-linecap": "round", "stroke-linejoin": "round" }, h("path", { d: P[name] || P.film }));
}

/* ---------- тосты ---------- */
function toast(msg, type) {
  const t = h("div", { class: "toast" + (type === "err" ? " err" : "") }, msg);
  document.getElementById("toasts").append(t);
  setTimeout(() => { t.style.opacity = "0"; t.style.transition = "opacity .3s"; setTimeout(() => t.remove(), 320); }, 3400);
}

/* ---------- подтверждение ---------- */
let confirmResolver = null;
function askConfirm(title, text, dangerLabel) {
  const box = h("div", { class: "modal-box confirm-box" },
    h("button", { class: "modal-close", onclick: () => closeConfirm(false) }, icon("x", 15)),
    h("h3", null, title),
    h("p", null, text),
    h("div", { class: "confirm-foot" },
      h("button", { class: "btn ghost sm", onclick: () => closeConfirm(false) }, "Отмена"),
      h("button", { class: "btn danger sm", onclick: () => closeConfirm(true) }, dangerLabel || "Удалить")));
  const m = h("div", { class: "modal", onclick: (e) => { if (e.target === m) closeConfirm(false); } }, box);
  document.body.append(m);
  return new Promise(res => { confirmResolver = (v) => { m.remove(); res(v); }; });
}
function closeConfirm(v) { if (confirmResolver) { confirmResolver(v); confirmResolver = null; } }

/* ---------- scroll-reveal ---------- */
const revealIO = new IntersectionObserver((es) => es.forEach(e => { if (e.isIntersecting) { e.target.classList.add("in"); revealIO.unobserve(e.target); } }), { threshold: 0.08 });
function bindReveals() { document.querySelectorAll(".reveal:not(.in)").forEach(el => revealIO.observe(el)); }

/* ---------- данные и состояние ---------- */
const LS = { movies: "kinometr_movies_v1", admins: "kinometr_admins_v1", session: "kinometr_session_v1" };
const PH = "data:image/svg+xml;utf8," + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="400" height="600"><rect width="400" height="600" fill="#1c1c21"/><g fill="none" stroke="#34343c" stroke-width="2"><circle cx="200" cy="268" r="84"/><circle cx="200" cy="268" r="58"/><circle cx="200" cy="268" r="10" fill="#34343c"/></g><rect x="118" y="252" width="16" height="32" rx="3" fill="#34343c"/><rect x="266" y="252" width="16" height="32" rx="3" fill="#34343c"/><text x="200" y="420" font-family="monospace" font-size="21" fill="#5c5c66" text-anchor="middle" letter-spacing="5">КИНОМЕТР</text></svg>');
const API_BASE = "?api=";   // API — в начале этого же файла index.php

const SEED = [
  { id: 1, title: "Дюна: Часть вторая", original_title: "Dune: Part Two", year: 2024, country: "США", director: "Дени Вильнёв", duration: 166, genres: ["фантастика", "приключения", "драма"], description: "Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один.", cover_url: "https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg", admin_score: 8.4, imdb_score: 8.5, created_at: "2026-02-08 12:00:00" },
  { id: 2, title: "Оппенгеймер", original_title: "Oppenheimer", year: 2023, country: "США", director: "Кристофер Нолан", duration: 180, genres: ["биография", "драма", "триллер"], description: "История «отца атомной бомбы» Роберта Оппенгеймера: проект «Манхэттен», триумф науки и моральная пропасть под ногами её творцов.", cover_url: "https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", admin_score: 8.6, imdb_score: 8.3, created_at: "2026-02-05 12:00:00" },
  { id: 3, title: "Интерстеллар", original_title: "Interstellar", year: 2014, country: "США", director: "Кристофер Нолан", duration: 169, genres: ["фантастика", "драма", "приключения"], description: "Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома для человечества.", cover_url: "https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", admin_score: 9.2, imdb_score: 8.7, created_at: "2026-01-28 12:00:00" },
  { id: 4, title: "Начало", original_title: "Inception", year: 2010, country: "США", director: "Кристофер Нолан", duration: 148, genres: ["фантастика", "боевик", "триллер"], description: "Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.", cover_url: "https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg", admin_score: 9.0, imdb_score: 8.8, created_at: "2026-01-20 12:00:00" },
  { id: 5, title: "Паразиты", original_title: "Gisaengchung", year: 2019, country: "Южная Корея", director: "Пон Джун-хо", duration: 132, genres: ["триллер", "драма", "комедия"], description: "Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, оборачивающаяся кровавой трагикомедией.", cover_url: "https://image.tmdb.org/t/p/w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg", admin_score: 8.9, imdb_score: 8.5, created_at: "2026-01-14 12:00:00" },
  { id: 6, title: "Побег из Шоушенка", original_title: "The Shawshank Redemption", year: 1994, country: "США", director: "Фрэнк Дарабонт", duration: 142, genres: ["драма"], description: "Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных.", cover_url: "https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg", admin_score: 9.5, imdb_score: 9.1, created_at: "2026-01-10 12:00:00" },
  { id: 7, title: "Бегущий по лезвию 2049", original_title: "Blade Runner 2049", year: 2017, country: "США", director: "Дени Вильнёв", duration: 164, genres: ["фантастика", "триллер"], description: "Репликант-бегущий по лезвию К раскрывает тайну, которая может перевернуть отношения людей и репликантов.", cover_url: "https://image.tmdb.org/t/p/w500/gajva2L0rPYkEWjzgFlBXCAVBE5.jpg", admin_score: 8.7, imdb_score: 8.0, created_at: "2025-12-22 12:00:00" },
  { id: 8, title: "1917", original_title: "1917", year: 2019, country: "Великобритания", director: "Сэм Мендес", duration: 119, genres: ["военный", "драма"], description: "Два солдата получают приказ доставить сообщение, способное спасти 1600 человек, — и у них меньше суток.", cover_url: "https://image.tmdb.org/t/p/w500/iZf0KyrE25z1sage4SYFLCCrMi9.jpg", admin_score: 8.3, imdb_score: 7.8, created_at: "2025-12-15 12:00:00" },
  { id: 9, title: "Одержимость", original_title: "Whiplash", year: 2014, country: "США", director: "Дэмьен Шазелл", duration: 106, genres: ["драма", "музыка"], description: "Молодой барабанщик попадает к дирижёру, для которого величие оправдывает любую жестокость.", cover_url: "https://image.tmdb.org/t/p/w500/7fn624j5lj3xTme2SgiLCeuedmO.jpg", admin_score: 8.8, imdb_score: 8.5, created_at: "2025-12-01 12:00:00" },
  { id: 10, title: "Драйв", original_title: "Drive", year: 2011, country: "США", director: "Николас Виндинг Рефн", duration: 100, genres: ["криминал", "триллер"], description: "Безымянный водитель-каскадёр по ночам возит грабителей. Одна встреча меняет правила его молчаливой игры.", cover_url: "https://image.tmdb.org/t/p/w500/602vevIURmpDfztn4iKSo5cQxkC.jpg", admin_score: 8.0, imdb_score: 7.8, created_at: "2025-11-18 12:00:00" },
  { id: 11, title: "Гравитация", original_title: "Gravity", year: 2013, country: "США", director: "Альфонсо Куарон", duration: 91, genres: ["фантастика", "триллер"], description: "После катастрофы на орбите астронавтка Райан Стоун остаётся одна в безмолвной пустоте над Землёй.", cover_url: "https://image.tmdb.org/t/p/w500/kZ2naSg5pcLbAagZsepo6AeDqYu.jpg", admin_score: 8.1, imdb_score: 7.7, created_at: "2025-11-05 12:00:00" },
  { id: 12, title: "Отель «Гранд Будапешт»", original_title: "The Grand Budapest Hotel", year: 2014, country: "США, Германия", director: "Уэс Андерсон", duration: 99, genres: ["комедия", "приключения"], description: "Консьерж легендарного отеля и его юный протеже втянуты в историю с украденной картиной и семейным состоянием.", cover_url: "https://image.tmdb.org/t/p/w500/eWdyYQreja6JGCzqHWXpWHDrrPo.jpg", admin_score: 8.4, imdb_score: 8.1, created_at: "2025-10-22 12:00:00" }
];

const state = {
  movies: [], mode: null, booted: false,
  session: null, admins: [],
  query: "", genre: null, sort: "new",
  adminTab: "movies", editing: null, importRows: null,
  modalMovie: null, confirm: null
};

function normalizeMovie(m) {
  let g = m.genres;
  if (typeof g === "string") {
    const t = g.trim();
    if (t.startsWith("[")) { try { g = JSON.parse(t); } catch (e) { g = []; } }
    else g = t ? t.split(",").map(s => s.trim()) : [];
  }
  return Object.assign({}, m, {
    genres: (Array.isArray(g) ? g : []).map(s => String(s).trim()).filter(Boolean),
    admin_score: parseFloat(m.admin_score) || 0,
    imdb_score: parseFloat(m.imdb_score) || 0,
    year: m.year ? parseInt(m.year, 10) : null,
    duration: parseInt(m.duration, 10) || 0,
    id: parseInt(m.id, 10)
  });
}

function api(method, action, body) {
  const opts = { method, headers: {} };
  if (body !== undefined) { opts.headers["Content-Type"] = "application/json"; opts.body = JSON.stringify(body); }
  const tok = state.session && state.session.token;
  if (tok) opts.headers["Authorization"] = "Bearer " + tok;
  return fetch(API_BASE + action, opts).then(r => {
    const ct = r.headers.get("content-type") || "";
    if (!ct.includes("application/json")) throw new Error("Сервер не отвечает JSON (проверьте, что файл открыт через PHP-хостинг)");
    return r.json().then(d => { if (!d || d.ok !== true) throw new Error((d && d.error) || "Ошибка API"); return d; });
  });
}

/* локальный (демо) режим, если БД недоступна */
function lsGet(key, fallback) { try { const v = JSON.parse(localStorage.getItem(key)); return v === null || v === undefined ? fallback : v; } catch (e) { return fallback; } }
function lsSet(key, v) { try { localStorage.setItem(key, JSON.stringify(v)); } catch (e) { toast("Хранилище браузера переполнено", "err"); } }
function lsSeedMovies() { if (!localStorage.getItem(LS.movies)) lsSet(LS.movies, SEED); }
function lsSeedAdmins() {
  if (!localStorage.getItem(LS.admins)) {
    lsSet(LS.admins, [{ id: 1, login: "admin", password: "kinometr", role: "root", created_at: new Date().toISOString() }]);
  }
}
const delay = (ms) => new Promise(r => setTimeout(r, ms));
function lsApi(action, body) {
  lsSeedMovies(); lsSeedAdmins();
  return delay(120).then(() => {
    if (action === "ping") return { ok: true };
    if (action === "list") return { ok: true, movies: lsGet(LS.movies, []) };
    if (action === "admins") {
      if (!state.session) throw new Error("Требуется авторизация");
      return { ok: true, admins: lsGet(LS.admins, []).map(a => ({ id: a.id, login: a.login, role: a.role, created_at: a.created_at })) };
    }
    if (action === "login") {
      const a = lsGet(LS.admins, []).find(x => x.login === String(body.login).trim().toLowerCase() && x.password === body.password);
      if (!a) throw new Error("Неверный логин или пароль");
      const token = "demo-" + a.id + "-" + Date.now();
      sessionStorage.setItem("kinometr_demo_token", JSON.stringify({ token, login: a.login, id: a.id }));
      return { ok: true, token, login: a.login, role: a.role };
    }
    if (!state.session) throw new Error("Требуется авторизация");
    const movies = lsGet(LS.movies, []);
    const nextId = movies.reduce((m, x) => Math.max(m, x.id || 0), 0) + 1;
    if (action === "save") { movies.unshift(Object.assign({}, body, { id: nextId, created_at: new Date().toISOString() })); lsSet(LS.movies, movies); return { ok: true, id: nextId }; }
    if (action === "update") { const i = movies.findIndex(x => x.id === body.id); if (i < 0) throw new Error("Фильм не найден"); movies[i] = Object.assign({}, movies[i], body); lsSet(LS.movies, movies); return { ok: true }; }
    if (action === "remove") { lsSet(LS.movies, movies.filter(x => x.id !== body.id)); return { ok: true }; }
    if (action === "import") { const rows = body.rows.filter(r => r.title); rows.forEach(r => movies.unshift(Object.assign({}, r, { id: nextId + rows.indexOf(r), created_at: new Date().toISOString() }))); lsSet(LS.movies, movies); return { ok: true, added: rows.length }; }
    if (action === "admin_add") {
      const admins = lsGet(LS.admins, []);
      if (admins.some(a => a.login === body.login.toLowerCase())) throw new Error("Такой логин уже занят");
      admins.push({ id: admins.reduce((m, a) => Math.max(m, a.id), 0) + 1, login: body.login.toLowerCase(), password: body.password, role: "admin", created_at: new Date().toISOString() });
      lsSet(LS.admins, admins); return { ok: true };
    }
    if (action === "admin_remove") { lsSet(LS.admins, lsGet(LS.admins, []).filter(a => a.id !== body.id)); return { ok: true }; }
    throw new Error("Неизвестный метод");
  });
}
function callApi(method, action, body) {
  if (state.mode === "demo") return lsApi(action, body);
  return api(method, action, body).catch(err => {
    if (!state.booted) throw err;
    toast("Связь с БД потеряна — переключаюсь в демо-режим", "err");
    state.mode = "demo"; render();
    return lsApi(action, body);
  });
}

async function refresh() {
  try {
    const d = await api("GET", "list");
    state.movies = (d.movies || []).map(normalizeMovie);
    if (state.mode !== "db") { state.mode = "db"; state.booted = true; }
    return;
  } catch (e) {
    lsSeedMovies();
    state.movies = lsGet(LS.movies, SEED).map(normalizeMovie);
    if (state.mode !== "demo") {
      const was = state.mode; state.mode = "demo"; state.booted = true;
      if (was === "db") toast("БД недоступна — данные из кэша браузера", "err");
    }
  }
}
function persistMovies(list) { if (state.mode === "demo") lsSet(LS.movies, list); }

async function tryRestoreSession() {
  const raw = sessionStorage.getItem("kinometr_session") || sessionStorage.getItem("kinometr_demo_token");
  if (!raw) return;
  try {
    const s = JSON.parse(raw);
    if (!s || !s.token) return;
    if (raw.indexOf("kinometr_demo") === 0 || s.token.indexOf("demo-") === 0) {
      lsSeedAdmins();
      const a = lsGet(LS.admins, []).find(x => x.id === s.id);
      if (a) state.session = { token: s.token, login: s.login || a.login, role: a.role, demo: true };
      return;
    }
    state.session = { token: s.token, login: s.login, role: s.role || "admin" };
  } catch (e) { /* повреждённая сессия — игнорируем */ }
}

/* =====================================================================
   ПУБЛИЧНАЯ ЧАСТЬ: ШАПКА, ЛЕНДИНГ, КАТАЛОГ, КАРТОЧКА
   ===================================================================== */
function header() {
  const modeOk = state.mode === "db";
  return h("header", { class: "top" },
    h("div", { class: "container top-in" },
      h("a", { class: "brand", href: "#/" },
        h("svg", { width: 34, height: 34, viewBox: "0 0 34 34" },
          h("circle", { cx: 17, cy: 17, r: 14, fill: "none", stroke: "var(--gold)", "stroke-width": 2.4 }),
          h("line", { x1: 17, y1: 17, x2: 26, y2: 8, stroke: "var(--gold)", "stroke-width": 2.4, "stroke-linecap": "round" }),
          h("circle", { cx: 17, cy: 17, r: 2.6, fill: "var(--gold)" })),
        h("div", null,
          h("div", { class: "brand-t" }, "КИНОМЕТР"),
          h("div", { class: "brand-s" }, "честные оценки кино"))),
      h("div", { class: "top-right" },
        h("span", { class: "mode" + (modeOk ? "" : " demo"), title: modeOk ? "Подключено к MySQL" : "Демо-режим: данные в браузере" },
          h("i", { class: "dot" }), modeOk ? "MySQL" : "демо"),
        h("a", { class: "btn sm ghost", href: "#/admin" }, icon("lock", 14), "Админ"))));
}

function marquee() {
  const top = state.movies.slice(0, 8);
  if (!top.length) return h("div", { class: "marquee" }, h("div", { class: "marquee-track" }, h("span", { class: "mq" }, "каталог пуст — добавьте первый фильм через админку")));
  const items = top.map(m => h("span", { class: "mq" },
    h("i", null, fmtScore(m.admin_score)), h("b", null, m.title),
    h("span", null, m.year || ""), h("em", null, "✦")));
  return h("div", { class: "marquee" }, h("div", { class: "marquee-track" }, items, items.map(x => x.cloneNode(true))));
}

function dial(value) {
  const C = 503;
  const v = Math.max(0, Math.min(10, value || 0));
  const arc = h("circle", { class: "dial-arc", cx: 105, cy: 105, r: 80 });
  const num = h("b", null, "0.0", h("em", null, "/10"));
  setTimeout(() => {
    arc.style.strokeDashoffset = C * (1 - v / 10);
    const t0 = performance.now();
    (function tick(t) {
      const p = Math.min(1, (t - t0) / 1400);
      const e = 1 - Math.pow(1 - p, 3);
      num.firstChild.nodeValue = (v * e).toFixed(1);
      if (p < 1) requestAnimationFrame(tick);
    })(t0);
  }, 120);
  return h("div", { class: "dial" },
    h("svg", { viewBox: "0 0 210 210" },
      h("defs", null, h("linearGradient", { id: "gauge", x1: "0", y1: "0", x2: "1", y2: "1" },
        h("stop", { offset: "0%", "stop-color": "#e8b84b" }), h("stop", { offset: "100%", "stop-color": "#f5d488" }))),
      h("circle", { class: "dial-track", cx: 105, cy: 105, r: 80 }), arc),
    h("div", { class: "dial-num" }, num, h("span", null, "оценка админа")));
}

function freshSection(m) {
  return h("section", { class: "fresh" },
    h("div", { class: "container fresh-grid" },
      h("div", { class: "score-col reveal" },
        h("div", { class: "sweep" }),
        dial(m.admin_score),
        h("span", { class: "imdb-row" }, h("b", null, "IMDb " + fmtScore(m.imdb_score)), "· зрители"),
        h("span", { class: "score-cap" }, "измерено " + fmtDate(m.created_at))),
      h("div", { class: "reveal" },
        h("div", { class: "eyebrow" }, "свежее измерение · №" + String(state.movies.length).padStart(3, "0")),
        h("h1", { class: "fresh-title" }, m.title),
        m.original_title ? h("div", { class: "fresh-orig" }, m.original_title + (m.year ? " · " + m.year : "")) : null,
        h("div", { class: "fresh-meta" }, m.genres.map(g => h("span", { class: "tag" }, g))),
        h("p", { class: "fresh-desc" }, m.description),
        h("div", { class: "fresh-actions" },
          h("button", { class: "btn", onclick: () => openMovie(m.id) }, icon("play", 15), "Смотреть разбор"),
          h("a", { class: "btn ghost", href: "#catalog" }, "Весь каталог")))));
}

function statsBar() {
  const total = state.movies.length;
  const avg = total ? state.movies.reduce((s, m) => s + m.admin_score, 0) / total : 0;
  const genres = new Set(state.movies.flatMap(m => m.genres)).size;
  const top = total ? state.movies.reduce((a, m) => m.admin_score > a.admin_score ? m : a, state.movies[0]) : null;
  const items = [
    [total, plural(total, "фильм", "фильма", "фильмов")],
    [avg.toFixed(1), "средняя оценка"],
    [genres, plural(genres, "жанр", "жанра", "жанров")],
    [top ? top.title : "—", "лидер каталога"]
  ];
  return h("section", { class: "stats" }, h("div", { class: "container stats-in" },
    items.map((it, i) => h("div", { class: "stat reveal", style: "transition-delay:" + (i * 70) + "ms" },
      h("b", null, String(it[0]).length > 18 ? String(it[0]).slice(0, 17) + "…" : it[0]), h("span", null, it[1])))));
}

function filteredMovies() {
  let list = state.movies.slice();
  const q = state.query.trim().toLowerCase();
  if (q) list = list.filter(m => [m.title, m.original_title, m.director, m.country, m.genres.join(" ")].join(" ").toLowerCase().includes(q));
  if (state.genre) list = list.filter(m => m.genres.includes(state.genre));
  const s = state.sort;
  list.sort((a, b) =>
    s === "admin" ? (b.admin_score - a.admin_score) :
    s === "imdb" ? (b.imdb_score - a.imdb_score) :
    s === "year" ? ((b.year || 0) - (a.year || 0)) :
    (String(b.created_at).localeCompare(String(a.created_at))));
  return list;
}

function posterImg(m) {
  const ph = h("div", { class: "poster-ph" }, icon("film", 34), h("span", null, m.title));
  if (!m.cover_url) return ph;
  const img = h("img", { src: m.cover_url, alt: m.title, loading: "lazy" });
  img.onerror = () => img.replaceWith(ph);
  return img;
}

function movieCard(m, i) {
  return h("article", { class: "card", style: "animation-delay:" + Math.min(i, 12) * 45 + "ms", tabindex: 0,
    onclick: () => openMovie(m.id),
    onkeydown: (e) => { if (e.key === "Enter") openMovie(m.id); } },
    h("div", { class: "poster" },
      posterImg(m),
      h("span", { class: "score-badge " + scoreClass(m.admin_score) }, fmtScore(m.admin_score)),
      m.imdb_score > 0 ? h("span", { class: "score-badge im" }, "IMDb " + fmtScore(m.imdb_score)) : null),
    h("div", { class: "card-body" },
      h("div", { class: "card-year" }, (m.year || "год не указан") + (m.director ? " · " + m.director : "")),
      h("h3", { class: "card-title" }, m.title),
      h("div", { class: "card-genres" }, m.genres.slice(0, 3).join(" / ") || "жанр не указан")));
}

function catalogSection() {
  const genreCounts = {};
  state.movies.forEach(m => m.genres.forEach(g => { genreCounts[g] = (genreCounts[g] || 0) + 1; }));
  const genres = Object.keys(genreCounts).sort((a, b) => genreCounts[b] - genreCounts[a]);
  const list = filteredMovies();
  const gridEl = h("div", { class: "grid" });
  const emptyEl = h("div", { class: "empty", hidden: true },
    h("b", null, "Ничего не нашлось"),
    h("p", null, "Попробуйте изменить запрос или сбросить фильтры. "),
    h("button", { class: "link", onclick: () => { state.query = ""; state.genre = null; render(); } }, "Сбросить всё"));

  function draw() {
    const rows = filteredMovies();
    gridEl.innerHTML = "";
    rows.forEach((m, i) => gridEl.append(movieCard(m, i)));
    emptyEl.hidden = rows.length > 0;
    counter.textContent = "показано " + rows.length + " из " + state.movies.length;
  }

  const searchInput = h("input", { type: "text", placeholder: "Название, режиссёр, жанр…", value: state.query,
    oninput: (e) => { state.query = e.target.value; draw(); } });
  const counter = h("span", { class: "cat-note" });
  const sortSel = h("select", { class: "sel", onchange: (e) => { state.sort = e.target.value; draw(); } },
    [["new", "Сначала новые"], ["admin", "По оценке админа"], ["imdb", "По рейтингу IMDb"], ["year", "По году выхода"]]
      .map(o => h("option", { value: o[0], selected: state.sort === o[0] ? true : null }, o[1])));

  const chips = h("div", { class: "chips" },
    h("button", { class: "chip" + (state.genre === null ? " on" : ""), onclick: (e) => { state.genre = null; syncChips(); draw(); } }, "Все"),
    genres.map(g => h("button", { class: "chip", "data-g": g, onclick: (e) => { state.genre = (state.genre === g ? null : g); syncChips(); draw(); } },
      g, h("span", { class: "n" }, genreCounts[g]))));
  function syncChips() { chips.querySelectorAll(".chip").forEach(c => c.classList.toggle("on", (c.dataset.g || null) === state.genre)); }

  draw();
  return h("section", { class: "catalog", id: "catalog" },
    h("div", { class: "container" },
      h("div", { class: "cat-head reveal" },
        h("div", null, h("div", { class: "eyebrow" }, "каталог"), h("h2", null, "Все измерения")),
        counter),
      h("div", { class: "toolbar" },
        h("label", { class: "search" }, icon("search", 16), searchInput),
        sortSel),
      chips, gridEl, emptyEl));
}

function footer() {
  return h("footer", { class: "foot" },
    h("div", { class: "container foot-in" },
      h("span", null, "© " + new Date().getFullYear() + " КИНОМЕТР — оценки, которым можно верить"),
      h("span", null, "оценка админа — субъективна и честна · IMDb — глас народа · ",
        h("a", { class: "link", href: "#/admin" }, "вход для админа"))));
}

function openMovie(id) {
  const m = state.movies.find(x => x.id === id);
  if (!m) return;
  const box = h("div", { class: "modal-box" },
    h("button", { class: "modal-close", onclick: closeModal }, icon("x", 16)),
    h("div", { class: "mv" },
      h("div", { class: "mv-cover" }, posterImg(m)),
      h("div", { class: "mv-body" },
        h("div", { class: "card-year" }, "измерение №" + String(m.id).padStart(3, "0") + " · добавлено " + fmtDate(m.created_at)),
        h("h2", { class: "fresh-title", style: "font-size:30px" }, m.title),
        m.original_title ? h("div", { class: "fresh-orig" }, m.original_title) : null,
        h("div", { class: "mv-genres" }, m.genres.map(g => h("span", { class: "tag" }, g))),
        h("dl", { class: "mv-meta" },
          h("div", null, h("dt", null, "год"), h("dd", null, m.year || "—")),
          h("div", null, h("dt", null, "страна"), h("dd", null, m.country || "—")),
          h("div", null, h("dt", null, "режиссёр"), h("dd", null, m.director || "—")),
          h("div", null, h("dt", null, "хронометраж"), h("dd", null, fmtDur(m.duration) || "—"))),
        h("p", { class: "mv-desc" }, m.description || "Описание появится после следующего сеанса."),
        h("div", { class: "mv-scores" },
          h("div", { class: "score-box" }, h("label", null, "оценка админа"), h("b", null, fmtScore(m.admin_score)),
            h("div", { class: "bar" }, h("i", { style: "width:" + (m.admin_score * 10) + "%" }))),
          h("div", { class: "score-box imdb" }, h("label", null, "рейтинг IMDb"), h("b", null, fmtScore(m.imdb_score)),
            h("div", { class: "bar" }, h("i", { style: "width:" + (m.imdb_score * 10) + "%" })))),
        m.admin_score > 0 ? h("div", { class: "verdict" }, "вердикт: " + verdictFor(m.admin_score)) : null)));
  const mEl = h("div", { class: "modal", onclick: (e) => { if (e.target === mEl) closeModal(); } }, box);
  document.body.append(mEl);
  setTimeout(() => box.querySelectorAll(".bar i").forEach(i => { i.style.width = i.style.width; }), 30);
  function closeModal() { mEl.remove(); }
}

function renderLanding() {
  const latest = state.movies[0];
  const frag = h("div", null,
    header(),
    marquee(),
    latest ? freshSection(latest) : h("section", { class: "fresh" }, h("div", { class: "container" },
      h("div", { class: "empty" }, h("b", null, "Каталог пуст"),
        h("p", null, "Добавьте первый фильм через "), h("a", { class: "link", href: "#/admin" }, "админ-панель")))),
    statsBar(),
    catalogSection(),
    footer());
  mount(frag);
}

/* =====================================================================
   АДМИНКА
   ===================================================================== */
function field(label, input, opts) {
  opts = opts || {};
  return h("div", { class: "field" + (opts.full ? " full" : "") },
    h("label", null, label, opts.req ? h("b", null, " *") : null), input,
    opts.hint ? h("span", { class: "hint" }, opts.hint) : null);
}

function loginPage() {
  const loginIn = h("input", { type: "text", autocomplete: "username", placeholder: "admin" });
  const passIn = h("input", { type: "password", autocomplete: "current-password", placeholder: "••••••••" });
  const errEl = h("div", { class: "login-err" });
  const btn = h("button", { class: "btn block" }, icon("lock", 15), "Войти в админку");
  async function submit() {
    errEl.textContent = ""; btn.disabled = true; btn.innerHTML = ""; btn.append(h("span", { class: "spin" }), "Проверяем…");
    try {
      const d = await callApi("POST", "login", { login: loginIn.value, password: passIn.value });
      state.session = { token: d.token, login: d.login, role: d.role, demo: d.token.indexOf("demo-") === 0 };
      sessionStorage.setItem(state.session.demo ? "kinometr_demo_token" : "kinometr_session", JSON.stringify(state.session));
      toast("С возвращением, " + d.login + "!");
      render();
    } catch (e) { errEl.textContent = e.message; }
    btn.disabled = false; btn.innerHTML = ""; btn.append(icon("lock", 15), "Войти в админку");
  }
  passIn.onkeydown = (e) => { if (e.key === "Enter") submit(); };
  loginIn.onkeydown = (e) => { if (e.key === "Enter") passIn.focus(); };
  btn.onclick = submit;
  mount(h("div", null, header(),
    h("div", { class: "login-wrap" },
      h("div", { class: "login-box reveal in" },
        h("div", { class: "eyebrow" }, "служебный вход"),
        h("h1", null, "Админ-панель"),
        h("p", { class: "sub" }, "управление каталогом и администраторами"),
        errEl,
        field("Логин", loginIn),
        field("Пароль", passIn),
        btn,
        h("div", { class: "login-hint" },
          "первый вход: admin / kinometr", h("br"),
          state.mode === "demo" ? "демо-режим: аккаунты хранятся в этом браузере" : "данные в MySQL на fdb1029.awardspace.net")))));
  setTimeout(() => loginIn.focus(), 60);
}

function adminMoviesList() {
  const q = h("input", { type: "text", placeholder: "Фильтр по названию…" });
  const tbody = h("tbody");
  function draw() {
    const f = q.value.trim().toLowerCase();
    tbody.innerHTML = "";
    state.movies.filter(m => !f || m.title.toLowerCase().includes(f)).forEach(m => {
      tbody.append(h("tr", null,
        h("td", { class: "th-cover" }, m.cover_url
          ? (() => { const im = h("img", { class: "mini-cover", src: m.cover_url, alt: "" }); im.onerror = () => im.replaceWith(h("div", { class: "mini-ph" }, icon("film", 14))); return im; })()
          : h("div", { class: "mini-ph" }, icon("film", 14))),
        h("td", { class: "title-cell" }, h("b", null, m.title), h("span", null, (m.original_title ? m.original_title + " · " : "") + (m.year || "б/г"))),
        h("td", { class: "mono", style: "font-size:11.5px;color:var(--mut)" }, m.genres.slice(0, 3).join(", ") || "—"),
        h("td", null, h("span", { class: "score-pill" }, fmtScore(m.admin_score))),
        h("td", null, m.imdb_score > 0 ? h("span", { class: "score-pill im" }, fmtScore(m.imdb_score)) : h("span", { class: "mono", style: "color:var(--dim);font-size:11px" }, "—")),
        h("td", { class: "mono", style: "font-size:11px;color:var(--dim)" }, fmtDate(m.created_at)),
        h("td", null, h("div", { class: "row-actions" },
          h("button", { class: "icon-btn", title: "Редактировать", onclick: () => { state.editing = { id: m.id }; render(); } }, icon("edit", 15)),
          h("button", { class: "icon-btn del", title: "Удалить", onclick: () => removeMovie(m) }, icon("trash", 15))))));
    });
    cnt.textContent = state.movies.length + " " + plural(state.movies.length, "фильм", "фильма", "фильмов");
  }
  const cnt = h("span", { class: "sub" });
  q.oninput = draw;
  draw();
  return h("div", null,
    h("div", { class: "page-head" },
      h("div", null, h("h1", null, "Фильмы"), cnt),
      h("div", { style: "display:flex;gap:10px;flex-wrap:wrap" },
        h("button", { class: "btn ghost sm", onclick: exportJson }, icon("down", 14), "Экспорт JSON"),
        h("button", { class: "btn sm", onclick: () => { state.adminTab = "import"; render(); } }, icon("upload", 14), "Импорт"),
        h("button", { class: "btn sm", onclick: () => { state.editing = {}; render(); } }, icon("plus", 14), "Добавить фильм"))),
    h("div", { class: "panel" },
      h("div", { class: "toolbar", style: "margin-bottom:6px" }, h("label", { class: "search" }, icon("search", 15), q)),
      h("div", { style: "overflow-x:auto" },
        h("table", { class: "tbl" },
          h("thead", null, h("tr", null,
            h("th", null, ""), h("th", null, "Фильм"), h("th", null, "Жанры"),
            h("th", null, "Админ"), h("th", null, "IMDb"), h("th", null, "Добавлен"), h("th", { style: "text-align:right" }, "Действия"))),
          tbody))));
}

async function removeMovie(m) {
  const yes = await askConfirm("Удалить фильм?", "«" + m.title + "» будет удалён из каталога безвозвратно.", "Удалить");
  if (!yes) return;
  try {
    await callApi("POST", "remove", { id: m.id });
    state.movies = state.movies.filter(x => x.id !== m.id);
    persistMovies(state.movies);
    toast("Фильм удалён");
    render();
  } catch (e) { toast(e.message, "err"); }
}

function exportJson() {
  const data = state.movies.map(m => ({
    title: m.title, original_title: m.original_title, year: m.year, country: m.country, director: m.director,
    duration: m.duration, genres: m.genres, description: m.description, cover_url: m.cover_url,
    admin_score: m.admin_score, imdb_score: m.imdb_score
  }));
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
  const a = h("a", { href: URL.createObjectURL(blob), download: "kinometr-catalog.json" });
  document.body.append(a); a.click(); a.remove();
  toast("Каталог выгружен в JSON");
}

function emptyForm() {
  return { title: "", original_title: "", year: "", country: "", director: "", duration: "", genres: "", description: "", cover_url: "", admin_score: "", imdb_score: "" };
}
function movieToForm(m) {
  return { title: m.title, original_title: m.original_title || "", year: m.year || "", country: m.country || "", director: m.director || "", duration: m.duration || "", genres: m.genres.join(", "), description: m.description || "", cover_url: m.cover_url || "", admin_score: m.admin_score || "", imdb_score: m.imdb_score || "" };
}

function movieEditor(existing) {
  const f = existing ? movieToForm(existing) : emptyForm();
  const inputs = {};
  const mk = (name, attrs, opts) => {
    inputs[name] = h(name === "description" ? "textarea" : "input", Object.assign({ value: f[name] }, attrs));
    if (name !== "description") inputs[name].setAttribute("value", f[name]);
    return field(opts.label, inputs[name], opts);
  };
  const coverPreview = h("img", { src: f.cover_url || PH, alt: "", style: "width:100%;height:100%;object-fit:cover" });
  const coverPh = h("div", { class: "poster-ph" }, icon("film", 34), h("span", null, "обложка появится здесь"));
  function refreshPreview() {
    const url = inputs.cover_url.value.trim();
    const box = previewPoster;
    box.innerHTML = "";
    if (!url) { box.append(coverPh.cloneNode(true)); return; }
    const im = h("img", { src: url, alt: "", style: "width:100%;height:100%;object-fit:cover" });
    im.onerror = () => { im.remove(); box.append(h("div", { class: "poster-ph", style: "background:rgba(224,82,82,.08)" }, icon("alert", 30), h("span", null, "ссылка не открылась — проверьте URL"))); };
    box.append(im);
    const b1 = previewAdmin.querySelector(".bar i"), b2 = previewImdb.querySelector(".bar i");
    b1.style.width = (Math.min(10, parseFloat(inputs.admin_score.value) || 0) * 10) + "%";
    b2.style.width = (Math.min(10, parseFloat(inputs.imdb_score.value) || 0) * 10) + "%";
    previewAdmin.querySelector("b").textContent = fmtScore(parseFloat(inputs.admin_score.value) || 0);
    previewImdb.querySelector("b").textContent = fmtScore(parseFloat(inputs.imdb_score.value) || 0);
  }
  inputs.cover_url.addEventListener("input", refreshPreview);

  const previewPoster = h("div", { class: "poster" });
  const previewAdmin = h("div", { class: "score-box" }, h("label", null, "админ"), h("b", null, "—"), h("div", { class: "bar" }, h("i", { style: "width:0%" })));
  const previewImdb = h("div", { class: "score-box imdb" }, h("label", null, "IMDb"), h("b", null, "—"), h("div", { class: "bar" }, h("i", { style: "width:0%" })));

  const saveBtn = h("button", { class: "btn" }, icon("check", 15), existing ? "Сохранить изменения" : "Добавить в каталог");
  saveBtn.onclick = async () => {
    const row = {
      title: inputs.title.value.trim(),
      original_title: inputs.original_title.value.trim(),
      year: inputs.year.value ? parseInt(inputs.year.value, 10) : null,
      country: inputs.country.value.trim(),
      director: inputs.director.value.trim(),
      duration: parseInt(inputs.duration.value, 10) || 0,
      genres: inputs.genres.value.split(",").map(s => s.trim()).filter(Boolean),
      description: inputs.description.value.trim(),
      cover_url: inputs.cover_url.value.trim(),
      admin_score: Math.max(0, Math.min(10, parseFloat(inputs.admin_score.value) || 0)),
      imdb_score: Math.max(0, Math.min(10, parseFloat(inputs.imdb_score.value) || 0))
    };
    if (!row.title) { toast("Укажите название фильма", "err"); inputs.title.focus(); return; }
    saveBtn.disabled = true;
    try {
      if (existing) {
        await callApi("POST", "update", Object.assign({ id: existing.id }, row));
        toast("Изменения сохранены");
      } else {
        await callApi("POST", "save", row);
        toast("«" + row.title + "» добавлен в каталог");
      }
      await refresh();
      state.editing = null;
      render();
    } catch (e) { toast(e.message, "err"); saveBtn.disabled = false; }
  };

  ["admin_score", "imdb_score"].forEach(k => inputs[k].addEventListener("input", refreshPreview));
  setTimeout(refreshPreview, 30);

  return h("div", null,
    h("div", { class: "page-head" },
      h("div", null, h("h1", null, existing ? "Редактирование" : "Новый фильм"),
        h("div", { class: "sub" }, existing ? "«" + existing.title + "»" : "карточка появится на лендинге сразу после сохранения")),
      h("button", { class: "btn ghost sm", onclick: () => { state.editing = null; render(); } }, "Назад к списку")),
    h("div", { class: "edit-layout" },
      h("div", { class: "panel" },
        h("div", { class: "form-grid" },
          mk("title", { placeholder: "Например: Интерстеллар" }, { label: "Название", req: true, full: false }),
          mk("original_title", { placeholder: "Interstellar" }, { label: "Оригинальное название" }),
          mk("year", { type: "number", placeholder: "2014", min: 1900, max: 2100 }, { label: "Год" }),
          mk("country", { placeholder: "США" }, { label: "Страна" }),
          mk("director", { placeholder: "Кристофер Нолан" }, { label: "Режиссёр" }),
          mk("duration", { type: "number", placeholder: "169" }, { label: "Хронометраж, мин" }),
          mk("admin_score", { type: "number", step: "0.1", min: 0, max: 10, placeholder: "8.5" }, { label: "Оценка админа (0–10)", req: true }),
          mk("imdb_score", { type: "number", step: "0.1", min: 0, max: 10, placeholder: "8.7" }, { label: "Рейтинг IMDb (0–10)" }),
          mk("genres", { placeholder: "фантастика, драма, приключения" }, { label: "Жанры через запятую", full: true }),
          mk("cover_url", { placeholder: "https://…/poster.jpg" }, { label: "Обложка — ссылка на картинку", full: true, hint: "подойдёт прямая ссылка на JPG/PNG (например, с TMDB или любого хостинга)" }),
          field("Описание", inputs.description, { full: true })),
        h("div", { class: "form-foot" },
          h("button", { class: "btn ghost", onclick: () => { state.editing = null; render(); } }, "Отмена"),
          saveBtn)),
      h("div", { class: "preview-card" },
        previewPoster,
        h("div", { style: "padding:14px" }, previewAdmin, h("div", { style: "height:10px" }), previewImdb),
        h("div", { class: "preview-label" }, "предпросмотр карточки"))));
}

function parseImportText(text, name) {
  const lower = name.toLowerCase();
  if (lower.endsWith(".json")) {
    let data = JSON.parse(text);
    if (!Array.isArray(data)) data = data.movies || data.items || data.rows;
    if (!Array.isArray(data)) throw new Error("В JSON не найден массив фильмов");
    return data;
  }
  if (lower.endsWith(".csv")) {
    const lines = text.replace(/^\uFEFF/, "").split(/\r?\n/).filter(l => l.trim() !== "");
    if (lines.length < 2) throw new Error("CSV пуст");
    const head = lines[0].split(";").map(s => s.trim().toLowerCase());
    return lines.slice(1).map(line => {
      const cells = line.split(";").map(s => s.trim().replace(/^"|"$/g, ""));
      const o = {};
      head.forEach((k, i) => { o[k] = cells[i] !== undefined ? cells[i] : ""; });
      return o;
    });
  }
  throw new Error("Нужен файл .json или .csv");
}

function importPanel() {
  const listEl = h("div", { style: "margin-top:16px" });
  const importBtn = h("button", { class: "btn", disabled: true }, icon("upload", 15), "Импортировать в каталог");
  let rows = null;

  function showParsed(parsed) {
    rows = parsed.map(r => {
      let g = r.genres;
      if (typeof g === "string") { const t = g.trim(); if (t.startsWith("[")) { try { g = JSON.parse(t); } catch (e) { g = t.split(","); } } else g = t ? t.split(",") : []; }
      return {
        title: String(r.title || r["название"] || "").trim(),
        original_title: String(r.original_title || "").trim(),
        year: r.year ? parseInt(r.year, 10) : null,
        country: String(r.country || r["страна"] || "").trim(),
        director: String(r.director || r["режиссёр"] || r["режиссер"] || "").trim(),
        duration: parseInt(r.duration, 10) || 0,
        genres: (Array.isArray(g) ? g : []).map(s => String(s).trim()).filter(Boolean),
        description: String(r.description || r["описание"] || "").trim(),
        cover_url: String(r.cover_url || r.cover || r["обложка"] || "").trim(),
        admin_score: Math.max(0, Math.min(10, parseFloat(r.admin_score) || 0)),
        imdb_score: Math.max(0, Math.min(10, parseFloat(r.imdb_score) || 0))
      };
    });
    const valid = rows.filter(r => r.title);
    listEl.innerHTML = "";
    listEl.append(h("div", { class: "mono", style: "font-size:11px;color:var(--dim);letter-spacing:.1em;margin-bottom:10px" },
      "РАСПОЗНАНО " + rows.length + " · ГОТОВЫ К ИМПОРТУ " + valid.length));
    rows.slice(0, 50).forEach(r => listEl.append(h("div", { class: "imp-row" + (r.title ? "" : " warn") },
      h("i", { class: "st" }),
      h("div", null,
        h("div", { class: "t" }, r.title || "— без названия, будет пропущен —"),
        h("div", { class: "m", style: "text-align:left" }, [r.year, r.director, r.genres.slice(0, 3).join(", ")].filter(Boolean).join(" · ") || " ")),
      h("div", { class: "m" }, h("span", { class: "score-pill" }, fmtScore(r.admin_score)))));
    importBtn.disabled = valid.length === 0;
    importBtn.textContent = ""; importBtn.append(icon("upload", 15), "Импортировать " + valid.length + " " + plural(valid.length, "фильм", "фильма", "фильмов"));
  }

  function onFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => { try { showParsed(parseImportText(String(reader.result), file.name)); toast("Файл разобран: " + file.name); } catch (e) { toast("Не удалось разобрать файл: " + e.message, "err"); } };
    reader.onerror = () => toast("Не удалось прочитать файл", "err");
    reader.readAsText(file, "utf-8");
  }

  const dz = h("div", { class: "dropzone" },
    icon("upload", 30),
    h("b", null, "Перетащите файл сюда или кликните"),
    h("div", { class: "hint" }, "JSON (массив фильмов) или CSV с разделителем «;»"));
  const fileIn = h("input", { type: "file", accept: ".json,.csv,application/json,text/csv", style: "display:none", onchange: (e) => onFile(e.target.files[0]) });
  dz.onclick = () => fileIn.click();
  ["dragover", "dragenter"].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.add("over"); }));
  ["dragleave", "drop"].forEach(ev => dz.addEventListener(ev, (e) => { e.preventDefault(); dz.classList.remove("over"); }));
  dz.addEventListener("drop", (e) => onFile(e.dataTransfer.files[0]));

  importBtn.onclick = async () => {
    const valid = rows.filter(r => r.title);
    if (!valid.length) return;
    importBtn.disabled = true;
    try {
      const d = await callApi("POST", "import", { rows: valid });
      toast("Импортировано: " + (d.added !== undefined ? d.added : valid.length));
      await refresh();
      state.adminTab = "movies"; state.importRows = null;
      render();
    } catch (e) { toast(e.message, "err"); importBtn.disabled = false; }
  };

  const tplJson = [
    { title: "Пример фильма", original_title: "Example", year: 2024, country: "США", director: "Режиссёр", duration: 120, genres: ["драма"], description: "Описание…", cover_url: "https://example.com/poster.jpg", admin_score: 8.0, imdb_score: 7.5 }
  ];
  const tplCsv = "title;original_title;year;country;director;duration;genres;description;cover_url;admin_score;imdb_score\nПример фильма;Example;2024;США;Режиссёр;120;\"драма,комедия\";Описание…;https://example.com/poster.jpg;8.0;7.5";
  function download(name, text, type) {
    const blob = new Blob([text], { type });
    const a = h("a", { href: URL.createObjectURL(blob), download: name });
    document.body.append(a); a.click(); a.remove();
  }

  return h("div", null,
    h("div", { class: "page-head" },
      h("div", null, h("h1", null, "Импорт фильмов"),
        h("div", { class: "sub" }, "массовое добавление карточек из файла — JSON или CSV")),
      h("button", { class: "btn ghost sm", onclick: () => { state.adminTab = "movies"; render(); } }, "Назад к фильмам")),
    h("div", { class: "panel" },
      h("h3", null, icon("file", 16), "Файл с фильмами", h("span", { class: "mono" }, "JSON / CSV")),
      dz, fileIn,
      h("div", { style: "display:flex;gap:10px;margin-top:14px;flex-wrap:wrap" },
        h("button", { class: "btn ghost sm", onclick: () => download("kinometr-template.json", JSON.stringify(tplJson, null, 2), "application/json") }, icon("down", 14), "Шаблон JSON"),
        h("button", { class: "btn ghost sm", onclick: () => download("kinometr-template.csv", tplCsv, "text/csv") }, icon("down", 14), "Шаблон CSV")),
      listEl,
      h("div", { class: "form-foot" }, importBtn)));
}

function adminsPanel() {
  const isRoot = state.session && state.session.role === "root";
  const loginIn = h("input", { placeholder: "ivan_editor" });
  const passIn = h("input", { type: "password", placeholder: "минимум 6 символов" });
  const addBtn = h("button", { class: "btn sm" }, icon("plus", 14), "Добавить");
  const errEl = h("div", { class: "login-err", style: "margin-top:8px" });
  const tbody = h("tbody");

  function draw(list) {
    tbody.innerHTML = "";
    list.forEach(a => tbody.append(h("tr", null,
      h("td", null, h("b", null, a.login)),
      h("td", null, h("span", { class: "admin-badge" + (a.role === "root" ? " root" : "") }, a.role === "root" ? "главный" : "админ")),
      h("td", { class: "mono", style: "font-size:11.5px;color:var(--dim)" }, fmtDate(a.created_at)),
      h("td", null, h("div", { class: "row-actions" },
        a.role === "root"
          ? h("span", { class: "mono", style: "font-size:10.5px;color:var(--dim)" }, "защищён")
          : h("button", { class: "icon-btn del", title: "Удалить админа", onclick: () => removeAdmin(a) }, icon("trash", 15)))))));
  }

  async function load() {
    try { const d = await callApi("GET", "admins"); state.admins = d.admins || []; draw(state.admins); }
    catch (e) { toast(e.message, "err"); }
  }

  async function removeAdmin(a) {
    const yes = await askConfirm("Удалить администратора?", "«" + a.login + "» потеряет доступ к админ-панели.", "Удалить");
    if (!yes) return;
    try { await callApi("POST", "admin_remove", { id: a.id }); toast("Администратор удалён"); load(); }
    catch (e) { toast(e.message, "err"); }
  }

  addBtn.onclick = async () => {
    errEl.textContent = "";
    const login = loginIn.value.trim().toLowerCase();
    if (!/^[a-z0-9_.-]{3,24}$/.test(login)) { errEl.textContent = "Логин: 3–24 символа, латиница и цифры"; return; }
    if (passIn.value.length < 6) { errEl.textContent = "Пароль: минимум 6 символов"; return; }
    addBtn.disabled = true;
    try {
      await callApi("POST", "admin_add", { login, password: passIn.value });
      toast("Администратор «" + login + "» создан");
      loginIn.value = ""; passIn.value = "";
      load();
    } catch (e) { errEl.textContent = e.message; }
    addBtn.disabled = false;
  };
  passIn.onkeydown = (e) => { if (e.key === "Enter") addBtn.click(); };
  load();

  return h("div", null,
    h("div", { class: "page-head" },
      h("div", null, h("h1", null, "Администраторы"),
        h("div", { class: "sub" }, isRoot ? "вы главный админ — можете добавлять и удалять админов" : "добавлять админов может только главный админ (root)"))),
    h("div", { class: "panel" },
      h("h3", null, icon("users", 16), "Команда"),
      h("div", { style: "overflow-x:auto" },
        h("table", { class: "tbl" },
          h("thead", null, h("tr", null, h("th", null, "Логин"), h("th", null, "Роль"), h("th", null, "Создан"), h("th", { style: "text-align:right" }, "Действия"))),
          tbody))),
    h("div", { class: "panel" },
      h("h3", null, icon("plus", 16), "Новый администратор"),
      isRoot
        ? h("div", null,
            h("div", { class: "form-grid" },
              field("Логин", loginIn, { hint: "латиница, цифры, 3–24 символа" }),
              field("Пароль", passIn, { hint: "минимум 6 символов; хранится в БД в виде хеша" })),
            errEl,
            h("div", { class: "form-foot" }, addBtn))
        : h("p", { class: "sub" }, "Недостаточно прав: войдите под главным админом (admin), чтобы управлять командой.")));
}

function renderAdmin() {
  if (!state.session) { loginPage(); return; }
  const tabs = [
    ["movies", "film", "Фильмы", state.movies.length],
    ["import", "upload", "Импорт", null],
    ["admins", "users", "Админы", null]
  ];
  const side = h("aside", { class: "side" },
    h("div", { class: "side-user" },
      h("span", null, state.session.role === "root" ? "главный админ" : "администратор"),
      h("b", null, state.session.login)),
    tabs.map(t => h("button", { class: "nav-item" + (state.adminTab === t[0] ? " on" : ""), onclick: () => { state.adminTab = t[0]; render(); } },
      icon(t[1], 16), t[2], t[3] !== null ? h("span", { class: "cnt" }, t[3]) : null)),
    h("button", { class: "nav-item danger", onclick: logout }, icon("out", 16), "Выйти"));

  let body;
  if (state.editing !== null && state.editing !== undefined) {
    const existing = state.editing.id ? state.movies.find(m => m.id === state.editing.id) : null;
    if (state.editing.id && !existing) { state.editing = null; body = adminMoviesList(); }
    else body = movieEditor(existing);
  } else if (state.adminTab === "import") body = importPanel();
  else if (state.adminTab === "admins") body = adminsPanel();
  else body = adminMoviesList();

  mount(h("div", null, header(), h("div", { class: "admin" }, side, h("main", { class: "main" }, body))));
}

function logout() {
  state.session = null;
  sessionStorage.removeItem("kinometr_session");
  sessionStorage.removeItem("kinometr_demo_token");
  toast("Вы вышли из админ-панели");
  location.hash = "#/";
  render();
}

/* ---------- роутер ---------- */
function render() {
  const route = location.hash || "#/";
  if (route.indexOf("#/admin") === 0) renderAdmin();
  else renderLanding();
  window.scrollTo({ top: 0 });
}
window.addEventListener("hashchange", render);

/* ---------- старт ---------- */
(async function boot() {
  await tryRestoreSession();
  await refresh();
  render();
})();
</script>
</body>
</html>
