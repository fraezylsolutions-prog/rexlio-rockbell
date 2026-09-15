-- =============================================================================
-- Migration: normalise tbl_food_menus.beverage_item (Part B, Food / Drinks).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Data only: no schema change.
--
-- WHY
--   The Add Food Menu form saved 'Beverage Yes' / 'Beverage No' while the Edit
--   form, the POS filter and the details pages all expect 'Bev Yes' / 'Bev No'.
--   An item added through the form therefore never showed under Drinks and its
--   Edit form preselected nothing. The form is fixed in the same change; this
--   brings the rows it already wrote in line (on the Rockbell data: 'Cup Yogurt'
--   plus two deleted rows).
--
-- The POS's Food / Drinks kinds are derived from this column alone (Drinks =
-- 'Bev Yes', Food = anything else); veg_item is dietary information only.
--
-- Rollback: not needed (the old spellings were never read correctly anywhere).
-- =============================================================================

UPDATE tbl_food_menus SET beverage_item = 'Bev Yes' WHERE beverage_item = 'Beverage Yes';
UPDATE tbl_food_menus SET beverage_item = 'Bev No'  WHERE beverage_item = 'Beverage No';

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_food_menus WHERE beverage_item NOT IN ('Bev Yes', 'Bev No') AND beverage_item IS NOT NULL AND beverage_item <> '') = 0
  THEN 'PASS' ELSE 'FAIL' END AS result;
