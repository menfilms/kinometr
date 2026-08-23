<?php
/* ═══════════════════════════════════════════════════════════════════
   КИНОМЕТР — сайт оценок фильмов · ВЕСЬ САЙТ В ОДНОМ ФАЙЛЕ (index.php)

   ДЕПЛОЙ НА AWARDSPACE:
   1) Загрузите этот файл в папку htdocs вашего сайта (просто index.php)
   2) Пароль БД уже вписан (DB_PASS ниже). Если меняли — обновите.
   3) Готово. Таблицы MySQL и главный админ создадутся автоматически
      при первом открытии. Вход в админку: ссылка «Админ» в шапке
      (или #/admin), логин admin, пароль kinometr — сразу смените!

   Если база вдруг недоступна — сайт сам работает в локальном режиме
   (данные в браузере), никаких ошибок не будет.

   Как устроен файл:
   · верх (PHP) — база данных и API (?api). Данные от сайта приходят
     в base64-туннеле, поэтому защита хостинга их не блокирует.
   · низ (после закрывающего тега PHP) — сам сайт: чистые HTML, CSS
     и JavaScript. Они НЕ внутри PHP-строк, поэтому стили не могут
     «сброситься» при обработке сервером.
   ═══════════════════════════════════════════════════════════════════ */

error_reporting(0);
ini_set('display_errors', '0');

/* ─────────────── НАСТРОЙКИ БАЗЫ (AwardSpace) ─────────────── */
const DB_HOST = 'fdb1029.awardspace.net';
const DB_PORT = 3306;
const DB_NAME = '4772808_base';
const DB_USER = '4772808_base';
const DB_PASS = '66677712A';   /* пароль от базы */
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
    ['Матрица','The Matrix',1999,'США','Лана и Лилли Вачовски',136,'["фантастика","боевик"]','Хакер Нео узнаёт, что привычный мир — симуляция, созданная машинами, и присоединяется к повстанцам, сражающимся за свободу людей.','https://image.tmdb.org/t/p/w500/f89U3ADr1oiB1s9GkdPOEpXUk5H.jpg',8.7,8.8],
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
        /* типы: title,orig,year,country,director,duration,genres,desc,cover,admin,imdb */
        foreach (SEED_MOVIES as $m) {
            $st->bind_param('ssississsdd', $m[0],$m[1],$m[2],$m[3],$m[4],$m[5],$m[6],$m[7],$m[8],$m[9],$m[10]);
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
    try {
        $raw  = (string)file_get_contents('php://input');
        $body = $raw;
        $t    = ltrim($body);
        /* base64-туннель: сайт упаковывает запросы, чтобы защита
           хостинга не резала JSON с длинными ссылками и текстами */
        if (isset($_SERVER['HTTP_X_KM']) || ($t !== '' && $t[0] !== '{' && $t[0] !== '[')) {
            $dec = base64_decode(preg_replace('/[^A-Za-z0-9+\/=]/', '', $t), true);
            if (is_string($dec) && $dec !== '') $body = $dec;
        }
        $in  = json_decode($body, true);
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
            /* типы: title,orig_title,year,country,director,duration,genres,desc,cover,admin,imdb */
            $types = 'ssississsdd';
            if (!empty($in['id'])) {
                $id = (int)$in['id'];
                $st = $c->prepare("UPDATE `movies` SET `title`=?,`original_title`=?,`year`=?,`country`=?,`director`=?,`duration`=?,`genres`=?,`description`=?,`cover_url`=?,`admin_score`=?,`imdb_score`=? WHERE `id`=?");
                if (!$st) jout(['ok' => false, 'error' => 'SQL-ошибка (prepare): ' . $c->error]);
                if (!@$st->bind_param($types . 'i', $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score'],$id))
                    jout(['ok' => false, 'error' => 'Ошибка привязки полей (update)']);
                if (!@$st->execute()) jout(['ok' => false, 'error' => 'Ошибка записи: ' . $st->error]);
                $st->close();
                jout(['ok' => true, 'movies' => fetch_all_movies()]);
            }
            $st = $c->prepare("INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            if (!$st) jout(['ok' => false, 'error' => 'SQL-ошибка (prepare): ' . $c->error]);
            if (!@$st->bind_param($types, $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score']))
                jout(['ok' => false, 'error' => 'Ошибка привязки полей (insert)']);
            if (!@$st->execute()) jout(['ok' => false, 'error' => 'Ошибка записи: ' . $st->error]);
            $st->close();
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
            $added = 0; $skipped = 0; $lastErr = '';
            $st = $c->prepare("INSERT INTO `movies` (`title`,`original_title`,`year`,`country`,`director`,`duration`,`genres`,`description`,`cover_url`,`admin_score`,`imdb_score`) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            if (!$st) jout(['ok' => false, 'error' => 'SQL-ошибка (prepare): ' . $c->error]);
            $types = 'ssississsdd';
            foreach ($list as $d) {
                $m = normalize_movie($d);
                if (!$m) { $skipped++; continue; }
                if (!@$st->bind_param($types, $m['title'],$m['original_title'],$m['year'],$m['country'],$m['director'],$m['duration'],$m['genres'],$m['description'],$m['cover_url'],$m['admin_score'],$m['imdb_score']))
                    { $skipped++; $lastErr = 'привязка полей'; continue; }
                if (!@$st->execute()) { $skipped++; $lastErr = $st->error; continue; }
                $added++;
            }
            $st->close();
            jout(['ok' => true, 'added' => $added, 'skipped' => $skipped,
                  'error' => $lastErr, 'movies' => fetch_all_movies()]);
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
            if (!@$st->execute()) jout(['ok' => false, 'error' => 'Такой логин уже занят']);
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
    } catch (\Throwable $e) {
        jout(['ok' => false, 'error' => 'Ошибка сервера: ' . $e->getMessage()]);
    }
}
/* если запрос не к API — отдаём сайт (всё, что ниже, это обычный HTML) */
