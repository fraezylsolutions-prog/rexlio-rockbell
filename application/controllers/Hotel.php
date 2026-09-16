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
  # H0: the shell (module gate + landing). Screens follow in
  # H1 (rooms), H2 (front desk) and H3 (housekeeping board).
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Hotel extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Common_model');
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
        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);
    }

    /**
     * landing page of the add-on (H0: a placeholder; H2 turns it into the
     * Front Desk board). Permission rows arrive with H1; until then the module
     * switch alone decides.
     * @access public
     * @return void
     */
    public function index() {
        $data = array();
        $data['main_content'] = $this->load->view('hotel/index', $data, TRUE);
        $this->load->view('userHome', $data);
    }
}
