-- =====================================================================
--  КИНОМЕТР — схема базы данных для AwardSpace
--  База: 4772808_base · Хост: fdb1029.awardspace.net:3306 · MySQL 8
--
--  Как импортировать: phpMyAdmin → база 4772808_base → вкладка «Импорт»
--  → выберите этот файл → «Вперёд».
--
--  Примечание: api.php создаёт эти таблицы АВТОМАТИЧЕСКИ при первом
--  обращении, так что импорт этого файла не обязателен. Главный
--  администратор (admin / kinometr) создаётся самим api.php —
--  смените пароль после первого входа.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `movies` (
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
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `login` VARCHAR(64) NOT NULL,
  `pass` VARCHAR(255) NOT NULL,
  `role` VARCHAR(16) NOT NULL DEFAULT 'admin',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_login` (`login`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Стартовый каталог (жанры хранятся JSON-массивом)
INSERT INTO `movies`
  (`title`, `original_title`, `year`, `country`, `director`, `duration`, `genres`, `description`, `cover_url`, `admin_score`, `imdb_score`)
VALUES
  ('Дюна: Часть вторая', 'Dune: Part Two', 2024, 'США', 'Дени Вильнёв', 166,
   '["фантастика","приключения","драма"]',
   'Пол Атрейдес объединяется с фременами, чтобы отомстить за свою семью и предотвратить страшное будущее, которое видит лишь он один.',
   'https://image.tmdb.org/t/p/w500/8b8R8l88Qje9dn9OE8PY05Nxl1X.jpg', 8.4, 8.5),
  ('Оппенгеймер', 'Oppenheimer', 2023, 'США, Великобритания', 'Кристофер Нолан', 180,
   '["биография","драма","триллер"]',
   'История «отца атомной бомбы» Роберта Оппенгеймера: проект «Манхэттен», триумф науки и моральная пропасть под ногами её творцов.',
   'https://image.tmdb.org/t/p/w500/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', 8.6, 8.3),
  ('Интерстеллар', 'Interstellar', 2014, 'США, Великобритания', 'Кристофер Нолан', 169,
   '["фантастика","драма","приключения"]',
   'Земля умирает, и экипаж исследователей отправляется сквозь червоточину в поисках нового дома.',
   'https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', 9.2, 8.7),
  ('Начало', 'Inception', 2010, 'США, Великобритания', 'Кристофер Нолан', 148,
   '["фантастика","боевик","триллер"]',
   'Дом Кобб — извлечатель идей из чужих снов — получает задание наоборот: внедрить мысль так глубоко, чтобы жертва приняла её за свою.',
   'https://image.tmdb.org/t/p/w500/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg', 9.0, 8.8),
  ('Паразиты', 'Gisaengchung', 2019, 'Южная Корея', 'Пон Джун-хо', 132,
   '["триллер","драма","комедия"]',
   'Бедная семья Ким хитростью внедряется в богатый дом Паков. Социальная сатира, оборачивающаяся кровавой трагикомедией.',
   'https://image.tmdb.org/t/p/w500/7IiTTgloJzvGI1TAYymCfbfl3vT.jpg', 8.9, 8.5),
  ('Побег из Шоушенка', 'The Shawshank Redemption', 1994, 'США', 'Фрэнк Дарабонт', 142,
   '["драма"]',
   'Банкир Энди Дюфрейн, осуждённый за убийство, которого не совершал, двадцать лет не теряет надежды — и учит надеяться остальных.',
   'https://image.tmdb.org/t/p/w500/q6y0Go1tsGEsmtFryDOJo3dEmqu.jpg', 9.5, 9.1);
