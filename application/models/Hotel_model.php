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
}
