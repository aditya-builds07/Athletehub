-- ═══════════════════════════════════════════════════════════════
--  AthleteHub — Full Database Schema + Sample Data
--  MySQL / MariaDB compatible (XAMPP)
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS `athletehub`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `athletehub`;

-- ──────────────────────────────────────
-- TABLE: users
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`            VARCHAR(100) NOT NULL,
  `email`           VARCHAR(150) NOT NULL UNIQUE,
  `password_hash`   VARCHAR(255) NOT NULL,
  `role`            ENUM('athlete','coach','recruiter','club','admin') NOT NULL DEFAULT 'athlete',
  `sport`           VARCHAR(80) DEFAULT NULL,
  `location`        VARCHAR(120) DEFAULT NULL,
  `bio`             TEXT DEFAULT NULL,
  `profile_pic`     VARCHAR(255) DEFAULT NULL,
  `is_verified`     TINYINT(1) NOT NULL DEFAULT 0,
  `followers_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `following_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: posts
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `posts` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `content`     TEXT NOT NULL,
  `image_url`   VARCHAR(500) DEFAULT NULL,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: post_likes
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `post_likes` (
  `id`       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`  INT UNSIGNED NOT NULL,
  `user_id`  INT UNSIGNED NOT NULL,
  UNIQUE KEY `unique_like` (`post_id`, `user_id`),
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: post_comments
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `post_comments` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `post_id`      INT UNSIGNED NOT NULL,
  `user_id`      INT UNSIGNED NOT NULL,
  `comment_text` TEXT NOT NULL,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`post_id`) REFERENCES `posts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: follows
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `follows` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `follower_id`  INT UNSIGNED NOT NULL,
  `following_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `unique_follow` (`follower_id`, `following_id`),
  FOREIGN KEY (`follower_id`)  REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`following_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: messages
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `messages` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sender_id`    INT UNSIGNED NOT NULL,
  `receiver_id`  INT UNSIGNED NOT NULL,
  `message_text` TEXT NOT NULL,
  `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_messages_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: recruitment
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recruitment` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `posted_by`   INT UNSIGNED NOT NULL,
  `title`       VARCHAR(200) NOT NULL,
  `sport`       VARCHAR(80) NOT NULL,
  `location`    VARCHAR(120) DEFAULT NULL,
  `type`        ENUM('job','scholarship','tryout','training') NOT NULL,
  `description` TEXT NOT NULL,
  `deadline`    DATE DEFAULT NULL,
  `is_active`   TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_recruitment_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: recruitment_applications
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `recruitment_applications` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `recruitment_id` INT UNSIGNED NOT NULL,
  `user_id`        INT UNSIGNED NOT NULL,
  `applied_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_application` (`recruitment_id`, `user_id`),
  FOREIGN KEY (`recruitment_id`) REFERENCES `recruitment`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)        REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: tournaments
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tournaments` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`        VARCHAR(200) NOT NULL,
  `sport`       VARCHAR(80) NOT NULL,
  `location`    VARCHAR(120) DEFAULT NULL,
  `start_date`  DATE NOT NULL,
  `end_date`    DATE NOT NULL,
  `prize_info`  VARCHAR(255) DEFAULT NULL,
  `status`         ENUM('open','closed','upcoming') NOT NULL DEFAULT 'upcoming',
  `created_by`     INT UNSIGNED NOT NULL,
  `host_user_id`   INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`host_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: tournament_registrations
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tournament_registrations` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tournament_id` INT UNSIGNED NOT NULL,
  `user_id`       INT UNSIGNED NOT NULL,
  `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_registration` (`tournament_id`, `user_id`),
  FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`)       REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: news
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `news` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title`      VARCHAR(255) NOT NULL,
  `content`    TEXT NOT NULL,
  `image_url`  VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `posted_by`  INT UNSIGNED NOT NULL,
  FOREIGN KEY (`posted_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ──────────────────────────────────────
