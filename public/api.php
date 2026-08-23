<?php
declare(strict_types=1);

/* =====================================================================
   КИНОМЕТР — API в одном файле (PHP 8 + MySQL, AwardSpace)

   Как развернуть:
   1. Загрузите этот файл в корень сайта (htdocs) рядом с index.html.
   2. Впишите пароль от БД в константу DB_PASS ниже.
   3. Откройте сайт — таблицы и главный администратор (admin / kinometr)
      создадутся автоматически. Схема для phpMyAdmin — в schema.sql.

   Эндпоинты (?action=…):
     ping           — проверка соединения (публичный)
     movies         — список фильмов (публичный)
     login          — POST {login,password} → токен
     admins         — список админов (нужен токен)
     admin_add      — POST {login,password} (нужен токен)
     admin_delete   — POST {id} (нужен токен)
     movie_save     — POST {…поля фильма…} (нужен токен)
     movie_delete   — POST {id} (нужен токен)
     import         — POST {movies:[…]} (нужен токен)
   ===================================================================== */

const DB_HOST = 'fdb1029.awardspace.net';
const DB_PORT = '3306';
const DB_NAME = '4772808_base';
const DB_USER = '4772808_base';
const DB_PASS = 'ВСТАВЬТЕ_ПАРОЛЬ_ОТ_БД';          // <-- ВАЖНО: впишите пароль от базы

const TOKEN_SECRET        = 'kinometr-4772808-q8Xp2VnL7d'; // желательно заменить на случайную строку
const TOKEN_TTL           = 60 * 60 * 24 * 14;             // токен живёт 14 дней
const DEFAULT_ADMIN_LOGIN = 'admin';
const DEFAULT_ADMIN_PASS  = 'kinometr';                    // смените после первого входа

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

function out(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function fail(string $msg, int $code = 400): void
{
    out(['ok' => false, 'error' => $msg], $code);
}
function input(): array
{
    $raw = (string) file_get_contents('php://input');
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
}
function cl(float $v): float
{
    return round(max(0, min(10, $v)), 1);
}

/* ---------- соединение с MySQL ---------- */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    fail('Нет соединения с MySQL. Проверьте DB_PASS в api.php. (' . $e->getMessage() . ')', 500);
}

