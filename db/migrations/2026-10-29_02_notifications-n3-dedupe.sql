-- =============================================================================
-- Migration: Notifications N3 - one outbox idempotency key.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one nullable column and
-- one unique index on tbl_notification_outbox. No existing row is modified,
-- nothing is dropped, and every existing row keeps dedupe_key NULL.
-- Idempotent (safe to re-run).
--
-- WHY
-- The POS retries push_online() every 7 seconds until it sees a success, which
-- is right: the money has been taken and the sale must not be lost. But the
-- customer receipt SMS was queued on every one of those attempts, because the
-- enqueue sits in the post-commit block and is gated only on send_sms_status
-- and the customer having a phone number - not on whether this attempt was the
-- one that created the sale. A dropped response, a timeout, a till on bad
-- Wi-Fi: the sale is updated once and correctly, and the customer is texted
-- again for each retry. Reproduced over real HTTP: two attempts, one sale,
-- two receipt rows.
--
-- A unique key on the outbox is the fix rather than a flag on the sale, because
-- the outbox is already the record of what has been queued, and because it then
-- works for any event that can be triggered more than once - the N5 heartbeat
-- and escalation ladder need exactly the same guarantee.
--
-- MySQL allows any number of NULLs in a UNIQUE index, so rows that do not ask
-- to be de-duplicated are completely unaffected. Only a caller that passes a
-- dedupe_key gets the constraint.
--
-- Rollback:
--   ALTER TABLE tbl_notification_outbox DROP INDEX uniq_notify_dedupe;
--   ALTER TABLE tbl_notification_outbox DROP COLUMN dedupe_key;
-- =============================================================================

-- Idempotent column add. information_schema is checked rather than using
-- "ADD COLUMN IF NOT EXISTS", which MySQL 8 does not support for columns.
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD COLUMN `dedupe_key` varchar(190) DEFAULT NULL
       COMMENT ''NULL = always queue. Set = queue at most once for this key.''',
    'DO 0')
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tbl_notification_outbox'
     AND COLUMN_NAME  = 'dedupe_key'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Idempotent index add.
SET @sql = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `tbl_notification_outbox`
       ADD UNIQUE KEY `uniq_notify_dedupe` (`dedupe_key`)',
    'DO 0')
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME   = 'tbl_notification_outbox'
     AND INDEX_NAME   = 'uniq_notify_dedupe'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
