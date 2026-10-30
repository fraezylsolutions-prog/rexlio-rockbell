-- =============================================================================
-- Migration: Notifications N5(d) - the end-of-day and low-stock digests.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: one nullable column on
-- tbl_outlets. No existing row is modified, nothing is dropped. Idempotent.
--
-- eod_closing_time IS NULL BY DEFAULT, AND NULL MEANS NO DIGEST. Opt in per
-- outlet by setting a time. A migration that gave every outlet a closing time
-- would start mailing seven venues the moment the module was switched on.
--
-- The time is stored WITHOUT a zone, as a wall-clock time, and read as
-- Africa/Lagos. "We close at 23:00" is a statement about the clock on the
-- venue's wall, not about UTC: it does not move when a server is rebuilt in
-- another region, and it must not be read through whatever timezone PHP
-- happens to be set to that request.
--
-- Rollback:
--   ALTER TABLE tbl_outlets DROP COLUMN eod_closing_time;
-- =============================================================================

SET @sql = (
  SELECT IF(COUNT(*) = 0,
    'ALTER TABLE `tbl_outlets`
       ADD COLUMN `eod_closing_time` time DEFAULT NULL
       COMMENT ''Wall-clock closing time, read as Africa/Lagos. NULL = no end-of-day digest.''',
    'DO 0')
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'tbl_outlets'
     AND COLUMN_NAME = 'eod_closing_time'
);
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
