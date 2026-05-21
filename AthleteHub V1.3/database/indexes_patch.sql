-- ═══════════════════════════════════════════════════════════════
--  AthleteHub — Phase 4 Missing Indexes Patch
--  Run this script in phpMyAdmin or MySQL CLI to safely apply the 
--  indexes identified during the performance audit.
-- ═══════════════════════════════════════════════════════════════

USE `athletehub`;

ALTER TABLE `messages` ADD INDEX `idx_messages_is_read` (`is_read`);
ALTER TABLE `recruitment` ADD INDEX `idx_recruitment_is_active` (`is_active`);
ALTER TABLE `live_streams` ADD INDEX `idx_live_streams_status` (`status`);
