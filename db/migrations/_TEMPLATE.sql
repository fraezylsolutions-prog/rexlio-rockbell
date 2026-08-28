-- =============================================================================
-- MIGRATION: <one line, what this does>
-- FILE:      YYYY-MM-DD_NN_short-description.sql
-- AUTHOR:    <name>
-- TICKET:    <tracker row / task, if any>
--
-- APPLIES TO:   live  (rexlio on the Rockbell subdomain)
-- RUN ORDER:    BEFORE the code deploy that needs it
-- BACKUP TAKEN: <filename of the dump>   <-- fill this in, do not leave blank
--
-- WHY
--   <what the code needs that the schema does not currently provide>
--
-- SAFETY
--   Additive only?            YES / NO
--   Old code still works?     YES / NO   <-- must be YES, or this is a 2-deploy change
--   Destructive statements?   none / <list them>
--   Estimated rows touched:   <n>        <-- matters on big tables; ALTER locks
--
-- ROLLBACK
--   <exact statements, or "restore from backup <filename>" - but say which>
--   Remember: MySQL DDL is NOT transactional. It will not roll back on error.
-- =============================================================================

-- Fail loudly rather than half-applying.
SET SESSION sql_mode = CONCAT(@@sql_mode, ',STRICT_ALL_TABLES');

-- -----------------------------------------------------------------------------
-- CHANGES
-- -----------------------------------------------------------------------------

-- Example (delete these - they are here to show the expected shape):
--
-- ALTER TABLE tbl_food_menus
--     ADD COLUMN IF NOT EXISTS loyalty_tier VARCHAR(20) NULL DEFAULT NULL
--     COMMENT 'Rockbell loyalty tier; NULL = not enrolled';
--
-- CREATE INDEX IF NOT EXISTS idx_food_menus_loyalty_tier
--     ON tbl_food_menus (loyalty_tier);


-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------

-- Example (replace with a real check):
--
-- SELECT CASE WHEN COUNT(*) = 1 THEN 'PASS' ELSE 'FAIL' END AS result
-- FROM information_schema.COLUMNS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME   = 'tbl_food_menus'
--   AND COLUMN_NAME  = 'loyalty_tier';

SELECT 'REPLACE THIS WITH A REAL VERIFICATION QUERY' AS result;
