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
  # This is Register Controller
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Register extends Cl_Controller {

    public function __construct() {
        parent::__construct();

        $this->load->library('excel'); //load PHPExcel library 
        $this->load->model('Authentication_model');
        $this->load->model('Common_model');
        $this->load->model('Master_model');
        $this->load->model('Register_model');
        $this->load->model('Sale_model'); //needed for computeForceCloseTotals()'s parameterized totals
        $this->load->library('form_validation');
        
        $this->Common_model->setDefaultTimezone();
        
        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }
        
        if (!$this->session->has_userdata('outlet_id')) {
            $this->session->set_flashdata('exception_2', 'Please click on green Enter button of an outlet');
            redirect('Outlet/outlets');
        }

        //BASE-PRODUCT FIX: this controller had no access check at all, so any logged
        //in user - waiters included - could open a register. Opening is now gated by
        //the 'open-376' permission, which is toggleable per role in the access system
        //rather than a hardcoded role name check.
        //Only the register-opening entry points are gated; registerDetails() and the
        //ajax helpers are left as they were so existing flows are unaffected.
        $segment_2 = $this->uri->segment(2);
        if($segment_2 == "openRegister" || $segment_2 == "addBalance" || $segment_2 == "addExistingBalance"){
            if(!checkAccess("376", "open")){
                $this->session->set_flashdata('exception_er', lang('not_permitted_open_register'));
                redirect('Authentication/userProfile');
            }
        }

        //Admin Register Management: list every open register and force-close one
        //on someone else's behalf (forgotten shift-end, staff departure mid-shift).
        //Configurable permission, same pattern as 'open' above - NOT hardcoded to a
        //role name. Granted to Admin/Manager by default (see the migration), but an
        //Admin/Manager designation alone grants nothing without this row.
        if($segment_2 == "manageRegisters" || $segment_2 == "forceCloseRegisterCalc" || $segment_2 == "forceCloseRegister"){
            if(!checkAccess("376", "manage_registers")){
                if($segment_2 == "manageRegisters"){
                    $this->session->set_flashdata('exception_er', lang('not_permitted_manage_registers'));
                    redirect('Authentication/userProfile');
                }
                echo json_encode(array('status' => 'error', 'message' => lang('not_permitted_manage_registers')));
                exit;
            }
        }

        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);
    }

     /**
     * open Register
     * @access public
     * @return void
     * @param no
     */
    public function openRegister(){
        $data = array();
        $company_id = $this->session->userdata('company_id'); 
        $data = array();
        $outlet_id = $this->session->userdata('outlet_id'); 
        $data['counters'] = $this->Common_model->getAllByCustomResultsId($outlet_id,"outlet_id","tbl_counters",$order='ASC');
        $data['payment_methods'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_payment_methods");
        $data['main_content'] = $this->load->view('register/openRegister', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * open Register
     * @access public
     * @return void
     * @param no
     */
    public function registerDetails(){ 
        //check register is open or not
        $is_waiter = $this->session->userdata('is_waiter');
        $designation = $this->session->userdata('designation');
        if($designation!="Waiter" && $this->session->has_userdata('is_online_order')!="Yes" && !isFoodCourt()){
            $user_id = $this->session->userdata('user_id');
            $outlet_id = $this->session->userdata('outlet_id');
            if($this->Common_model->isOpenRegister($user_id,$outlet_id)==0){
                $this->session->set_flashdata('exception_3', lang('register_open_msg'));
                if($this->uri->segment(2)=='registerDetailCalculationToShowAjax' || $this->uri->segment(2)=='closeRegister'){
                    redirect('Register/openRegister');
                }else{
                    $this->session->set_userdata("clicked_controller", $this->uri->segment(1));
                    $this->session->set_userdata("clicked_method", $this->uri->segment(2));
                    redirect('Register/openRegister');
                }

            }
        }

        $data = array();
        $data['main_content'] = $this->load->view('register/registerDetails', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * add Balance
     * @access public
     * @return void
     * @param int
     */
    public function addBalance($encrypted_id = ""){
        $id = $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $company_id = $this->session->userdata('company_id'); 
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('opening_balance', lang('opening_balance'), 'required');
            $this->form_validation->set_rules('counter_id', lang('counter_name'), 'required|max_length[11]');
            if ($this->form_validation->run() == TRUE) {
                $register_info = array();
                $register_info['opening_balance'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('opening_balance')));
                $register_info['closing_balance'] = 0.00;
                $register_info['counter_id'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('counter_id')));
                $register_info['opening_balance_date_time'] = date('Y-m-d H:i:s');
                $register_info['register_status'] = 1;
                $register_info['user_id'] = $this->session->userdata('user_id');
                $register_info['outlet_id'] = $this->session->userdata('outlet_id');
                $register_info['company_id'] = $this->session->userdata('company_id');

                //This variable could not be escaped because this is array content
                $payment_names = $this->input->post($this->security->xss_clean('payment_names'));
                $payment_ids = $this->input->post($this->security->xss_clean('payment_ids'));
                $payments = $this->input->post($this->security->xss_clean('payments'));
                $arr = array();

                foreach ($payment_ids as $key=>$value){
                    $arr[] = $value."||".$payment_names[$key]."||".$payments[$key];
                }
                $register_info['opening_details'] = json_encode($arr);

                   $this->Common_model->insertInformation($register_info, "tbl_register");
                   // Printer Session Data Set
                   $counter_details = $this->Common_model->getPrinterIdByCounterId($register_info['counter_id']);
                   $printer_info = $this->Common_model->getPrinterInfoById($counter_details->invoice_printer_id);
                   $print_arr = [];
                   $print_arr['counter_id'] = $register_info['counter_id'];
                   $print_arr['counter_name'] = $counter_details->name;
                   $print_arr['printer_id'] = $counter_details->invoice_printer_id;
                   if($printer_info):
                        $print_arr['path'] = $printer_info->path;
                        $print_arr['title'] = $printer_info->title;
                        $print_arr['type'] = $printer_info->type;
                        $print_arr['characters_per_line'] = $printer_info->characters_per_line;
                        $print_arr['printer_ip_address'] = $printer_info->printer_ip_address;
                        $print_arr['printer_port'] = $printer_info->printer_port;
                        $print_arr['printing_choice'] = $printer_info->printing_choice;
                        $print_arr['ipvfour_address'] = $printer_info->ipvfour_address;
                        $print_arr['print_format'] = $printer_info->print_format;
                        $print_arr['inv_qr_code_enable_status'] = $printer_info->inv_qr_code_enable_status;
                   endif;
                   //bill
                   $printer_info_bill = $this->Common_model->getPrinterInfoById($counter_details->bill_printer_id);
                   $print_arr['bill_printer_id'] = $counter_details->bill_printer_id;
                   if($printer_info_bill):
                        $print_arr['path_bill'] = $printer_info_bill->path;
                        $print_arr['title_bill'] = $printer_info_bill->title;
                        $print_arr['type_bill'] = $printer_info_bill->type;
                        $print_arr['characters_per_line_bill'] = $printer_info_bill->characters_per_line;
                        $print_arr['printer_ip_address_bill'] = $printer_info_bill->printer_ip_address;
                        $print_arr['printer_port_bill'] = $printer_info_bill->printer_port;
                        $print_arr['printing_choice_bill'] = $printer_info_bill->printing_choice;
                        $print_arr['ipvfour_address_bill'] = $printer_info_bill->ipvfour_address;
                        $print_arr['print_format_bill'] = $printer_info_bill->print_format;
                        $print_arr['inv_qr_code_enable_status_bill'] = $printer_info_bill->inv_qr_code_enable_status;
                   endif;
                   $this->session->set_userdata($print_arr);
                   
                if (!$this->session->has_userdata('clicked_controller')) {
                    if ($this->session->userdata('role') == 'Admin') {
                        redirect('Dashboard/dashboard');
                    } else {
                        redirect('Authentication/userProfile');
                    }
                } else {
                    $controller = $this->session->userdata('clicked_controller');
                    $function = $this->session->userdata('clicked_method');
                    if($function=="getWaiterOrders.html"){
                        redirect('Dashboard/dashboard');
                    }else{
                        if($function=="get_new_notifications_ajax" || $function=="getWaiterOrders"){
                            redirect('Dashboard/dashboard');
                        }else{
                            redirect($controller."/".$function);
                        }
                        
                    }
                }
            }else {
                $data = array();
                $outlet_id = $this->session->userdata('outlet_id'); 
                $data['counters'] = $this->Common_model->getAllByCustomResultsId($outlet_id,"outlet_id","tbl_counters",$order='ASC');
                $data['main_content'] = $this->load->view('register/openRegister', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        }else{
            $data = array();
            $outlet_id = $this->session->userdata('outlet_id'); 
            $data['counters'] = $this->Common_model->getAllByCustomResultsId($outlet_id,"outlet_id","tbl_counters",$order='ASC');
            $data['main_content'] = $this->load->view('register/openRegister', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
    public function addExistingBalance($encrypted_id = ""){
        $id = $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('counter_id', lang('counter_name'), 'required|max_length[11]');
            if ($this->form_validation->run() == TRUE) {
                $register_info = array();
                $register_info['counter_id'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('counter_id')));
  
                   // Printer Session Data Set
                   $counter_details = $this->Common_model->getPrinterIdByCounterId($register_info['counter_id']);
                   $printer_info = $this->Common_model->getPrinterInfoById($counter_details->invoice_printer_id);
                   $print_arr = [];
                   $print_arr['counter_id'] = $register_info['counter_id'];
                   $print_arr['counter_name'] = $counter_details->name;
                   $print_arr['printer_id'] = $counter_details->invoice_printer_id;
                   if($printer_info):
                        $print_arr['path'] = $printer_info->path;
                        $print_arr['title'] = $printer_info->title;
                        $print_arr['type'] = $printer_info->type;
                        $print_arr['characters_per_line'] = $printer_info->characters_per_line;
                        $print_arr['printer_ip_address'] = $printer_info->printer_ip_address;
                        $print_arr['printer_port'] = $printer_info->printer_port;
                        $print_arr['printing_choice'] = $printer_info->printing_choice;
                        $print_arr['ipvfour_address'] = $printer_info->ipvfour_address;
                        $print_arr['print_format'] = $printer_info->print_format;
                        $print_arr['inv_qr_code_enable_status'] = $printer_info->inv_qr_code_enable_status;
                   endif;
                   //bill
                   $printer_info_bill = $this->Common_model->getPrinterInfoById($counter_details->bill_printer_id);
                   $print_arr['bill_printer_id'] = $counter_details->bill_printer_id;
                   if($printer_info_bill):
                        $print_arr['path_bill'] = $printer_info_bill->path;
                        $print_arr['title_bill'] = $printer_info_bill->title;
                        $print_arr['type_bill'] = $printer_info_bill->type;
                        $print_arr['characters_per_line_bill'] = $printer_info_bill->characters_per_line;
                        $print_arr['printer_ip_address_bill'] = $printer_info_bill->printer_ip_address;
                        $print_arr['printer_port_bill'] = $printer_info_bill->printer_port;
                        $print_arr['printing_choice_bill'] = $printer_info_bill->printing_choice;
                        $print_arr['ipvfour_address_bill'] = $printer_info_bill->ipvfour_address;
                        $print_arr['print_format_bill'] = $printer_info_bill->print_format;
                        $print_arr['inv_qr_code_enable_status_bill'] = $printer_info_bill->inv_qr_code_enable_status;
                   endif;
                   $this->session->set_userdata($print_arr);
                   
                if (!$this->session->has_userdata('clicked_controller')) {
                    if ($this->session->userdata('role') == 'Admin') {
                        redirect('Dashboard/dashboard');
                    } else {
                        redirect('Authentication/userProfile');
                    }
                } else {
                    $controller = $this->session->userdata('clicked_controller');
                    $function = $this->session->userdata('clicked_method');
                    if($function=="getWaiterOrders.html"){
                        redirect('Dashboard/dashboard');
                    }else{
                        if($function=="get_new_notifications_ajax" || $function=="getWaiterOrders"){
                            redirect('Dashboard/dashboard');
                        }else{
                            redirect($controller."/".$function);
                        }
                        
                    }
                }
            }else {
                $data = array();
                $outlet_id = $this->session->userdata('outlet_id'); 
                $data['counters'] = $this->Common_model->getAllByCustomResultsId($outlet_id,"outlet_id","tbl_counters",$order='ASC');
                $data['main_content'] = $this->load->view('register/openRegister', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        }else{
            $data = array();
            $outlet_id = $this->session->userdata('outlet_id'); 
            $data['counters'] = $this->Common_model->getAllByCustomResultsId($outlet_id,"outlet_id","tbl_counters",$order='ASC');
            $data['main_content'] = $this->load->view('register/openRegister', $data, TRUE);
            $this->load->view('userHome', $data);
        }
    }
     /**
     * check Register Ajax
     * @access public
     * @return void
     * @param no
     */
    public function checkRegisterAjax()
    {
        $user_id = $this->session->userdata('user_id');
        $outlet_id = $this->session->userdata('outlet_id');
        $checkRegister = $this->Register_model->checkRegister($user_id,$outlet_id);
        if(!is_null($checkRegister)){
            echo escape_output($checkRegister->status);
        }else{
            echo "";
        }
                
    }


     /**
     * Admin Register Management: list every OPEN register across every outlet
     * this user may see (getAccessibleOutletIds - same company-and-assignment
     * scoping already used by the R6 register-details popup), oldest first.
     * @access public
     * @return void
     * @param no
     */
    public function manageRegisters(){
        $data = array();
        $data['open_registers'] = $this->Register_model->getAllOpenRegisters(getAccessibleOutletIds());
        $data['main_content'] = $this->load->view('register/manageRegisters', $data, TRUE);
        $this->load->view('userHome', $data);
    }

     /**
     * Shared math for both the force-close preview and the force-close write, so
     * the number an admin is shown is GUARANTEED to be the number that gets
     * written - never two code paths that could quietly drift apart.
     *
     * Deliberately a NEW parameterized calculation rather than a call into
     * Sale::closeRegister() - that method reads counter_id/outlet_id from the
     * SESSION, so calling it for someone else's register would compute against
     * the ADMIN's own counter/outlet instead of the target's. Same formula,
     * same per-payment-method shape (payment_methods_sale/others_currency), just
     * fed the target register's own counter/outlet/opening time explicitly.
     *
     * Window is opening time -> NOW, same as a normal close - no backdating.
     * @access private
     * @return array
     * @param object $register
     */
    private function computeForceCloseTotals($register){
        $counter_id = $register->counter_id;
        $outlet_id = $register->outlet_id;
        $opening_date_time = $register->opening_balance_date_time;
        $opening_details_decode = json_decode($register->opening_details);

        $total_closing = 0;
        $total_sale_all = 0;
        $total_purchase_all = 0;
        $total_refund_all = 0;
        $total_due_receive_all = 0;
        $total_due_payment_all = 0;
        $total_expense_all = 0;
        $payment_details = array();
        $others_currency = array();
        $breakdown = array();

        if(is_array($opening_details_decode)){
            foreach ($opening_details_decode as $value){
                $payments = explode("||",$value);

                $total_sale = $this->Sale_model->getAllSaleByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                $total_purchase = $this->Sale_model->getAllPurchaseByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                $total_due_receive = $this->Sale_model->getAllDueReceiveByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                $total_due_payment = $this->Sale_model->getAllDuePaymentByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                $total_expense = $this->Sale_model->getAllExpenseByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                $refund_amount = $this->Sale_model->getAllRefundByPaymentForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);

                $total_sale_all += $total_sale;
                $total_purchase_all += $total_purchase;
                $total_refund_all += $refund_amount;
                $total_due_receive_all += $total_due_receive;
                $total_due_payment_all += $total_due_payment;
                $total_expense_all += $total_expense;
                $inline_closing = ($payments[2] - $total_purchase + $total_sale + $total_due_receive - $total_due_payment - $total_expense - $refund_amount);
                $total_closing += $inline_closing;

                $preview_amount = isset($payment_details[$payments[1]]) && $payment_details[$payments[1]]?$payment_details[$payments[1]]:0;
                $payment_details[$payments[1]] = $preview_amount + $inline_closing;

                $breakdown[] = array(
                    'payment_name' => $payments[1],
                    'opening' => (float) $payments[2],
                    'sale' => (float) $total_sale,
                    'purchase' => (float) $total_purchase,
                    'due_receive' => (float) $total_due_receive,
                    'due_payment' => (float) $total_due_payment,
                    'expense' => (float) $total_expense,
                    'refund' => (float) $refund_amount,
                    'closing' => (float) $inline_closing,
                );

                if($payments[0]==1){
                    $total_sale_mul_c_rows = $this->Sale_model->getAllSaleByPaymentMultiCurrencyRowsForRegister($opening_date_time,$payments[0],$counter_id,$outlet_id);
                    if($total_sale_mul_c_rows){
                        foreach ($total_sale_mul_c_rows as $value1){
                            $tmp_arr = array();
                            $tmp_arr['payment_name'] = $value1->multi_currency;
                            $tmp_arr['amount'] = getAmtPCustom($value1->total_amount);
                            $others_currency[] = $tmp_arr;
                        }
                    }
                }
            }
        }

        return array(
            'system_closing_balance' => $total_closing,
            'sale_paid_amount' => $total_sale_all,
            'total_purchase' => $total_purchase_all,
            'refund_amount' => $total_refund_all,
            'customer_due_receive' => $total_due_receive_all,
            'total_due_payment' => $total_due_payment_all,
            'total_expense' => $total_expense_all,
            'payment_methods_sale' => $payment_details,
            'others_currency' => $others_currency,
            'breakdown' => $breakdown,
        );
    }

     /**
     * Force-close preview. Read-only - computes and RETURNS the system figure,
     * writes nothing. The admin sees this number, then must type the actually
     * counted cash amount before anything is saved (confirmed: never a blind
     * one-click accept - a force-close is exactly the abandoned-register case
     * where a discrepancy is most likely and most important to catch).
     * @access public
     * @return void (json)
     * @param no
     */
    public function forceCloseRegisterCalc(){
        $register_id = (int) $this->input->post('register_id');
        $register = $this->Register_model->getRegisterById($register_id);
        if(!$register || (int)$register->register_status !== 1){
            echo json_encode(array('status' => 'error', 'message' => lang('register_already_closed')));
            return;
        }
        if(!in_array((int)$register->outlet_id, getAccessibleOutletIds(), TRUE)){
            echo json_encode(array('status' => 'error', 'message' => lang('not_permitted_manage_registers')));
            return;
        }
        $totals = $this->computeForceCloseTotals($register);
        echo json_encode(array(
            'status' => 'ok',
            'register_id' => (int) $register->id,
            'user_name' => $register->user_name,
            'outlet_name' => $register->outlet_name,
            'counter_name' => $register->counter_name,
            'opening_balance' => (float) $register->opening_balance,
            'opening_balance_date_time' => $register->opening_balance_date_time,
            'system_closing_balance' => (float) $totals['system_closing_balance'],
            'breakdown' => $totals['breakdown'],
        ));
    }

     /**
     * Force-close write. Recomputes server-side rather than trusting any total
     * the client might send back - the browser is never the source of truth for
     * money. closing_balance is set to the admin's COUNTED figure (matching what
     * a normal close means: cash actually in the drawer); the system-computed
     * figure is kept separately in system_closing_balance so a discrepancy is
     * visible and queryable rather than silently overwritten either way.
     *
     * WHERE ... register_status=1 is a race guard: if the register's own holder
     * closes it normally in the moment between the preview and this submit, this
     * updates zero rows instead of double-closing or clobbering their numbers.
     * @access public
     * @return void (json)
     * @param no
     */
    public function forceCloseRegister(){
        $register_id = (int) $this->input->post('register_id');
        $counted_amount = $this->input->post('counted_amount');
        $reason = trim((string) $this->input->post($this->security->xss_clean('reason')));

        if($counted_amount === NULL || $counted_amount === '' || !is_numeric($counted_amount)){
            echo json_encode(array('status' => 'error', 'message' => lang('force_close_counted_amount_required')));
            return;
        }
        if($reason === ''){
            echo json_encode(array('status' => 'error', 'message' => lang('force_close_reason_required')));
            return;
        }

        $register = $this->Register_model->getRegisterById($register_id);
        if(!$register || (int)$register->register_status !== 1){
            echo json_encode(array('status' => 'error', 'message' => lang('register_already_closed')));
            return;
        }
        if(!in_array((int)$register->outlet_id, getAccessibleOutletIds(), TRUE)){
            echo json_encode(array('status' => 'error', 'message' => lang('not_permitted_manage_registers')));
            return;
        }

        $totals = $this->computeForceCloseTotals($register);
        $counted_amount = (float) $counted_amount;
        $discrepancy = $counted_amount - $totals['system_closing_balance'];

        $changes = array(
            'closing_balance' => $counted_amount,
            'system_closing_balance' => $totals['system_closing_balance'],
            'closing_balance_date_time' => date('Y-m-d H:i:s'),
            'customer_due_receive' => $totals['customer_due_receive'],
            'total_purchase' => $totals['total_purchase'],
            'refund_amount' => $totals['refund_amount'],
            'total_due_payment' => $totals['total_due_payment'],
            'total_expense' => $totals['total_expense'],
            'sale_paid_amount' => $totals['sale_paid_amount'],
            'others_currency' => json_encode($totals['others_currency']),
            'payment_methods_sale' => json_encode($totals['payment_methods_sale']),
            'register_status' => 2,
            'force_closed_by' => $this->session->userdata('user_id'),
            'force_close_reason' => $reason,
        );

        $this->db->where('id', $register_id);
        $this->db->where('register_status', 1);
        $this->db->update('tbl_register', $changes);

        if($this->db->affected_rows() === 0){
            echo json_encode(array('status' => 'error', 'message' => lang('register_already_closed')));
            return;
        }

        $details = '<b>Reason: '.escape_output($reason).'</b><br>'
            .'Register Owner: '.escape_output($register->user_name).', Outlet: '.escape_output($register->outlet_name)
            .', Counter: '.escape_output($register->counter_name).'<br>'
            .'Opened: '.escape_output($register->opening_balance_date_time).'<br>'
            .'System-Computed Closing: '.getAmtP($totals['system_closing_balance'])
            .', Counted: '.getAmtP($counted_amount)
            .', Discrepancy: '.getAmtP($discrepancy);
        putAuditLog($this->session->userdata('user_id'), $details, 'Force Closed Register', date('Y-m-d H:i:s'));

        echo json_encode(array(
            'status' => 'ok',
            'system_closing_balance' => (float) $totals['system_closing_balance'],
            'counted_amount' => $counted_amount,
            'discrepancy' => $discrepancy,
        ));
    }
}
