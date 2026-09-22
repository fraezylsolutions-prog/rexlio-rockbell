-- =============================================================================
-- Migration: installation origin marker, for two-way sync between a venue's
--            LOCAL server and its ONLINE copy (S7).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one new table and one
-- new nullable column on tbl_sales. No existing row is modified, nothing is
-- dropped, and every existing query keeps working (the column is ignored by
-- code that does not know about it). Idempotent (safe to re-run).
--
-- WHY  When both sides take orders, a merge has to know which installation
--   created each row, for one reason above all: a sale number is
--   <device tag><date>-<counter>, and device tags come from tbl_device_tags'
--   auto-increment - each installation issues its own AAB, AAC, ... So two
--   genuinely different sales, one taken at the venue and one taken online,
--   can wear the same number. Without an origin marker a merge cannot tell
--   them apart, and that is precisely how rows get overwritten.
--
-- WHAT IT ADDS
--   tbl_sync_identity   one row, this installation's own id and a label. The id
--                       is a random 8-character string generated here, so two
--                       installations can never share one.
--   tbl_sales.origin_id the installation that created the sale. NULL means
--                       "created before this migration" and is treated as
--                       belonging to whichever side the row is sitting on.
--
-- WHAT IT DOES NOT DO  It changes no behaviour on its own. Until the sync tool
--   is used, the column is simply written by the completion path and read by
--   nobody else.
--
-- Rollback: DROP TABLE tbl_sync_identity;
--           ALTER TABLE tbl_sales DROP COLUMN origin_id;
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_sync_identity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `install_id` varchar(16) NOT NULL,
  `label` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_install_id` (`install_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- one identity per installation; the random id makes a clash between two
-- installations practically impossible (32^8 combinations)
INSERT INTO `tbl_sync_identity` (`install_id`, `label`, `created_at`)
SELECT UPPER(SUBSTRING(REPLACE(REPLACE(REPLACE(TO_BASE64(RANDOM_BYTES(8)), '+', ''), '/', ''), '=', ''), 1, 8)),
       CONCAT('installation on ', @@hostname), NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `tbl_sync_identity`);

-- the marker itself: nullable on purpose, so every existing sale stays valid
SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sales' AND COLUMN_NAME = 'origin_id');
SET @sql := IF(@col = 0,
    'ALTER TABLE `tbl_sales` ADD COLUMN `origin_id` varchar(16) DEFAULT NULL AFTER `sale_no`, ADD INDEX `idx_sales_origin` (`origin_id`)',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- a merge looks rows up by this pair; the index keeps that cheap on a busy site
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sales' AND INDEX_NAME = 'idx_sales_fingerprint');
SET @sql2 := IF(@idx = 0,
    'ALTER TABLE `tbl_sales` ADD INDEX `idx_sales_fingerprint` (`sale_no`(64), `random_code`)',
    'SELECT 1');
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sync_identity') = 1
    AND (SELECT COUNT(*) FROM tbl_sync_identity) = 1
    AND (SELECT LENGTH(install_id) FROM tbl_sync_identity LIMIT 1) = 8
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sales' AND COLUMN_NAME = 'origin_id') = 1
    AND (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_sales' AND INDEX_NAME = 'idx_sales_fingerprint') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;

SELECT install_id AS this_installation, label FROM tbl_sync_identity;
