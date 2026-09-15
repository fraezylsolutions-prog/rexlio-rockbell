-- =============================================================================
-- Migration: order-number uniqueness across tills (device tags).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive: one new table, three
-- columns widened. No existing row is modified; existing order numbers stay
-- valid and keep working.
--
-- WHY: a sale number is built in the browser as
--   <first letter of name><2 RANDOM capitals drawn per page load><YYMMDD>-<counter>
-- with the counter kept in that browser's localStorage. Two tills used by the
-- same person on the same day collide whenever they draw the same two letters
-- (1 in 676 per till-pair per day), and when they do, EVERY order that day for
-- that pair collides. Sale::add_kitchen_sale_by_ajax() looks the number up and
-- UPDATES the row it finds, so the second till silently overwrites the first
-- till's order on the server.
--
-- FIX: the two random letters are replaced by a three-character DEVICE TAG
-- issued once per browser from this table's auto-increment id (base-32 encoded,
-- alphabet without 0/O/1/I), so tags are unique by construction. The server
-- additionally refuses to update a row whose random_code differs from the one
-- posted (a different order wearing the same number).
--
-- The three side tables below hold sale_no as varchar(20); the new number is
-- 14 characters and a split sale appends "-n", so they are widened to 50 for
-- headroom. tbl_kitchen_sales and tbl_sales are already varchar(500).
--
-- SAFE DEGRADATION: if this is not applied, Sale/issueDeviceTag fails, the
-- browser falls back to a random 3-character tag (better odds than today, not
-- guaranteed), and the server-side random_code guard still works.
--
-- Rollback: DROP TABLE tbl_device_tags; the widened columns can stay.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_device_tags` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tag` VARCHAR(10) NOT NULL DEFAULT '',
  `user_id` INT NULL,
  `outlet_id` INT NULL,
  `issued_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `tbl_orders_table`          MODIFY `sale_no` VARCHAR(50) NOT NULL;
ALTER TABLE `tbl_running_order_tables`  MODIFY `sale_no` VARCHAR(50) DEFAULT NULL;
ALTER TABLE `tbl_running_orders`        MODIFY `sale_no` VARCHAR(50) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- Verification (run after applying):
--   SELECT COUNT(*) FROM tbl_device_tags;                       -- 0 on a fresh apply
--   SELECT table_name, column_type FROM information_schema.columns
--     WHERE table_schema = DATABASE() AND column_name = 'sale_no';
--   -- expect varchar(50) for the three side tables, varchar(500) for the two main ones
-- ---------------------------------------------------------------------------
