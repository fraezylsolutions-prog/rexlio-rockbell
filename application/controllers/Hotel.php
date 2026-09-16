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
  #   H2  Front Desk board, check-in / check-out, status log
  #   H3  Housekeeping board
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
}
