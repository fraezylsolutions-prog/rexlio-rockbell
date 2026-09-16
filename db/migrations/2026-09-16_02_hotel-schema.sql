-- =============================================================================
-- Migration: Hotel Operations add-on - schema, permissions, Housekeeping role (H1).
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: five NEW tables, new
-- tbl_access rows, one new role, default grants. No existing table is altered;
-- no existing row is modified. Every statement is idempotent (safe to re-run).
--
-- The add-on is isolated: it owns tbl_hotel_* and nothing else. It is only
-- reachable while Settings > Modules has 'hotel' ON (migration 2026-09-16_01).
--
-- TABLES
--   tbl_hotel_room_types          name + informational base_rate (display only,
--                                 never billed - Guest Folio is out of scope)
--   tbl_hotel_rooms               per outlet; TWO independent states:
--                                 occupancy_status  vacant | occupied | out_of_order
--                                 housekeeping_status clean | dirty | in_progress | inspected
--   tbl_hotel_room_status_log     every change of either state (audit / "since when")
--   tbl_hotel_housekeeping_tasks  the Housekeeping board: pending -> in_progress ->
--                                 done -> verified; assigned_to NULL = pool
--   tbl_hotel_stays               check-in / check-out log; one in_house stay per room
--                                 (enforced in code). reference = free text typed by
--                                 staff (receipt / booking no.) - not a billing link.
--
-- PERMISSIONS (tbl_access, looked up BY NAME at runtime - ids are not fixed)
--   hotel_rooms          add | update | view | delete   (Rooms + Room Types)
--   hotel_front_desk     view | checkin | checkout | status
--   hotel_housekeeping   view | update_task | assign | verify
-- GRANTS   Admin: all. Manager: all. Cashier: front desk view/checkin/checkout,
--          housekeeping view. Housekeeping (NEW role, per company): housekeeping
--          view + update_task, plus outlet enter/view (67) so they can check in.
-- Permissions are snapshotted at LOGIN - sign out and in after applying.
--
-- Rollback: DROP the five tbl_hotel_* tables; DELETE tbl_role_access rows whose
--           access_child_id belongs to the three hotel_* modules; DELETE those
--           tbl_access rows; DELETE the 'Housekeeping' roles (if unused).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_hotel_room_types` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `company_id`   INT NOT NULL,
  `name`         VARCHAR(100) NOT NULL,
  `base_rate`    DECIMAL(12,2) NULL,
  `description`  VARCHAR(250) NULL,
  `user_id`      INT NULL,
  `created_at`   DATETIME NULL,
  `del_status`   VARCHAR(20) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  KEY `ix_hrt_company` (`company_id`, `del_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbl_hotel_rooms` (
  `id`                  INT NOT NULL AUTO_INCREMENT,
  `company_id`          INT NOT NULL,
  `outlet_id`           INT NOT NULL,
  `room_type_id`        INT NULL,
  `number`              VARCHAR(50) NOT NULL,
  `floor`               VARCHAR(50) NULL,
  `occupancy_status`    VARCHAR(20) NOT NULL DEFAULT 'vacant',
  `housekeeping_status` VARCHAR(20) NOT NULL DEFAULT 'clean',
  `notes`               VARCHAR(250) NULL,
  `user_id`             INT NULL,
  `created_at`          DATETIME NULL,
  `updated_at`          DATETIME NULL,
  `del_status`          VARCHAR(20) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  KEY `ix_hr_outlet` (`outlet_id`, `del_status`),
  KEY `ix_hr_company` (`company_id`, `del_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbl_hotel_room_status_log` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `room_id`      INT NOT NULL,
  `status_kind`  VARCHAR(20) NOT NULL,
  `from_status`  VARCHAR(20) NULL,
  `to_status`    VARCHAR(20) NOT NULL,
  `user_id`      INT NULL,
  `note`         VARCHAR(250) NULL,
  `created_at`   DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_hrsl_room` (`room_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbl_hotel_housekeeping_tasks` (
  `id`           INT NOT NULL AUTO_INCREMENT,
  `company_id`   INT NOT NULL,
  `outlet_id`    INT NOT NULL,
  `room_id`      INT NOT NULL,
  `task_type`    VARCHAR(20) NOT NULL DEFAULT 'cleaning',
  `status`       VARCHAR(20) NOT NULL DEFAULT 'pending',
  `assigned_to`  INT NULL,
  `created_by`   INT NULL,
  `created_at`   DATETIME NOT NULL,
  `started_at`   DATETIME NULL,
  `done_at`      DATETIME NULL,
  `verified_by`  INT NULL,
  `verified_at`  DATETIME NULL,
  `note`         VARCHAR(250) NULL,
  `del_status`   VARCHAR(20) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  KEY `ix_hht_outlet_status` (`outlet_id`, `status`, `del_status`),
  KEY `ix_hht_room` (`room_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `tbl_hotel_stays` (
  `id`                INT NOT NULL AUTO_INCREMENT,
  `company_id`        INT NOT NULL,
  `outlet_id`         INT NOT NULL,
  `room_id`           INT NOT NULL,
  `guest_name`        VARCHAR(150) NOT NULL,
  `guest_phone`       VARCHAR(50) NULL,
  `adults`            TINYINT NOT NULL DEFAULT 1,
  `children`          TINYINT NOT NULL DEFAULT 0,
  `checkin_at`        DATETIME NOT NULL,
  `expected_checkout` DATE NULL,
  `checkout_at`       DATETIME NULL,
  `checked_in_by`     INT NULL,
  `checked_out_by`    INT NULL,
  `status`            VARCHAR(20) NOT NULL DEFAULT 'in_house',
  `reference`         VARCHAR(100) NULL,
  `notes`             VARCHAR(250) NULL,
  `del_status`        VARCHAR(20) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  KEY `ix_hs_room_status` (`room_id`, `status`),
  KEY `ix_hs_outlet_status` (`outlet_id`, `status`, `checkin_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ---- permissions: three module rows (main_module_id 4 = the "Panel" group on the Role screen) ----
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT 'hotel_rooms', '', 'hotel_rooms', 0, 4, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE module_name='hotel_rooms' AND parent_id=0);
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT 'hotel_front_desk', '', 'hotel_front_desk', 0, 4, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE module_name='hotel_front_desk' AND parent_id=0);
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT 'hotel_housekeeping', '', 'hotel_housekeeping', 0, 4, 'Live'
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access WHERE module_name='hotel_housekeeping' AND parent_id=0);

