-- =============================================================================
-- Migration: Notifications module, stage N1 - the shape only.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Additive only: three new tables, seven
-- new tbl_modules rows (every one OFF), and one permission. No existing row is
-- modified, nothing is dropped, and no existing query changes behaviour.
-- Idempotent (safe to re-run).
--
-- WHAT THIS STAGE DOES NOT DO: send anything. There is no worker, no hook and
-- no provider in this migration. It installs the shape so the later stages have
-- somewhere to write, and so a half-finished deploy cannot start messaging
-- customers. Every switch is off and stays off until somebody turns it on.
--
-- THE SWITCHES
--   notifications      the master switch. Off means the hooks no-op after one
--                      cached check, nothing is queued, nothing is sent and the
--                      menu is hidden. Existing data is kept.
--   notify_inapp       the existing tbl_notifications bell
--   notify_email       SMTP through sendEmailOnly()
--   notify_sms         the module's own provider setting - deliberately NOT the
--                      legacy tbl_companies.sms_enable_status (a dropdown whose
--                      value 5 means "Nexmo" and which nothing reads at send
--                      time) nor sms_service_provider (a different dropdown,
--                      different numbering, which is what actually decides).
--                      Reading either would inherit that contradiction.
--   notify_whatsapp    one channel, three drivers, one active (see N4)
--   notify_telegram
--   notify_push
--
-- All default 0. Unlike the hotel add-on these carry no 'default' in the
-- registry, so code deployed before this migration simply sees them off.
--
-- Rollback:
--   DROP TABLE tbl_notification_outbox, tbl_notification_rules, tbl_notification_recipients;
--   DELETE FROM tbl_modules WHERE module_key IN ('notifications','notify_inapp',
--     'notify_email','notify_sms','notify_whatsapp','notify_telegram','notify_push');
--   DELETE ra FROM tbl_role_access ra JOIN tbl_access m ON m.id=ra.access_parent_id
--     WHERE m.module_name='notifications';
--   DELETE c FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='notifications';
--   DELETE FROM tbl_access WHERE module_name='notifications';
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. The switches. One master, one per channel, every one OFF.
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_modules` (`module_key`, `is_enabled`, `settings`)
SELECT k, 0, NULL FROM (
    SELECT 'notifications'   AS k UNION ALL
    SELECT 'notify_inapp'    UNION ALL
    SELECT 'notify_email'    UNION ALL
    SELECT 'notify_sms'      UNION ALL
    SELECT 'notify_whatsapp' UNION ALL
    SELECT 'notify_telegram' UNION ALL
    SELECT 'notify_push'
) want
WHERE NOT EXISTS (
    SELECT 1 FROM (SELECT module_key FROM `tbl_modules`) have WHERE have.module_key = want.k
);

-- -----------------------------------------------------------------------------
-- 2. Rules: which event, on which channel, to whom.
--
-- A rule says "when THIS happens, tell THESE people THIS way". Recipients are
-- resolved at send time rather than stored as addresses, so a staff change does
-- not leave a rule pointing at somebody who has left.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbl_notification_rules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `event_key` varchar(60) NOT NULL COMMENT 'order_placed, register_force_closed, ...',
  `channel` varchar(20) NOT NULL COMMENT 'inapp|email|sms|whatsapp|telegram|push|voice',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `is_critical` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'joins the escalation ladder (N5)',
  `quiet_from` time DEFAULT NULL COMMENT 'NULL = no quiet hours',
  `quiet_to` time DEFAULT NULL,
  `digest` varchar(12) NOT NULL DEFAULT 'none' COMMENT 'none|hourly|daily - batching, N5',
  -- 0 means every outlet, NOT NULL. It cannot be NULL: MySQL treats NULLs as
  -- distinct in a UNIQUE index, so a nullable column here would let the same
  -- event+channel be added twice for 'all outlets' - which is the commonest
  -- rule of all. Caught by the N1 test, which inserted the duplicate happily.
  `outlet_id` int NOT NULL DEFAULT 0 COMMENT '0 = every outlet',
  `company_id` int NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_by` int DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `del_status` varchar(10) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rule` (`event_key`, `channel`, `outlet_id`, `company_id`),
  KEY `idx_lookup` (`event_key`, `is_enabled`, `del_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If this table was created by the first version of this migration, outlet_id
-- is still nullable and the unique key does not bite. Put it right in place.
SET @oc := (SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tbl_notification_rules'
               AND COLUMN_NAME = 'outlet_id');
