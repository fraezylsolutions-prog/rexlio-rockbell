<?php
/*
  ###########################################################
  # PRODUCT NAME: 	iRestora PLUS - Next Gen Restaurant POS
  ###########################################################
  # AUTHER:		Doorsoft
  ###########################################################
  # EMAIL:		info@doorsoft.co
  ###########################################################
  # COPYRIGHTS:		RESERVED BY Door Soft
  ###########################################################
  # WEBSITE:		http://www.doorsoft.co
  ###########################################################
  # This is Setting Controller
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');
require APPPATH.'third_party/endroid_qrcode/autoload.php';

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;


class Setting extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Authentication_model');
        $this->load->model('Common_model');
        $this->load->model('Outlet_model');
        $this->load->model('User_model');
        $this->load->model('Sale_model');
        $this->load->library('form_validation');
        $this->Common_model->setDefaultTimezone();
        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }
        //start check access function
        $segment_2 = $this->uri->segment(2);
        $segment_3 = $this->uri->segment(3);
        $controller = "6";
        $function = "";

        if($segment_2=="index" || $segment_2==""){
            $function = "update";
        }else if($segment_2=="tax"){
            $controller = "52";
            $function = "update_tax";
        }else if($segment_2=="selfOrderEnableDisable" || $segment_2=="tableQrcodeGenerator" || $segment_2=="orderReceivingUserSelfOrder"){
            $controller = "364";
            $function = "all_setting_sel_order";
        }else if($segment_2=="reservationEnableDisable" || $segment_2=="orderReceivingUserReservation"){
            $controller = "368";
            $function = "all_setting_reservation_order";
        }else if($segment_2=="onlineOrderEnableDisable" || $segment_2=="orderReceivingUserOnlineOrder" || $segment_2=="urlGenerator"){
            $controller = "368";
            $function = "all_setting_online_order";
        }else if($segment_2=="resetTransactionalData" || $segment_2=="resettransactionaldata"){
               $controller = "350";
            $function = "reset";
        }else if($segment_2=="modules"){
            //module switches (H0): own access row, looked up by name
            $controller = (string) irAccessModuleId('modules');
            $function = "update";
        }else if($segment_2=="notifications" || $segment_2=="notificationTest"){
            //Notifications (N4.5): own access row, looked up by name because the
            //id is assigned when migration 2026-10-29_01 runs rather than being
            //fixed in the product. 'view' gets you the screen; the write paths
            //check 'update' themselves and the provider keys check
            //'view_credentials' separately again.
            $controller = (string) irAccessModuleId('notifications');
            $function = "view";
        }else{
           $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }

        if(!checkAccess($controller,$function)){
            $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }
        //end check access function
    }

     /**
     * setting info
     * @access public
     * @return void
     * @param int
     */
    public function index($id = '') {
        $encrypted_id = $this->session->userdata('company_id');;
        $company_id = $this->session->userdata('company_id');
        $language_manifesto = $this->session->userdata('language_manifesto');
        $id = $this->custom->encrypt_decrypt($id, 'decrypt');

        if (htmlspecialcharscustom($this->input->post('submit'))) {
            if($id==1):
                $this->form_validation->set_rules('business_name', lang('business_name'), 'required|max_length[50]');
                $this->form_validation->set_rules('short_name', lang('short_name'), 'required|max_length[2]');
            endif;

            $this->form_validation->set_rules('invoice_logo', lang('invoice_logo'), 'callback_validate_invoice_logo|max_length[500]');
            $this->form_validation->set_rules('date_format', lang('date_format'), 'required|max_length[250]');
            $this->form_validation->set_rules('zone_name', lang('Time_Zone'), 'required|max_length[250]');
            $this->form_validation->set_rules('currency', lang('currency'), 'required|max_length[250]');
            $this->form_validation->set_rules('invoice_footer', lang('invoice_footer'), 'max_length[250]');
            if(str_rot13($language_manifesto)!="eriutoeri"):
                $this->form_validation->set_rules('default_waiter', lang('Default_Waiter'), 'max_length[11]');
            endif;
            $this->form_validation->set_rules('default_customer', lang('Default_Customer'), 'required|max_length[11]');
            $this->form_validation->set_rules('default_payment', lang('Default_Payment_Method'), 'required|max_length[11]');

            $is_loyalty_enable = htmlspecialcharscustom($this->input->post($this->security->xss_clean('is_loyalty_enable')));
            if($is_loyalty_enable=="enable"){
                $this->form_validation->set_rules('minimum_point_to_redeem', lang('minimum_point_to_redeem'), 'required|max_length[11]');
                $this->form_validation->set_rules('loyalty_rate', lang('loyalty_rate'), 'required|max_length[11]');
            }

            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                if($id==1):
                    $outlet_info['business_name'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('business_name')));
                    $outlet_info['short_name'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('short_name')));
                endif;
                if ($_FILES['invoice_logo']['name'] != "") {
                    $outlet_info['invoice_logo'] = $this->session->userdata('invoice_logo');
                    $this->session->unset_userdata('invoice_logo');
                }else{
                    $outlet_info['invoice_logo'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('invoice_logo_p')));
                }
                $outlet_info['website'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('website')));
                $outlet_info['date_format'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('date_format')));
                $outlet_info['zone_name'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('zone_name')));
                $outlet_info['currency'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('currency')));
                $outlet_info['currency_position'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('currency_position')));
                $outlet_info['precision'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('precision')));
                if(isServiceAccessOnly('sGmsJaFJE')):
                    $outlet_info['saas_landing_page'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('saas_landing_page')));
                endif;
                if(str_rot13($language_manifesto)!="eriutoeri"):
                    $outlet_info['default_waiter'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('default_waiter')));
                endif;

                $outlet_info['default_customer'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('default_customer')));
                $outlet_info['default_payment'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('default_payment')));
                $outlet_info['invoice_footer'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('invoice_footer')));

                $outlet_info['service_amount'] = htmlspecialcharscustom($this->input->post('service_amount'));
                $outlet_info['delivery_amount'] = htmlspecialcharscustom($this->input->post('delivery_amount'));
                $outlet_info['decimals_separator'] = htmlspecialcharscustom($this->input->post('decimals_separator'));
                $outlet_info['thousands_separator'] = htmlspecialcharscustom($this->input->post('thousands_separator'));
                $outlet_info['default_order_type_delivery_p'] = htmlspecialcharscustom($this->input->post('default_order_type_delivery_p'));
                $outlet_info['when_clicking_on_item_in_pos'] = htmlspecialcharscustom($this->input->post('when_clicking_on_item_in_pos'));
                $outlet_info['is_rounding_enable'] = htmlspecialcharscustom($this->input->post('is_rounding_enable'));
                $outlet_info['default_order_type'] = htmlspecialcharscustom($this->input->post('default_order_type'));
                $outlet_info['is_loyalty_enable'] = htmlspecialcharscustom($this->input->post('is_loyalty_enable'));
                //Phase F item 17: global oversale default. Anything other than an
                //explicit 'No' is treated as 'Yes', so a form that omits the field
                //can never silently switch blocking on.
                $ir_allow_oversale = $this->input->post($this->security->xss_clean('allow_oversale'));
                $outlet_info['allow_oversale'] = (strcasecmp(trim((string) $ir_allow_oversale), 'No') == 0) ? 'No' : 'Yes';
                $outlet_info['pre_or_post_payment'] = htmlspecialcharscustom($this->input->post('pre_or_post_payment'));
                $outlet_info['minimum_point_to_redeem'] = htmlspecialcharscustom($this->input->post('minimum_point_to_redeem'));
                $outlet_info['loyalty_rate'] = htmlspecialcharscustom($this->input->post('loyalty_rate'));
                $outlet_info['split_bill'] = htmlspecialcharscustom($this->input->post('split_bill'));
                $outlet_info['place_order_tooltip'] = htmlspecialcharscustom($this->input->post('place_order_tooltip'));
                $outlet_info['food_menu_tooltip'] = htmlspecialcharscustom($this->input->post('food_menu_tooltip'));
                $outlet_info['sms_send_auto'] = htmlspecialcharscustom($this->input->post('sms_send_auto'));
                $outlet_info['active_login_button'] = htmlspecialcharscustom($this->input->post('active_login_button'));
                $outlet_info['login_type'] = htmlspecialcharscustom($this->input->post('login_type'));


                if(!isServiceAccessOnlyLogin('sGmsJaFJE')):
                    $outlet_info['export_daily_sale'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('export_daily_sale')));
                    endif;

                if ($id == "") {
                    $outlet_info['starting_date'] = date("Y-m-d");
                    $outlet_info['user_id'] = $this->session->userdata('user_id');
                    $outlet_info['company_id'] = $this->session->userdata('company_id');
                    $outlet_info['outlet_code'] = $this->Outlet_model->generateOutletCode();
                }
                if ($id == "") {
                    $this->Common_model->insertInformation($outlet_info, "tbl_companies");
                    $this->session->set_flashdata('exception', lang('insertion_success'));
                } else {
                    $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");
                    $this->session->set_flashdata('exception', lang('update_success'));
                }
                $this->session->set_userdata($outlet_info);
                redirect('setting');
            } else {
                $id = $this->session->userdata("company_id");
                $data = array();
                $data['encrypted_id'] = $encrypted_id;
                $data['outlet_information'] = $this->Common_model->getDataById($id, "tbl_companies");
                $data['zone_names'] = $this->Common_model->getAllForDropdown("tbl_time_zone");
                $data['customers'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_customers");
                $data['printers'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_printers");
                if(str_rot13($language_manifesto)!="eriutoeri"):
                    $data['waiters'] = $this->Sale_model->getWaitersForThisCompany($company_id,'tbl_users');
                endif;
                $data['paymentMethods'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_payment_methods");
                $data['main_content'] = $this->load->view('authentication/setting', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        } else {
            $id = $this->session->userdata("company_id");
            $data = array();
            $data['encrypted_id'] = $encrypted_id;
            $data['outlet_information'] = $this->Common_model->getDataById($id, "tbl_companies");
            $data['zone_names'] = $this->Common_model->getAllForDropdown("tbl_time_zone");
            $data['customers'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_customers");
            $data['printers'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_printers");
            if(str_rot13($language_manifesto)!="eriutoeri"):
                $data['waiters'] = $this->Sale_model->getWaitersForThisCompany($company_id,'tbl_users');
            endif;
            $data['deliveryPartners'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_delivery_partners");
            $data['paymentMethods'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_payment_methods");
            $data['main_content'] = $this->load->view('authentication/setting', $data, TRUE);
            $this->load->view('userHome', $data);
        }

    }
       
     /**
     * validate invoice logo
     * @access public
     * @return string
     * @param boolean
     */
    public function validate_invoice_logo() {

        if ($_FILES['invoice_logo']['name'] != "") {
            $config['upload_path'] = './images';
            $config['allowed_types'] = 'jpg|jpeg|jfif|png';
            $config['max_size'] = '1000';
            $config['encrypt_name'] = TRUE;
            $config['detect_mime'] = TRUE;
            $this->load->library('upload', $config);
            if ($this->upload->do_upload("invoice_logo")) {
                $upload_info = $this->upload->data();
                $file_name = $upload_info['file_name'];
                $config['image_library'] = 'gd2';
                $config['source_image'] = './images/' . $file_name;
                $config['maintain_ratio'] = TRUE;
                $config['width'] = 100;
                $config['height'] = 100;
                $this->load->library('image_lib', $config);
                $this->image_lib->resize();
                $this->session->set_userdata('invoice_logo', $file_name);

            } else {
                $this->form_validation->set_message('validate_invoice_logo', $this->upload->display_errors());
                return FALSE;
            }
        }
        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);
    }
     /**
     * smtp Email Setting
     * @access public
     * @return void
     * @param int
     */
    public function smtpEmailSetting($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {
                $this->form_validation->set_rules('host_name', "Host Name", "required|max_length[300]");
                $this->form_validation->set_rules('port_address', "Port Address", "required|max_length[300]");
                $this->form_validation->set_rules('user_name', "Username", "required|max_length[300]");
                $this->form_validation->set_rules('password', "Password", "required|max_length[300]");

            if ($this->form_validation->run() == TRUE) {

                $data['host_name'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('host_name')));
                $data['port_address'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('port_address')));
                $data['user_name'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('user_name')));
                $data['password'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('password')));
                $data_json['smtp_enable_status'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('enable_status')));
                $data_json['smtp_details'] = json_encode($data);

                $this->Common_model->updateInformation($data_json, $id, "tbl_companies");
                $this->session->set_flashdata('exception', lang('update_success'));
                redirect('setting/smtpEmailSetting');
            }else{
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/smtpEmailSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/smtpEmailSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    /**
     * resetTransactional Data
     * @access public
     * @return void
     * @param no
     */
    public function resetTransactionalData() {
        //truncate all transactional data
        $this->db->query("TRUNCATE tbl_sales");
        $this->db->query("TRUNCATE tbl_sales_details");
        $this->db->query("TRUNCATE tbl_sales_details_modifiers");
        $this->db->query("TRUNCATE tbl_sale_consumptions");
        $this->db->query("TRUNCATE tbl_sale_consumptions_of_menus");
        $this->db->query("TRUNCATE tbl_sale_consumptions_of_modifiers_of_menus");
        $this->db->query("TRUNCATE tbl_purchase");
        $this->db->query("TRUNCATE tbl_purchase_ingredients");
        $this->db->query("TRUNCATE tbl_holds");
        $this->db->query("TRUNCATE tbl_holds_details");
        $this->db->query("TRUNCATE tbl_holds_details_modifiers");
        $this->db->query("TRUNCATE tbl_holds_table");
        $this->db->query("TRUNCATE tbl_expenses");
        $this->db->query("TRUNCATE tbl_expense_items");
        $this->db->query("TRUNCATE tbl_supplier_payments");
        $this->db->query("TRUNCATE tbl_customer_due_receives");
        $this->session->set_flashdata('exception', lang('truncate_update_success'));
        redirect('setting/index');
    }
     /**
     * tax
     * @access public
     * @return void
     * @param int
     */
    public function tax($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('collect_tax', 'Collect Tax', 'required|max_length[10]');
            if ($this->input->post('collect_tax') == "Yes") {
                $this->form_validation->set_rules('tax_title', 'Tax Title', 'required|max_length[50]');
                $this->form_validation->set_rules('tax_registration_no', 'Tax Registration No', 'required|max_length[50]');
                $this->form_validation->set_rules('tax_is_gst', 'Tax is GST', 'required|max_length[50]');
                $this->form_validation->set_rules('taxes[]', 'Taxes', 'required|max_length[10]');
            }

            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                $outlet_info['collect_tax'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('collect_tax')));
                if ($this->input->post('collect_tax') == "Yes") {
                    $outlet_info['tax_title'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('collect_tax')));
                    $outlet_info['tax_registration_no'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('collect_tax')));
                    $outlet_info['tax_is_gst'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('collect_tax')));
                }
                $outlet_info['apply_on_delivery_charge'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('apply_on_delivery_charge')));
                $outlet_info['tax_type'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('tax_type')));
                $outlet_info['tax_title'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('tax_title')));
                $outlet_info['tax_registration_no'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('tax_registration_no')));
                $outlet_info['tax_is_gst'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('tax_is_gst')));
                $this->session->set_userdata($outlet_info);

                $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");

                if(!empty($_POST['taxes'])){
                    $this->saveOutletTaxes($_POST['taxes'], $id, 'tbl_outlet_taxes');
                }
                $this->session->set_flashdata('exception', 'Information has been updated successfully!');

                redirect('setting/tax');
            } else {
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/tax', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/tax', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    public function onlineOrderEnableDisable($id = '') {
        //start check access function
        $segment_2 = $this->uri->segment(2);
        $segment_3 = $this->uri->segment(3);
        $controller = "6";
        $function = "";

        if($segment_2=="index" || $segment_2==""){
            $function = "update";
        }else if($segment_2=="tax"){
            $controller = "52";
            $function = "update_tax";
        }else if($segment_2=="onlineOrderEnableDisable" || $segment_2=="onlineOrder" || $segment_2=="onlineOrderEnableDisable" || $segment_2=="orderReceivingUserReservation" || $segment_2=="tableQrcodeGenerator"  || $segment_2=="orderReceivingUserSelfOrder" || $segment_2=="orderReceivingUserOnlineOrder" || $segment_2=="urlGenerator" || $segment_2=="downloadQRcode"){
            $controller = "64";
            $function = "update";
        }else if($segment_2=="resetTransactionalData" || $segment_2=="resettransactionaldata"){
               $controller = "350";
            $function = "reset";
        }else{
           $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }

        if(!checkAccess($controller,$function)){
            $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }
        //end check access function

        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('sos_enable_online_order_frontend_website', lang('sos_enable_online_order_frontend_website'), 'required|max_length[10]');
            
            $this->form_validation->set_rules('sos_enable_online_order', lang('sos_enable_online_order'), 'required|max_length[10]');
            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                $outlet_info['sos_enable_online_order_frontend_website'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('sos_enable_online_order_frontend_website')));
                $outlet_info['sos_enable_online_order'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('sos_enable_online_order')));
                $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");
                $this->session->set_flashdata('exception', 'Information has been updated successfully!');
                redirect('setting/onlineOrderEnableDisable');
            } else {
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/onlineOrderSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/onlineOrderSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    public function reservationEnableDisable($id = '') {
        //start check access function
        $segment_2 = $this->uri->segment(2);
        $segment_3 = $this->uri->segment(3);
        $controller = "6";
        $function = "";

        if($segment_2=="index" || $segment_2==""){
            $function = "update";
        }else if($segment_2=="tax"){
            $controller = "52";
            $function = "update_tax";
        }else if($segment_2=="reservationEnableDisable" || $segment_2=="onlineOrder" || $segment_2=="onlineOrderEnableDisable" || $segment_2=="orderReceivingUserReservation" || $segment_2=="tableQrcodeGenerator"  || $segment_2=="orderReceivingUserSelfOrder" || $segment_2=="orderReceivingUserOnlineOrder" || $segment_2=="urlGenerator" || $segment_2=="downloadQRcode"){
            $controller = "64";
            $function = "update";
        }else if($segment_2=="resetTransactionalData" || $segment_2=="resettransactionaldata"){
               $controller = "350";
            $function = "reset";
        }else{
           $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }

        if(!checkAccess($controller,$function)){
            $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }
        //end check access function
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('sos_enable_reservation', lang('sos_enable_reservation'), 'required|max_length[10]');
            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                $start_time = $this->input->post($this->security->xss_clean('start_time'));
                $end_time = $this->input->post($this->security->xss_clean('end_time'));
                $text_header = $this->input->post($this->security->xss_clean('text_header'));
                $main_array = array();
                $i =1;
                foreach ($start_time as $key=>$value){
                    $end_time1 = explode(":",$end_time[$key]);
                    $end_time2 = explode(" ",$end_time[$key]);
                    if($end_time1[0]==12 && $end_time2[1]=="am"){
                        $end_time_int = 24;
                    }else{
                        $end_time_int = date("G:i", strtotime($end_time[$key]));
                    }
                    $status_name = "status_".$i;
                    $tmp_array = array();
                    $tmp_array['counter'] = getCounter($text_header[$key]);
                    $tmp_array['status'] = isset($_POST[$status_name]) && $_POST[$status_name]?$_POST[$status_name]:'';
                    $tmp_array['counter_name'] = ($text_header[$key]);
                    $tmp_array['start_time'] = $value;
                    $tmp_array['end_time'] = $end_time[$key];
                    $tmp_array['start_time_int'] = date("G:i", strtotime($value));
                    $tmp_array['end_time_int'] = $end_time_int;
                    $main_array[] = $tmp_array;
                    $i++;
                }
    
                $outlet_info = array(); 
                $outlet_info['reservation_times'] = json_encode($main_array);
                $outlet_info['sos_enable_reservation'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('sos_enable_reservation')));
                
                $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");
                $this->session->set_flashdata('exception', 'Information has been updated successfully!');
                redirect('setting/reservationEnableDisable');
            } else {
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/reservationOrderSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/reservationOrderSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
 
    public function selfOrderEnableDisable($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('sos_enable_self_order', lang('sos_enable_self_order'), 'required|max_length[10]');
            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                $outlet_info['sos_enable_self_order'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('sos_enable_self_order')));
                $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");
                $this->session->set_flashdata('exception', 'Information has been updated successfully!');

                redirect('setting/selfOrderEnableDisable');
            } else {
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/selfOrderSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/selfOrderSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    public function tableQrcodeGenerator($id = '') {
        
        $outlet_id_post = htmlspecialcharscustom($this->input->post($this->security->xss_clean('outlet_id')));
        $outlet_id = isset($outlet_id_post) && $outlet_id_post?$outlet_id_post:$this->session->userdata('outlet_id');
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            if(isLMni()):
                $this->form_validation->set_rules('outlet_id', lang('outlet'), 'required|max_length[10]');
            endif;

            if ($this->form_validation->run() == TRUE) {
                $data = array();
                if($outlet_id){
                    $tables = $this->Common_model->getAllByCustomId($outlet_id,"outlet_id","tbl_tables",$order='');
                    $company_id = $this->session->userdata("company_id");
                    foreach ($tables as $value){
                        $url_qrcode = base_url().'self-order/'.$value->id.'/'.$outlet_id.'/'.$company_id.'/Yes/';
                        $save_path = "qr_code/";
                        $file_name = $save_path."table-qrcode-".$value->id.".png";
                        $footer_text = "Table: ".$value->name;
                        $this->create_qr($url_qrcode,$file_name,$footer_text);
                    }
                    $data['tables'] = $tables;
                }
                $data['main_content'] = $this->load->view('authentication/generate_qrcode', $data, TRUE);
                $this->load->view('userHome', $data);

            } else {

                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/generate_qrcode', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            if($outlet_id){
                $tables = $this->Common_model->getAllByCustomId($outlet_id,"outlet_id","tbl_tables",$order='');
                $company_id = $this->session->userdata("company_id");
                foreach ($tables as $value){
                    $url_qrcode = base_url().'self-order/'.$value->id.'/'.$outlet_id.'/'.$company_id.'/Yes/';
                    $save_path = "qr_code/";
                    $file_name = $save_path."table-qrcode-".$value->id.".png";
                    $footer_text = "Table: ".$value->name;
                    $this->create_qr($url_qrcode,$file_name,$footer_text);
                }
                $data['tables'] = $tables;
            }
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/generate_qrcode', $data, TRUE);
            $this->load->view('userHome', $data);

        }
    }
    public function orderReceivingUserSelfOrder() {
        $company_id = $this->session->userdata("company_id");
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            //due to array data, we skip the escape
            $outlets = ($this->input->post($this->security->xss_clean('outlet_id')));
            $online_self_order_receiving_id = ($this->input->post($this->security->xss_clean('online_self_order_receiving_id')));
            foreach($outlets as $key=>$value){
                        $data_food_menus = array();
                        $data_food_menus['online_self_order_receiving_id'] = $online_self_order_receiving_id[$key];
                        $this->Common_model->updateInformation($data_food_menus, $value, "tbl_outlets");
            }
            
            $this->session->set_flashdata('exception', 'Information has been updated successfully!');
            redirect('setting/orderReceivingUserSelfOrder');

        } else {
            $data = array();
            $data['waiters'] = $this->User_model->getUsersByCompanyId($company_id, "tbl_users");
            $data['main_content'] = $this->load->view('authentication/orderReceivingUserSelfOrder', $data, TRUE);
            $this->load->view('userHome', $data);

        }
    }
    public function orderReceivingUserOnlineOrder() {
        $company_id = $this->session->userdata("company_id");
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            //due to array data, we skip the escape
            $outlets = ($this->input->post($this->security->xss_clean('outlet_id')));
            $online_order_receiving_id = ($this->input->post($this->security->xss_clean('online_order_receiving_id')));
            foreach($outlets as $key=>$value){
                        $data_food_menus = array();
                        $data_food_menus['online_order_receiving_id'] = $online_order_receiving_id[$key];
                        $this->Common_model->updateInformation($data_food_menus, $value, "tbl_outlets");
            }
            
            $this->session->set_flashdata('exception', 'Information has been updated successfully!');
            redirect('setting/orderReceivingUserOnlineOrder');

        } else {
            $data = array();
            $data['waiters'] = $this->Sale_model->getWaitersForThisCompanyForOutlet($company_id,'tbl_users');
            $data['main_content'] = $this->load->view('authentication/orderReceivingUserOnlineOrder', $data, TRUE);
            $this->load->view('userHome', $data);

        }
    }
    public function orderReceivingUserReservation() {
        $company_id = $this->session->userdata("company_id");
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            //due to array data, we skip the escape
            $outlets = ($this->input->post($this->security->xss_clean('outlet_id')));
            $reservation_order_receiving_id = ($this->input->post($this->security->xss_clean('reservation_order_receiving_id')));
            foreach($outlets as $key=>$value){
                        $data_food_menus = array();
                        $data_food_menus['reservation_order_receiving_id'] = $reservation_order_receiving_id[$key];
                        $this->Common_model->updateInformation($data_food_menus, $value, "tbl_outlets");
            }
            
            $this->session->set_flashdata('exception', 'Information has been updated successfully!');
            redirect('setting/orderReceivingUserReservation');

        } else {
            $data = array();
            $data['waiters'] = $this->User_model->getUsersByCompanyId($company_id, "tbl_users");
            $data['main_content'] = $this->load->view('authentication/orderReceivingUserReservation', $data, TRUE);
            $this->load->view('userHome', $data);

        }
    }
    public function onlineOrder($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('sos_enable_online_order', lang('sos_enable_online_order'), 'required|max_length[10]');
            if ($this->form_validation->run() == TRUE) {
                $outlet_info = array();
                $outlet_info['sos_enable_online_order'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('sos_enable_online_order')));
                $this->Common_model->updateInformation($outlet_info, $id, "tbl_companies");
                $this->session->set_flashdata('exception', 'Information has been updated successfully!');

                redirect('setting/onlineOrder');
            } else {
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/onlineOrderSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/onlineOrderSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    public function urlGenerator($id = '') {
        $outlet_id_post = htmlspecialcharscustom($this->input->post($this->security->xss_clean('outlet_id')));
        $outlet_id = isset($outlet_id_post) && $outlet_id_post?$outlet_id_post:$this->session->userdata('outlet_id');
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            if(isLMni()):
                $this->form_validation->set_rules('outlet_id', lang('outlet'), 'required|max_length[10]');
            endif;

            if ($this->form_validation->run() == TRUE) {
                $data = array();
                if($outlet_id){
                    $tables = $this->Common_model->getAllByCustomId($outlet_id,"outlet_id","tbl_tables",$order='');
                    $company_id = $this->session->userdata("company_id");
                    foreach ($tables as $value){
                        $url_qrcode = base_url().'self-order/'.$value->id.'/'.$outlet_id.'/'.$company_id.'/Yes/';
                        $save_path = "qr_code/";
                        $file_name = $save_path."table-qrcode-".$value->id.".png";
                        $footer_text = "Table: ".$value->name;
                        $this->create_qr($url_qrcode,$file_name,$footer_text);
                    }
                    $data['tables'] = $tables;
                }
                $data['main_content'] = $this->load->view('authentication/generate_url', $data, TRUE);
                $this->load->view('userHome', $data);

            } else {

                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/generate_url', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            if($outlet_id){
                $tables = $this->Common_model->getAllByCustomId($outlet_id,"outlet_id","tbl_tables",$order='');
                $company_id = $this->session->userdata("company_id");
                foreach ($tables as $value){
                    $url_qrcode = base_url().'self-order/'.$value->id.'/'.$outlet_id.'/'.$company_id.'/Yes/';
                    $save_path = "qr_code/";
                    $file_name = $save_path."table-qrcode-".$value->id.".png";
                    $footer_text = "Table: ".$value->name;
                    $this->create_qr($url_qrcode,$file_name,$footer_text);
                }
                $data['tables'] = $tables;
            }
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/generate_url', $data, TRUE);
            $this->load->view('userHome', $data);

        }
    }
    public function downloadQRcode($file = "") {
        // load ci download helder
        $this->load->helper('download');
        $data = file_get_contents("qr_code/" . $file); // Read the file's
        $name = $file;
        force_download($name, $data);
    }
    function create_qr($datanya,$filename,$footer_text){
        // Create a basic QR code
        $data = $datanya;
        $qrCode = new QrCode($data);
        $qrCode->setSize(230);

        // Set advanced options
        $qrCode->setWriterByName('png');
        $qrCode->setMargin(15);
        $qrCode->setEncoding('UTF-8');
        $qrCode->setLabel($footer_text,'18');
        $qrCode->setErrorCorrectionLevel(ErrorCorrectionLevel::HIGH);
        $qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
        $qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);
        $qrCode->setValidateResult(false);
        $qrCode->writeFile($filename);
        return $filename;
    }
     /**
     * save Outlet Taxes
     * @access public
     * @return void
     * @param string
     * @param int
     * @param string
     */
    public function saveOutletTaxes($outlet_taxes, $company_id, $table_name)
    {
        $main_arr = array();
        $tax_string ='';
        foreach($outlet_taxes as $key=>$single_tax){
            $oti = array();
            if(isset($_POST['p_tax_id'][$key]) && $_POST['p_tax_id'][$key]){
                $oti['id'] = $_POST['p_tax_id'][$key];
            }else{
                $oti['id'] = 1;
            }
            $oti['tax'] = $single_tax;
            $oti['tax_rate'] = $_POST['tax_rate'][$key];
            $main_arr[] = $oti;
            $tax_string.=$single_tax.":";
        }
        $data['tax_setting'] = json_encode($main_arr);
        $data['tax_string'] = $tax_string;
        $this->Common_model->updateInformation($data, $company_id, "tbl_companies");
    }
     /**
     * whats app Setting
     * @access public
     * @return void
     * @param int
     */
    public function whatsappSetting($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {
                $this->form_validation->set_rules('whatsapp_share_number', "Whatsapp Share Number", "required|max_length[300]");
            if ($this->form_validation->run() == TRUE) {
                $data['whatsapp_share_number'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('whatsapp_share_number')));
                $this->Common_model->updateInformation($data, $id, "tbl_companies");
                $this->session->set_flashdata('exception', lang('update_success'));
                redirect('setting/whatsappSetting');
            }else{
                $data = array();
                $data['company'] = getCompanyInfo();
                $data['main_content'] = $this->load->view('authentication/whatsappSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $data['main_content'] = $this->load->view('authentication/whatsappSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
     /**
     * sms Setting
     * @access public
     * @return void
     * @param int
     */
    public function smsSetting($id = '') {
        if (htmlspecialcharscustom($this->input->post('submit'))) {

            $enable_status  =htmlspecialcharscustom($this->input->post($this->security->xss_clean('enable_status')));
            $this->form_validation->set_rules('enable_status', "Enable Status", "max_length[50]");
            if($enable_status==1){
                $this->form_validation->set_rules('f_1_1', "User Name", "required|max_length[300]");
                $this->form_validation->set_rules('f_1_2', "Password", "required|max_length[300]");
            }else if($enable_status==2){
                $this->form_validation->set_rules('f_2_1', "User Name", "required|max_length[300]");
                $this->form_validation->set_rules('f_2_2', "Password", "required|max_length[300]");
            }else if($enable_status==3){
                $this->form_validation->set_rules('f_3_1', "User Name", "required|max_length[300]");
                $this->form_validation->set_rules('f_3_2', "Password", "required|max_length[300]");
                $this->form_validation->set_rules('f_3_3', "API Key", "required");
            }else if($enable_status==4){
                $this->form_validation->set_rules('f_4_1', "SID", "required|max_length[300]");
                $this->form_validation->set_rules('f_4_2', "Token", "required");
                $this->form_validation->set_rules('f_4_3', "Twilio Number", "required|max_length[300]");
            }else if($enable_status==5){
                $this->form_validation->set_rules('f_5_1', "API Key", "required|max_length[300]");
                $this->form_validation->set_rules('f_5_2', "API Secret Key", "required|max_length[300]");
            }

            if ($this->form_validation->run() == TRUE) {
                $data = array();
                $data['sms_enable_status'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('enable_status')));
                $data_json['f_1_1'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_1_1')));
                $data_json['f_1_2'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_1_2')));
                $data_json['f_2_1'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_2_1')));
                $data_json['f_2_2'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_2_2')));
                $data_json['f_3_1'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_3_1')));
                $data_json['f_3_2'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_3_2')));
                $data_json['f_3_3'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_3_3')));
                $data_json['f_4_1'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_4_1')));
                $data_json['f_4_2'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_4_2')));
                $data_json['f_4_3'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_4_3')));
                $data_json['f_5_1'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_5_1')));
                $data_json['f_5_2'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('f_5_2')));
                $data['sms_details'] = json_encode($data_json);
                $this->Common_model->updateInformation($data, $id, "tbl_companies");
                redirect('setting/smsSetting');
            }else{
                $data = array();
                $data['company'] = getCompanyInfo();
                $company_id = $this->session->userdata('company_id');
                $data['users'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, "tbl_users");
                $data['main_content'] = $this->load->view('authentication/smsSetting', $data, TRUE);
                $this->load->view('userHome', $data);
            }

        } else {
            $data = array();
            $data['company'] = getCompanyInfo();
            $company_id = $this->session->userdata('company_id');
            $data['users'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, "tbl_users");
            $data['main_content'] = $this->load->view('authentication/smsSetting', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }

    /**
     * Settings > Modules (H0 of the Hotel Operations add-on). One switch per
     * add-on in irModuleRegistry(). The switch is business-wide and read per
     * request (irModuleEnabled), so it takes effect on the next request with no
     * re-login and no deploy. Gated by its own tbl_access row ('modules',
     * function 'update'), granted to Admin by default; the row's id is looked up
     * by name because migrations do not fix ids.
     * @access public
     * @return void
     */
    public function modules() {
        $module_access_id = irAccessModuleId('modules');
        if(!$module_access_id || !checkAccess((string) $module_access_id, "update")){
            $this->session->set_flashdata('exception_er', lang('menu_not_permit_access'));
            redirect('Authentication/userProfile');
        }
        if(htmlspecialcharscustom($this->input->post('submit')) == 'toggle'){
            $key = htmlspecialcharscustom($this->input->post($this->security->xss_clean('module_key')));
            $enabled = htmlspecialcharscustom($this->input->post($this->security->xss_clean('enabled'))) === '1';
            $reg = irModuleRegistry();
            if(irModuleSet($key, $enabled, (int) $this->session->userdata('user_id'))){
                $this->session->set_flashdata('exception', lang('module_updated'));
            }elseif($enabled && isset($reg[$key]) && irModuleSchemaMissing($key)){
                //H4: half-installed (tbl_modules row there, module tables not) - name the migration
                $this->session->set_flashdata('exception_1', lang('module_schema_missing') . ' ' . $reg[$key]['migration'] . ' (' . implode(', ', irModuleSchemaMissing($key)) . ')');
            }else{
                $this->session->set_flashdata('exception_1', lang('module_not_installed'));
            }
            redirect('setting/modules');
        }
        $data = array();
        $data['registry'] = irModuleRegistry();
        $data['rows'] = irModuleRows();
        $users = array();
        foreach($data['rows'] as $row){
            if($row->updated_by && !isset($users[$row->updated_by])){
                $users[$row->updated_by] = userName($row->updated_by);
            }
        }
        $data['user_names'] = $users;
        $data['main_content'] = $this->load->view('setting/modules', $data, TRUE);
        $this->load->view('userHome', $data);
    }


    /**
     * Settings > Notifications (N4.5): the screen that makes the module usable.
     *
     * Until this existed nothing could be turned on without hand-editing JSON in
     * tbl_modules. It carries the master switch, the per-channel switches, test
     * mode, each channel's provider settings, the rules that decide who hears
     * about what, and the last 25 rows of the outbox so "was it even queued?"
     * has an answer on the same page.
     *
     * Three permissions, deliberately separate:
     *   view              see the screen
     *   update            change anything
     *   view_credentials  read a provider key in the clear rather than masked
     * Deciding which events fire is a different act from reading an API key, so
     * a manager who needs the first does not get the second. The access row id
     * is looked up by name because migrations do not fix ids.
     * @access public
     * @return void
     */
    public function notifications() {
        $access_id = (string) irAccessModuleId('notifications');
        if(!$access_id){
            //no access row means migration 2026-10-29_01 has not been applied here
            $this->session->set_flashdata('exception_er', lang('notify_not_installed'));
            redirect('Authentication/userProfile');
        }
        $can_update = checkAccess($access_id, "update");
        $can_see_keys = checkAccess($access_id, "view_credentials");
        $me = (int) $this->session->userdata('user_id');
        $action = htmlspecialcharscustom($this->input->post($this->security->xss_clean('ir_action')));

        if($action != ''){
            if(!$can_update){
                $this->session->set_flashdata('exception_1', lang('menu_not_permit_access'));
                redirect('setting/notifications');
            }

            if($action == 'toggle'){
                $key = htmlspecialcharscustom($this->input->post($this->security->xss_clean('module_key')));
                $enabled = htmlspecialcharscustom($this->input->post($this->security->xss_clean('enabled'))) === '1';
                //only this module's own keys: this form must not be able to flip
                //the hotel add-on, which has its own screen and its own permission
                if(strpos($key, 'notif') !== 0){
                    $this->session->set_flashdata('exception_1', lang('menu_not_permit_access'));
                    redirect('setting/notifications');
                }
                if(irModuleSet($key, $enabled, $me)){
                    $this->session->set_flashdata('exception', lang('module_updated'));
                }else{
                    $this->session->set_flashdata('exception_1', lang('module_not_installed'));
                }
                redirect('setting/notifications');
            }

            if($action == 'general'){
                $settings = irNotifySettings();
                $settings['test_mode'] = $this->input->post('test_mode') ? 1 : 0;
                $settings['test_recipient'] = trim(htmlspecialcharscustom($this->input->post($this->security->xss_clean('test_recipient'))));
                $attempts = (int) $this->input->post($this->security->xss_clean('max_attempts'));
                $settings['max_attempts'] = ($attempts >= 1 && $attempts <= 10) ? $attempts : 3;
                $hold = (int) $this->input->post($this->security->xss_clean('hold_expiry_hours'));
                $settings['hold_expiry_hours'] = ($hold >= 1 && $hold <= 720) ? $hold : 48;
                $batch = (int) $this->input->post($this->security->xss_clean('batch_size'));
                $settings['batch_size'] = ($batch >= 1 && $batch <= 500) ? $batch : 50;
                $this->irSaveModuleSettings('notifications', $settings);
                $this->session->set_flashdata('exception', lang('notify_saved'));
                redirect('setting/notifications');
            }

            if($action == 'sms'){
                $cfg = irNotifyChannelSettings('sms');
                $provider = htmlspecialcharscustom($this->input->post($this->security->xss_clean('provider')));
                $cfg['provider'] = in_array($provider, array('termii', 'legacy'), TRUE) ? $provider : '';
                $cfg['sender_id'] = trim(htmlspecialcharscustom($this->input->post($this->security->xss_clean('sender_id'))));
                $cfg['route'] = (htmlspecialcharscustom($this->input->post($this->security->xss_clean('route'))) == 'generic') ? 'generic' : 'dnd';
                $code = preg_replace('/\D+/', '', (string) $this->input->post($this->security->xss_clean('country_code')));
                $cfg['country_code'] = ($code != '') ? $code : '234';
                //the key is NOT read straight from the form: see irNotifyKeepSecret,
                //which is what stops a masked value overwriting a working key
                $cfg['api_key'] = irNotifyKeepSecret(
                    (string) $this->input->post('api_key'),
                    isset($cfg['api_key']) ? $cfg['api_key'] : '',
                    (bool) $this->input->post('clear_api_key')
                );
                $this->irSaveModuleSettings('notify_sms', $cfg);
                $this->session->set_flashdata('exception', lang('notify_saved'));
                redirect('setting/notifications');
            }

            if($action == 'rules'){
                /* The whole grid saves at once, from ONE form. Per-row forms were
                   the obvious design and are not possible: a <form> is not valid
                   inside a <tr>, so the parser hoists it out of the table and the
                   inputs end up submitting nothing. One form also means a venue
                   setting up six channels for an event presses Save once.
                   Unticked boxes do not post at all, so the matrix is walked from
                   the event list rather than from what arrived. */
                $events = irNotifyEvents();
                $channels = $this->irNotifyChannels();
                $recipients_in = $this->input->post('rec');
                $enabled_in = $this->input->post('en');
                $critical_in = $this->input->post('cr');
                if(!is_array($recipients_in)){ $recipients_in = array(); }
                if(!is_array($enabled_in)){ $enabled_in = array(); }
                if(!is_array($critical_in)){ $critical_in = array(); }
                $company_id = (int) $this->session->userdata('company_id');
                $saved = 0;

                foreach($events as $event_key => $event){
                    foreach($channels as $channel){
                        /* ':' and not '~': CodeIgniter sanitises every POST
                           key through _clean_input_keys(), which allows only
                           [a-z0-9:_/|-] and quietly turns anything else into an
                           empty key. With '~' the whole grid collapsed into one
                           blank entry and saving appeared to do nothing at all. */
                        $field = $event_key . ':' . $channel;
                        //one rule per cell, written by the helper that irNotifyRecipientsFor
                        //reads back; it returns 0 for an untouched cell with no rule
                        //behind it, so an empty grid writes nothing at all
                        $rule_id = irNotifyRuleSave(
                            $event_key, $channel,
                            !empty($enabled_in[$field]),
                            !empty($critical_in[$field]),
                            isset($recipients_in[$field]) ? htmlspecialcharscustom($recipients_in[$field]) : '',
                            $company_id, $me
                        );
                        if($rule_id){ $saved++; }
                    }
                }
                $this->session->set_flashdata('exception', lang('notify_rules_saved') . ' (' . $saved . ')');
                redirect('setting/notifications');
            }
        }

        $data = array();
        $data['can_update'] = $can_update;
        $data['can_see_keys'] = $can_see_keys;
        $data['channels'] = $this->irNotifyChannels();
        $data['events'] = irNotifyEvents();
        $data['general'] = irNotifySettings(TRUE);
        $data['sms_cfg'] = irNotifyChannelSettings('sms');
        $data['rows'] = irModuleRows(TRUE);
        $data['registry'] = irModuleRegistry();
        $data['roles'] = $this->db->where('del_status', 'Live')->order_by('id', 'ASC')->get('tbl_roles')->result();

        //rules keyed event|channel so the grid can find its own cell in one pass
        $rules = array();
        foreach($this->db->where('del_status', 'Live')->get('tbl_notification_rules')->result() as $rule){
            $parts = array();
            $recipients = $this->db->where('rule_id', $rule->id)->where('del_status', 'Live')
                                   ->order_by('escalation_order', 'ASC')
                                   ->get('tbl_notification_recipients')->result();
            foreach($recipients as $one){
                $parts[] = ($one->recipient_type == 'fixed')
                         ? $one->recipient_value
                         : $one->recipient_type . ':' . $one->recipient_value;
            }
            $rules[$rule->event_key . '|' . $rule->channel] = array(
                'row' => $rule,
                'recipients' => implode(', ', $parts),
            );
        }
        $data['rules'] = $rules;

        $data['recent'] = $this->db->order_by('id', 'DESC')->limit(25)->get('tbl_notification_outbox')->result();
        $data['counts'] = array();
        foreach($this->db->select('status, COUNT(*) AS total')->group_by('status')
                         ->get('tbl_notification_outbox')->result() as $count){
            $data['counts'][$count->status] = (int) $count->total;
        }

        $data['main_content'] = $this->load->view('setting/notifications', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * "Send a test to me": queue one message to the test recipient and send it
     * here and now, so pressing the button gives an answer instead of a shrug.
     *
     * This is the ONLY place the web sends anything. It is one message, to the
     * address the tester typed, started by somebody holding 'update'. Everything
     * else still goes through the cron worker, because a sale must never wait on
     * a gateway.
     * @access public
     * @return void
     */
    public function notificationTest() {
        $access_id = (string) irAccessModuleId('notifications');
        if(!$access_id || !checkAccess($access_id, "update")){
            $this->session->set_flashdata('exception_1', lang('menu_not_permit_access'));
            redirect('setting/notifications');
        }
        $channel = htmlspecialcharscustom($this->input->post($this->security->xss_clean('channel')));
        if(!in_array($channel, $this->irNotifyChannels(), TRUE)){
            $this->session->set_flashdata('exception_1', lang('notify_bad_rule'));
            redirect('setting/notifications');
        }
        if(!irNotifyOn() || !irNotifyChannelOn($channel)){
            $this->session->set_flashdata('exception_1', lang('notify_switch_off_first'));
            redirect('setting/notifications');
        }
        $settings = irNotifySettings(TRUE);
        $to = trim((string) $settings['test_recipient']);
        if($to == ''){
            $this->session->set_flashdata('exception_1', lang('notify_no_test_recipient'));
            redirect('setting/notifications');
        }
        $driver = irNotifyDriver($channel);
        if($driver === NULL){
            //a channel with no driver yet: say so rather than queue a row the
            //worker would leave pending for ever
            $this->session->set_flashdata('exception_1', lang('notify_no_driver'));
            redirect('setting/notifications');
        }

        $id = irNotifyEnqueue(array(
            'event_key' => 'manual_test',
            'channel' => $channel,
            'intended_recipient' => $to,
            'subject' => 'Rexlio test notification',
            'body' => 'Rexlio test notification sent by ' . $this->session->userdata('full_name')
                      . ' at ' . date('Y-m-d H:i') . '. If you are reading this, the ' . $channel
                      . ' channel works.',
            'payload' => array('manual' => TRUE, 'by' => (int) $this->session->userdata('user_id')),
        ));
        if(!$id){
            $this->session->set_flashdata('exception_1', lang('notify_test_not_queued'));
            redirect('setting/notifications');
        }

        $row = $this->db->where('id', $id)->get('tbl_notification_outbox')->row();
        $result = call_user_func($driver, $row);
        if(!empty($result['ok'])){
            $this->db->where('id', $id)->update('tbl_notification_outbox', array(
                'status' => 'sent', 'attempts' => 1, 'sent_at' => date('Y-m-d H:i:s'),
            ));
            irNotifyLog('test #' . $id . ' ' . $channel . ' -> ' . $row->actual_recipient . ' sent');
            $this->session->set_flashdata('exception', lang('notify_test_sent') . ' ' . $row->actual_recipient);
        }else{
            $error = isset($result['error']) ? (string) $result['error'] : 'unknown';
            $this->db->where('id', $id)->update('tbl_notification_outbox', array(
                'status' => 'failed', 'attempts' => 1, 'last_error' => substr($error, 0, 480),
            ));
            irNotifyLog('test #' . $id . ' ' . $channel . ' -> ' . $row->actual_recipient . ' FAILED: ' . $error);
            $this->session->set_flashdata('exception_1', lang('notify_test_failed') . ' ' . $error);
        }
        redirect('setting/notifications');
    }

    /**
     * The channels this build knows about, in the order the screen shows them.
     * Kept here rather than derived from tbl_modules so an unknown channel name
     * posted at the rules form is refused instead of stored.
     * @access private
     * @return array
     */
    private function irNotifyChannels() {
        return array('inapp', 'email', 'sms', 'whatsapp', 'telegram', 'push');
    }

    /**
     * Write one module's settings JSON, leaving its ON/OFF state alone, and drop
     * the caches that were populated earlier in this same request.
     * @access private
     * @return void
     */
    private function irSaveModuleSettings($module_key, $settings) {
        $this->db->where('module_key', $module_key)->update('tbl_modules', array(
            'settings' => json_encode($settings),
            'updated_by' => (int) $this->session->userdata('user_id'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        irModuleRows(TRUE);
        irNotifySettings(TRUE);
    }
}
