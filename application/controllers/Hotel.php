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
                            'can' => array('checkin' => $this->can('hotel_front_desk', 'checkin') ? 1 : 0, 'checkout' => $this->can('hotel_front_desk', 'checkout') ? 1 : 0, 'status' => $this->can('hotel_front_desk', 'status') ? 1 : 0)));
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
        if ($expected !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected)) { return $this->jsonFail('date'); }
        if (!in_array($room->housekeeping_status, array('clean', 'inspected'), TRUE) && (int) $this->input->post('confirm_dirty') !== 1) {
            return $this->jsonFail('dirty', array('warn' => 1, 'housekeeping_status' => $room->housekeeping_status));
        }
        $user_id = (int) $this->session->userdata('user_id');
        $stay_id = $this->Hotel_model->insertStay(array(
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
        putAuditLog($user_id, 'Check-in: room ' . $room->number . ', ' . $guest . ' (stay ' . $stay_id . ')', 'Hotel Check-in', date('Y-m-d H:i:s'));
        $this->jsonOk(array('stay_id' => $stay_id));
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
        if (!$this->Hotel_model->closeStay($stay->id, $user_id)) { return $this->jsonFail('not_in_house'); }
        $this->Hotel_model->setRoomStatus($room->id, 'occupancy', 'vacant', $user_id, lang('hotel_log_checkout') . ' ' . $stay->guest_name);
        $this->Hotel_model->setRoomStatus($room->id, 'housekeeping', 'dirty', $user_id, lang('hotel_log_checkout'));
        $task_id = $this->Hotel_model->createTask($room->company_id, $room->outlet_id, $room->id, 'cleaning', $user_id, lang('hotel_task_after_checkout'));
        putAuditLog($user_id, 'Check-out: room ' . $room->number . ', ' . $stay->guest_name . ' (stay ' . $stay->id . ')', 'Hotel Check-out', date('Y-m-d H:i:s'));
        $this->jsonOk(array('stay_id' => (int) $stay->id, 'task_id' => $task_id));
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
            fputcsv($out, array('Outlet', 'Room', 'Guest', 'Phone', 'Adults', 'Children', 'Check-in', 'Expected check-out', 'Check-out', 'Status', 'Reference', 'Checked in by', 'Checked out by', 'Notes'));
            foreach ($stays as $s) {
                fputcsv($out, array($s->outlet_name, $s->room_number, $s->guest_name, $s->guest_phone, $s->adults, $s->children, $s->checkin_at, $s->expected_checkout, $s->checkout_at, $s->status, $s->reference, $s->in_by, $s->out_by, $s->notes));
            }
            fclose($out);
            return;
        }
        $data = array();
        $data['filters'] = $filters;
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
