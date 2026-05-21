# Database Reference — AthleteHub Audit Skill

## Full Schema Map

### Core Tables & Expected Indexes

```sql
-- users (parent of everything)
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    email         VARCHAR(255) UNIQUE NOT NULL,
    password      VARCHAR(255) NOT NULL,           -- bcrypt hash
    role          ENUM('athlete','coach','recruiter','club','admin') DEFAULT 'athlete',
    is_verified   TINYINT(1) DEFAULT 0,
    profile_photo VARCHAR(500) DEFAULT NULL,
    created_at    DATETIME DEFAULT NOW(),
    INDEX idx_role (role),
    INDEX idx_email (email)                        -- usually auto from UNIQUE
);

-- posts
-- Required indexes: user_id, created_at
ALTER TABLE posts ADD INDEX idx_posts_user (user_id);
ALTER TABLE posts ADD INDEX idx_posts_created (created_at);

-- post_likes — composite unique prevents double-likes
-- Required: unique(user_id, post_id), index on post_id
ALTER TABLE post_likes ADD UNIQUE KEY uq_like (user_id, post_id);
ALTER TABLE post_likes ADD INDEX idx_likes_post (post_id);

-- messages — heavily queried by receiver
ALTER TABLE messages ADD INDEX idx_msg_receiver (receiver_id);
ALTER TABLE messages ADD INDEX idx_msg_sender (sender_id);
ALTER TABLE messages ADD INDEX idx_msg_read (is_read);

-- recruitment
ALTER TABLE recruitment ADD INDEX idx_rec_active (is_active);
ALTER TABLE recruitment ADD INDEX idx_rec_user (user_id);

-- recruitment_applications
ALTER TABLE recruitment_applications
    ADD UNIQUE KEY uq_application (recruitment_id, user_id);

-- tournaments
ALTER TABLE tournaments ADD INDEX idx_tourn_status (status);
ALTER TABLE tournaments ADD INDEX idx_tourn_host (created_by);

-- tournament_registrations
ALTER TABLE tournament_registrations
    ADD UNIQUE KEY uq_registration (tournament_id, user_id);

-- live_streams
ALTER TABLE live_streams ADD INDEX idx_stream_status (status);
ALTER TABLE live_streams ADD INDEX idx_stream_host (host_user_id);

-- role_applications
ALTER TABLE role_applications ADD INDEX idx_roleapp_user (user_id);
ALTER TABLE role_applications ADD INDEX idx_roleapp_status (status);
```

---

## Orphaned Record Detection Queries

Run all of these during audit. Any non-zero count is a data integrity problem:

```sql
-- Posts without valid user
SELECT COUNT(*) as orphaned_posts FROM posts p
LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL;

-- Messages with invalid sender
SELECT COUNT(*) as orphaned_msg_sender FROM messages m
LEFT JOIN users u ON m.sender_id = u.id WHERE u.id IS NULL;

-- Messages with invalid receiver
SELECT COUNT(*) as orphaned_msg_receiver FROM messages m
LEFT JOIN users u ON m.receiver_id = u.id WHERE u.id IS NULL;

-- Post likes with no valid post
SELECT COUNT(*) as orphaned_likes FROM post_likes pl
LEFT JOIN posts p ON pl.post_id = p.id WHERE p.id IS NULL;

-- Post comments with no valid post
SELECT COUNT(*) as orphaned_comments FROM post_comments pc
LEFT JOIN posts p ON pc.post_id = p.id WHERE p.id IS NULL;

-- Recruitment applications with no valid job
SELECT COUNT(*) as orphaned_applications FROM recruitment_applications ra
LEFT JOIN recruitment r ON ra.recruitment_id = r.id WHERE r.id IS NULL;

-- Tournament registrations with no valid tournament
SELECT COUNT(*) as orphaned_registrations FROM tournament_registrations tr
LEFT JOIN tournaments t ON tr.tournament_id = t.id WHERE t.id IS NULL;

-- Live streams with no valid host
SELECT COUNT(*) as orphaned_streams FROM live_streams ls
LEFT JOIN users u ON ls.host_user_id = u.id WHERE u.id IS NULL;

-- Role applications with no valid user
SELECT COUNT(*) as orphaned_role_apps FROM role_applications rla
LEFT JOIN users u ON rla.user_id = u.id WHERE u.id IS NULL;

-- Follows with invalid follower or following
SELECT COUNT(*) as orphaned_follows FROM follows f
LEFT JOIN users u1 ON f.follower_id = u1.id
LEFT JOIN users u2 ON f.following_id = u2.id
WHERE u1.id IS NULL OR u2.id IS NULL;
```

---

## Column Type Audit Queries

```sql
-- Find VARCHAR columns that should be TINYINT (boolean flags)
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'athletehub'
  AND COLUMN_NAME IN ('is_read','is_active','is_verified','is_open')
  AND COLUMN_TYPE NOT LIKE 'tinyint%';

-- Find VARCHAR columns storing dates
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'athletehub'
  AND COLUMN_NAME LIKE '%_at'
  AND COLUMN_TYPE LIKE 'varchar%';

-- Find columns that should be TEXT but are VARCHAR(255)
-- (descriptions, bios, messages, content)
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'athletehub'
  AND COLUMN_NAME IN ('description','bio','content','message','body','note')
  AND COLUMN_TYPE = 'varchar(255)';
```

---

## Missing Columns Checklist

Verify these columns exist (added in recent features):

```sql
-- Added for role application system
SHOW COLUMNS FROM users LIKE 'is_verified';
SHOW COLUMNS FROM role_applications LIKE 'profile_photo';
SHOW COLUMNS FROM role_applications LIKE 'city';
SHOW COLUMNS FROM role_applications LIKE 'years_experience';
SHOW COLUMNS FROM role_applications LIKE 'team_player_count';
SHOW COLUMNS FROM role_applications LIKE 'instagram';

-- Added for live streams
SHOW COLUMNS FROM live_streams LIKE 'host_user_id';
SHOW COLUMNS FROM live_streams LIKE 'tournament_id';
SHOW COLUMNS FROM live_streams LIKE 'status';

-- Admin role in ENUM
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'athletehub'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';
-- Should include 'admin' in the ENUM
```

---

## Performance Queries

### Find slow query candidates (N+1 risks in PHP):
When auditing PHP, look for patterns like:
```php
// ❌ N+1: query inside loop
$posts = $pdo->query("SELECT * FROM posts")->fetchAll();
foreach ($posts as $post) {
    $likes = $pdo->query("SELECT COUNT(*) FROM post_likes WHERE post_id = {$post['id']}")->fetchColumn();
}

// ✅ Single JOIN query
$posts = $pdo->query("
    SELECT p.*, COUNT(pl.id) as like_count
    FROM posts p
    LEFT JOIN post_likes pl ON p.id = pl.post_id
    GROUP BY p.id
")->fetchAll();
```

### Queries that must have LIMIT:
- Feed queries: `SELECT * FROM posts` → must have `LIMIT X OFFSET Y`
- Message queries: must be paginated
- Recruitment listings: must be paginated
- Any query returning a list must have `LIMIT`

---

## Role Enum Verification

```sql
-- Should return: athlete, coach, recruiter, club, admin
SELECT COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'athletehub'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'role';

-- If admin is missing, run:
ALTER TABLE users
MODIFY COLUMN role
ENUM('athlete','coach','recruiter','club','admin') DEFAULT 'athlete';
```
