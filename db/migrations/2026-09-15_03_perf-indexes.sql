-- =============================================================================
-- Migration: indexes for the per-request hot paths (login, Running Order,
-- Table Status, every POS sync call).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive: indexes only, no data or
-- column change. Safe to run while the shop is open on tables this size
-- (seconds); tbl_sessions is rewritten to add its primary key, so apply at a
-- quiet moment - anyone mid-request may see one slow request.
--
-- WHY (measured on the local dev copy, inflated to 40,000 session rows and
-- 20,000 tbl_orders_table rows to stand in for months of live use):
--
--   tbl_sessions has NO primary key (the vendor omitted it; CodeIgniter's
--   own schema has one). Every request reads AND writes the session by id,
--   so both are full table scans on every request, for every screen.
--       login page   212 ms -> 46 ms       per request, MySQL time
--   The Running Order / Table Status cards count kitchen items and list
--   booked tables per order with correlated sub-queries; neither
--   tbl_kitchen_sales_details(sales_id) nor tbl_orders_table(sale_id) was
--   indexed (the details index leads with food_menu_id), so each card scanned
--   both tables - and tbl_orders_table is never pruned.
--       Running Order page   247 ms -> 63 ms
--       Table Status page    271 ms -> 56 ms
--   tbl_kitchen_sales.sale_no had no index at all, yet every KOT push, every
--   7-second sync poll and every cancel looks orders up by it.
--
-- The one-time DELETE below purges sessions older than a day (CodeIgniter
-- expires them at 2 h; if the host's PHP never runs session garbage
-- collection the table only grows). It removes NO live session: anyone
-- signed in has a row younger than 2 h.
--
-- SAFE DEGRADATION: without this, everything works exactly as today, just
-- slower as the tables grow.
--
-- Rollback: DROP the five indexes; ALTER TABLE tbl_sessions DROP PRIMARY KEY.
-- =============================================================================

-- must be 0; a duplicate id would make the primary key fail (never seen: ids
-- are 32-character random session ids)
SELECT COUNT(*) - COUNT(DISTINCT id) AS duplicate_session_ids_must_be_zero FROM tbl_sessions;

DELETE FROM tbl_sessions WHERE `timestamp` < UNIX_TIMESTAMP() - 86400;

ALTER TABLE `tbl_sessions` ADD PRIMARY KEY (`id`);
ALTER TABLE `tbl_orders_table`
  ADD INDEX `idx_orders_table_sale_id` (`sale_id`),
  ADD INDEX `idx_orders_table_sale_no` (`sale_no`);
ALTER TABLE `tbl_kitchen_sales_details` ADD INDEX `idx_ksd_sales_id` (`sales_id`);
ALTER TABLE `tbl_kitchen_sales` ADD INDEX `idx_kitchen_sales_sale_no` (`sale_no`(64));
ALTER TABLE `tbl_running_order_tables` ADD INDEX `idx_rot_sale_no` (`sale_no`);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_sessions' AND INDEX_NAME='PRIMARY') = 1
    AND (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME IN
        ('idx_orders_table_sale_id','idx_orders_table_sale_no','idx_ksd_sales_id','idx_kitchen_sales_sale_no','idx_rot_sale_no')) = 5
  THEN 'PASS' ELSE 'FAIL' END AS result;
