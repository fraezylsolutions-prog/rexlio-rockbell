-- =============================================================================
-- Migration: Notifications N5(a) - acknowledging a critical alert.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one nullable column on
-- tbl_notification_outbox and one new table. No existing row is modified,
-- nothing is dropped. Idempotent (safe to re-run).
--
-- WHY A SEPARATE TABLE
-- tbl_notification_outbox already has acknowledged_at / acknowledged_by from N1,
-- and they stay: they record WHICH ROWS are settled. But a token cannot live
-- there, because one acknowledgement has to settle SEVERAL rows - the same alert
-- goes to two managers, and in N5(b) an unacknowledged alert is re-sent to the
-- next tier as NEW rows. Whoever taps first must stop the whole ladder, so the
-- token identifies the ALERT, not one message. ack_group is that identity.
--
-- THE TOKEN
-- Short, random, single-purpose and expiring, in that order of importance:
--   - short, because it has to fit in an SMS. 10 characters from a 62-letter
--     alphabet on a /ack/ path keeps a whole link inside 43 characters.
--   - random: 10 base62 characters is about 59 bits from random_bytes(). Not a
--     password, and it does not need to be: the worst a guess achieves is
--     silencing one alert, and it expires.
--   - single-purpose: it acknowledges one alert group and does nothing else. It
--     is not a login, it grants no session and it reveals no data beyond the one
--     alert's own subject.
--   - expiring: expires_at is set when the token is made. An old SMS in
--     somebody's phone must not silence next month's alert.
--
-- VISITING THE LINK MUST NOT ACKNOWLEDGE
-- SMS, WhatsApp and Telegram all fetch links to build previews, so a GET that
-- acknowledged would mean every critical alert arrived pre-acknowledged and the
-- escalation ladder would never fire. The GET only renders a page with a button;
-- the acknowledgement is the POST. acknowledged_ip is recorded because there is
-- no session behind it - the token is the authorisation.
--
-- Rollback:
--   DROP TABLE tbl_notification_acks;
--   ALTER TABLE tbl_notification_outbox DROP INDEX idx_notify_ack_group;
--   ALTER TABLE tbl_notification_outbox DROP COLUMN ack_group;
-- =============================================================================

-- 1. Which alert an outbox row belongs to. NULL for everything non-critical.
SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD COLUMN `ack_group` varchar(40) DEFAULT NULL
       COMMENT ''the alert this message is part of; NULL = nothing to acknowledge''',
    'DO 0')
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_notification_outbox'
     AND COLUMN_NAME = 'ack_group'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD KEY `idx_notify_ack_group` (`ack_group`)',
    'DO 0')
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_notification_outbox'
     AND INDEX_NAME = 'idx_notify_ack_group'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. One row per acknowledgeable alert.
CREATE TABLE IF NOT EXISTS `tbl_notification_acks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `token` varchar(16) NOT NULL COMMENT 'short, random, single-purpose, expiring',
  `ack_group` varchar(40) NOT NULL COMMENT 'matches tbl_notification_outbox.ack_group',
  `event_key` varchar(60) NOT NULL,
  `label` varchar(190) DEFAULT NULL COMMENT 'what to show on the page, so it needs no join',
  `outlet_id` int DEFAULT NULL,
  `company_id` int NOT NULL DEFAULT 1,
  `expires_at` datetime NOT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int DEFAULT NULL COMMENT 'user id when a session happens to exist; the token is the authorisation',
  `acknowledged_ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_notify_ack_token` (`token`),
  KEY `idx_notify_ack_group` (`ack_group`),
  KEY `idx_notify_ack_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
