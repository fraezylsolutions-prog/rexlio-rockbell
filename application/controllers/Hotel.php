<?php
/*
  ###########################################################
  # PRODUCT NAME:   iRestora PLUS - Next Gen Restaurant POS
  ###########################################################
  # Hotel Operations add-on (Rexlio, 2026-09).
  # Housekeeping + Front Desk basics. An isolated, switchable
  # add-on: its own tables (tbl_hotel_*), its own permissions,
  # and it exists only while Settings > Modules has it ON -
  # the constructor refuses every method otherwise, so nothing
  # of it is reachable by URL or bookmark when it is off.
  #   H0  module gate + landing
  #   H1  Room Types + Rooms (this file's CRUD; Table.php pattern)
  #   H2  Front Desk board, check-in / check-out / out of order, status log, stay log
  #   H3  Housekeeping board (standalone page, Kitchen Panel pattern)
  # Permissions are tbl_access rows looked up BY NAME
  # (irAccessModuleId), never by a fixed id.
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Hotel extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Common_model');
        $this->load->model('Hotel_model');
        $this->load->library('form_validation');
        $this->Common_model->setDefaultTimezone();

        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }
        //the module switch comes BEFORE the outlet check: a switched-off add-on
        //must not even send the user to the outlet chooser on its behalf
        irRequireModule('hotel');
        if (!$this->session->has_userdata('outlet_id')) {
            $this->session->set_flashdata('exception_2', lang('please_click_green_button'));
            $this->session->set_userdata("clicked_controller", $this->uri->segment(1));
            $this->session->set_userdata("clicked_method", $this->uri->segment(2));
            redirect('Outlet/outlets');
        }

        //start check access function - one line per method, like every controller here
        $segment_2 = $this->uri->segment(2);
        $segment_3 = $this->uri->segment(3);
        $module = ''; $function = '';
        if ($segment_2 == "index" || $segment_2 == "") {
            //the landing: anyone holding any hotel permission at all
            if (!$this->anyHotelAccess()) { $this->refuse(); }
        } elseif ($segment_2 == "roomTypes" || $segment_2 == "rooms") {
            $module = 'hotel_rooms'; $function = 'view';
        } elseif (($segment_2 == "addEditRoomType" || $segment_2 == "addEditRoom") && $segment_3) {
            $module = 'hotel_rooms'; $function = 'update';
        } elseif ($segment_2 == "addEditRoomType" || $segment_2 == "addEditRoom") {
            $module = 'hotel_rooms'; $function = 'add';
        } elseif ($segment_2 == "deleteRoomType" || $segment_2 == "deleteRoom") {
            $module = 'hotel_rooms'; $function = 'delete';
        } elseif ($segment_2 == "frontDesk" || $segment_2 == "boardAjax" || $segment_2 == "roomHistoryAjax" || $segment_2 == "stays") {
            $module = 'hotel_front_desk'; $function = 'view';
        } elseif ($segment_2 == "checkIn") {
            $module = 'hotel_front_desk'; $function = 'checkin';
        } elseif ($segment_2 == "checkOut") {
            $module = 'hotel_front_desk'; $function = 'checkout';
        } elseif ($segment_2 == "setOutOfOrder") {
            $module = 'hotel_front_desk'; $function = 'status';
        } elseif ($segment_2 == "statsAjax") {
            $module = 'hotel_front_desk'; $function = 'view';
        } elseif ($segment_2 == "editStayValue") {
            $module = 'hotel_front_desk'; $function = 'value';
        } elseif (in_array($segment_2, array("reports", "reportValue", "reportOccupancy", "reportStays", "reportHousekeeping", "reportTurnaround", "reportOutOfOrder", "reportStatusHistory"), TRUE)) {
            $module = 'hotel_reports'; $function = 'view';
        } elseif ($segment_2 == "housekeeping" || $segment_2 == "tasksAjax") {
            $module = 'hotel_housekeeping'; $function = 'view';
        } elseif ($segment_2 == "taskStart" || $segment_2 == "taskDone") {
            $module = 'hotel_housekeeping'; $function = 'update_task';
        } elseif ($segment_2 == "taskVerify") {
            $module = 'hotel_housekeeping'; $function = 'verify';
        } elseif ($segment_2 == "taskAssign" || $segment_2 == "taskCreate" || $segment_2 == "taskCancel") {
            $module = 'hotel_housekeeping'; $function = 'assign';
        } else {
            $this->refuse();
        }
        if ($module !== '' && !$this->can($module, $function)) {
            $this->refuse();
        }
        //end check access function

        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);
    }

    /** checkAccess against a hotel module row looked up by name */
    private function can($module, $function) {
        $id = irAccessModuleId($module);
        return $id && checkAccess((string) $id, $function);
    }
    private function anyHotelAccess() {
        return $this->can('hotel_rooms', 'view') || $this->can('hotel_front_desk', 'view') || $this->can('hotel_housekeeping', 'view');
    }
    private function refuse() {
        $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
        redirect('Authentication/userProfile');
    }
    /** the outlets this user may work with (Admin: the company's; others: their assignment) */
    private function outletIds() {
        $ids = getAccessibleOutletIds();
        $session = (int) $this->session->userdata('outlet_id');
        if ($session && !in_array($session, $ids, TRUE)) { $ids[] = $session; }
        return $ids;
    }

    /**
     * landing page (H2 turns it into the Front Desk board)
     * @access public
     * @return void
     */
    public function index() {
        if ($this->can('hotel_front_desk', 'view')) { redirect('Hotel/frontDesk'); }
        //H3: floor staff with the board but no front desk go straight to it
        if ($this->can('hotel_housekeeping', 'view')) { redirect('Hotel/housekeeping'); }
        $data = array();
        $data['can_rooms'] = $this->can('hotel_rooms', 'view');
        $data['can_front_desk'] = $this->can('hotel_front_desk', 'view');
        $data['can_housekeeping'] = $this->can('hotel_housekeeping', 'view');
        $data['main_content'] = $this->load->view('hotel/index', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /* ================================================================ room types */

    public function roomTypes() {
        $data = array();
        $data['room_types'] = $this->Hotel_model->getRoomTypes($this->session->userdata('company_id'));
        $data['can_add'] = $this->can('hotel_rooms', 'add');
        $data['can_update'] = $this->can('hotel_rooms', 'update');
        $data['can_delete'] = $this->can('hotel_rooms', 'delete');
        $data['main_content'] = $this->load->view('hotel/room_types', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    public function addEditRoomType($encrypted_id = "") {
        $company_id = $this->session->userdata('company_id');
        //encrypt_decrypt('') answers FALSE, so normalise: "" = new, otherwise the id
        $id = ($encrypted_id === "" || $encrypted_id === NULL) ? "" : (string) $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $existing = $id !== "" ? $this->Hotel_model->getRoomType($id) : NULL;
        if ($id !== "" && (!$existing || (int) $existing->company_id !== (int) $company_id)) {
            $this->refuse();
        }
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('name', lang('room_type'), 'required|max_length[100]');
            $this->form_validation->set_rules('base_rate', lang('base_rate'), 'numeric|max_length[14]');
            $this->form_validation->set_rules('description', lang('description'), 'max_length[250]');
            if ($this->form_validation->run() == TRUE) {
                $info = array();
                $info['name'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('name')));
                $rate = trim((string) $this->input->post($this->security->xss_clean('base_rate')));
                $info['base_rate'] = $rate === '' ? NULL : round((float) $rate, 2);
                $info['description'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('description')));
                if ($id == "") {
                    $info['company_id'] = $company_id;
                    $info['user_id'] = $this->session->userdata('user_id');
                    $info['created_at'] = date('Y-m-d H:i:s');
                    $this->Common_model->insertInformation($info, "tbl_hotel_room_types");
                    $this->session->set_flashdata('exception', lang('insertion_success'));
                } else {
                    $this->Common_model->updateInformation($info, $id, "tbl_hotel_room_types");
                    $this->session->set_flashdata('exception', lang('update_success'));
                }
                redirect('Hotel/roomTypes');
            }
        }
        $data = array();
        $data['encrypted_id'] = $encrypted_id;
        $data['room_type'] = $existing;
        $data['main_content'] = $this->load->view('hotel/addEditRoomType', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    public function deleteRoomType($encrypted_id) {
        //encrypt_decrypt('') answers FALSE, so normalise: "" = new, otherwise the id
        $id = ($encrypted_id === "" || $encrypted_id === NULL) ? "" : (string) $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $existing = $this->Hotel_model->getRoomType($id);
        if (!$existing || (int) $existing->company_id !== (int) $this->session->userdata('company_id')) {
            $this->refuse();
        }
        if ($this->Hotel_model->roomTypeInUse($id)) {
            $this->session->set_flashdata('exception_1', lang('room_type_in_use'));
        } else {
            $this->Common_model->deleteStatusChange($id, "tbl_hotel_room_types");
            $this->session->set_flashdata('exception', lang('delete_success'));
        }
        redirect('Hotel/roomTypes');
    }

    /* ================================================================ rooms */

    public function rooms() {
        $data = array();
        $data['rooms'] = $this->Hotel_model->getRooms($this->session->userdata('company_id'), $this->outletIds());
        $data['can_add'] = $this->can('hotel_rooms', 'add');
        $data['can_update'] = $this->can('hotel_rooms', 'update');
        $data['can_delete'] = $this->can('hotel_rooms', 'delete');
        $data['main_content'] = $this->load->view('hotel/rooms', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    public function addEditRoom($encrypted_id = "") {
        $company_id = $this->session->userdata('company_id');
        //encrypt_decrypt('') answers FALSE, so normalise: "" = new, otherwise the id
        $id = ($encrypted_id === "" || $encrypted_id === NULL) ? "" : (string) $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $existing = $id !== "" ? $this->Hotel_model->getRoom($id) : NULL;
        if ($id !== "" && (!$existing || (int) $existing->company_id !== (int) $company_id)) {
            $this->refuse();
        }
        $outlet_ids = $this->outletIds();
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('outlet_id', lang('outlet'), 'required|integer');
            $this->form_validation->set_rules('number', lang('room_number'), 'required|max_length[50]');
            $this->form_validation->set_rules('room_type_id', lang('room_type'), 'integer');
            $this->form_validation->set_rules('floor', lang('floor'), 'max_length[50]');
            $this->form_validation->set_rules('notes', lang('notes'), 'max_length[250]');
            if ($this->form_validation->run() == TRUE) {
                $outlet_id = (int) $this->input->post('outlet_id');
                $number = htmlspecialcharscustom($this->input->post($this->security->xss_clean('number')));
                if (!in_array($outlet_id, $outlet_ids, TRUE)) {
                    $this->session->set_flashdata('exception_1', lang('outlet_not_assigned'));
                    redirect('Hotel/rooms');
                }
                if ($this->Hotel_model->roomNumberExists($outlet_id, $number, $id !== "" ? (int) $id : 0)) {
                    $this->form_validation->set_message('number', lang('room_number_exists'));
                    $data = array('encrypted_id' => $encrypted_id, 'room' => $existing, 'room_types' => $this->Hotel_model->getRoomTypes($company_id),
                                  'outlets' => $this->Hotel_model->getOutlets($outlet_ids), 'number_error' => lang('room_number_exists'));
                    $data['main_content'] = $this->load->view('hotel/addEditRoom', $data, TRUE);
                    $this->load->view('userHome', $data);
                    return;
                }
                $info = array();
                $info['outlet_id'] = $outlet_id;
                $info['number'] = $number;
                $type_id = (int) $this->input->post('room_type_id');
                $info['room_type_id'] = $type_id ? $type_id : NULL;
                $info['floor'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('floor')));
                $info['notes'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('notes')));
                $info['updated_at'] = date('Y-m-d H:i:s');
                if ($id == "") {
                    $info['company_id'] = $company_id;
                    $info['user_id'] = $this->session->userdata('user_id');
                    $info['created_at'] = date('Y-m-d H:i:s');
                    //a new room starts vacant and clean (the defaults of the table)
                    $this->Common_model->insertInformation($info, "tbl_hotel_rooms");
                    $this->session->set_flashdata('exception', lang('insertion_success'));
                } else {
                    $this->Common_model->updateInformation($info, $id, "tbl_hotel_rooms");
                    $this->session->set_flashdata('exception', lang('update_success'));
                }
                redirect('Hotel/rooms');
            }
        }
        $data = array();
        $data['encrypted_id'] = $encrypted_id;
        $data['room'] = $existing;
        $data['room_types'] = $this->Hotel_model->getRoomTypes($company_id);
        $data['outlets'] = $this->Hotel_model->getOutlets($outlet_ids);
        $data['main_content'] = $this->load->view('hotel/addEditRoom', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    public function deleteRoom($encrypted_id) {
        //encrypt_decrypt('') answers FALSE, so normalise: "" = new, otherwise the id
        $id = ($encrypted_id === "" || $encrypted_id === NULL) ? "" : (string) $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $existing = $this->Hotel_model->getRoom($id);
        if (!$existing || (int) $existing->company_id !== (int) $this->session->userdata('company_id')) {
            $this->refuse();
        }
        if ($this->Hotel_model->roomHasInHouseStay($id) || $existing->occupancy_status === 'occupied') {
            $this->session->set_flashdata('exception_1', lang('room_occupied_cannot_delete'));
        } else {
            $this->Common_model->deleteStatusChange($id, "tbl_hotel_rooms");
            $this->session->set_flashdata('exception', lang('delete_success'));
        }
        redirect('Hotel/rooms');
    }

    /* ================================================================ front desk (H2) */

    /**
     * which outlet the board shows: the posted one when it is accessible, else
     * the session outlet
     */
    private function boardOutlet() {
        $ids = $this->outletIds();
        $posted = (int) $this->input->get_post('outlet_id');
        if ($posted && in_array($posted, $ids, TRUE)) { return $posted; }
        return (int) $this->session->userdata('outlet_id');
    }

    /** JSON helpers for the board's actions. Text is stored HTML-encoded (htmlspecialcharscustom
     *  on the way in, the app's convention) and the boards escape again when they render, so
     *  strings are decoded here once: "O'Brien" must not reach the screen as O&#039;Brien. */
    private function jsonOk($extra = array()) {
        header('Content-Type: application/json');
        echo json_encode($this->jsonDecodeText(array_merge(array('ok' => 1), $extra)));
    }
    private function jsonDecodeText($v) {
        if (is_string($v)) { return html_entity_decode($v, ENT_QUOTES | ENT_HTML401, 'UTF-8'); }
        if (is_array($v)) { foreach ($v as $k => $x) { $v[$k] = $this->jsonDecodeText($x); } return $v; }
        if (is_object($v)) { foreach (get_object_vars($v) as $k => $x) { $v->$k = $this->jsonDecodeText($x); } return $v; }
        return $v;
    }
    private function jsonFail($reason, $extra = array()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(array('ok' => 0, 'reason' => $reason, 'message' => lang('hotel_err_' . $reason)), $extra));
    }
    /** a room this user may act on: live, in the company, in an accessible outlet */
    private function actionableRoom($room_id) {
        $room = $this->Hotel_model->getRoom($room_id);
        if (!$room || (int) $room->company_id !== (int) $this->session->userdata('company_id')) { return NULL; }
        if (!in_array((int) $room->outlet_id, $this->outletIds(), TRUE)) { return NULL; }
        return $room;
    }

    /**
     * the Front Desk board (server-rendered once, then polled through boardAjax)
     * @access public
     * @return void
     */
    public function frontDesk() {
        $outlet_id = $this->boardOutlet();
        $data = array();
        $data['outlet_id'] = $outlet_id;
        $data['outlets'] = $this->Hotel_model->getOutlets($this->outletIds());
        $data['rooms'] = $this->Hotel_model->getBoard($outlet_id);
        $data['can_checkin'] = $this->can('hotel_front_desk', 'checkin');
        $data['can_checkout'] = $this->can('hotel_front_desk', 'checkout');
        $data['can_status'] = $this->can('hotel_front_desk', 'status');
        $data['can_value'] = $this->can('hotel_front_desk', 'value');
        $data['main_content'] = $this->load->view('hotel/front_desk', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * the board as JSON, polled every 15 s and after every action
     * @access public
     * @return void
     */
    public function boardAjax() {
        $outlet_id = $this->boardOutlet();
        $rooms = $this->Hotel_model->getBoard($outlet_id);
        $counts = array('rooms' => count($rooms), 'occupied' => 0, 'vacant' => 0, 'out_of_order' => 0, 'dirty' => 0);
        foreach ($rooms as $r) {
            if (isset($counts[$r->occupancy_status])) { $counts[$r->occupancy_status]++; }
            if ($r->housekeeping_status === 'dirty' || $r->housekeeping_status === 'in_progress') { $counts['dirty']++; }
        }
        $this->jsonOk(array('outlet_id' => $outlet_id, 'rooms' => $rooms, 'counts' => $counts, 'server_time' => date('Y-m-d H:i:s'),
                            'can' => array('checkin' => $this->can('hotel_front_desk', 'checkin') ? 1 : 0, 'checkout' => $this->can('hotel_front_desk', 'checkout') ? 1 : 0, 'status' => $this->can('hotel_front_desk', 'status') ? 1 : 0, 'value' => $this->can('hotel_front_desk', 'value') ? 1 : 0)));
    }

    /**
     * check a guest in. Rules: the room must be vacant (occupied / out of order
     * refused) and hold no in-house stay; a room that is not clean / inspected
     * is WARNED about, not blocked - the caller re-posts with confirm_dirty=1.
     * @access public
     * @return void
     */
    public function checkIn() {
        $room = $this->actionableRoom((int) $this->input->post('room_id'));
        if (!$room) { return $this->jsonFail('room'); }
        $guest = trim(htmlspecialcharscustom($this->input->post($this->security->xss_clean('guest_name'))));
        if ($guest === '' || mb_strlen($guest) > 150) { return $this->jsonFail('guest_name'); }
        if ($room->occupancy_status === 'occupied' || $this->Hotel_model->getInHouseStay($room->id)) { return $this->jsonFail('occupied'); }
        if ($room->occupancy_status === 'out_of_order') { return $this->jsonFail('out_of_order'); }
        $expected = trim((string) $this->input->post('expected_checkout'));
        if ($expected !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected) || $expected < date('Y-m-d'))) { return $this->jsonFail('date'); }
        /* H5 - the stay's value, counted at check-in (business decision). The rate comes from the
           room type; only a 'value' holder may type a different one. A rate above 0 needs the
           expected check-out, because that is what the value is computed from. */
        $type = $this->Hotel_model->getRoomType($room->room_type_id);
        $rate = $type && $type->base_rate !== NULL ? (float) $type->base_rate : 0.0;
        if ($this->can('hotel_front_desk', 'value') && trim((string) $this->input->post('rate')) !== '') {
            if (!is_numeric($this->input->post('rate')) || (float) $this->input->post('rate') < 0) { return $this->jsonFail('rate'); }
            $rate = round((float) $this->input->post('rate'), 2);
        }
        if ($rate > 0 && $expected === '') { return $this->jsonFail('expected_checkout'); }
        $nights = $this->nightsBetween(date('Y-m-d'), $expected !== '' ? $expected : date('Y-m-d'));
        $amount = round($nights * $rate, 2);
        if (!in_array($room->housekeeping_status, array('clean', 'inspected'), TRUE) && (int) $this->input->post('confirm_dirty') !== 1) {
            return $this->jsonFail('dirty', array('warn' => 1, 'housekeeping_status' => $room->housekeeping_status));
        }
        $user_id = (int) $this->session->userdata('user_id');
        $stay_id = $this->Hotel_model->insertStay(array(
            'rate' => $rate, 'nights' => $nights, 'amount' => $amount,
            'company_id' => (int) $room->company_id, 'outlet_id' => (int) $room->outlet_id, 'room_id' => (int) $room->id,
            'guest_name' => $guest,
            'guest_phone' => htmlspecialcharscustom($this->input->post($this->security->xss_clean('guest_phone'))),
            'adults' => max(1, min(20, (int) $this->input->post('adults'))),
            'children' => max(0, min(20, (int) $this->input->post('children'))),
            'checkin_at' => date('Y-m-d H:i:s'), 'expected_checkout' => $expected !== '' ? $expected : NULL,
            'checked_in_by' => $user_id, 'status' => 'in_house',
            'reference' => htmlspecialcharscustom($this->input->post($this->security->xss_clean('reference'))),
            'notes' => htmlspecialcharscustom($this->input->post($this->security->xss_clean('notes'))),
        ));
        $this->Hotel_model->setRoomStatus($room->id, 'occupancy', 'occupied', $user_id, lang('hotel_log_checkin') . ' ' . $guest);
        $this->Hotel_model->logStatus($room->id, 'value', NULL, number_format($amount, 2, '.', ''), $user_id, lang('hotel_log_checkin') . ' ' . $nights . ' x ' . number_format($rate, 2, '.', '') . ' (stay ' . $stay_id . ')');
        putAuditLog($user_id, 'Check-in: room ' . $room->number . ', ' . $guest . ' (stay ' . $stay_id . ', value ' . $amount . ')', 'Hotel Check-in', date('Y-m-d H:i:s'));
        $this->jsonOk(array('stay_id' => $stay_id, 'rate' => $rate, 'nights' => $nights, 'amount' => $amount));
    }

    /**
     * check the guest out: closes the stay, the room becomes vacant AND dirty,
     * and a cleaning task goes to the housekeeping pool
     * @access public
     * @return void
     */
    public function checkOut() {
        $room = $this->actionableRoom((int) $this->input->post('room_id'));
        if (!$room) { return $this->jsonFail('room'); }
        $stay = $this->Hotel_model->getInHouseStay($room->id);
        if (!$stay) { return $this->jsonFail('not_in_house'); }
        $user_id = (int) $this->session->userdata('user_id');
        /* H5 - actual nights vs the nights the value was computed from (option C, 2026-09-16):
           a 'value' holder is asked to keep or adjust the recorded value; other staff check out
           and the variance stays visible in the Stay Log and the reports for a manager. */
        $actual = $this->nightsBetween(substr($stay->checkin_at, 0, 10), date('Y-m-d'));
        $expected_n = $stay->nights !== NULL ? (int) $stay->nights : NULL;
        $can_value = $this->can('hotel_front_desk', 'value');
        if ($can_value && $expected_n !== NULL && $actual !== $expected_n && (int) $this->input->post('confirm_value') !== 1) {
            return $this->jsonFail('variance', array('warn' => 1, 'expected_nights' => $expected_n, 'actual_nights' => $actual, 'rate' => (float) $stay->rate,
                                                     'amount' => (float) $stay->amount, 'suggested_amount' => round($actual * (float) $stay->rate, 2), 'guest_name' => $stay->guest_name));
        }
        if ($can_value && (int) $this->input->post('confirm_value') === 1 && trim((string) $this->input->post('amount')) !== '') {
            if (!is_numeric($this->input->post('amount')) || (float) $this->input->post('amount') < 0) { return $this->jsonFail('amount'); }
            $new_amount = round((float) $this->input->post('amount'), 2);
            if ($new_amount !== round((float) $stay->amount, 2)) {
                $note = trim(htmlspecialcharscustom($this->input->post($this->security->xss_clean('value_note'))));
                $this->Hotel_model->updateStay($stay->id, array('amount' => $new_amount, 'value_note' => $note !== '' ? $note : lang('hotel_log_checkout') . ': ' . $actual . ' ' . lang('nights')));
                $this->Hotel_model->logStatus($room->id, 'value', number_format((float) $stay->amount, 2, '.', ''), number_format($new_amount, 2, '.', ''), $user_id, ($note !== '' ? $note . ' - ' : '') . lang('hotel_log_checkout') . ' ' . $actual . ' / ' . $expected_n . ' ' . lang('nights'));
                putAuditLog($user_id, 'Stay value: room ' . $room->number . ' ' . $stay->amount . ' -> ' . $new_amount . ' at check-out (stay ' . $stay->id . ')', 'Hotel Stay Value', date('Y-m-d H:i:s'));
            }
        }
        if (!$this->Hotel_model->closeStay($stay->id, $user_id, $actual)) { return $this->jsonFail('not_in_house'); }
        $this->Hotel_model->setRoomStatus($room->id, 'occupancy', 'vacant', $user_id, lang('hotel_log_checkout') . ' ' . $stay->guest_name);
        $this->Hotel_model->setRoomStatus($room->id, 'housekeeping', 'dirty', $user_id, lang('hotel_log_checkout'));
        $task_id = $this->Hotel_model->createTask($room->company_id, $room->outlet_id, $room->id, 'cleaning', $user_id, lang('hotel_task_after_checkout'));
        putAuditLog($user_id, 'Check-out: room ' . $room->number . ', ' . $stay->guest_name . ' (stay ' . $stay->id . ')', 'Hotel Check-out', date('Y-m-d H:i:s'));
        $this->jsonOk(array('stay_id' => (int) $stay->id, 'task_id' => $task_id, 'actual_nights' => $actual, 'variance' => ($expected_n !== NULL && $actual !== $expected_n) ? 1 : 0));
    }

    /**
     * H10 - the Front Desk analytics row. Two clearly separate things: a LIVE snapshot (rooms
     * checked in / vacant / out of order per category with the value of the stays in house and
     * the potential value of the vacant rooms at base rate, occupancy %, housekeeping states)
     * and the VALUE GENERATED IN A PERIOD (stays checked in between two dates, per category and
     * in total) - decision 3: a historical count is never blended into the live row.
     * @access public
     * @return void
     */
    public function statsAjax() {
        $company_id = (int) $this->session->userdata('company_id');
        $outlet_id = $this->boardOutlet();
        $from = trim((string) $this->input->post('start_date')); $to = trim((string) $this->input->post('end_date'));
        if (!$this->validDate($from)) { $from = date('Y-m-01'); }
        if (!$this->validDate($to)) { $to = date('Y-m-t'); }
        if ($from > $to) { $t = $from; $from = $to; $to = $t; }
        $st = trim((string) $this->input->post('start_time')); $et = trim((string) $this->input->post('end_time'));
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $st)) { $st = ''; }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $et)) { $et = ''; }
        $live = array(); $tot = array('rooms' => 0, 'occupied' => 0, 'vacant' => 0, 'out_of_order' => 0, 'value' => 0.0, 'potential' => 0.0);
        foreach ($this->Hotel_model->liveStatsByType($company_id, $outlet_id) as $r) {
            $row = array('type_id' => (int) $r->room_type_id, 'name' => $r->type_name ? $r->type_name : lang('hk_no_category'), 'rooms' => (int) $r->rooms, 'occupied' => (int) $r->occupied,
                         'vacant' => (int) $r->vacant, 'out_of_order' => (int) $r->out_of_order, 'value' => (float) $r->value, 'potential' => (int) $r->vacant * (float) $r->base_rate,
                         'occupancy' => (int) $r->rooms > 0 ? round((int) $r->occupied * 100 / (int) $r->rooms, 1) : 0);
            $live[] = $row;
            foreach (array('rooms', 'occupied', 'vacant', 'out_of_order', 'value', 'potential') as $k) { $tot[$k] += $row[$k]; }
        }
        $tot['occupancy'] = $tot['rooms'] > 0 ? round($tot['occupied'] * 100 / $tot['rooms'], 1) : 0;
        $period = array(); $period_total = array('stays' => 0, 'nights' => 0, 'amount' => 0.0);
        foreach ($this->Hotel_model->valueByTypeAndBucket($company_id, array($outlet_id), $from, $to, 'year', $st, $et) as $r) {
            $tid = (int) $r->room_type_id;
            if (!isset($period[$tid])) { $period[$tid] = array('type_id' => $tid, 'name' => $r->type_name ? $r->type_name : lang('hk_no_category'), 'stays' => 0, 'nights' => 0, 'amount' => 0.0); }
            $period[$tid]['stays'] += (int) $r->stays; $period[$tid]['nights'] += (int) $r->nights; $period[$tid]['amount'] += (float) $r->amount;
            $period_total['stays'] += (int) $r->stays; $period_total['nights'] += (int) $r->nights; $period_total['amount'] += (float) $r->amount;
        }
        $this->jsonOk(array('outlet_id' => $outlet_id, 'server_time' => date('Y-m-d H:i:s'), 'live' => $live, 'live_total' => $tot,
                            'housekeeping' => $this->Hotel_model->housekeepingCounts($outlet_id), 'period' => array('from' => $from, 'to' => $to, 'start_time' => $st, 'end_time' => $et, 'rows' => array_values($period), 'total' => $period_total)));
    }

    /**
     * H5 - change a stay's expected check-out / rate / amount ('value' permission).
     * Works on in-house and checked-out stays (a manager settling a variance later).
     * The amount follows nights x rate unless one is typed; every change is logged
     * as kind 'value' with the old and new figure.
     * @access public
     * @return void
     */
    public function editStayValue() {
        $stay = $this->Hotel_model->getStay((int) $this->input->post('stay_id'));
        if (!$stay || (int) $stay->company_id !== (int) $this->session->userdata('company_id') || !in_array((int) $stay->outlet_id, $this->outletIds(), TRUE) || $stay->status === 'cancelled') { return $this->jsonFail('stay'); }
        $user_id = (int) $this->session->userdata('user_id');
        $expected = trim((string) $this->input->post('expected_checkout'));
        if ($expected !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected) || $expected < substr($stay->checkin_at, 0, 10))) { return $this->jsonFail('date'); }
        if ($expected === '') { $expected = $stay->expected_checkout; }
        $rate = trim((string) $this->input->post('rate')) !== '' ? $this->input->post('rate') : $stay->rate;
        if ($rate === NULL || !is_numeric($rate) || (float) $rate < 0) { return $this->jsonFail('rate'); }
        $rate = round((float) $rate, 2);
        if ($rate > 0 && ($expected === NULL || $expected === '')) { return $this->jsonFail('expected_checkout'); }
        $nights = $this->nightsBetween(substr($stay->checkin_at, 0, 10), $expected ? $expected : substr($stay->checkin_at, 0, 10));
        $amount = trim((string) $this->input->post('amount')) !== '' ? $this->input->post('amount') : round($nights * $rate, 2);
        if (!is_numeric($amount) || (float) $amount < 0) { return $this->jsonFail('amount'); }
        $amount = round((float) $amount, 2);
        $note = trim(htmlspecialcharscustom($this->input->post($this->security->xss_clean('value_note'))));
        $upd = array('expected_checkout' => $expected ? $expected : NULL, 'rate' => $rate, 'nights' => $nights, 'amount' => $amount);
        if ($note !== '') { $upd['value_note'] = $note; }
        $this->Hotel_model->updateStay($stay->id, $upd);
        $old = $stay->amount === NULL ? NULL : number_format((float) $stay->amount, 2, '.', '');
        if ($old !== number_format($amount, 2, '.', '') || (int) $stay->nights !== $nights || round((float) $stay->rate, 2) !== $rate) {
            $this->Hotel_model->logStatus($stay->room_id, 'value', $old, number_format($amount, 2, '.', ''), $user_id, ($note !== '' ? $note . ' - ' : '') . $nights . ' x ' . number_format($rate, 2, '.', '') . ' (stay ' . $stay->id . ')');
            putAuditLog($user_id, 'Stay value: stay ' . $stay->id . ' ' . $stay->amount . ' -> ' . $amount . ' (' . $nights . ' x ' . $rate . ')', 'Hotel Stay Value', date('Y-m-d H:i:s'));
        }
        $this->jsonOk(array('stay_id' => (int) $stay->id, 'expected_checkout' => $expected, 'rate' => $rate, 'nights' => $nights, 'amount' => $amount));
    }

    /* ================================================================ reports (H6+) */

    /**
     * the filter every hotel report shares: outlet scope (one accessible outlet or all of
     * them), the view (day / week / month / year buckets) and a date range, defaulting to
     * this month. Reads GET or POST so a report URL can be bookmarked.
     */
    private function reportFilters() {
        $ids = $this->outletIds();
        $f = array('outlet_ids' => $ids, 'outlet_id' => 'all', 'view' => 'month', 'from' => date('Y-m-01'), 'to' => date('Y-m-t'));
        $posted = (string) $this->input->get_post('outlet_id');
        if ($posted !== '' && $posted !== 'all' && in_array((int) $posted, $ids, TRUE)) { $f['outlet_ids'] = array((int) $posted); $f['outlet_id'] = (string) (int) $posted; }
        $view = (string) $this->input->get_post('view');
        if (in_array($view, array('day', 'week', 'month', 'year'), TRUE)) { $f['view'] = $view; }
        foreach (array('from' => 'start_date', 'to' => 'end_date') as $k => $name) {
            $v = trim((string) $this->input->get_post($name));
            if ($this->validDate($v)) { $f[$k] = $v; }
        }
        if ($f['from'] > $f['to']) { $t = $f['from']; $f['from'] = $f['to']; $f['to'] = $t; }
        /* a day view over years of data would be thousands of columns - cap the span per view */
        $max_days = array('day' => 92, 'week' => 371, 'month' => 1830, 'year' => 7300);
        $span = (strtotime($f['to']) - strtotime($f['from'])) / 86400;
        if ($span > $max_days[$f['view']]) { $f['from'] = date('Y-m-d', strtotime($f['to'] . ' -' . $max_days[$f['view']] . ' days')); $f['capped'] = 1; }
        $f['outlets'] = $this->Hotel_model->getOutlets($ids);
        return $f;
    }

    /** the period buckets of a range, in order, keyed by their Y-m-d start: the report's columns */
    private function reportBuckets($from, $to, $view) {
        $out = array();
        $t = strtotime($from); $end = strtotime($to);
        if ($view === 'week') { $t = strtotime('monday this week', $t); }
        elseif ($view === 'month') { $t = strtotime(date('Y-m-01', $t)); }
        elseif ($view === 'year') { $t = strtotime(date('Y-01-01', $t)); }
        $guard = 0;
        while ($t <= $end && $guard++ < 10000) {
            $key = date('Y-m-d', $t);
            switch ($view) {
                case 'day':   $out[$key] = date('d M Y', $t); $t = strtotime('+1 day', $t); break;
                case 'week':  $out[$key] = lang('week') . ' ' . date('W', $t) . ' · ' . date('d M', $t); $t = strtotime('+7 days', $t); break;
                case 'year':  $out[$key] = date('Y', $t); $t = strtotime('+1 year', $t); break;
                default:      $out[$key] = date('M Y', $t); $t = strtotime('+1 month', $t); break;
            }
        }
        return $out;
    }

    /** landing for the Reports menu: the first report */
    public function reports() { redirect('Hotel/reportValue'); }

    /**
     * H6 - Value Generated: rows = room categories (every live type, zeros included),
     * columns = the buckets of the selected view over the range, row / column / grand totals.
     * @access public
     * @return void
     */
    public function reportValue() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $buckets = $this->reportBuckets($f['from'], $f['to'], $f['view']);
        $rows = $this->Hotel_model->valueByTypeAndBucket($company_id, $f['outlet_ids'], $f['from'], $f['to'], $f['view']);
        /* every live category is a row, even with nothing in the period */
        $types = array();
        foreach ($this->Hotel_model->getRoomTypes($company_id) as $t) { $types[(int) $t->id] = array('name' => $t->name, 'cells' => array(), 'stays' => 0, 'nights' => 0, 'amount' => 0.0); }
        $col = array(); foreach ($buckets as $k => $label) { $col[$k] = array('stays' => 0, 'nights' => 0, 'amount' => 0.0); }
        $grand = array('stays' => 0, 'nights' => 0, 'amount' => 0.0);
        foreach ($rows as $r) {
            $tid = (int) $r->room_type_id;
            if (!isset($types[$tid])) { $types[$tid] = array('name' => $r->type_name ? $r->type_name : lang('hotel_no_category'), 'cells' => array(), 'stays' => 0, 'nights' => 0, 'amount' => 0.0); }
            $b = substr($r->bucket, 0, 10);
            if (!isset($buckets[$b])) { continue; }
            $types[$tid]['cells'][$b] = array('stays' => (int) $r->stays, 'nights' => (int) $r->nights, 'amount' => (float) $r->amount);
            $types[$tid]['stays'] += (int) $r->stays; $types[$tid]['nights'] += (int) $r->nights; $types[$tid]['amount'] += (float) $r->amount;
            $col[$b]['stays'] += (int) $r->stays; $col[$b]['nights'] += (int) $r->nights; $col[$b]['amount'] += (float) $r->amount;
            $grand['stays'] += (int) $r->stays; $grand['nights'] += (int) $r->nights; $grand['amount'] += (float) $r->amount;
        }
        $data = array('filters' => $f, 'buckets' => $buckets, 'types' => $types, 'columns' => $col, 'grand' => $grand);
        $data['main_content'] = $this->load->view('hotel/report_value', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /** the bucket key (Y-m-d start) a day belongs to, for a view - the same keys reportBuckets() emits */
    private function bucketKey($day, $view) {
        $t = strtotime($day);
        switch ($view) {
            case 'day':   return $day;
            case 'week':  return date('Y-m-d', strtotime('monday this week', $t));
            case 'year':  return date('Y-01-01', $t);
            default:      return date('Y-m-01', $t);
        }
    }

    /**
     * H7 - Occupancy: per period bucket, rooms available (live rooms x days), occupied
     * room-nights, occupancy %, arrivals, departures, value booked (on check-in days) and
     * RevPAR = value / available room-nights; then the same for the whole range per room
     * category. A stay occupies its room from the check-in date up to the day before
     * check-out (a same-day stay counts one night); an in-house stay counts up to today -
     * nothing in the future is counted as occupied.
     * @access public
     * @return void
     */
    public function reportOccupancy() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $buckets = $this->reportBuckets($f['from'], $f['to'], $f['view']);
        $today = date('Y-m-d');
        $types = array(); $rooms_total = 0;
        foreach ($this->Hotel_model->roomCountsByType($company_id, $f['outlet_ids']) as $t) {
            $types[(int) $t->room_type_id] = array('name' => $t->type_name ? $t->type_name : lang('hk_no_category'), 'rooms' => (int) $t->rooms, 'available' => 0, 'occupied' => 0, 'arrivals' => 0, 'departures' => 0, 'value' => 0.0);
            $rooms_total += (int) $t->rooms;
        }
        $per = array(); foreach ($buckets as $k => $label) { $per[$k] = array('label' => $label, 'days' => 0, 'available' => 0, 'occupied' => 0, 'arrivals' => 0, 'departures' => 0, 'value' => 0.0); }
        /* days in range -> bucket, available room-nights */
        $days_in_range = 0;
        for ($d = strtotime($f['from']); $d <= strtotime($f['to']); $d = strtotime('+1 day', $d)) {
            $k = $this->bucketKey(date('Y-m-d', $d), $f['view']);
            if (!isset($per[$k])) { continue; }
            $per[$k]['days']++; $per[$k]['available'] += $rooms_total; $days_in_range++;
        }
        foreach ($types as $tid => $t) { $types[$tid]['available'] = $t['rooms'] * $days_in_range; }
        /* stays -> occupied room-nights, arrivals, departures, value */
        foreach ($this->Hotel_model->staysOverlapping($company_id, $f['outlet_ids'], $f['from'], $f['to']) as $s) {
            $tid = (int) $s->room_type_id;
            if (!isset($types[$tid])) { $types[$tid] = array('name' => lang('hk_no_category'), 'rooms' => 0, 'available' => 0, 'occupied' => 0, 'arrivals' => 0, 'departures' => 0, 'value' => 0.0); }
            $in = substr($s->checkin_at, 0, 10);
            if ($s->status === 'checked_out' && $s->checkout_at) { $out = substr($s->checkout_at, 0, 10); $last = max($in, date('Y-m-d', strtotime($out . ' -1 day'))); }
            else { $out = NULL; $last = max($in, $today); }
            /* arrivals / value on the check-in day, departures on the check-out day */
            if ($in >= $f['from'] && $in <= $f['to']) { $k = $this->bucketKey($in, $f['view']); if (isset($per[$k])) { $per[$k]['arrivals']++; $per[$k]['value'] += (float) $s->amount; } $types[$tid]['arrivals']++; $types[$tid]['value'] += (float) $s->amount; }
            if ($out !== NULL && $out >= $f['from'] && $out <= $f['to']) { $k = $this->bucketKey($out, $f['view']); if (isset($per[$k])) { $per[$k]['departures']++; } $types[$tid]['departures']++; }
            /* occupied room-nights: each day from check-in to the last night, clipped to the range */
            $from_d = max($in, $f['from']); $to_d = min($last, $f['to']);
            for ($d = strtotime($from_d); $d <= strtotime($to_d); $d = strtotime('+1 day', $d)) {
                $k = $this->bucketKey(date('Y-m-d', $d), $f['view']);
                if (isset($per[$k])) { $per[$k]['occupied']++; }
                $types[$tid]['occupied']++;
            }
        }
        $total = array('days' => $days_in_range, 'rooms' => $rooms_total, 'available' => 0, 'occupied' => 0, 'arrivals' => 0, 'departures' => 0, 'value' => 0.0);
        foreach ($per as $k => $b) { foreach (array('available', 'occupied', 'arrivals', 'departures', 'value') as $m) { $total[$m] += $b[$m]; } }
        $data = array('filters' => $f, 'per' => $per, 'types' => $types, 'total' => $total);
        $data['main_content'] = $this->load->view('hotel/report_occupancy', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * H8 - Stays: one row per stay checked in within the range, with its value; subtotals by
     * period bucket, by room category and by the staff member who checked the guest in. Extra
     * filters: category, staff, status. Variance (actual nights <> expected) is flagged per row.
     * @access public
     * @return void
     */
    public function reportStays() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $type_id = (int) $this->input->get_post('type_id'); $user_id = (int) $this->input->get_post('user_id');
        $status = (string) $this->input->get_post('status'); if (!in_array($status, array('in_house', 'checked_out'), TRUE)) { $status = ''; }
        $buckets = $this->reportBuckets($f['from'], $f['to'], $f['view']);
        $stays = $this->Hotel_model->staysReport($company_id, $f['outlet_ids'], $f['from'], $f['to'], $type_id, $user_id, $status);
        $zero = array('stays' => 0, 'nights' => 0, 'amount' => 0.0);
        $by_bucket = array(); foreach ($buckets as $k => $label) { $by_bucket[$k] = array('label' => $label) + $zero; }
        $by_type = array(); $by_staff = array(); $total = $zero; $variances = 0;
        foreach ($stays as $st) {
            $k = $this->bucketKey(substr($st->checkin_at, 0, 10), $f['view']);
            $tn = $st->type_name ? $st->type_name : lang('hk_no_category'); $sn = $st->in_by ? $st->in_by : '-';
            if (!isset($by_type[$tn])) { $by_type[$tn] = $zero; } if (!isset($by_staff[$sn])) { $by_staff[$sn] = $zero; }
            foreach (array(array(&$total), array(&$by_type[$tn]), array(&$by_staff[$sn])) as $ref) { $ref[0]['stays']++; $ref[0]['nights'] += (int) $st->nights; $ref[0]['amount'] += (float) $st->amount; }
            if (isset($by_bucket[$k])) { $by_bucket[$k]['stays']++; $by_bucket[$k]['nights'] += (int) $st->nights; $by_bucket[$k]['amount'] += (float) $st->amount; }
            if ($st->actual_nights !== NULL && (int) $st->actual_nights !== (int) $st->nights) { $variances++; }
        }
        ksort($by_type); ksort($by_staff);
        $types = array(); foreach ($this->Hotel_model->getRoomTypes($company_id) as $t) { $types[(int) $t->id] = $t->name; }
        $staff = array(); foreach ($this->Hotel_model->checkinStaff($company_id) as $u) { $staff[(int) $u->id] = $u->full_name; }
        $f['extra_filters'] = array(
            array('name' => 'type_id', 'label' => lang('room_type'), 'options' => $types, 'selected' => $type_id ? $type_id : ''),
            array('name' => 'user_id', 'label' => lang('hk_checked_in_by'), 'options' => $staff, 'selected' => $user_id ? $user_id : ''),
            array('name' => 'status', 'label' => lang('status'), 'options' => array('in_house' => lang('stay_in_house'), 'checked_out' => lang('stay_checked_out')), 'selected' => $status),
        );
        $data = array('filters' => $f, 'stays' => $stays, 'by_bucket' => $by_bucket, 'by_type' => $by_type, 'by_staff' => $by_staff, 'total' => $total, 'variances' => $variances);
        $data['main_content'] = $this->load->view('hotel/report_stays', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * H9 - Housekeeping productivity: the tasks CREATED in the range, grouped by the attendant who
     * held them (pool = never assigned), by task type and by room category. For each group: created,
     * done (done or verified), verified, cancelled, still open, and the average minutes from
     * creation to start (waiting), start to done (working) and done to verify (checking).
     * Extra filters: task type, attendant.
     * @access public
     * @return void
     */
    public function reportHousekeeping() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $task_type = (string) $this->input->get_post('task_type'); if (!in_array($task_type, array('cleaning', 'turndown', 'inspection', 'maintenance'), TRUE)) { $task_type = ''; }
        $user_id = (int) $this->input->get_post('user_id');
        $tasks = $this->Hotel_model->tasksReport($company_id, $f['outlet_ids'], $f['from'], $f['to'], $task_type, $user_id);
        $blank = array('created' => 0, 'done' => 0, 'verified' => 0, 'cancelled' => 0, 'open' => 0, 'wait' => array(), 'work' => array(), 'check' => array());
        $by_staff = array(); $by_type = array(); $by_cat = array(); $by_bucket = array(); $total = $blank;
        foreach ($this->reportBuckets($f['from'], $f['to'], $f['view']) as $k => $label) { $by_bucket[$k] = array('label' => $label) + $blank; }
        $mins = function($a, $b) { return ($a && $b) ? max(0, (strtotime($b) - strtotime($a)) / 60) : NULL; };
        foreach ($tasks as $t) {
            $sn = $t->assigned_name ? $t->assigned_name : lang('hk_pool'); $tn = lang('hotel_task_' . $t->task_type); $cn = $t->type_name ? $t->type_name : lang('hk_no_category');
            $bk = $this->bucketKey(substr($t->created_at, 0, 10), $f['view']);
            foreach (array('by_staff' => $sn, 'by_type' => $tn, 'by_cat' => $cn) as $var => $key) { if (!isset($$var[$key])) { $$var[$key] = $blank; } }
            $groups = array(&$total, &$by_staff[$sn], &$by_type[$tn], &$by_cat[$cn]); if (isset($by_bucket[$bk])) { $groups[] = &$by_bucket[$bk]; }
            $w = $mins($t->created_at, $t->started_at); $k2 = $mins($t->started_at, $t->done_at); $c = $mins($t->done_at, $t->verified_at);
            foreach ($groups as &$g) {
                $g['created']++;
                if ($t->status === 'done' || $t->status === 'verified') { $g['done']++; }
                if ($t->status === 'verified') { $g['verified']++; }
                if ($t->status === 'cancelled') { $g['cancelled']++; }
                if ($t->status === 'pending' || $t->status === 'in_progress') { $g['open']++; }
                if ($w !== NULL) { $g['wait'][] = $w; } if ($k2 !== NULL) { $g['work'][] = $k2; } if ($c !== NULL) { $g['check'][] = $c; }
            }
            unset($g);
        }
        $avg = function(&$rows) { foreach ($rows as &$g) { foreach (array('wait', 'work', 'check') as $m) { $g[$m . '_avg'] = $g[$m] ? round(array_sum($g[$m]) / count($g[$m])) : NULL; $g[$m . '_n'] = count($g[$m]); unset($g[$m]); } } unset($g); };
        $avg($by_staff); $avg($by_type); $avg($by_cat); $avg($by_bucket); $one = array($total); $avg($one); $total = $one[0];
        ksort($by_staff); ksort($by_type); ksort($by_cat);
        $staff = array(); foreach ($this->Hotel_model->taskStaff($company_id) as $u) { $staff[(int) $u->id] = $u->full_name; }
        $f['extra_filters'] = array(
            array('name' => 'task_type', 'label' => lang('hk_task_type'), 'options' => array('cleaning' => lang('hotel_task_cleaning'), 'turndown' => lang('hotel_task_turndown'), 'inspection' => lang('hotel_task_inspection'), 'maintenance' => lang('hotel_task_maintenance')), 'selected' => $task_type),
            array('name' => 'user_id', 'label' => lang('hk_attendant'), 'options' => $staff, 'selected' => $user_id ? $user_id : ''),
        );
        $data = array('filters' => $f, 'tasks' => $tasks, 'by_staff' => $by_staff, 'by_type' => $by_type, 'by_cat' => $by_cat, 'by_bucket' => $by_bucket, 'total' => $total);
        $data['main_content'] = $this->load->view('hotel/report_housekeeping', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * H11 - Room Turnaround: for every check-out in the range, how long the room stayed dirty
     * (check-out -> housekeeping 'clean' in the room's log) and how long until it was inspected
     * (clean -> 'inspected'). A room not yet clean is open, measured to now. Averages per
     * category and overall; the table sorts slowest first.
     * @access public
     * @return void
     */
    public function reportTurnaround() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $stays = $this->Hotel_model->staysCheckedOut($company_id, $f['outlet_ids'], $f['from'], $f['to']);
        $logs = $stays ? $this->Hotel_model->statusLogRange($company_id, $f['outlet_ids'], $f['from'] . ' 00:00:00', '2999-12-31 23:59:59', 'housekeeping') : array();
        $by_room = array(); foreach ($logs as $l) { $by_room[(int) $l->room_id][] = $l; }
        $rows = array(); $cats = array(); $tot = array('n' => 0, 'dirty' => array(), 'inspect' => array(), 'open' => 0);
        $now = date('Y-m-d H:i:s');
        foreach ($stays as $st) {
            $clean = NULL; $insp = NULL;
            foreach (isset($by_room[(int) $st->room_id]) ? $by_room[(int) $st->room_id] : array() as $l) {
                if ($l->created_at < $st->checkout_at) { continue; }
                if ($clean === NULL && ($l->to_status === 'clean' || $l->to_status === 'inspected')) { $clean = $l->created_at; }
                if ($clean !== NULL && $l->to_status === 'inspected' && $l->created_at >= $clean) { $insp = $l->created_at; break; }
                if ($clean !== NULL && $l->to_status === 'dirty') { break; }   // the next guest's cycle
            }
            $dirty_m = round((strtotime($clean ? $clean : $now) - strtotime($st->checkout_at)) / 60);
            $insp_m = ($clean && $insp) ? round((strtotime($insp) - strtotime($clean)) / 60) : NULL;
            $cn = $st->type_name ? $st->type_name : lang('hk_no_category');
            if (!isset($cats[$cn])) { $cats[$cn] = array('n' => 0, 'dirty' => array(), 'inspect' => array(), 'open' => 0); }
            foreach (array(&$tot, &$cats[$cn]) as &$g) { $g['n']++; if ($clean) { $g['dirty'][] = $dirty_m; } else { $g['open']++; } if ($insp_m !== NULL) { $g['inspect'][] = $insp_m; } } unset($g);
            $rows[] = array('room' => $st->room_number, 'type' => $cn, 'guest' => $st->guest_name, 'checkout_at' => $st->checkout_at, 'clean_at' => $clean, 'dirty_min' => $dirty_m, 'inspected_at' => $insp, 'inspect_min' => $insp_m, 'open' => $clean === NULL);
        }
        usort($rows, function($a, $b) { return $b['dirty_min'] <=> $a['dirty_min']; });
        $avg = function($g) { return array('n' => $g['n'], 'open' => $g['open'], 'dirty_avg' => $g['dirty'] ? round(array_sum($g['dirty']) / count($g['dirty'])) : NULL, 'dirty_max' => $g['dirty'] ? max($g['dirty']) : NULL, 'inspect_avg' => $g['inspect'] ? round(array_sum($g['inspect']) / count($g['inspect'])) : NULL); };
        $cat_rows = array(); ksort($cats); foreach ($cats as $k => $g) { $cat_rows[$k] = $avg($g); }
        $data = array('filters' => $f, 'rows' => $rows, 'cats' => $cat_rows, 'total' => $avg($tot));
        $data['main_content'] = $this->load->view('hotel/report_turnaround', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * H11 - Out of Order: every out-of-order spell that starts in the range (or is still open at
     * its start), from the occupancy log: taken out (when, by whom, why), back in service (when,
     * by whom), duration; room-days lost per category and overall.
     * @access public
     * @return void
     */
    public function reportOutOfOrder() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $logs = $this->Hotel_model->statusLogRange($company_id, $f['outlet_ids'], date('Y-m-d', strtotime($f['from'] . ' -1 year')) . ' 00:00:00', $f['to'] . ' 23:59:59', 'occupancy');
        $open = array(); $spells = array();
        foreach ($logs as $l) {
            $rid = (int) $l->room_id;
            if ($l->to_status === 'out_of_order') { $open[$rid] = $l; }
            elseif ($l->from_status === 'out_of_order' && isset($open[$rid])) { $spells[] = array('start' => $open[$rid], 'end' => $l); unset($open[$rid]); }
        }
        foreach ($open as $l) { $spells[] = array('start' => $l, 'end' => NULL); }
        $now = date('Y-m-d H:i:s'); $rows = array(); $cats = array(); $tot = array('n' => 0, 'hours' => 0.0, 'open' => 0);
        foreach ($spells as $sp) {
            $s0 = $sp['start']->created_at; $e0 = $sp['end'] ? $sp['end']->created_at : NULL;
            /* in the range when it starts in it, or was still open when the range started */
            if ($s0 > $f['to'] . ' 23:59:59') { continue; }
            if ($s0 < $f['from'] . ' 00:00:00' && $e0 !== NULL && $e0 < $f['from'] . ' 00:00:00') { continue; }
            $hours = round((strtotime($e0 ? $e0 : $now) - strtotime($s0)) / 3600, 1);
            $cn = $sp['start']->type_name ? $sp['start']->type_name : lang('hk_no_category');
            if (!isset($cats[$cn])) { $cats[$cn] = array('n' => 0, 'hours' => 0.0, 'open' => 0); }
            foreach (array(&$tot, &$cats[$cn]) as &$g) { $g['n']++; $g['hours'] += $hours; if ($e0 === NULL) { $g['open']++; } } unset($g);
            $rows[] = array('room' => $sp['start']->room_number, 'type' => $cn, 'start' => $s0, 'start_by' => $sp['start']->user_name, 'reason' => $sp['start']->note,
                            'end' => $e0, 'end_by' => $sp['end'] ? $sp['end']->user_name : NULL, 'end_note' => $sp['end'] ? $sp['end']->note : NULL, 'hours' => $hours);
        }
        usort($rows, function($a, $b) { return strcmp($b['start'], $a['start']); });
        ksort($cats);
        $data = array('filters' => $f, 'rows' => $rows, 'cats' => $cats, 'total' => $tot);
        $data['main_content'] = $this->load->view('hotel/report_out_of_order', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * H11 - Room Status History: the log rows in the range, filterable by room and by kind
     * (occupancy / housekeeping / value) - the front desk's history modal as a printable list.
     * @access public
     * @return void
     */
    public function reportStatusHistory() {
        $company_id = (int) $this->session->userdata('company_id');
        $f = $this->reportFilters();
        $kind = (string) $this->input->get_post('kind'); if (!in_array($kind, array('occupancy', 'housekeeping', 'value'), TRUE)) { $kind = ''; }
        $room_id = (int) $this->input->get_post('room_id');
        $rows = array_reverse($this->Hotel_model->statusLogRange($company_id, $f['outlet_ids'], $f['from'] . ' 00:00:00', $f['to'] . ' 23:59:59', $kind, $room_id));
        $rooms = array(); foreach ($this->Hotel_model->getRooms($company_id, $f['outlet_ids']) as $r) { $rooms[(int) $r->id] = $r->number . (count($f['outlets']) > 1 ? ' (' . $r->outlet_name . ')' : ''); }
        $f['extra_filters'] = array(
            array('name' => 'room_id', 'label' => lang('room'), 'options' => $rooms, 'selected' => $room_id ? $room_id : ''),
            array('name' => 'kind', 'label' => lang('hk_kind'), 'options' => array('occupancy' => lang('occupancy'), 'housekeeping' => lang('housekeeping'), 'value' => lang('hotel_log_value')), 'selected' => $kind),
        );
        $data = array('filters' => $f, 'rows' => $rows);
        $data['main_content'] = $this->load->view('hotel/report_status_history', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /** a real Y-m-d calendar date ("2020-13-45" matches the shape but would blow up in the query) */
    private function validDate($v) {
        return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** whole nights between two Y-m-d dates, never less than 1 (a same-day stay is one night's value) */
    private function nightsBetween($from, $to) {
        $d = (int) floor((strtotime($to) - strtotime($from)) / 86400);
        return max(1, $d);
    }

    /**
     * take a room out of order (only while vacant) or bring it back
     * @access public
     * @return void
     */
    public function setOutOfOrder() {
        $room = $this->actionableRoom((int) $this->input->post('room_id'));
        if (!$room) { return $this->jsonFail('room'); }
        $on = (int) $this->input->post('out_of_order') === 1;
        $note = htmlspecialcharscustom($this->input->post($this->security->xss_clean('note')));
        $user_id = (int) $this->session->userdata('user_id');
        if ($on) {
            if ($room->occupancy_status === 'occupied' || $this->Hotel_model->getInHouseStay($room->id)) { return $this->jsonFail('occupied'); }
            if ($room->occupancy_status === 'out_of_order') { return $this->jsonOk(); }
            $this->Hotel_model->setRoomStatus($room->id, 'occupancy', 'out_of_order', $user_id, $note);
        } else {
            if ($room->occupancy_status !== 'out_of_order') { return $this->jsonOk(); }
            $this->Hotel_model->setRoomStatus($room->id, 'occupancy', 'vacant', $user_id, $note);
        }
        $this->jsonOk();
    }

    /**
     * the last status changes of one room (the card's history modal)
     * @access public
     * @return void
     */
    public function roomHistoryAjax() {
        $room = $this->actionableRoom((int) $this->input->get_post('room_id'));
        if (!$room) { return $this->jsonFail('room'); }
        $this->jsonOk(array('room' => array('id' => (int) $room->id, 'number' => $room->number), 'history' => $this->Hotel_model->getRoomHistory($room->id)));
    }

    /**
     * the stay log with filters; ?export=csv streams the same rows as a file
     * @access public
     * @return void
     */
    public function stays() {
        $company_id = (int) $this->session->userdata('company_id');
        $ids = $this->outletIds();
        $filters = array('outlet_ids' => $ids, 'status' => '', 'room_id' => 0, 'date_from' => '', 'date_to' => '', 'guest' => '');
        $posted_outlet = (int) $this->input->get_post('outlet_id');
        if ($posted_outlet && in_array($posted_outlet, $ids, TRUE)) { $filters['outlet_ids'] = array($posted_outlet); $filters['outlet_id'] = $posted_outlet; } else { $filters['outlet_id'] = ''; }
        $status = (string) $this->input->get_post('status');
        $filters['status'] = in_array($status, array('in_house', 'checked_out'), TRUE) ? $status : '';
        foreach (array('date_from', 'date_to') as $k) {
            $v = trim((string) $this->input->get_post($k));
            $filters[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        }
        $filters['room_id'] = (int) $this->input->get_post('room_id');
        $filters['guest'] = trim(htmlspecialcharscustom($this->input->get_post($this->security->xss_clean('guest'))));
        $stays = $this->Hotel_model->getStays($company_id, $filters);

        if ((string) $this->input->get_post('export') === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="hotel_stays_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, array('Outlet', 'Room', 'Room type', 'Guest', 'Phone', 'Adults', 'Children', 'Check-in', 'Expected check-out', 'Check-out', 'Status', 'Rate', 'Nights', 'Actual nights', 'Amount', 'Reference', 'Checked in by', 'Checked out by', 'Notes', 'Value note'));
            foreach ($stays as $s) {
                fputcsv($out, array($s->outlet_name, $s->room_number, $s->type_name, $s->guest_name, $s->guest_phone, $s->adults, $s->children, $s->checkin_at, $s->expected_checkout, $s->checkout_at, $s->status, $s->rate, $s->nights, $s->actual_nights, $s->amount, $s->reference, $s->in_by, $s->out_by, $s->notes, $s->value_note));
            }
            fclose($out);
            return;
        }
        $data = array();
        $data['filters'] = $filters;
        $data['can_value'] = $this->can('hotel_front_desk', 'value');
        $data['outlets'] = $this->Hotel_model->getOutlets($ids);
        $data['rooms'] = $this->Hotel_model->getRooms($company_id, $ids);
        $data['stays'] = $stays;
        $data['main_content'] = $this->load->view('hotel/stays', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /* ================================================================ housekeeping board (H3) */

    /**
     * the task a user may act on: live, in an accessible outlet; an
     * update_task holder may only touch tasks that are theirs or unassigned
     * (the pool) unless they also hold assign
     */
    /** one audit row per task action, the same shape as the front desk's */
    private function auditTask($title, $task, $extra = '') {
        $room = $this->Hotel_model->getRoom($task->room_id);
        putAuditLog($this->session->userdata('user_id'), $title . ': room ' . ($room ? $room->number : $task->room_id) . ', ' . $task->task_type . ' (task ' . $task->id . ')' . ($extra !== '' ? ' ' . $extra : ''), 'Hotel Task ' . $title, date('Y-m-d H:i:s'));
    }

    private function actionableTask($task_id, $own_only) {
        $task = $this->Hotel_model->getTask($task_id);
        if (!$task || (int) $task->company_id !== (int) $this->session->userdata('company_id')) { return NULL; }
        if (!in_array((int) $task->outlet_id, $this->outletIds(), TRUE)) { return NULL; }
        if ($own_only && $task->assigned_to !== NULL && (int) $task->assigned_to !== (int) $this->session->userdata('user_id')) { return NULL; }
        return $task;
    }

    /**
     * the Housekeeping board - a standalone full-screen page on the Kitchen
     * Panel pattern (no sidebar; made for a tablet on the floor), polled
     * every 10 s through tasksAjax
     * @access public
     * @return void
     */
    public function housekeeping() {
        $outlet_id = $this->boardOutlet();
        $data = array();
        $data['outlet_id'] = $outlet_id;
        $data['outlet'] = $this->Common_model->getDataById($outlet_id, 'tbl_outlets');
        $data['outlets'] = $this->Hotel_model->getOutlets($this->outletIds());
        $data['can_update'] = $this->can('hotel_housekeeping', 'update_task');
        $data['can_assign'] = $this->can('hotel_housekeeping', 'assign');
        $data['can_verify'] = $this->can('hotel_housekeeping', 'verify');
        $data['can_front_desk'] = $this->can('hotel_front_desk', 'view');
        $data['staff'] = $data['can_assign'] ? $this->Hotel_model->getHousekeepingUsers($this->session->userdata('company_id')) : array();
        $data['rooms'] = $data['can_assign'] ? $this->Hotel_model->getRooms($this->session->userdata('company_id'), array($outlet_id)) : array();
        $this->load->view('hotel/housekeeping_panel', $data);
    }

    /**
     * the open tasks as JSON, sorted into sections for the caller
     * @access public
     * @return void
     */
    public function tasksAjax() {
        $outlet_id = $this->boardOutlet();
        $me = (int) $this->session->userdata('user_id');
        $tasks = $this->Hotel_model->getOpenTasks($outlet_id);
        $counts = array('mine' => 0, 'pool' => 0, 'others' => 0, 'done' => 0);
        foreach ($tasks as $t) {
            $t->is_mine = ((int) $t->assigned_to === $me) ? 1 : 0;
            if ($t->status === 'done') { $counts['done']++; }
            elseif ($t->assigned_to === NULL) { $counts['pool']++; }
            elseif ($t->is_mine) { $counts['mine']++; }
            else { $counts['others']++; }
        }
        $this->jsonOk(array('outlet_id' => $outlet_id, 'tasks' => $tasks, 'counts' => $counts, 'server_time' => date('Y-m-d H:i:s'), 'me' => $me,
                            'can' => array('update' => $this->can('hotel_housekeeping', 'update_task') ? 1 : 0, 'assign' => $this->can('hotel_housekeeping', 'assign') ? 1 : 0, 'verify' => $this->can('hotel_housekeeping', 'verify') ? 1 : 0)));
    }

    /**
     * start a task: pending -> in_progress. From the pool it is claimed by the
     * actor. The room is marked in_progress.
     * @access public
     * @return void
     */
    public function taskStart() {
        $task = $this->actionableTask((int) $this->input->post('task_id'), !$this->can('hotel_housekeeping', 'assign'));
        if (!$task) { return $this->jsonFail('task'); }
        if ($task->status !== 'pending') { return $this->jsonFail('task_state'); }
        $me = (int) $this->session->userdata('user_id');
        $this->Hotel_model->updateTask($task->id, array('status' => 'in_progress', 'started_at' => date('Y-m-d H:i:s'), 'assigned_to' => $task->assigned_to !== NULL ? (int) $task->assigned_to : $me));
        if ($task->task_type !== 'maintenance') { $this->Hotel_model->setRoomStatus($task->room_id, 'housekeeping', 'in_progress', $me, lang('hotel_task_' . $task->task_type)); }
        $this->auditTask('Start', $task);
        $this->jsonOk();
    }

    /**
     * finish a task: pending / in_progress -> done. The room is clean
     * (inspection / maintenance tasks do not touch the room state).
     * @access public
     * @return void
     */
    public function taskDone() {
        $task = $this->actionableTask((int) $this->input->post('task_id'), !$this->can('hotel_housekeeping', 'assign'));
        if (!$task) { return $this->jsonFail('task'); }
        if (!in_array($task->status, array('pending', 'in_progress'), TRUE)) { return $this->jsonFail('task_state'); }
        $me = (int) $this->session->userdata('user_id');
        $this->Hotel_model->updateTask($task->id, array('status' => 'done', 'done_at' => date('Y-m-d H:i:s'), 'started_at' => $task->started_at ? $task->started_at : date('Y-m-d H:i:s'), 'assigned_to' => $task->assigned_to !== NULL ? (int) $task->assigned_to : $me));
        if (in_array($task->task_type, array('cleaning', 'turndown'), TRUE)) { $this->Hotel_model->setRoomStatus($task->room_id, 'housekeeping', 'clean', $me, lang('hotel_task_' . $task->task_type)); }
        $this->auditTask('Finish', $task);
        $this->jsonOk();
    }

    /**
     * a supervisor verifies a done task: done -> verified; the room is inspected
     * @access public
     * @return void
     */
    public function taskVerify() {
        $task = $this->actionableTask((int) $this->input->post('task_id'), FALSE);
        if (!$task) { return $this->jsonFail('task'); }
        if ($task->status !== 'done') { return $this->jsonFail('task_state'); }
        $me = (int) $this->session->userdata('user_id');
        $this->Hotel_model->updateTask($task->id, array('status' => 'verified', 'verified_by' => $me, 'verified_at' => date('Y-m-d H:i:s')));
        $this->auditTask('Verify', $task);
        if (in_array($task->task_type, array('cleaning', 'turndown', 'inspection'), TRUE)) { $this->Hotel_model->setRoomStatus($task->room_id, 'housekeeping', 'inspected', $me, lang('hotel_verified')); }
        $this->jsonOk();
    }

    /**
     * hand a task to someone (or back to the pool with user 0)
     * @access public
     * @return void
     */
    public function taskAssign() {
        $task = $this->actionableTask((int) $this->input->post('task_id'), FALSE);
        if (!$task) { return $this->jsonFail('task'); }
        if (!in_array($task->status, array('pending', 'in_progress'), TRUE)) { return $this->jsonFail('task_state'); }
        $user_id = (int) $this->input->post('user_id');
        if ($user_id) {
            $ok = FALSE;
            foreach ($this->Hotel_model->getHousekeepingUsers($this->session->userdata('company_id')) as $u) { if ((int) $u->id === $user_id) { $ok = TRUE; } }
            if (!$ok) { return $this->jsonFail('user'); }
        }
        $this->Hotel_model->updateTask($task->id, array('assigned_to' => $user_id ? $user_id : NULL));
        $this->auditTask('Assign', $task, $user_id ? 'to user ' . $user_id : 'to the pool');
        $this->jsonOk();
    }

    /**
     * a task by hand (cleaning / turndown / inspection / maintenance) for a room
     * of the outlet; a cleaning task marks a clean room dirty
     * @access public
     * @return void
     */
    public function taskCreate() {
        $room = $this->actionableRoom((int) $this->input->post('room_id'));
        if (!$room) { return $this->jsonFail('room'); }
        $type = (string) $this->input->post('task_type');
        if (!in_array($type, array('cleaning', 'turndown', 'inspection', 'maintenance'), TRUE)) { return $this->jsonFail('task_type'); }
        $me = (int) $this->session->userdata('user_id');
        $note = htmlspecialcharscustom($this->input->post($this->security->xss_clean('note')));
        $assign = (int) $this->input->post('user_id');
        if ($assign) {
            $ok = FALSE;
            foreach ($this->Hotel_model->getHousekeepingUsers($this->session->userdata('company_id')) as $u) { if ((int) $u->id === $assign) { $ok = TRUE; } }
            if (!$ok) { return $this->jsonFail('user'); }
        }
        $task_id = $this->Hotel_model->createTask($room->company_id, $room->outlet_id, $room->id, $type, $me, $note);
        if (!$task_id) { return $this->jsonFail('task_exists'); }
        if ($assign) { $this->Hotel_model->updateTask($task_id, array('assigned_to' => $assign)); }
        putAuditLog($me, 'Create: room ' . $room->number . ', ' . $type . ' (task ' . $task_id . ')' . ($assign ? ' to user ' . $assign : ''), 'Hotel Task Create', date('Y-m-d H:i:s'));
        if ($type === 'cleaning' && in_array($room->housekeeping_status, array('clean', 'inspected'), TRUE)) {
            $this->Hotel_model->setRoomStatus($room->id, 'housekeeping', 'dirty', $me, $note !== '' ? $note : lang('hotel_task_cleaning'));
        }
        $this->jsonOk(array('task_id' => $task_id));
    }

    /**
     * drop an open task (assign permission). The room state is left as it is.
     * @access public
     * @return void
     */
    public function taskCancel() {
        $task = $this->actionableTask((int) $this->input->post('task_id'), FALSE);
        if (!$task) { return $this->jsonFail('task'); }
        if (!in_array($task->status, array('pending', 'in_progress', 'done'), TRUE)) { return $this->jsonFail('task_state'); }
        $this->Hotel_model->updateTask($task->id, array('status' => 'cancelled'));
        $this->auditTask('Cancel', $task);
        $this->jsonOk();
    }
}
