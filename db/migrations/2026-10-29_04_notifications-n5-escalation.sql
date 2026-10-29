-- =============================================================================
-- Migration: Notifications N5(b) - the escalation ladder.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: two nullable-or-
-- defaulted columns and one index. No existing row is modified, nothing is
-- dropped. Idempotent (safe to re-run).
--
-- escalate_after_minutes DEFAULTS TO 0, AND 0 MEANS OFF. That matters more than
-- it looks: every rule that already exists keeps behaving exactly as it does
-- today, notifying all of its recipients at once. The ladder only starts
-- working on a rule where somebody has set an interval. A migration that
-- silently turned every existing rule into a one-person-at-a-time ladder would
-- quietly stop telling people things.
--
-- escalation_tier records which rung of the ladder a message was sent on, so
-- the worker can work out what the next rung is. Tier 0 is the first
-- notification, which is why the default is 0 and every existing row is
-- correct without being touched.
--
-- Rollback:
--   ALTER TABLE tbl_notification_outbox DROP INDEX idx_notify_ladder;
--   ALTER TABLE tbl_notification_outbox DROP COLUMN escalation_tier;
--   ALTER TABLE tbl_notification_rules DROP COLUMN escalate_after_minutes;
-- =============================================================================

-- 1. Per-rule interval. 0 = no ladder, which is every existing rule.
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_rules`
       ADD COLUMN `escalate_after_minutes` int NOT NULL DEFAULT 0
       COMMENT ''0 = notify everyone at once (the old behaviour). Above 0 = tier by tier, this many minutes apart.''',
    'DO 0')
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_notification_rules'
     AND COLUMN_NAME = 'escalate_after_minutes'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Which rung a message went out on.
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD COLUMN `escalation_tier` int NOT NULL DEFAULT 0
       COMMENT ''0 = the first notification; 1+ = sent by the ladder''',
    'DO 0')
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_notification_outbox'
     AND COLUMN_NAME = 'escalation_tier'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3. The worker's ladder query: which rung has this alert reached?
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD KEY `idx_notify_ladder` (`ack_group`, `rule_id`, `escalation_tier`)',
    'DO 0')
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_notification_outbox'
     AND INDEX_NAME = 'idx_notify_ladder'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
