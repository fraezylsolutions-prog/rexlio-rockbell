-- =============================================================================
-- Migration: origin marker on the RUNNING ORDERS table, so orders placed on one
--            side appear on the other side's kitchen screen (S7e).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one new nullable column
-- and one index on tbl_kitchen_sales. No existing row is modified, nothing is
-- dropped, and every existing query keeps working (code that does not know
-- about the column simply ignores it). Idempotent (safe to re-run).
--
-- WHY  2026-09-23_01 marked COMPLETED sales (tbl_sales) so the two sides can
--   merge them without overwriting each other. But an order does not start
--   life there: while it is being cooked and served it lives in
--   tbl_kitchen_sales, and that is the table the kitchen screen reads. A
--   website order that only reaches tbl_sales is an order the kitchen never
--   sees - it would appear in the reports after the fact and never on the
--   pass. Carrying the running order across needs the same marker, for the
--   same reason: sale numbers come from a per-installation device tag, so the
--   venue and the website can both issue the same number for different orders,
--   and only the origin plus the order's own random_code tells them apart.
--
-- WHAT IT ADDS
--   tbl_kitchen_sales.origin_id  the installation that created the order.
--                                NULL means "created before this migration"
--                                and is treated as belonging to whichever side
--                                the row is sitting on.
--   idx_kitchen_fingerprint      (sale_no, random_code), the pair a sync looks
--                                orders up by.
--
-- It relies on tbl_sync_identity, created by 2026-09-23_01. Apply that first.
--
-- WHAT IT DOES NOT DO  It changes no behaviour on its own. Until the sync tool
--   is used, the column is written by the order-placing path and read by
--   nobody else. The kitchen screen's own query is untouched.
--
-- Rollback: ALTER TABLE tbl_kitchen_sales DROP COLUMN origin_id;
-- =============================================================================

-- the marker itself: nullable on purpose, so every existing running order stays valid
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_kitchen_sales' AND COLUMN_NAME = 'origin_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE `tbl_kitchen_sales` ADD COLUMN `origin_id` varchar(16) DEFAULT NULL AFTER `sale_no`, ADD INDEX `idx_kitchen_origin` (`origin_id`)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- a sync looks orders up by this pair; the index keeps that cheap on a busy pass
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_kitchen_sales' AND INDEX_NAME = 'idx_kitchen_fingerprint');
SET @sql2 := IF(@idx = 0,
    'ALTER TABLE `tbl_kitchen_sales` ADD INDEX `idx_kitchen_fingerprint` (`sale_no`(64), `random_code`)',
    'SELECT 1');
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sync_identity') = 1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_kitchen_sales' AND COLUMN_NAME = 'origin_id') = 1
    AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_kitchen_sales' AND INDEX_NAME = 'idx_kitchen_fingerprint') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
