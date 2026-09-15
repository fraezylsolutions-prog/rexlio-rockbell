<?php
/*
  ###########################################################
  # PRODUCT NAME: 	iRestora PLUS - Next Gen Restaurant POS
  ###########################################################
  # This is Monitor Controller
  # Standalone operational screens that must stay reachable
  # without an open register, so they are deliberately NOT
  # hung off the Sale controller.
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Monitor extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Common_model');
        $this->load->model('Sale_model');
        $this->Common_model->setDefaultTimezone();

        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }
        if (!$this->session->has_userdata('outlet_id')) {
            $this->session->set_flashdata('exception_2', lang('please_click_green_button'));
            $this->session->set_userdata("clicked_controller", $this->uri->segment(1));
            $this->session->set_userdata("clicked_method", $this->uri->segment(2));
            redirect('Outlet/outlets');
        }
        //NOTE: no open-register guard here on purpose. Sale::__construct()
        //redirects every non-waiter without an open register to Register/openRegister,
        //which would stop a manager from simply viewing running orders.

        //start check access function
        $segment_2 = $this->uri->segment(2);
        $controller = "372";
        $function = "";

        if($segment_2=="runningOrders" || $segment_2=="runningOrdersAjax" || $segment_2=="orderDetailsAjax" || $segment_2==""){
            $function = "view";
        }elseif($segment_2=="tables"){
            $controller = "374";
            $function = "view";
        }elseif($segment_2=="orderLookup"){
            //R5 Order Lookup. Its own access row (378/379) rather than riding on
            //running_order, so it can be granted or withheld independently.
            $controller = "378";
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

        $login_session['active_menu_tmp'] = '';
        $this->session->set_userdata($login_session);
    }

    /**
     * decide whether the logged in user may look at other users' running orders.
     * Admin always may. Beyond that it is driven by the role name configured
     * against the user in tbl_roles.
     * @access private
     * @return bool
     */
    private function canViewAllUsers() {
        //Decided by PERMISSION, never by role name. checkAccess() already
        //short-circuits TRUE for Admin, so Admin keeps the behaviour it had.
        //
        //This replaced a hardcoded list of role names ('Leader', 'Sales
        //Supervisor') that matched no role in this install, so in practice only
        //Admin ever saw the full list - every other role, however privileged and
        //whatever it had been granted on the role screen, silently saw only the
        //orders it had personally created. A cashier therefore never saw a
        //waiter's order, which is the whole point of the screen.
        //
        //Degrades safely: if the access row is missing, checkAccess() returns
        //FALSE for non-Admin and the scoping is exactly what it was before.
        return checkAccess("372", "view_all_running_orders") ? TRUE : FALSE;
    }

    /**
     * running orders screen
     * @access public
     * @return void
     * @param no
     */
    public function runningOrders() {
        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');

        $data = array();
        $data['can_view_all_users'] = $this->canViewAllUsers();
        $data['filters'] = $this->collectFilters($data['can_view_all_users']);
        $data['tables'] = $this->Common_model->getAllByOutletIdForDropdown($outlet_id, 'tbl_tables');
        $data['users'] = $data['can_view_all_users']
            ? $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_users')
            : array();
        $data['orders'] = $this->buildRunningOrders($data['filters']);
        $data['main_content'] = $this->load->view('monitor/running_orders', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * running orders as json, used by the poll on the running order screen
     * @access public
     * @return void
     * @param no
     */
    public function runningOrdersAjax() {
        $filters = $this->collectFilters($this->canViewAllUsers());
        $orders = $this->buildRunningOrders($filters);
        //render the same partial the page uses so the card markup is not duplicated
        $html = $this->load->view('monitor/_running_order_cards', array('orders' => $orders), TRUE);
        echo json_encode(array('count' => count($orders), 'html' => $html));
    }

    /**
     * read the filter inputs. view_user_id is only honoured for privileged
     * roles so a waiter cannot widen their own scope by posting a user id.
     * @access private
     * @return array
     * @param bool
     */
    private function collectFilters($can_view_all_users) {
        $filters = array();
        $filters['table_id'] = $this->input->post('table_id');
        $filters['sale_no'] = trim((string) $this->input->post('sale_no'));
        $filters['can_view_all'] = $can_view_all_users;
        $filters['view_user_id'] = $can_view_all_users ? $this->input->post('view_user_id') : '';
        return $filters;
    }

    /**
     * same enrichment the POS running order panel gets from Sale::get_new_orders()
     * @access private
     * @return array
     * @param array
     */
    private function buildRunningOrders($filters) {
        $outlet_id = $this->session->userdata('outlet_id');
        //tbl_kitchen_sales, not tbl_sales: a running POS order only exists there
        //until it is completed and pushed by the offline sync. See the note on
        //Sale_model::getRunningKitchenOrders() for the pre_or_post_payment caveat.
        $orders = $this->Sale_model->getRunningKitchenOrders($outlet_id, $filters);
        if(!$orders){
            return array();
        }
        //counts and booked tables now come back with the query, only the running
        //time is still worked out here
        for($i=0; $i<count($orders); $i++){
            $to_time = strtotime(date('Y-m-d H:i:s'));
            $from_time = strtotime($orders[$i]->date_time);
            $minutes = floor(abs($to_time - $from_time) / 60);
            $seconds = abs($to_time - $from_time) % 60;

            $orders[$i]->minute_difference = str_pad(floor($minutes), 2, "0", STR_PAD_LEFT);
            $orders[$i]->second_difference = str_pad(floor($seconds), 2, "0", STR_PAD_LEFT);
        }
        return $orders;
    }

    /**
     * Table Status (Stage 5c, one tables screen): the standalone page is retired.
     * The tables panel on the POS is the one screen - filters, counts, every
     * table and order the caller may see, and the six actions - so this route
     * (also `table-status`, the old header icon and sidebar item, bookmarks)
     * simply lands on the POS, where the panel opens on load. Data:
     * Sale::myTablesAjax; markup: sale/POS/main_screen.php #ir_tables_panel.
     * @access public
     * @return void
     */
    public function tables() {
        redirect("Sale/POS");
    }

    /**
     * order details for the inline modal. Composed from the same helper and
     * model calls Sale::get_all_information_of_a_sale() uses, called directly so
     * the request does not pass through Sale's open-register guard.
     * @access public
     * @return void
     * @param no
     */
    public function orderDetailsAjax() {
        $sale_id = $this->input->post('sale_id');
        $outlet_id = $this->session->userdata('outlet_id');

        $response = new stdClass();
        $response->found = FALSE;
        $response->items = array();

        //Read from tbl_kitchen_sales, the same source the cards are built from.
        //Verified against real orders: every POS order lands here at placement,
        //including drinks only orders, and no KOT step is required.
        $sale = $this->Sale_model->getKitchenSaleById($sale_id);
        //scope check, never serve an order belonging to another outlet
        if(!isset($sale->id) || !$sale->id || $sale->outlet_id != $outlet_id){
            echo json_encode($response);
            return;
        }
        //a non privileged user may only open their own orders
        if(!$this->canViewAllUsers()){
            $user_id = $this->session->userdata('user_id');
            $is_waiter = $this->session->userdata('is_waiter');
            if(isset($is_waiter) && $is_waiter=="Yes"){
                if($sale->waiter_id != $user_id){
                    echo json_encode($response);
                    return;
                }
            }else if($sale->user_id != $user_id){
                echo json_encode($response);
                return;
            }
        }

        $items = $this->Sale_model->getAllItemsFromSalesDetailBySalesIdKitchen($sale->id);
        if(!is_array($items)){
            $items = array();
        }
        foreach($items as $item){
            $item->modifiers = $this->Sale_model->getModifiersBySaleAndSaleDetailsIdKitchen($sale->id, $item->sales_details_id);
        }

        //flatten the booked tables to a display string, a sale can hold several merged tables
        $table_names = array();
        $booked_tables = $this->Sale_model->get_all_tables_of_a_sale_items($sale->id);
        if (is_array($booked_tables)) {
            foreach ($booked_tables as $booked_table) {
                if (isset($booked_table->table_name) && $booked_table->table_name) {
                    $table_names[] = $booked_table->table_name;
                }
            }
        }

        $response->found = TRUE;
        $response->sale = $sale;
        $response->items = $items;
        $response->waiter_name = userName($sale->waiter_id);
        $response->tables_booked = $table_names ? implode(', ', $table_names) : '';
        echo json_encode($response);
    }


    /**
     * ORDER LOOKUP (report batch R5).
     *
     * A thin, READ ONLY index over both order sources so "where is order X?" can be
     * answered in one place. Running orders live in tbl_kitchen_sales and completed
     * ones in tbl_sales, and neither existing screen lists both.
     *
     * Deliberately has no actions of its own. Each row links out to the screen that
     * already owns them:
     *   completed -> the shared invoice popup built for the Detailed Sale Report
     *   running   -> the Running Order screen, via its existing ?open_sale_no= deep link
     *
     * The running deep link carries the Phase C limitation unchanged: it can only
     * open an order present in THIS device/browser's IndexedDB under the same user
     * who placed it. Cross-device lookup shows the row but cannot open it.
     *
     * @access public
     * @return void
     */
    public function orderLookup() {
        $company_id = $this->session->userdata('company_id');

        //Outlet scope: validated against the outlets this user may actually see, the
        //same helper the F2 stock screens and the R3 report use.
        $scope = resolveOutletScope($this->input->post('outlet_id'));

        $filters = array();
        $filters['sale_no']    = trim((string) $this->input->post('sale_no'));
        $filters['user_id']    = $this->input->post('user_id');
        $filters['start_date'] = $this->input->post('startDate');
        $filters['end_date']   = $this->input->post('endDate');
        $filters['table']      = trim((string) $this->input->post('table_name'));
        $status = $this->input->post('status');
        //only the two known states are honoured; anything else means "all"
        $filters['status'] = in_array($status, array('running','completed'), TRUE) ? $status : '';

        $data = array();
        $data['filters']            = $filters;
        $data['outlet_scope']       = $scope;
        $data['outlet_scope_label'] = outletScopeLabel($scope);
        $data['show_outlet_filter'] = count(getAccessibleOutletIds()) > 1;
        $data['scope_outlets']      = getAllOutlestByAssign();
        $data['users']              = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_users');
        $data['orders']             = $this->Sale_model->getOrderLookup($scope['ids'], $filters);

        $data['main_content'] = $this->load->view('monitor/order_lookup', $data, TRUE);
        $this->load->view('userHome', $data);
    }
}
