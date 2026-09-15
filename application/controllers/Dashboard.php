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
  # This is Dashboard Controller
  ###########################################################
 */
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Authentication_model');
        $this->load->model('Common_model');
        $this->load->model('Dashboard_model');
        $this->load->model('Inventory_model');
        $this->load->model('Report_model');
        $this->Common_model->setDefaultTimezone();
        $this->load->library('form_validation');
        
        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }

        if (!$this->session->has_userdata('outlet_id')) {
            $this->session->set_flashdata('exception_2',lang('please_click_green_button'));

            $this->session->set_userdata("clicked_controller", $this->uri->segment(1));
            $this->session->set_userdata("clicked_method", $this->uri->segment(2));
            redirect('Outlet/outlets');
        }


        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);




    }

     /**
     * bar panel
     * @access public
     * @return void
     * @param no
     */
    public function dashboard() {
        //start check access function
        $segment_2 = $this->uri->segment(2);
        $controller = "1";
        $function = "";

        if($segment_2=="dashboard"){
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

        $first_day_this_month  = isset($_POST['start_date_dashboard']) && $_POST['start_date_dashboard']?$_POST['start_date_dashboard']:date('Y-m-01');
        $last_day_this_month  = isset($_POST['end_date_dashboard']) && $_POST['end_date_dashboard']?$_POST['end_date_dashboard']:date('Y-m-t');

        $outlet_id  = isset($_POST['outlet_id']) && $_POST['outlet_id']?$_POST['outlet_id']:'';
        if(!$outlet_id){
            $outlet_id = $this->session->userdata('outlet_id');
        }

        $data['outlet_id'] =  $outlet_id;
        $data['food_menu_count'] =  $this->Dashboard_model->getAllFoodMenus($outlet_id);
        $data['ingredient_count'] = sizeof($this->Dashboard_model->getInventory($outlet_id));
        $data['customer_count'] = $this->Dashboard_model->countData('tbl_customers');
        $data['employee_count'] = $this->Dashboard_model->countData('tbl_users');

        $data['start_date_dashboard'] = $first_day_this_month;
        $data['end_date_dashboard'] = $last_day_this_month;

        //Echoed back so the time inputs survive the form submit. Without this they
        //blank on reload and the filter looks like it did nothing - the JS reads
        //the rendered inputs to build its request. No default: blank means "no
        //time filter", which must stay the resting state.
        $data['start_time_dashboard'] = isset($_POST['start_time_dashboard']) ? trim((string)$_POST['start_time_dashboard']) : '';
        $data['end_time_dashboard']   = isset($_POST['end_time_dashboard'])   ? trim((string)$_POST['end_time_dashboard'])   : '';

        $data['low_stock_ingredients'] = $this->Inventory_model->getInventoryAlertList($outlet_id);
        $data['top_ten_food_menu'] = $this->Dashboard_model->top_ten_food_menu($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['top_ten_customer'] = $this->Dashboard_model->top_ten_customer($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['customer_receivable'] = $this->Dashboard_model->customer_receivable($outlet_id);
        $data['supplier_payable'] = $this->Dashboard_model->supplier_payable($outlet_id);

        $data['dinein_count'] = $this->Dashboard_model->dinein_count($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['take_away_count'] = $this->Dashboard_model->take_away_count($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['delivery_count'] = $this->Dashboard_model->delivery_count($first_day_this_month, $last_day_this_month,$outlet_id);

        $data['purchase_sum'] = $this->Dashboard_model->purchase_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['sale_sum'] = $this->Dashboard_model->sale_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['waste_sum'] = $this->Dashboard_model->waste_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['expense_sum'] = $this->Dashboard_model->expense_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['customer_due_receive_sum'] = $this->Dashboard_model->customer_due_receive_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['supplier_due_payment_sum'] = $this->Dashboard_model->supplier_due_payment_sum($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['outlets'] = $this->Common_model->getAllOutlestByAssign();
        $data['sale_by_payments'] = $this->Dashboard_model->sale_by_payments($first_day_this_month, $last_day_this_month,$outlet_id);
        $data['sale_by_paymentsTotal'] = $this->Dashboard_model->sale_by_paymentsTotal($first_day_this_month, $last_day_this_month,$outlet_id);


        $data['main_content'] = $this->load->view('dashboard/dashboard', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * bar panel
     * @access public
     * @return void
     * @param no
     */
    function operation_comparision_by_date_ajax(){
        $from_this_day = $this->input->post('from_this_day');
        $to_this_day = $this->input->post('to_this_day');
        
        $data = array();

        $data['purchase_sum'] = $this->Dashboard_model->purchase_sum($from_this_day, $to_this_day);  
        $data['sale_sum'] = $this->Dashboard_model->sale_sum($from_this_day, $to_this_day);  
        $data['waste_sum'] = $this->Dashboard_model->waste_sum($from_this_day, $to_this_day);  
        $data['expense_sum'] = $this->Dashboard_model->expense_sum($from_this_day, $to_this_day);  
        $data['customer_due_receive_sum'] = $this->Dashboard_model->customer_due_receive_sum($from_this_day, $to_this_day);  
        $data['supplier_due_payment_sum'] = $this->Dashboard_model->supplier_due_payment_sum($from_this_day, $to_this_day);
        $data['from_this_day'] = $from_this_day;
        $data['to_this_day'] = $to_this_day;
        echo json_encode($data);
    }
     /**
     * bar panel
     * @access public
     * @return void
     * @param no
     */
    function comparison_sale_report_ajax_get() {
        $selectedMonth = $_GET['months'];
        $finalOutput = array();
        for ($i = $selectedMonth - 1; $i >= 0; $i--) {
            $dateCalculate = $i > 0 ? '-' . $i : $i;
            $sqlStartDate = date('Y-m-01', strtotime($dateCalculate . ' month'));
            $sqlEndDate = date('Y-m-31', strtotime($dateCalculate . ' month'));
            $saleAmount = $this->Common_model->comparison_sale_report($sqlStartDate, $sqlEndDate);
            $finalOutput[] = array(
                'month' => date('M-y', strtotime($dateCalculate . ' month')),
                'saleAmount' => !empty($saleAmount) ? $saleAmount->total_amount : 0.0,
            );
        }
        echo json_encode($finalOutput);
    }
    function get_sale_report_charge(){
        $outlet_id  = isset($_POST['outlet_id']) && $_POST['outlet_id']?$_POST['outlet_id']:'';
        $start_date  = isset($_POST['start_date']) && $_POST['start_date']?$_POST['start_date']:'';
        $end_date  = isset($_POST['end_date']) && $_POST['end_date']?$_POST['end_date']:'';
        $type  = isset($_POST['type']) && $_POST['type']?$_POST['type']:'';
        $action_type  = isset($_POST['action_type']) && $_POST['action_type']?$_POST['action_type']:'';

        if(!$outlet_id){
            $outlet_id = $this->session->userdata('outlet_id');
        }
        $return_date_day = array();
        $transaction_details_main = (object)$this->Report_model->getTotalTransaction($start_date, $end_date,$outlet_id);

        if($action_type=="revenue"){
            $day_wise_date = (getSaleDate($start_date,$end_date,$type));
            foreach ($day_wise_date as $key=>$value){
                $date_split = explode("||",$value);
                $start_date_generated = date("Y-m-d",strtotime($date_split[0]));
                $start_end_generated = date("Y-m-d",strtotime($date_split[1]));
                $profit_details = (object)$this->Report_model->profitLossReport($start_date_generated, $start_end_generated,$outlet_id);
                $inline_array = array();
                $inline_array['y_value'] = getAmtP($profit_details->profit_1);
                $inline_array['y_label'] =$date_split[2];
                $inline_array['x_label'] =$date_split[3];
                $inline_array['x_label_tmp'] ='';
                $return_date_day[] = $inline_array;
            }
        }else if($action_type=="profit"){
            $day_wise_date = (getSaleDate($start_date,$end_date,$type));
            foreach ($day_wise_date as $key=>$value){
                $date_split = explode("||",$value);
                $start_date_generated = date("Y-m-d",strtotime($date_split[0]));
                $start_end_generated = date("Y-m-d",strtotime($date_split[1]));
                $profit_details = (object)$this->Report_model->profitLossReport($start_date_generated, $start_end_generated,$outlet_id);
                $inline_array = array();
                $inline_array['y_value'] = getAmtP($profit_details->profit_9);
                $inline_array['y_label'] =$date_split[2];
                $inline_array['x_label'] =$date_split[3];
                $inline_array['x_label_tmp'] ='';
                $return_date_day[] = $inline_array;
            }
        }else if($action_type=="transactions"){
            $day_wise_date = (getSaleDate($start_date,$end_date,$type));
            foreach ($day_wise_date as $key=>$value){
                $date_split = explode("||",$value);
                $start_date_generated = date("Y-m-d",strtotime($date_split[0]));
                $start_end_generated = date("Y-m-d",strtotime($date_split[1]));
                $profit_details = (object)$this->Report_model->getTotalTransaction($start_date_generated, $start_end_generated,$outlet_id);
                $inline_array = array();
                $inline_array['y_value'] = getAmtP($profit_details->total_transaction);
                $inline_array['y_label'] =$date_split[2];
                $inline_array['x_label'] =$date_split[3];
                $inline_array['x_label_tmp'] =lang('transactions');
                $return_date_day[] = $inline_array;
            }
        }else if($action_type=="customers"){
            $day_wise_date = (getSaleDate($start_date,$end_date,$type));
            foreach ($day_wise_date as $key=>$value){
                $date_split = explode("||",$value);
                $start_date_generated = date("Y-m-d",strtotime($date_split[0]));
                $start_end_generated = date("Y-m-d",strtotime($date_split[1]));
                $profit_details = (object)$this->Report_model->getTotalCustomer($start_date_generated, $start_end_generated,$outlet_id);
                $inline_array = array();
                $inline_array['y_value'] = getAmtP($profit_details->total_customer);
                $inline_array['y_label'] =$date_split[2];
                $inline_array['x_label'] =$date_split[3];
                $inline_array['x_label_tmp'] =lang('customers');
                $return_date_day[] = $inline_array;
            }
        }else if($action_type=="average_receipt"){
            $day_wise_date = (getSaleDate($start_date,$end_date,$type));
            foreach ($day_wise_date as $key=>$value){
                $date_split = explode("||",$value);
                $start_date_generated = date("Y-m-d",strtotime($date_split[0]));
                $start_end_generated = date("Y-m-d",strtotime($date_split[1]));

                $profit_details = (object)$this->Report_model->profitLossReport($start_date_generated, $start_end_generated,$outlet_id);
                $average_trans = @(($profit_details->profit_1)/$transaction_details_main->total_transaction);
                $inline_array = array();
                $inline_array['y_value'] = getAmtP($average_trans);
                $inline_array['y_label'] =$date_split[2];
                $inline_array['x_label'] =$date_split[3];
                $inline_array['x_label_tmp'] =lang('average_receipt');
                $return_date_day[] = $inline_array;
            }
        }
        $return_array['data_points'] =  $return_date_day;

        $return_value_set_total_1 = 0;
        $return_value_set_total_2 = 0;
        $return_value_set_total_3 = 0;
        $return_value_set_total_4 = 0;

        $profit_details = (object)$this->Report_model->profitLossReport($start_date, $end_date,$outlet_id);
        $customer_details = (object)$this->Report_model->getTotalCustomer($start_date, $end_date,$outlet_id);

        $return_value_set_total_1+=($profit_details->profit_1);
        $return_value_set_total_2+=$profit_details->profit_9;
        $return_value_set_total_3+=$transaction_details_main->total_transaction;
        $return_value_set_total_4+=$customer_details->total_customer;

        $return_array['set_total_1'] =  getAmtP($return_value_set_total_1);
        $return_array['set_total_2'] =  getAmtP($return_value_set_total_2);
        $return_array['set_total_3'] =  getAmtP($return_value_set_total_3);
        $return_array['set_total_4'] =  getAmtP($return_value_set_total_4);

        echo json_encode($return_array);
    }
    function get_sale_report_charge_today(){
        $outlet_id  = isset($_POST['outlet_id']) && $_POST['outlet_id']?$_POST['outlet_id']:'';
        $start_date  = date('Y-m-d');
        $end_date  =date('Y-m-d');

        if(!$outlet_id){
            $outlet_id = $this->session->userdata('outlet_id');
        }

        //Time-of-day range. Applies to the tbl_sales-derived figures only - see the
        //Net Profit note below. Blank means no time predicate at all.
        $start_time = isset($_POST['start_time_dashboard']) ? trim((string)$_POST['start_time_dashboard']) : '';
        $end_time   = isset($_POST['end_time_dashboard'])   ? trim((string)$_POST['end_time_dashboard'])   : '';

        //Cards 1-3 remain TODAY-only, exactly as before - $start_date/$end_date
        //above are both date('Y-m-d'). Cards 1 and 3 now also honour the time range.
        $return_value_set_total_1 = 0;
        $return_value_set_total_2 = 0;
        $return_value_set_total_3 = 0;

        //Card 1 - Revenue. Moved off profitLossReport()'s profit_1 onto the same
        //query card 5 uses, so the time range can be applied without also
        //time-filtering profit_9 (which must not be - see below). The two are
        //arithmetically identical with no time filter set: profit_1 is
        //sum(total_payable) on tbl_sales, order_status 3, same outlet and
        //del_status. Verified equal before this change shipped.
        $revenue_details = $this->Dashboard_model->completed_order_value($start_date, $end_date, $outlet_id, $start_time, $end_time);
        $return_value_set_total_1 += $revenue_details->completed_order_value;

        //Card 2 - Net Profit. DELIBERATELY NOT TIME-FILTERED, by client decision.
        //profit_9 nets sales against wastes, expenses and transfers, and those
        //three tables record a DATE only - they have no time column at all. Time
        //-filtering the sales half while counting a whole day's expenses would
        //understate profit by however much falls outside the window, and it would
        //look entirely plausible while doing so. The card therefore stays on the
        //date range, and the view carries a note saying so.
        $profit_details = (object)$this->Report_model->profitLossReport($start_date, $end_date,$outlet_id);
        $return_value_set_total_2 += $profit_details->profit_9;

        //Card 3 - Transactions. Own query rather than Report_model::getTotalTransaction(),
        //which is shared with the dashboard charts.
        $transaction_details_main = $this->Dashboard_model->transaction_count($start_date, $end_date, $outlet_id, $start_time, $end_time);
        $return_value_set_total_3 += $transaction_details_main->transaction_count;

        //Card 4 - Running Order Value. A LIVE snapshot: no date range at all, by
        //decision. An order open last week is either still open (and counted
        //now) or was completed (and belongs to card 5), so a date filter here
        //would answer a question nobody asks.
        $running_details = $this->Dashboard_model->running_order_value($outlet_id);
        $return_value_set_total_4 = $running_details->running_order_value;

        //Card 5 - Completed Order Value. Honours the dashboard's date range,
        //which is what distinguishes it from the Revenue card above (same
        //formula, but Revenue is fixed to today).
        //The date fallback matters: a time range with no dates would otherwise
        //reach the query with both blank, and while completed_order_value() has no
        //early-return gate (so it would still return a figure), that figure would
        //silently span all history. Falling back to today keeps a time-only
        //selection meaning "today, between these hours".
        $range_start = isset($_POST['start_date_dashboard']) && $_POST['start_date_dashboard'] ? $_POST['start_date_dashboard'] : $start_date;
        $range_end   = isset($_POST['end_date_dashboard']) && $_POST['end_date_dashboard'] ? $_POST['end_date_dashboard'] : $end_date;
        $completed_details = $this->Dashboard_model->completed_order_value($range_start, $range_end, $outlet_id, $start_time, $end_time);
        $return_value_set_total_5 = $completed_details->completed_order_value;

        $return_array['set_total_1'] =  getAmtP($return_value_set_total_1);
        $return_array['set_total_2'] =  getAmtP($return_value_set_total_2);
        $return_array['set_total_3'] =  getAmtP($return_value_set_total_3);
        $return_array['set_total_4'] =  getAmtP($return_value_set_total_4);
        $return_array['set_total_5'] =  getAmtP($return_value_set_total_5);
        //count is not shown on the card, but is cheap to return and useful if the
        //client later asks "how many orders is that?"
        $return_array['running_order_count'] = $running_details->running_order_count;

        echo json_encode($return_array);
    }
}