-- TABLE: verification_requests
-- ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `verification_requests` (
  `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`      INT UNSIGNED NOT NULL,
  `reason`       TEXT DEFAULT NULL,
  `status`       ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_note`   VARCHAR(255) DEFAULT NULL,
  `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at`  TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ═══════════════════════════════════════════════════════════════
--  SAMPLE DATA
-- ═══════════════════════════════════════════════════════════════

-- Passwords are hashed using PHP password_hash('...', PASSWORD_DEFAULT)
-- Admin@123   → $2y$10$YQ8Klz2rKgZ0H5Rp6x1zXeJvC3aN9Q2wVr8YVbF7mXpW6uGZ1LhLO
-- Pass@123    → $2y$10$FkZk7YJGcmg1pD5RSRH2OOT1xJ3mN2l5BVqY0Z9oR8p6a4cKj7Wem
-- (For demo purposes; regenerate in production)

-- ── Users ──
INSERT INTO `users` (`name`, `email`, `password_hash`, `role`, `sport`, `location`, `bio`, `is_verified`, `followers_count`, `following_count`) VALUES
('Admin',            'admin@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin',     NULL,          'Mumbai, India',    'AthleteHub platform administrator.',                                          1, 0, 0),
('Arjun Sharma',     'arjun@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Football',    'Pune, India',      'Striker at FC Pune City. National U-21 team player. 🏈',                      1, 245, 120),
('Priya Patel',      'priya@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Basketball',  'Delhi, India',     'Point guard | Delhi Dynamos 🏀 | Looking for scholarship opportunities.',     0, 132, 98),
('Rahul Verma',      'rahul@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Cricket',     'Bangalore, India', 'All-rounder. Karnataka state team. Dream: play for India 🏏',                 1, 510, 45),
('Coach Mehra',      'mehra@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'coach',     'Football',    'Mumbai, India',    'AFC Pro License coach. 15+ years experience in youth development.',           1, 890, 200),
('Talent Scout India','scout@athletehub.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'recruiter', 'Multi-sport', 'Hyderabad, India', 'Professional sports recruiter. Connecting talent with opportunity.',           1, 320, 150),
('Mumbai Sports Club','mumbaisc@athletehub.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','club',      'Multi-sport', 'Mumbai, India',    'Est. 1998. Premier sports club with football, cricket, and tennis academies.',1, 1200, 50),
('David Osei',       'david@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Track & Field','Kingston, Jamaica','National Sprinter prepping for the upcoming relays. 🏃‍♂️💨',                1, 89000, 310),
('Liam O\'Connor',   'liam@athletehub.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Swimming',    'Sydney, Australia','Butterfly / Freestyle specialist. Chasing records in the pool. 🏊‍♂️🌊',      1, 31000, 420),
('Elena Rostova',    'elena@athletehub.com',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'athlete',   'Weightlifting','Sofia, Bulgaria',  '71kg Category weightlifter. Strength, focus, and heavy lifts. 🏋️‍♀️💪',       1, 62500, 180);


-- ── Posts ──
INSERT INTO `posts` (`user_id`, `content`, `image_url`, `created_at`) VALUES
(2, '⚽ Just scored a hat-trick in today''s ISL qualifier! Hard work pays off. Grateful to Coach Mehra for the incredible training sessions. #Football #ISL #DreamBig', NULL, '2026-03-17 14:30:00'),
(3, '🏀 Great practice session today with the team. Working on our pick-and-roll plays. The chemistry is building! Who else is prepping for the nationals?', NULL, '2026-03-17 10:15:00'),
(4, '🏏 Century in the Ranji Trophy today! 112 off 98 balls. Feeling blessed and motivated. Thanks to everyone who believed in me! 🙏 #Cricket #RanjiTrophy', NULL, '2026-03-16 18:45:00'),
(5, 'Looking for talented U-19 football players for our elite training camp this summer. If you have what it takes, reach out! 🔥 #TalentSearch #Football #CoachLife', NULL, '2026-03-16 09:00:00'),
(7, '🎾 Mumbai Sports Club is excited to announce our new state-of-the-art tennis courts! Grand opening next month. Stay tuned for registration details. #Tennis #MumbaiSC', NULL, '2026-03-15 12:00:00'),
(3, 'Early morning grind. Championship mindset never sleeps. 🏀🔥', 'https://images.unsplash.com/photo-1608245449230-4ac19066d2d0?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w5NTE0ODF8MHwxfHNlYXJjaHwxfHxiYXNrZXRiYWxsJTIwcGxheWVyJTIwYXRobGV0ZXxlbnwwfHx8fDE3Nzg3ODYzOTV8MA&ixlib=rb-4.1.0&q=80&w=1080', '2026-05-15 08:30:00'),
(2, 'Eyes on the goal. Every match is an opportunity to prove yourself on the pitch. ⚽⚡', 'https://images.unsplash.com/photo-1560272564-c83b66b1ad12?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w5NTE0ODF8MHwxfHNlYXJjaHwxfHxmb290YmFsbCUyMHNvY2NlciUyMGF0aGxldGUlMjBhY3Rpb258ZW58MHx8fHwxNzc4Nzg2Mzk2fDA&ixlib=rb-4.1.0&q=80&w=1080', '2026-05-15 09:15:00'),
(8, 'Speed is just execution meets preparation. Ready for the upcoming relays! 🏃‍♂️💨', 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w5NTE0ODF8MHwxfHNlYXJjaHwxfHxydW5uZXIlMjBzcHJpbnRlciUyMHRyYWNrJTIwYXRobGV0ZXxlbnwwfHx8fDE3Nzg3ODYzOTd8MA&ixlib=rb-4.1.0&q=80&w=1080', '2026-05-15 09:45:00'),
(9, 'Water is my element. Focused on shaving off those milliseconds in the pool today. 🏊‍♂️🌊', 'https://images.unsplash.com/photo-1530549387789-4c1017266635?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w5NTE0ODF8MHwxfHNlYXJjaHwxfHxzd2ltbWVyJTIwYXRobGV0ZSUyMHBvb2x8ZW58MHx8fHwxNzc4Nzg2Mzk4fDA&ixlib=rb-4.1.0&q=80&w=1080', '2026-05-15 10:00:00'),
(10, 'Heavy lifts, strong mind. Pushing new personal bests in the gym this week. 🏋️‍♀️💪', 'https://images.unsplash.com/photo-1595078475328-1ab05d0a6a0e?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w5NTE0ODF8MHwxfHNlYXJjaHwyfHxneW0lMjB3ZWlnaHRsaWZ0aW5nJTIwYXRobGV0ZXxlbnwwfHx8fDE3Nzg3ODYzOTl8MA&ixlib=rb-4.1.0&q=80&w=1080', '2026-05-15 10:30:00');


-- ── Post Likes ──
INSERT INTO `post_likes` (`post_id`, `user_id`) VALUES
(1, 3), (1, 4), (1, 5), (1, 7),
(2, 2), (2, 4),
(3, 2), (3, 3), (3, 5), (3, 6), (3, 7),
(4, 2), (4, 3),
(5, 2), (5, 3), (5, 4), (5, 6),
(6, 2), (6, 4), (6, 5),
(7, 3), (7, 5), (7, 6),
(8, 2), (8, 5), (8, 7),
(9, 3), (9, 4), (9, 6),
(10, 2), (10, 5), (10, 7);


-- ── Post Comments ──
INSERT INTO `post_comments` (`post_id`, `user_id`, `comment_text`, `created_at`) VALUES
(1, 5, 'Incredible performance, Arjun! Keep it up! 👏', '2026-03-17 15:00:00'),
(1, 3, 'Congrats bro! You make it look easy 🔥', '2026-03-17 15:30:00'),
(3, 5, 'Well played, Rahul! The future of Indian cricket.', '2026-03-16 19:00:00'),
(3, 2, 'That straight drive was insane! 🏏💪', '2026-03-16 19:30:00'),
(5, 6, 'Would love to scout some players there. Count me in!', '2026-03-15 13:00:00');


-- ── Follows ──
INSERT INTO `follows` (`follower_id`, `following_id`) VALUES
(2, 5), (2, 7), (3, 2), (3, 5),
(4, 2), (4, 5), (4, 7), (5, 2),
(5, 4), (6, 2), (6, 3), (6, 4),
(7, 5), (7, 6);


-- ── Messages ──
INSERT INTO `messages` (`sender_id`, `receiver_id`, `message_text`, `is_read`, `created_at`) VALUES
(6, 2, 'Hi Arjun, I saw your hat-trick today. Impressive! Let''s discuss opportunities.', 0, '2026-03-17 16:00:00'),
(5, 2, 'Great game today! Let''s schedule extra training for the semifinal.', 1, '2026-03-17 17:00:00'),
(6, 4, 'Rahul, your Ranji century was outstanding. Some franchises are interested.', 0, '2026-03-16 20:00:00');


-- ── Recruitment ──
INSERT INTO `recruitment` (`posted_by`, `title`, `sport`, `location`, `type`, `description`, `deadline`, `is_active`) VALUES
(6, 'U-23 Football Striker — FC Goa Academy',  'Football',   'Goa, India',      'tryout',
 'FC Goa is looking for talented U-23 strikers for its academy program. Must have state-level experience. Accommodation and training provided.',
 '2026-04-15', 1),
(7, 'Sports Scholarship — Cricket Excellence Program', 'Cricket', 'Mumbai, India', 'scholarship',
 'Mumbai Sports Club offers a full scholarship for outstanding cricketers aged 16-22. Covers training, nutrition, and competition fees for one year.',
 '2026-05-01', 1);


-- ── Recruitment Applications ──
INSERT INTO `recruitment_applications` (`recruitment_id`, `user_id`) VALUES
(1, 2),
(2, 4);


-- ── Tournaments ──
INSERT INTO `tournaments` (`name`, `sport`, `location`, `start_date`, `end_date`, `prize_info`, `status`, `created_by`, `host_user_id`) VALUES
('All India Inter-Club Football Cup 2026',     'Football',   'Pune, India',      '2026-04-10', '2026-04-20', '₹5,00,000 prize pool',   'open',     7, 7),
('National Basketball Championship — West Zone','Basketball', 'Mumbai, India',    '2026-05-05', '2026-05-12', '₹3,00,000 + trophies',   'upcoming', 7, 7),
('Southern Cricket Premier League 2025',        'Cricket',    'Chennai, India',   '2025-11-01', '2025-11-15', '₹10,00,000 prize pool',  'closed',   5, 5);


-- ── Tournament Registrations ──
INSERT INTO `tournament_registrations` (`tournament_id`, `user_id`) VALUES
(1, 2), (1, 4),
(2, 3);


-- ── News ──
INSERT INTO `news` (`title`, `content`, `image_url`, `posted_by`, `created_at`) VALUES
('India Qualifies for FIFA U-20 World Cup',
 'In a historic achievement, the Indian U-20 football team has qualified for the FIFA U-20 World Cup after a stunning 3-1 victory over Australia in the Asian qualifiers. Coach Mehra, who has trained several players on the squad, expressed his pride: "This is just the beginning for Indian football." The tournament will be held in Indonesia later this year.',
 NULL, 1, '2026-03-17 08:00:00'),

('New National Sports Policy Announced — Focus on Grassroots Development',
 'The Ministry of Youth Affairs and Sports has announced a comprehensive National Sports Policy aimed at nurturing grassroots talent across India. Key highlights include: establishment of 500 new sports academies in tier-2 and tier-3 cities, increased funding for athlete scholarships, and partnerships with international coaching institutes. AthleteHub has been recognized as a key digital platform for athlete networking.',
 NULL, 1, '2026-03-15 10:00:00'),

('IPL 2026 Auction: Record-Breaking Deals for Young Talent',
 'The IPL 2026 mega auction saw unprecedented investment in young cricketers. Several U-23 players fetched record prices, signaling a shift toward youth development in franchise cricket. Rahul Verma from Karnataka, an AthleteHub member, was among the players who attracted attention from multiple franchises during pre-auction trials.',
 NULL, 1, '2026-03-13 14:00:00');


-- ── Live Streams ──
CREATE TABLE IF NOT EXISTS `live_streams` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `host_user_id`    INT UNSIGNED NOT NULL,
  `title`           VARCHAR(255) NOT NULL,
  `youtube_url`     VARCHAR(500) NOT NULL,
  `tournament_id`   INT UNSIGNED DEFAULT NULL,
  `status`          ENUM('live','ended') DEFAULT 'live',
  `created_at`      DATETIME DEFAULT NOW(),
  FOREIGN KEY (`host_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`tournament_id`) REFERENCES `tournaments`(`id`) ON DELETE SET NULL,
  INDEX `idx_live_streams_status` (`status`)
);