-- child functions (one statement per function, each idempotent)
INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
SELECT '', f.fn, f.fn, m.id, NULL, 'Live'
  FROM tbl_access m
  JOIN (SELECT 'hotel_rooms' AS mod_name, 'add' AS fn UNION ALL SELECT 'hotel_rooms','update' UNION ALL SELECT 'hotel_rooms','view' UNION ALL SELECT 'hotel_rooms','delete'
        UNION ALL SELECT 'hotel_front_desk','view' UNION ALL SELECT 'hotel_front_desk','checkin' UNION ALL SELECT 'hotel_front_desk','checkout' UNION ALL SELECT 'hotel_front_desk','status'
        UNION ALL SELECT 'hotel_housekeeping','view' UNION ALL SELECT 'hotel_housekeeping','update_task' UNION ALL SELECT 'hotel_housekeeping','assign' UNION ALL SELECT 'hotel_housekeeping','verify') f
    ON f.mod_name = m.module_name AND m.parent_id = 0
 WHERE NOT EXISTS (SELECT 1 FROM tbl_access c WHERE c.parent_id = m.id AND c.function_name = f.fn);

-- ---- the Housekeeping role, one per live company ----
INSERT INTO tbl_roles (role_name, del_status, company_id)
SELECT 'Housekeeping', 'Live', co.id FROM tbl_companies co
 WHERE NOT EXISTS (SELECT 1 FROM tbl_roles r WHERE r.role_name='Housekeeping' AND r.company_id=co.id AND r.del_status='Live');

-- ---- default grants ----
-- Admin + Manager: everything hotel
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.parent_id=0 AND m.module_name IN ('hotel_rooms','hotel_front_desk','hotel_housekeeping')
  JOIN tbl_access c ON c.parent_id=m.id
 WHERE r.role_name IN ('Admin','Manager') AND r.del_status='Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id=r.id AND ra.access_child_id=c.id);
-- Cashier: front desk (no out-of-order), housekeeping board read-only
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.parent_id=0 AND m.module_name IN ('hotel_front_desk','hotel_housekeeping')
  JOIN tbl_access c ON c.parent_id=m.id AND ((m.module_name='hotel_front_desk' AND c.function_name IN ('view','checkin','checkout')) OR (m.module_name='hotel_housekeeping' AND c.function_name='view'))
 WHERE r.role_name='Cashier' AND r.del_status='Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id=r.id AND ra.access_child_id=c.id);
-- Housekeeping: the board + own tasks, and the outlet chooser (67: enter, view)
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, m.id, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access m ON m.parent_id=0 AND m.module_name='hotel_housekeeping'
  JOIN tbl_access c ON c.parent_id=m.id AND c.function_name IN ('view','update_task')
 WHERE r.role_name='Housekeeping' AND r.del_status='Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id=r.id AND ra.access_child_id=c.id);
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, 67, c.id, 'Live'
  FROM tbl_roles r
  JOIN tbl_access c ON c.parent_id=67 AND c.function_name IN ('enter','view')
 WHERE r.role_name='Housekeeping' AND r.del_status='Live'
   AND NOT EXISTS (SELECT 1 FROM tbl_role_access ra WHERE ra.role_id=r.id AND ra.access_child_id=c.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
      AND TABLE_NAME IN ('tbl_hotel_room_types','tbl_hotel_rooms','tbl_hotel_room_status_log','tbl_hotel_housekeeping_tasks','tbl_hotel_stays')) = 5
    AND (SELECT COUNT(*) FROM tbl_access WHERE parent_id=0 AND module_name IN ('hotel_rooms','hotel_front_desk','hotel_housekeeping')) = 3
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name IN ('hotel_rooms','hotel_front_desk','hotel_housekeeping')) = 12
    AND (SELECT COUNT(*) FROM tbl_roles WHERE role_name='Housekeeping' AND del_status='Live') >= 1
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_roles r ON r.id=ra.role_id JOIN tbl_access c ON c.id=ra.access_child_id JOIN tbl_access m ON m.id=c.parent_id
          WHERE r.role_name='Admin' AND m.module_name IN ('hotel_rooms','hotel_front_desk','hotel_housekeeping')) = 12
    AND (SELECT COUNT(*) FROM tbl_role_access ra JOIN tbl_roles r ON r.id=ra.role_id JOIN tbl_access c ON c.id=ra.access_child_id JOIN tbl_access m ON m.id=c.parent_id
          WHERE r.role_name='Housekeeping' AND m.module_name='hotel_housekeeping') = 2
  THEN 'PASS' ELSE 'FAIL' END AS result;
