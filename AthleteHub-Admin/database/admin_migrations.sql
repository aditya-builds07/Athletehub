-- ═══════════════════════════════════════════════════════════════
--  AthleteHub Admin Panel — Required SQL Migrations
--  Run these on the existing `athletehub` database
-- ═══════════════════════════════════════════════════════════════

USE `athletehub`;

-- Add 'suspended' column to users table (used by admin panel)
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `suspended` TINYINT(1) NOT NULL DEFAULT 0;
