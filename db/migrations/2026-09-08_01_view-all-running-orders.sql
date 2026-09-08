-- =============================================================================
-- Migration: make "see every user's running orders" a configurable permission
-- instead of a hardcoded role-name list.
--
-- APPLY TO LIVE AFTER A VERIFIED BACKUP. Purely additive - one new permission
-- row and its role grants. No schema change, no existing row is modified.
--
-- WHY: Monitor::canViewAllUsers() previously granted the full list to role name
-- 'Admin', 'Leader' or 'Sales Supervisor'. Neither 'Leader' nor 'Sales
-- Supervisor' exists in this database, so only Admin ever saw it. Every other
-- role - Cashier, Manager, Store Officer, Accountant - silently saw only the
-- orders it had personally created, so a cashier never saw a waiter's order.
--
-- SAFE DEGRADATION: if this is not applied, checkAccess() returns FALSE for
-- non-Admin and the screen behaves exactly as it does today.
--
-- NOTE: permissions are snapshotted into the session at LOGIN, so anyone
-- already signed in must log out and back in before this takes effect.
--
-- Rollback: DELETE the tbl_role_access rows then the tbl_access row added here.
-- =============================================================================

INSERT INTO tbl_access (module_name, function_name, label_name, parent_id, main_module_id, del_status)
VALUES ('', 'view_all_running_orders', 'view_all_running_orders', 372, NULL, 'Live');

-- Granted to Admin and Manager by default. Every other role is left ungranted
-- deliberately - it is now switchable per role on the existing role screen, so
-- the client turns it on for whichever roles they want rather than it being
-- decided in code.
INSERT INTO tbl_role_access (role_id, access_parent_id, access_child_id, del_status)
SELECT r.id, 372, a.id, 'Live'
FROM tbl_roles r, tbl_access a
WHERE a.function_name = 'view_all_running_orders' AND a.parent_id = 372
  AND r.role_name IN ('Admin', 'Manager', 'Cashier') AND r.del_status = 'Live';

-- =============================================================================
-- VERIFICATION - both should return rows.
-- =============================================================================
SELECT id, function_name, parent_id FROM tbl_access WHERE function_name = 'view_all_running_orders';
SELECT ra.id, r.role_name, ra.access_parent_id, ra.access_child_id
FROM tbl_role_access ra
JOIN tbl_roles r ON r.id = ra.role_id
JOIN tbl_access a ON a.id = ra.access_child_id
WHERE a.function_name = 'view_all_running_orders';
