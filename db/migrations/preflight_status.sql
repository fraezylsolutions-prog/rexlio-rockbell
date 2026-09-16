-- =============================================================================
-- PREFLIGHT (read-only): which migrations does THIS database already have?
--
-- Run against live before a deploy:
--     mysql -u <user> -p <livedb> < db/migrations/preflight_status.sql
-- Prints one row per migration: APPLIED or MISSING (11 migrations as of 2026-09-17). Apply the MISSING ones in
-- the listed order, each with its own script (each prints PASS at the end).
-- Nothing here writes. Every check uses information_schema or tables that
-- exist in every install (tbl_access, tbl_roles, tbl_role_access, tbl_food_menus).
-- =============================================================================
SELECT '2026-09-03_01_register-force-close' AS migration, CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_register'
       AND COLUMN_NAME IN ('system_closing_balance','force_closed_by','force_close_reason')) = 3
    AND (SELECT COUNT(*) FROM tbl_access WHERE function_name='manage_registers' AND parent_id=376) = 1
  THEN 'APPLIED' ELSE 'MISSING' END AS status
UNION ALL
SELECT '2026-09-08_01_view-all-running-orders', CASE WHEN
    (SELECT COUNT(*) FROM tbl_access WHERE function_name='view_all_running_orders' AND parent_id=372) = 1
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-15_01_device-tags', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_device_tags') = 1
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='sale_no'
       AND TABLE_NAME IN ('tbl_orders_table','tbl_running_order_tables','tbl_running_orders') AND CHARACTER_MAXIMUM_LENGTH >= 50) = 3
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-15_02_running-order-versioning', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_kitchen_sales'
       AND COLUMN_NAME IN ('version','adopted_by','adopted_at')) = 3
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-15_03_perf-indexes', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_sessions' AND INDEX_NAME='PRIMARY') = 1
    AND (SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND INDEX_NAME IN
        ('idx_orders_table_sale_id','idx_orders_table_sale_no','idx_ksd_sales_id','idx_kitchen_sales_sale_no','idx_rot_sale_no')) = 5
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-15_04_table-first-flow', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_tables' AND COLUMN_NAME IN ('auto_created','created_at')) = 2
    AND (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_users' AND COLUMN_NAME='ir_table_seq') = 1
    AND (SELECT COUNT(*) FROM tbl_access WHERE function_name='act_on_any_running_order' AND parent_id=372) = 1
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-15_05_beverage-values', CASE WHEN
    (SELECT COUNT(*) FROM tbl_food_menus WHERE beverage_item NOT IN ('Bev Yes','Bev No') AND beverage_item IS NOT NULL AND beverage_item <> '') = 0
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-16_01_modules', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_modules') = 1
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='modules' AND m.parent_id=0 AND c.function_name='update') = 1
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-16_02_hotel-schema', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('tbl_hotel_room_types','tbl_hotel_rooms','tbl_hotel_room_status_log','tbl_hotel_housekeeping_tasks','tbl_hotel_stays')) = 5
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name IN ('hotel_rooms','hotel_front_desk','hotel_housekeeping')) >= 12
    AND (SELECT COUNT(*) FROM tbl_roles WHERE role_name='Housekeeping' AND del_status='Live') >= 1
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-17_01_hotel-stay-value', CASE WHEN
    (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_hotel_stays'
       AND COLUMN_NAME IN ('rate','nights','amount','actual_nights','value_note')) = 5
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='hotel_front_desk' AND c.function_name='value') = 1
  THEN 'APPLIED' ELSE 'MISSING' END
UNION ALL
SELECT '2026-09-17_02_hotel-reports-permission', CASE WHEN
    (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='hotel_reports' AND c.function_name='view') = 1
  THEN 'APPLIED' ELSE 'MISSING' END;
