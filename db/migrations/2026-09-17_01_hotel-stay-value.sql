-- =============================================================================
-- Migration: Hotel Operations add-on - stay value (H5).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: five NEW nullable columns
-- on the add-on's own tbl_hotel_stays, one NEW tbl_access function, grants for
-- Admin and Manager. No existing row is modified. Idempotent (safe to re-run):
-- each ADD COLUMN is guarded by information_schema, each INSERT by NOT EXISTS.
--
-- WHAT IT IS
--   rate          per-night rate recorded at check-in (defaults from the room
--                 type's base_rate; only 'value' holders may type another)
--   nights        expected nights at check-in = expected_checkout - check-in date
--                 (minimum 1); drives the value
--   amount        THE VALUE of the stay = nights x rate, counted at check-in
--                 (business decision 2026-09-16). Changed only by a 'value' holder,
--                 every change logged in tbl_hotel_room_status_log as kind 'value'.
--   actual_nights set at check-out from the real dates; a difference from
--                 `nights` is the variance the reports flag
--   value_note    the reason typed with a value change
--
-- WHAT IT IS NOT: a sale. Nothing here reaches tbl_sales, registers, Today's
-- Sale or an invoice. Recorded / informational only; Guest Folio stays out of scope.
--
-- PERMISSION  hotel_front_desk > value : edit the rate / nights / amount of a stay
--             and settle a check-out variance. Granted to Admin and Manager;
--             give it to other roles under Settings > Roles. Permissions are
--             snapshotted at LOGIN - sign out and in after applying.
--
-- Rollback: ALTER TABLE tbl_hotel_stays DROP COLUMN rate, DROP COLUMN nights,
--           DROP COLUMN amount, DROP COLUMN actual_nights, DROP COLUMN value_note;
--           DELETE the tbl_role_access rows for the 'value' function, then the
--           tbl_access row (module hotel_front_desk, function_name 'value').
-- =============================================================================

SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD COLUMN `rate` DECIMAL(12,2) NULL DEFAULT NULL AFTER `expected_checkout`', 'SELECT 1')
              FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND COLUMN_NAME = 'rate');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD COLUMN `nights` SMALLINT NULL DEFAULT NULL AFTER `rate`', 'SELECT 1')
              FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND COLUMN_NAME = 'nights');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD COLUMN `amount` DECIMAL(12,2) NULL DEFAULT NULL AFTER `nights`', 'SELECT 1')
              FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND COLUMN_NAME = 'amount');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD COLUMN `actual_nights` SMALLINT NULL DEFAULT NULL AFTER `amount`', 'SELECT 1')
              FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND COLUMN_NAME = 'actual_nights');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD COLUMN `value_note` VARCHAR(250) NULL DEFAULT NULL AFTER `actual_nights`', 'SELECT 1')
              FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND COLUMN_NAME = 'value_note');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- reports group stays by check-in date and room type: cover both
SET @sql = (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `tbl_hotel_stays` ADD INDEX `ix_hs_company_checkin` (`company_id`, `checkin_at`)', 'SELECT 1')
              FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays' AND INDEX_NAME = 'ix_hs_company_checkin');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ---- permission: hotel_front_desk > value ----
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT '', 'value', 'value', m.id, NULL, 'Live'
  FROM tbl_access m
 WHERE m.module_name = 'hotel_front_desk' AND m.parent_id = 0
   AND NOT EXISTS (SELECT 1 FROM tbl_access c WHERE c.parent_id = m.id AND c.function_name = 'value');

INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.module_name = 'hotel_front_desk' AND m.parent_id = 0
  JOIN tbl_access c ON c.parent_id = m.id AND c.function_name = 'value'
 WHERE r.role_name IN ('Admin', 'Manager') AND r.del_status = 'Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id = r.id AND ra.access_child_id = c.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_hotel_stays'
       AND COLUMN_NAME IN ('rate', 'nights', 'amount', 'actual_nights', 'value_note')) = 5
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id = c.parent_id WHERE m.module_name = 'hotel_front_desk' AND c.function_name = 'value') = 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_roles r ON r.id = ra.role_id JOIN tbl_access c ON c.id = ra.access_child_id JOIN tbl_access m ON m.id = c.parent_id
          WHERE m.module_name = 'hotel_front_desk' AND c.function_name = 'value' AND r.role_name = 'Admin') >= 1
  THEN 'PASS' ELSE 'FAIL' END AS result;