/* ---------- схема и стартовые данные ---------- */
function ensureSchema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS movies (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        original_title VARCHAR(255) NOT NULL DEFAULT '',
        year SMALLINT NULL,
        country VARCHAR(150) NOT NULL DEFAULT '',
        director VARCHAR(200) NOT NULL DEFAULT '',
        duration INT NOT NULL DEFAULT 0,
        genres TEXT,
        description TEXT,
        cover_url TEXT,
        admin_score DECIMAL(3,1) NOT NULL DEFAULT 0,
        imdb_score DECIMAL(3,1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        login VARCHAR(64) NOT NULL,
        pass VARCHAR(255) NOT NULL,
        role VARCHAR(16) NOT NULL DEFAULT 'admin',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_login (login)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if ((int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO admins (login, pass, role) VALUES (?, ?, "root")')
            ->execute([DEFAULT_ADMIN_LOGIN, password_hash(DEFAULT_ADMIN_PASS, PASSWORD_DEFAULT)]);
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn() === 0) {
        seedMovies($pdo);
    }
}

function seedMovies(PDO $pdo): void
{
    $w500 = 'https://image.tmdb.org/t/p/w500';
    $rows = [
        ['Дюна: Часть вторая', 'Dune: Part Two', 2024, 'США', 'Дени Вильнёв', 166, 'фантастика,приключения,драма',
         'Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один.',
         "$w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg", 8.4, 8.5],
        ['Оппенгеймер', 'Oppenheimer', 2023, 'США, Великобритания', 'Кристофер Нолан', 180, 'биография,драма,триллер',
         'История «отца атомной бомбы» Роберта Оппенгеймера: проект «Манхэттен», триумф науки и моральная пропасть под ногами её творцов.',
         "$w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg", 8.6, 8.3],
        ['Интерстеллар', 'Interstellar', 2014, 'США, Великобритания', 'Кристофер Нолан', 169, 'фантастика,драма,приключения',
         'Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома.',
         "$w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg", 9.2, 8.7],
        ['Начало', 'Inception', 2010, 'США, Великобритания', 'Кристофер Нолан', 148, 'фантастика,боевик,триллер',
         'Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.',
         "$w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg", 9.0, 8.8],
        ['Паразиты', 'Gisaengchung', 2019, 'Южная Корея', 'Пон Джун-хо', 132, 'триллер,драма,комедия',
         'Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, оборачивающаяся кровавой трагикомедией.',
         "$w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg", 8.9, 8.5],
        ['Побег из Шоушенка', 'The Shawshank Redemption', 1994, 'США', 'Фрэнк Дарабонт', 142, 'драма',
         'Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных.',
         "$w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg", 9.5, 9.1],
    ];
    $ins = $pdo->prepare('INSERT INTO movies
        (title, original_title, year, country, director, duration, genres, description, cover_url, admin_score, imdb_score)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) {
        $r[6] = json_encode(array_map('trim', explode(',', $r[6])), JSON_UNESCAPED_UNICODE);
        $ins->execute($r);
    }
}

ensureSchema($pdo);

/* ---------- авторизация (HMAC-токены) ---------- */
function makeToken(string $login): string
{
    $exp = time() + TOKEN_TTL;
    $sig = hash_hmac('sha256', $login . '|' . $exp, TOKEN_SECRET);
    return rtrim(strtr(base64_encode($login . '|' . $exp . '|' . $sig), '+/', '-_'), '=');
}

function requireAuth(PDO $pdo): array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        $all = getallheaders() ?: [];
        $h = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    $tok = stripos($h, 'bearer ') === 0 ? substr($h, 7) : '';
    if ($tok === '') fail('Требуется авторизация — войдите в панель', 401);

    $raw = base64_decode(strtr($tok, '-_', '+/'));
    if ($raw === false) fail('Неверный токен — войдите заново', 401);
    $parts = explode('|', $raw);
    if (count($parts) !== 3) fail('Неверный токен — войдите заново', 401);
    [$login, $exp, $sig] = $parts;
    if ((int) $exp < time()) fail('Сессия истекла — войдите заново', 401);
    if (!hash_equals(hash_hmac('sha256', $login . '|' . $exp, TOKEN_SECRET), $sig)) {
        fail('Подпись токена недействительна — войдите заново', 401);
    }
    $st = $pdo->prepare('SELECT id, login, role FROM admins WHERE login = ? LIMIT 1');
    $st->execute([$login]);
    $a = $st->fetch();
    if (!$a) fail('Администратор не найден — войдите заново', 401);
    return $a;
}

/* ---------- преобразование строки БД в объект фильма ---------- */
function mapMovie(?array $r): array
{
    if (!$r) return [];
    $g = json_decode((string) ($r['genres'] ?? '[]'), true);
    return [
        'id'            => (int) $r['id'],
        'title'         => (string) $r['title'],
        'originalTitle' => (string) ($r['original_title'] ?? ''),
        'year'          => $r['year'] !== null ? (int) $r['year'] : 0,
        'country'       => (string) ($r['country'] ?? ''),
        'director'      => (string) ($r['director'] ?? ''),
        'duration'      => (int) ($r['duration'] ?? 0),
        'genres'        => is_array($g) ? array_values($g) : [],
        'description'   => (string) ($r['description'] ?? ''),
        'coverUrl'      => (string) ($r['cover_url'] ?? ''),
        'adminScore'    => (float) ($r['admin_score'] ?? 0),
        'imdbScore'     => (float) ($r['imdb_score'] ?? 0),
        'createdAt'     => (string) ($r['created_at'] ?? date('Y-m-d H:i:s')),
    ];
}

function genresToList($g): array
{
    if (is_string($g)) {
        $g = array_map('trim', explode(',', $g));
    }
    if (!is_array($g)) return [];
    return array_values(array_filter(array_map(static fn($x) => trim((string) $x), $g), static fn($x) => $x !== ''));
}

/* ---------- маршрутизация ---------- */
$action = (string) ($_GET['action'] ?? 'movies');

try {
    switch ($action) {

        case 'ping':
            out([
                'ok'     => true,
                'mode'   => 'server',
                'time'   => date('c'),
                'movies' => (int) $pdo->query('SELECT COUNT(*) FROM movies')->fetchColumn(),
            ]);

        case 'movies':
            $rows = $pdo->query('SELECT * FROM movies ORDER BY created_at DESC, id DESC')->fetchAll();
            out(['ok' => true, 'movies' => array_map('mapMovie', $rows)]);

        case 'login': {
            $b     = input();
            $login = trim((string) ($b['login'] ?? ''));
            $pass  = (string) ($b['password'] ?? '');
            if ($login === '' || $pass === '') fail('Введите логин и пароль');
            $st = $pdo->prepare('SELECT * FROM admins WHERE login = ? LIMIT 1');
            $st->execute([$login]);
            $a = $st->fetch();
            if (!$a || !password_verify($pass, (string) $a['pass'])) fail('Неверный логин или пароль', 401);
            out(['ok' => true, 'token' => makeToken((string) $a['login']), 'login' => (string) $a['login'], 'role' => (string) $a['role']]);
        }

        case 'admins': {
            requireAuth($pdo);
            $rows = $pdo->query('SELECT id, login, role, created_at FROM admins ORDER BY id')->fetchAll();
            out(['ok' => true, 'admins' => $rows]);
        }

        case 'admin_add': {
            requireAuth($pdo);
            $b     = input();
            $login = trim((string) ($b['login'] ?? ''));
            $pass  = (string) ($b['password'] ?? '');
            if (mb_strlen($login) < 3) fail('Логин — минимум 3 символа');
            if (mb_strlen($pass) < 6) fail('Пароль — минимум 6 символов');
            $st = $pdo->prepare('SELECT id FROM admins WHERE login = ?');
            $st->execute([$login]);
            if ($st->fetch()) fail('Такой логин уже занят');
            $pdo->prepare('INSERT INTO admins (login, pass, role) VALUES (?, ?, "admin")')
                ->execute([$login, password_hash($pass, PASSWORD_DEFAULT)]);
            out(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        }

        case 'admin_delete': {
            $me = requireAuth($pdo);
            $id = (int) (input()['id'] ?? 0);
            if ($id === (int) $me['id']) fail('Нельзя удалить самого себя');
            $st = $pdo->prepare('SELECT role FROM admins WHERE id = ?');
            $st->execute([$id]);
            $row = $st->fetch();
            if (!$row) fail('Администратор не найден', 404);
            if ($row['role'] === 'root') fail('Нельзя удалить главного администратора');
            $pdo->prepare('DELETE FROM admins WHERE id = ?')->execute([$id]);
            out(['ok' => true]);
        }

        case 'movie_save': {
            requireAuth($pdo);
            $b     = input();
            $title = trim((string) ($b['title'] ?? ''));
            if ($title === '') fail('Название фильма обязательно');
            $p = [
                $title,
                trim((string) ($b['originalTitle'] ?? '')),
                ((int) ($b['year'] ?? 0)) ?: null,
                trim((string) ($b['country'] ?? '')),
                trim((string) ($b['director'] ?? '')),
                (int) ($b['duration'] ?? 0),
                json_encode(genresToList($b['genres'] ?? []), JSON_UNESCAPED_UNICODE),
                trim((string) ($b['description'] ?? '')),
                trim((string) ($b['coverUrl'] ?? '')),
                cl((float) ($b['adminScore'] ?? 0)),
                cl((float) ($b['imdbScore'] ?? 0)),
            ];
            $id = (int) ($b['id'] ?? 0);
            if ($id > 0) {
                $p[] = $id;
                $pdo->prepare('UPDATE movies SET title=?, original_title=?, year=?, country=?, director=?, duration=?,
                    genres=?, description=?, cover_url=?, admin_score=?, imdb_score=? WHERE id=?')->execute($p);
            } else {
                $pdo->prepare('INSERT INTO movies (title, original_title, year, country, director, duration, genres,
                    description, cover_url, admin_score, imdb_score) VALUES (?,?,?,?,?,?,?,?,?,?,?)')->execute($p);
                $id = (int) $pdo->lastInsertId();
            }
            $st = $pdo->prepare('SELECT * FROM movies WHERE id = ?');
            $st->execute([$id]);
            out(['ok' => true, 'movie' => mapMovie($st->fetch() ?: null)]);
        }

        case 'movie_delete': {
            requireAuth($pdo);
            $id = (int) (input()['id'] ?? 0);
            $pdo->prepare('DELETE FROM movies WHERE id = ?')->execute([$id]);
            out(['ok' => true]);
        }

        case 'import': {
            requireAuth($pdo);
            $list = input()['movies'] ?? [];
            if (!is_array($list) || count($list) === 0) fail('Список фильмов пуст');
            $n   = 0;
            $ins = $pdo->prepare('INSERT INTO movies (title, original_title, year, country, director, duration, genres,
                description, cover_url, admin_score, imdb_score) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $pdo->beginTransaction();
            foreach ($list as $m) {
                if (!is_array($m)) continue;
                $title = trim((string) ($m['title'] ?? ''));
                if ($title === '') continue;
                $ins->execute([
                    $title,
                    trim((string) ($m['originalTitle'] ?? '')),
                    ((int) ($m['year'] ?? 0)) ?: null,
                    trim((string) ($m['country'] ?? '')),
                    trim((string) ($m['director'] ?? '')),
                    (int) ($m['duration'] ?? 0),
                    json_encode(genresToList($m['genres'] ?? []), JSON_UNESCAPED_UNICODE),
                    trim((string) ($m['description'] ?? '')),
                    trim((string) ($m['coverUrl'] ?? $m['cover'] ?? '')),
                    cl((float) ($m['adminScore'] ?? 0)),
                    cl((float) ($m['imdbScore'] ?? 0)),
                ]);
                $n++;
            }
            $pdo->commit();
            out(['ok' => true, 'imported' => $n]);
        }

        default:
            fail('Неизвестное действие: ' . $action, 404);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fail('Ошибка сервера: ' . $e->getMessage(), 500);
}