-- ── Role Applications & Verification ──
ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `is_verified` TINYINT(1) DEFAULT 0;

-- ── Notifications Table ──
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `type`        VARCHAR(50) NOT NULL,
  `message`     TEXT NOT NULL,
  `is_read`     TINYINT(1) NOT NULL DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_notifications_user_id` (`user_id`),
  INDEX `idx_notifications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Role Applications Table ──
CREATE TABLE IF NOT EXISTS `role_applications` (
  `id`                INT AUTO_INCREMENT PRIMARY KEY,
  `user_id`           INT UNSIGNED NOT NULL,
  `requested_role`    ENUM('club', 'recruiter') NOT NULL,
  `organisation_name` VARCHAR(255) NOT NULL,
  `description`       TEXT NOT NULL,
  `website`           VARCHAR(500) DEFAULT NULL,
  `phone`             VARCHAR(20) NOT NULL,
  `document_path`     VARCHAR(500) NOT NULL,
  `document_type`     VARCHAR(100) NOT NULL,
  `profile_photo`     VARCHAR(255) DEFAULT NULL,
  `years_experience`  INT DEFAULT NULL,
  `team_player_count` INT DEFAULT NULL,
  `city`              VARCHAR(100) DEFAULT NULL,
  `country`           VARCHAR(100) DEFAULT NULL,
  `instagram`         VARCHAR(100) DEFAULT NULL,
  `twitter`           VARCHAR(100) DEFAULT NULL,
  `linkedin`          VARCHAR(255) DEFAULT NULL,
  `facebook`          VARCHAR(255) DEFAULT NULL,
  `youtube`           VARCHAR(255) DEFAULT NULL,
  `status`            ENUM('pending','approved','rejected') DEFAULT 'pending',
  `admin_note`        TEXT DEFAULT NULL,
  `reviewed_by`       INT UNSIGNED DEFAULT NULL,
  `reviewed_at`       DATETIME DEFAULT NULL,
  `submitted_at`      DATETIME DEFAULT NULL,
  `created_at`        DATETIME DEFAULT NOW(),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  UNIQUE KEY `unique_pending` (`user_id`, `requested_role`, `status`),
  INDEX `idx_role_applications_status` (`status`),
  INDEX `idx_role_applications_user_id` (`user_id`),
  INDEX `idx_role_applications_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_users_role` ON `users`(`role`);

