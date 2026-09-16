<?php
/*
  ###########################################################
  # Hotel Operations add-on - model. Reads and writes only
  # the add-on's own tables (tbl_hotel_*); joins to
  # tbl_outlets / tbl_users are read-only lookups for names.
  ###########################################################
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Hotel_model extends CI_Model {

    public function __construct() {
        parent::__construct();
    }

    /* ---------------------------------------------------------------- room types */

    public function getRoomTypes($company_id) {
        return $this->db->select('t.*, (SELECT COUNT(*) FROM tbl_hotel_rooms r WHERE r.room_type_id = t.id AND r.del_status = "Live") AS rooms_count', FALSE)
                        ->from('tbl_hotel_room_types t')
                        ->where('t.company_id', $company_id)->where('t.del_status', 'Live')
                        ->order_by('t.name', 'ASC')->get()->result();
    }

    public function getRoomType($id) {
        return $this->db->get_where('tbl_hotel_room_types', array('id' => (int) $id, 'del_status' => 'Live'))->row();
    }

    /** live rooms still using this type - a type in use cannot be deleted */
    public function roomTypeInUse($type_id) {
        return (int) $this->db->where('room_type_id', (int) $type_id)->where('del_status', 'Live')->count_all_results('tbl_hotel_rooms');
    }

    /* ---------------------------------------------------------------- rooms */

    /**
     * rooms with their type and outlet names, optionally limited to outlets
     * @param int   $company_id
     * @param array $outlet_ids empty = every outlet of the company
     */
    public function getRooms($company_id, $outlet_ids = array()) {
        $this->db->select('r.*, t.name AS type_name, o.outlet_name, u.full_name AS added_by,
                (SELECT s.guest_name FROM tbl_hotel_stays s WHERE s.room_id = r.id AND s.status = "in_house" AND s.del_status = "Live" ORDER BY s.id DESC LIMIT 1) AS guest_name', FALSE)
                 ->from('tbl_hotel_rooms r')
                 ->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                 ->join('tbl_outlets o', 'o.id = r.outlet_id', 'left')
                 ->join('tbl_users u', 'u.id = r.user_id', 'left')
                 ->where('r.company_id', $company_id)->where('r.del_status', 'Live');
        if ($outlet_ids) { $this->db->where_in('r.outlet_id', $outlet_ids); }
        return $this->db->order_by('o.outlet_name', 'ASC')->order_by('r.floor', 'ASC')->order_by('r.number', 'ASC')->get()->result();
    }

    public function getRoom($id) {
        return $this->db->get_where('tbl_hotel_rooms', array('id' => (int) $id, 'del_status' => 'Live'))->row();
    }

    /** a room number must be unique among the LIVE rooms of an outlet (soft deletes free the number) */
    public function roomNumberExists($outlet_id, $number, $except_id = 0) {
        $this->db->where('outlet_id', (int) $outlet_id)->where('number', $number)->where('del_status', 'Live');
        if ($except_id) { $this->db->where('id !=', (int) $except_id); }
        return $this->db->count_all_results('tbl_hotel_rooms') > 0;
    }

    /** a room with a guest in it cannot be deleted */
    public function roomHasInHouseStay($room_id) {
        return $this->db->where('room_id', (int) $room_id)->where('status', 'in_house')->where('del_status', 'Live')->count_all_results('tbl_hotel_stays') > 0;
    }

    /** outlets the caller may see, for filters and the room form */
    public function getOutlets($ids) {
        if (!$ids) { return array(); }
        return $this->db->select('id, outlet_name')->from('tbl_outlets')->where_in('id', $ids)->where('del_status', 'Live')
                        ->order_by('outlet_name', 'ASC')->get()->result();
    }

    /** the status log rows the H2 board and every state change write */
    public function logStatus($room_id, $kind, $from, $to, $user_id, $note = '') {
        $this->db->insert('tbl_hotel_room_status_log', array(
            'room_id' => (int) $room_id, 'status_kind' => $kind, 'from_status' => $from, 'to_status' => $to,
            'user_id' => (int) $user_id, 'note' => $note !== '' ? $note : NULL, 'created_at' => date('Y-m-d H:i:s'),
        ));
    }
    /* ---------------------------------------------------------------- front desk (H2) */

    /**
     * the board: every live room of one outlet with its type, the guest in it,
     * when its occupancy last changed and how many housekeeping tasks are open
     */
    public function getBoard($outlet_id) {
        return $this->db->select('r.id, r.number, r.floor, r.occupancy_status, r.housekeeping_status, r.notes, t.name AS type_name,
                s.id AS stay_id, s.guest_name, s.guest_phone, s.adults, s.children, s.checkin_at, s.expected_checkout, s.reference, s.rate, s.nights, s.amount, t.base_rate,
                (SELECT MAX(l.created_at) FROM tbl_hotel_room_status_log l WHERE l.room_id = r.id AND l.status_kind = "occupancy") AS occupancy_since,
                (SELECT MAX(l2.created_at) FROM tbl_hotel_room_status_log l2 WHERE l2.room_id = r.id AND l2.status_kind = "housekeeping") AS housekeeping_since,
                (SELECT COUNT(*) FROM tbl_hotel_housekeeping_tasks k WHERE k.room_id = r.id AND k.status IN ("pending","in_progress","done") AND k.del_status = "Live") AS open_tasks', FALSE)
                 ->from('tbl_hotel_rooms r')
                 ->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                 ->join('tbl_hotel_stays s', 's.room_id = r.id AND s.status = "in_house" AND s.del_status = "Live"', 'left', FALSE)
                 ->where('r.outlet_id', (int) $outlet_id)->where('r.del_status', 'Live')
                 ->order_by('r.floor', 'ASC')->order_by('r.number', 'ASC')->get()->result();
    }

    public function getInHouseStay($room_id) {
        return $this->db->get_where('tbl_hotel_stays', array('room_id' => (int) $room_id, 'status' => 'in_house', 'del_status' => 'Live'))->row();
    }

    public function getStay($id) {
        return $this->db->get_where('tbl_hotel_stays', array('id' => (int) $id, 'del_status' => 'Live'))->row();
    }

    /**
     * change one of a room's two states and write the log row for it. The
     * whole state machine lives in the controller; this only records.
     */
    public function setRoomStatus($room_id, $kind, $to, $user_id, $note = '') {
        $room = $this->getRoom($room_id);
        if (!$room) { return FALSE; }
        $column = $kind === 'occupancy' ? 'occupancy_status' : 'housekeeping_status';
        $from = $room->$column;
        $this->db->where('id', (int) $room_id)->update('tbl_hotel_rooms', array($column => $to, 'updated_at' => date('Y-m-d H:i:s')));
        $this->logStatus($room_id, $kind, $from, $to, $user_id, $note);
        return TRUE;
    }

    public function insertStay($data) {
        $this->db->insert('tbl_hotel_stays', $data);
        return (int) $this->db->insert_id();
    }

    public function closeStay($stay_id, $user_id, $actual_nights = NULL) {
        $upd = array('status' => 'checked_out', 'checkout_at' => date('Y-m-d H:i:s'), 'checked_out_by' => (int) $user_id);
        if ($actual_nights !== NULL) { $upd['actual_nights'] = (int) $actual_nights; }
        $this->db->where('id', (int) $stay_id)->where('status', 'in_house')->update('tbl_hotel_stays', $upd);
        return $this->db->affected_rows() > 0;
    }

    /** H5: rate / nights / amount / expected check-out corrections */
    public function updateStay($stay_id, $data) {
        $this->db->where('id', (int) $stay_id)->update('tbl_hotel_stays', $data);
        return $this->db->affected_rows() >= 0;
    }

    /** a housekeeping task (H3 works them); one open task of a type per room is enough */
    public function createTask($company_id, $outlet_id, $room_id, $type, $created_by, $note = '') {
        $open = $this->db->where('room_id', (int) $room_id)->where('task_type', $type)->where_in('status', array('pending', 'in_progress'))
                         ->where('del_status', 'Live')->count_all_results('tbl_hotel_housekeeping_tasks');
        if ($open > 0) { return 0; }
        $this->db->insert('tbl_hotel_housekeeping_tasks', array(
            'company_id' => (int) $company_id, 'outlet_id' => (int) $outlet_id, 'room_id' => (int) $room_id, 'task_type' => $type,
            'status' => 'pending', 'created_by' => (int) $created_by, 'created_at' => date('Y-m-d H:i:s'), 'note' => $note !== '' ? $note : NULL,
        ));
        return (int) $this->db->insert_id();
    }

    /** the last status changes of one room, newest first */
    public function getRoomHistory($room_id, $limit = 20) {
        return $this->db->select('l.*, u.full_name AS user_name')->from('tbl_hotel_room_status_log l')
                        ->join('tbl_users u', 'u.id = l.user_id', 'left')
                        ->where('l.room_id', (int) $room_id)->order_by('l.id', 'DESC')->limit((int) $limit)->get()->result();
    }

    /**
     * the stay log. $filters: outlet_ids (array, required scope), status, room_id,
     * date_from / date_to (on checkin_at), guest (name/phone/reference contains)
     */
    public function getStays($company_id, $filters) {
        $this->db->select('s.*, r.number AS room_number, t.name AS type_name, o.outlet_name, ui.full_name AS in_by, uo.full_name AS out_by')
                 ->from('tbl_hotel_stays s')
                 ->join('tbl_hotel_rooms r', 'r.id = s.room_id', 'left')
                 ->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                 ->join('tbl_outlets o', 'o.id = s.outlet_id', 'left')
                 ->join('tbl_users ui', 'ui.id = s.checked_in_by', 'left')
                 ->join('tbl_users uo', 'uo.id = s.checked_out_by', 'left')
                 ->where('s.company_id', (int) $company_id)->where('s.del_status', 'Live');
        if (!empty($filters['outlet_ids'])) { $this->db->where_in('s.outlet_id', $filters['outlet_ids']); } else { $this->db->where('1', '0', FALSE); }
        if (!empty($filters['status'])) { $this->db->where('s.status', $filters['status']); }
        if (!empty($filters['room_id'])) { $this->db->where('s.room_id', (int) $filters['room_id']); }
        if (!empty($filters['date_from'])) { $this->db->where('s.checkin_at >=', $filters['date_from'] . ' 00:00:00'); }
        if (!empty($filters['date_to'])) { $this->db->where('s.checkin_at <=', $filters['date_to'] . ' 23:59:59'); }
        if (!empty($filters['guest'])) {
            $g = $this->db->escape_like_str($filters['guest']);
            $this->db->where("(s.guest_name LIKE '%$g%' OR s.guest_phone LIKE '%$g%' OR s.reference LIKE '%$g%')", NULL, FALSE);
        }
        return $this->db->order_by('s.checkin_at', 'DESC')->limit(1000)->get()->result();
    }

    /* ---------------------------------------------------------------- housekeeping (H3) */

    /**
     * open tasks of one outlet (pending / in_progress / done) with their room,
     * who holds them and who made them - the board's whole data set
     */
    public function getOpenTasks($outlet_id) {
        return $this->db->select('k.*, r.number AS room_number, r.floor, r.housekeeping_status, r.occupancy_status, t.name AS type_name,
                                  ua.full_name AS assigned_name, uc.full_name AS created_name, uv.full_name AS verified_name')
                        ->from('tbl_hotel_housekeeping_tasks k')
                        ->join('tbl_hotel_rooms r', 'r.id = k.room_id', 'left')
                        ->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                        ->join('tbl_users ua', 'ua.id = k.assigned_to', 'left')
                        ->join('tbl_users uc', 'uc.id = k.created_by', 'left')
                        ->join('tbl_users uv', 'uv.id = k.verified_by', 'left')
                        ->where('k.outlet_id', (int) $outlet_id)->where('k.del_status', 'Live')
                        ->where_in('k.status', array('pending', 'in_progress', 'done'))
                        ->order_by('k.status', 'ASC')->order_by('k.created_at', 'ASC')->get()->result();
    }

    public function getTask($id) {
        return $this->db->get_where('tbl_hotel_housekeeping_tasks', array('id' => (int) $id, 'del_status' => 'Live'))->row();
    }

    public function updateTask($id, $data) {
        $this->db->where('id', (int) $id)->update('tbl_hotel_housekeeping_tasks', $data);
        return $this->db->affected_rows() > 0;
    }

    /**
     * who can be handed a task: every live user of the company whose role holds
     * hotel_housekeeping > update_task (Admin as well). Resolved from the role
     * grants, so the list follows the Role screen without any hard-coded role name.
     */
    public function getHousekeepingUsers($company_id) {
        $sql = "SELECT u.id, u.full_name, ro.role_name
                  FROM tbl_users u
                  JOIN tbl_roles ro ON ro.id = u.role_id AND ro.del_status = 'Live'
                 WHERE u.company_id = ? AND u.del_status = 'Live'
                   AND (ro.role_name = 'Admin' OR EXISTS (
                        SELECT 1 FROM tbl_role_access ra
                          JOIN tbl_access c ON c.id = ra.access_child_id AND c.function_name = 'update_task'
                          JOIN tbl_access m ON m.id = c.parent_id AND m.module_name = 'hotel_housekeeping'
                         WHERE ra.role_id = ro.id AND ra.del_status = 'Live'))
                 ORDER BY u.full_name ASC";
        return $this->db->query($sql, array((int) $company_id))->result();
    }

    /* ---------------------------------------------------------------- reports (H6+) */

    /** the SQL expression that buckets a datetime column for a report view */
    public function bucketExpr($column, $view) {
        switch ($view) {
            case 'day':   return "DATE($column)";
            case 'week':  return "DATE(DATE_SUB($column, INTERVAL WEEKDAY($column) DAY))";   // Monday of that week
            case 'year':  return "DATE_FORMAT($column, '%Y-01-01')";
            default:      return "DATE_FORMAT($column, '%Y-%m-01')";                          // month
        }
    }

    /**
     * H6 - value generated, by room type and period bucket. Stays are counted on their
     * CHECK-IN date (the value is booked then, decision 2a); cancelled stays are excluded,
     * in-house stays included (their value is already recorded). Grouped by room_type_id,
     * so any category the business adds later appears by itself.
     */
    public function valueByTypeAndBucket($company_id, $outlet_ids, $from, $to, $view) {
        if (!$outlet_ids) { return array(); }
        $b = $this->bucketExpr('s.checkin_at', $view);
        return $this->db->select("r.room_type_id, t.name AS type_name, $b AS bucket, COUNT(*) AS stays, COALESCE(SUM(s.nights), 0) AS nights, COALESCE(SUM(s.amount), 0) AS amount", FALSE)
                        ->from('tbl_hotel_stays s')
                        ->join('tbl_hotel_rooms r', 'r.id = s.room_id')
                        ->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                        ->where('s.company_id', (int) $company_id)->where('s.del_status', 'Live')->where('s.status !=', 'cancelled')
                        ->where_in('s.outlet_id', $outlet_ids)
                        ->where('s.checkin_at >=', $from . ' 00:00:00')->where('s.checkin_at <=', $to . ' 23:59:59')
                        ->group_by(array('r.room_type_id', 't.name', 'bucket'))->order_by('t.name', 'ASC')->get()->result();
    }

    /** H7 - live rooms per room type in the outlet scope: the "rooms available" base */
    public function roomCountsByType($company_id, $outlet_ids) {
        if (!$outlet_ids) { return array(); }
        return $this->db->select('r.room_type_id, t.name AS type_name, COUNT(*) AS rooms')
                        ->from('tbl_hotel_rooms r')->join('tbl_hotel_room_types t', 't.id = r.room_type_id', 'left')
                        ->where('r.company_id', (int) $company_id)->where('r.del_status', 'Live')->where_in('r.outlet_id', $outlet_ids)
                        ->group_by(array('r.room_type_id', 't.name'))->order_by('t.name', 'ASC')->get()->result();
    }

    /** H7 - every non-cancelled stay that touches the range (checked in before its end, not out before its start) */
    public function staysOverlapping($company_id, $outlet_ids, $from, $to) {
        if (!$outlet_ids) { return array(); }
        return $this->db->select('s.id, s.room_id, r.room_type_id, s.status, s.checkin_at, s.checkout_at, s.expected_checkout, s.nights, s.actual_nights, s.amount')
                        ->from('tbl_hotel_stays s')->join('tbl_hotel_rooms r', 'r.id = s.room_id')
                        ->where('s.company_id', (int) $company_id)->where('s.del_status', 'Live')->where('s.status !=', 'cancelled')
                        ->where_in('s.outlet_id', $outlet_ids)
                        ->where('s.checkin_at <=', $to . ' 23:59:59')
                        ->where("(s.checkout_at IS NULL OR s.checkout_at >= '" . $this->db->escape_str($from) . " 00:00:00')", NULL, FALSE)
                        ->get()->result();
    }
}