-- the rows first: MODIFY ... NOT NULL would refuse while a NULL is present
UPDATE `tbl_notification_rules` SET `outlet_id` = 0 WHERE `outlet_id` IS NULL;
-- no COMMENT clause here on purpose; quoting one inside a prepared statement
-- costs more than it is worth, and the CREATE above already carries it
SET @sql := IF(@oc = 'YES',
    'ALTER TABLE `tbl_notification_rules` MODIFY `outlet_id` int NOT NULL DEFAULT 0',
    'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Who a rule tells. Separate from the rule so one event can reach a role AND a
-- fixed number without duplicating the rule, and so the escalation ladder (N5)
-- has an order to walk.
CREATE TABLE IF NOT EXISTS `tbl_notification_recipients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `rule_id` int NOT NULL,
  `recipient_type` varchar(12) NOT NULL COMMENT 'role|user|fixed',
  `recipient_value` varchar(190) NOT NULL COMMENT 'role id, user id, or a literal address/number',
  `escalation_order` int NOT NULL DEFAULT 0 COMMENT '0 = first, 1 = the second person (N5)',
  `del_status` varchar(10) NOT NULL DEFAULT 'Live',
  PRIMARY KEY (`id`),
  KEY `idx_rule` (`rule_id`, `del_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 3. The outbox. Every event point only INSERTs here and returns.
--
-- This table is BOTH the queue and the send log: rows are never deleted, they
-- move through pending -> sent | failed | held | expired. That is deliberate -
-- a separate log would drift from the queue, and the first question after a
-- missed alert is always "was it even queued?".
--
-- intended_recipient vs actual_recipient exists for test mode: with test mode on
-- every message goes to one address, and the row still records who it was meant
-- for, so a test proves the routing as well as the sending.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbl_notification_outbox` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `event_key` varchar(60) NOT NULL,
  `channel` varchar(20) NOT NULL,
  `rule_id` int DEFAULT NULL COMMENT 'NULL for a manual test send',
  `intended_recipient` varchar(190) NOT NULL COMMENT 'who it was for',
  `actual_recipient` varchar(190) DEFAULT NULL COMMENT 'who it went to - differs in test mode',
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `payload` text COMMENT 'JSON: the event context, for re-rendering and for debugging',
  `status` varchar(12) NOT NULL DEFAULT 'pending' COMMENT 'pending|sent|failed|held|expired',
  `is_critical` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int NOT NULL DEFAULT 0,
  `last_error` varchar(500) DEFAULT NULL,
  `next_attempt_at` datetime DEFAULT NULL COMMENT 'backoff; NULL = as soon as possible',
  `expires_at` datetime DEFAULT NULL COMMENT 'held messages are dropped, not sent late',
  `acknowledged_at` datetime DEFAULT NULL COMMENT 'stops the escalation ladder (N5)',
  `acknowledged_by` int DEFAULT NULL,
  `test_mode` tinyint(1) NOT NULL DEFAULT 0,
  `outlet_id` int DEFAULT NULL,
  `company_id` int NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- the worker's own query: what is due, oldest first
  KEY `idx_due` (`status`, `next_attempt_at`, `id`),
  KEY `idx_event` (`event_key`, `created_at`),
  KEY `idx_channel` (`channel`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------------------------
-- 4. The permission, so the screen can be delegated through the Roles screen.
--    Granted to Admin only here; anyone else is a decision for the Roles screen.
-- -----------------------------------------------------------------------------
INSERT INTO `tbl_access` (`module_name`, `function_name`, `label_name`, `parent_id`, `main_module_id`, `del_status`)
SELECT 'notifications', '', 'notifications', 0, 8, 'Live'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM `tbl_access`
                                   WHERE module_name = 'notifications' AND parent_id = 0) a);

SET @nt_parent := (SELECT id FROM `tbl_access` WHERE module_name = 'notifications' AND parent_id = 0 LIMIT 1);

INSERT INTO `tbl_access` (`module_name`, `function_name`, `label_name`, `parent_id`, `main_module_id`, `del_status`)
SELECT '', f, f, @nt_parent, NULL, 'Live'
  FROM (SELECT 'view' AS f UNION ALL SELECT 'update' UNION ALL SELECT 'view_credentials') fn
 WHERE @nt_parent IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT function_name, parent_id FROM `tbl_access`) b
                    WHERE b.parent_id = @nt_parent AND b.function_name = fn.f);

-- Admin gets all three. view_credentials is separate on purpose: seeing a
-- provider key is a different act from changing which events fire.
INSERT INTO `tbl_role_access` (`role_id`, `access_parent_id`, `access_child_id`, `del_status`)
SELECT ro.id, @nt_parent, ch.id, 'Live'
  FROM `tbl_roles` ro
  JOIN (SELECT id FROM `tbl_access` WHERE parent_id = @nt_parent) ch
 WHERE @nt_parent IS NOT NULL
   AND ro.del_status = 'Live'
   AND LOWER(TRIM(ro.role_name)) = 'admin'
   AND NOT EXISTS (SELECT 1 FROM (SELECT role_id, access_child_id FROM `tbl_role_access`) ex
                    WHERE ex.role_id = ro.id AND ex.access_child_id = ch.id);

-- -----------------------------------------------------------------------------
-- VERIFICATION - must print PASS. If it prints FAIL, STOP: do not deploy code.
-- -----------------------------------------------------------------------------
SELECT CASE WHEN
    (SELECT COUNT(*) FROM tbl_modules WHERE module_key IN ('notifications','notify_inapp','notify_email','notify_sms','notify_whatsapp','notify_telegram','notify_push')) = 7
    AND (SELECT COUNT(*) FROM tbl_modules WHERE module_key LIKE 'notif%' AND is_enabled = 1) = 0
    AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_notification_rules') = 1
    AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_notification_recipients') = 1
    AND (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tbl_notification_outbox') = 1
    AND (SELECT COUNT(*) FROM tbl_access c JOIN tbl_access m ON m.id=c.parent_id WHERE m.module_name='notifications') = 3
  THEN 'PASS' ELSE 'FAIL' END AS result;

SELECT module_key, is_enabled FROM tbl_modules WHERE module_key LIKE 'notif%' ORDER BY module_key;
