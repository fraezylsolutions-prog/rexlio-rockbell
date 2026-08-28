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
  # This is Sale_model Model
  ###########################################################
 */
class Sale_model extends CI_Model {

    /**
     * get Sale List
     * @access public
     * @return object
     * @param int
     */
    public function getSaleList($outlet_id) {
        $result = $this->db->query("SELECT s.*,u.full_name,c.name as customer_name,m.name,c.phone as customer_phone
          FROM tbl_sales s
          INNER JOIN tbl_customers c ON(s.customer_id=c.id)
          LEFT JOIN tbl_users u ON(s.user_id=u.id)
          LEFT JOIN tbl_payment_methods m ON(s.payment_method_id=m.id) 
          WHERE s.order_status = '3' AND s.del_status = 'Live' AND s.outlet_id=$outlet_id ORDER BY s.id DESC")->result();
        return $result;
    }
    /**
     * get Item Menu Categories
     * @access public
     * @return object
     * @param int
     */
    public function getItemMenuCategories($company_id) {
        $result = $this->db->query("SELECT * 
          FROM tbl_ingredient_categories 
          WHERE company_id=$company_id AND del_status = 'Live'  
          ORDER BY category_name");
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * export Daily Sales
     * @access public
     * @return object
     * @param no
     */
    public function exportDailySale() {
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select('tbl_sales.*,tbl_users.full_name,tbl_payment_methods.name,tbl_customers.name as customer_name');
        $this->db->from('tbl_sales');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.user_id', 'left');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sales.payment_method_id', 'left');
        $this->db->where('order_status', '3');
        $this->db->where('tbl_sales.outlet_id', $outlet_id);
        $this->db->where('tbl_sales.del_status', 'Live');
        $this->db->order_by('sale_date', 'ASC');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    /**
     * get Item Menus
     * @access public
     * @return object
     * @param int
     */
    public function getItemMenus($outlet_id) {
        $result = $this->db->query("SELECT * 
          FROM tbl_ingredients 
          WHERE outlet_id=$outlet_id AND del_status = 'Live'  
          ORDER BY name");
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Item List With Unit
     * @access public
     * @return object
     * @param int
     */
    public function getItemListWithUnit($outlet_id) {
        $result = $this->db->query("SELECT tbl_ingredients.id, tbl_ingredients.name, tbl_units.unit_name 
          FROM tbl_ingredients 
          JOIN tbl_units ON tbl_ingredients.unit_id = tbl_units.id
          WHERE outlet_id=$outlet_id AND tbl_ingredients.del_status = 'Live'  
          ORDER BY tbl_ingredients.name ASC");
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Food Menu Ingredients
     * @access public
     * @return object
     * @param int
     */
    public function getFoodMenuIngredients($id) {
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("*");
        $this->db->from("tbl_food_menus_ingredients");
        $this->db->order_by('id', 'ASC');
        $this->db->where("food_menu_id", $id);
        $this->db->where("del_status", 'Live');
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Item Menu Items
     * @access public
     * @return object
     * @param int
     */
    public function getItemMenuItems($id) {
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("*");
        $this->db->from("tbl_ingredients_items");
        $this->db->order_by('id', 'ASC');
        $this->db->where("ingredient_id", $id);
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("del_status", 'Live');
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        };
    }
    /**
     * get All Item menus
     * @access public
     * @return object
     * @param no
     */
    public function getAllItemmenus() {
        $company_id = $this->session->userdata('company_id');
        $result = $this->db->query("SELECT tbl_food_menus.id, tbl_food_menus.code, tbl_food_menus.name, tbl_food_menus.sale_price, tbl_food_menus.photo, tbl_food_menu_categories.category_name
          FROM tbl_food_menus 
          LEFT JOIN tbl_food_menu_categories ON tbl_food_menus.category_id = tbl_food_menu_categories.id
          WHERE tbl_food_menus.company_id=$company_id AND tbl_food_menus.del_status = 'Live' AND parent_id =  '0' 
          ORDER BY tbl_food_menus.name ASC");
        $result = $this->db->get();

        if($result != false){
            return $result->result();
        }else{
            return false;
        }
    }
    /**
     * get Food Menu Categories
     * @access public
     * @return object
     * @param int
     */
    public function getFoodMenuCategories($company_id) {
        $this->db->select("*");
        $this->db->from("tbl_food_menu_categories");
        $this->db->where("company_id", $company_id);
        $this->db->where("del_status", 'Live');
        $this->db->order_by("category_name", 'ASC');
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Sale Info
     * @access public
     * @return object
     * @param int
     */
    public function getSaleInfo($sales_id) {
        $outlet_id = $this->session->userdata('outlet_id');
        $result = $this->db->query("SELECT s.*,u.full_name,c.name as customer_name,m.name,tbl.name as table_name
          FROM tbl_sales s
          INNER JOIN tbl_customers c ON(s.customer_id=c.id)
          LEFT JOIN tbl_users u ON(s.user_id=u.id)
          LEFT JOIN tbl_payment_methods m ON(s.payment_method_id=m.id)
          LEFT JOIN tbl_tables tbl ON(s.table_id=tbl.id) 
          WHERE s.id=$sales_id AND s.del_status = 'Live' AND s.outlet_id=$outlet_id")->row();
        return $result;
    }
    /**
     * get Sale Details
     * @access public
     * @return boolean
     * @param int
     */
    public function getSaleDetails($sales_id) {
        $outlet_id = $this->session->userdata('outlet_id');
        $result = $this->db->query("SELECT sd.*,fm.code
          FROM tbl_sales_details sd
          LEFT JOIN tbl_food_menus fm ON(sd.food_menu_id=fm.id)
          WHERE sd.sales_id=$sales_id AND sd.outlet_id=$outlet_id AND sd.del_status = 'Live'  
          ORDER BY sd.id ASC");
        $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * generate Token no
     * @access public
     * @return object
     * @param int
     */
    public function generateToken_no($outlet_id) {
        $year = date('ymd', strtotime('today'));
        $sale_count = $this->db->query("SELECT count(id) as sale_count
               FROM tbl_sales where outlet_id=$outlet_id")->row('sale_count');
        $token_no = $year . str_pad($sale_count + 1, 2, '0', STR_PAD_LEFT);
        return $token_no;
    }
    /**
     * get All Food Menus
     * @access public
     * @return boolean
     * @param no
     */
    public function getAllFoodMenus(){
      $outlet_id = $this->session->userdata('outlet_id');
      $getFM = getFMIds($outlet_id);
      $result = $this->db->query("SELECT fm.*,fmc.category_name, COUNT(sd.food_menu_id) as item_sold,kitchen_id 
      FROM tbl_food_menus fm  LEFT JOIN (select * from tbl_food_menu_categories where del_status='Live') fmc ON fmc.id = fm.category_id LEFT JOIN (select * from tbl_sales_details where del_status='Live') sd ON sd.food_menu_id = fm.id LEFT JOIN (select kitchen_id,cat_id from tbl_kitchen_categories where del_status='Live') kt_cat ON kt_cat.cat_id = fm.category_id WHERE FIND_IN_SET(fm.id, '$getFM') AND fm.del_status='Live' GROUP BY fm.id order BY name ASC")->result();
      if($result != false){
        return $result;
      }else{
        return false;
      }
    }
    /**
     * get All Menu Categories
     * @access public
     * @return boolean
     * @param no
     */
    public function getAllMenuCategories(){
      $company_id = $this->session->userdata('company_id');
      $this->db->select("*");
      $this->db->from("tbl_food_menu_categories");
      $this->db->where("company_id", $company_id);
      $this->db->where("del_status", 'Live');
      $this->db->order_by('id', 'ASC');
      $result = $this->db->get();

      if($result != false){
        return $result->result();
      }else{
        return false;
      }
    }
    /**
     * get All Menu Modifiers
     * @access public
     * @return boolean
     * @param no
     */
    public function getAllMenuModifiers(){
     $company_id = $this->session->userdata('company_id');
      $this->db->select("tbl_food_menus_modifiers.*,tbl_modifiers.name,tbl_modifiers.price,tbl_modifiers.tax_information");
      $this->db->from("tbl_food_menus_modifiers");
      $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_food_menus_modifiers.modifier_id', 'left');
      $this->db->where("tbl_food_menus_modifiers.company_id", $company_id);
      $this->db->where("tbl_food_menus_modifiers.del_status", 'Live');
      $this->db->order_by('id', 'ASC');
      $result = $this->db->get();

      if($result != false){
        return $result->result();
      }else{
        return false;
      }
    }
    /**
     * get Waiters For This Company
     * @access public
     * @return object
     * @param int
     * @param string
     */
    public function getWaitersForThisCompany($company_id,$table){
        $language_manifesto = $this->session->userdata('language_manifesto');
        if(str_rot13($language_manifesto)=="eriutoeri"){
          $value = $this->session->userdata("outlet_id");
          if(!$value){
            $value = 1;
          }
          // Build the query
          $this->db->select('*');
          $this->db->from('tbl_users');
          $this->db->where("FIND_IN_SET($value, outlets) >", 0);
          $this->db->where("designation", 'Waiter');
          $this->db->where("company_id", $company_id);
          $this->db->where("del_status", 'Live');
          $this->db->order_by('full_name', 'ASC');
          $query = $this->db->get();
          $data =  $query->result();
          return $data;
        }else{
            $outlet_id = $this->session->userdata('outlet_id');
            if(!$outlet_id){
              $outlet_id = 1;
            }

            $this->db->select("*");
            $this->db->from($table);
            $this->db->where("company_id", $company_id);
            $this->db->where("outlet_id", $outlet_id);
            $this->db->where("designation", 'Waiter');
            $this->db->where("del_status", 'Live');
            $this->db->order_by('full_name', 'ASC');
            $result = $this->db->get();
            if($result != false){
                return $result->result();
            }else{
                return false;
            }
        }

    }
    public function getWaitersForThisCompanyForOutlet($company_id,$table){
      $language_manifesto = $this->session->userdata('language_manifesto');
      if(str_rot13($language_manifesto)=="eriutoeri"){
        $value = $this->session->userdata("outlet_id");
        if($value==''){
          $value = 0;
        }
        // Build the query
        $this->db->select('*');
        $this->db->from('tbl_users');
        $this->db->where("FIND_IN_SET($value, outlets) >", 0);
        $this->db->where("company_id", $company_id);
        $this->db->where("del_status", 'Live');
        $this->db->order_by('full_name', 'ASC');
        $query = $this->db->get();
        $data =  $query->result();
        return $data;
      }else{
          $outlet_id = $this->session->userdata('outlet_id');
          if($outlet_id==''){
            $outlet_id = 0;
          }
          $this->db->select("*");
          $this->db->from($table);
          $this->db->where("company_id", $company_id);
          $this->db->where("outlet_id", $outlet_id);
          $this->db->where("del_status", 'Live');
          $this->db->order_by('full_name', 'ASC');
          $result = $this->db->get();
          if($result != false){
              return $result->result();
          }else{
              return false;
          }
      }

  }
    /**
     * get New Orders
     * @access public
     * @return object
     * @param int
     */
    public function getNewOrders($outlet_id, $filters = array()){
        $today_date = date('Y-m-d');
        $is_waiter = $this->session->userdata('is_waiter');
        $user_id = $this->session->userdata('user_id');
        $role = $this->session->userdata('role');

      $this->db->select("*,tbl_sales.id as sale_id,tbl_customers.name as customer_name, tbl_sales.id as sales_id,tbl_users.full_name as waiter_name,tbl_tables.name as table_name");
      $this->db->from('tbl_sales');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
      $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.waiter_id', 'left');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      //can_view_all is decided by the calling controller, never by posted input.
      //when it is not set the original per-user scoping below is untouched.
      $can_view_all = isset($filters['can_view_all']) && $filters['can_view_all'] ? TRUE : FALSE;
      if(!$can_view_all){
          if(isset($is_waiter) && $is_waiter=="Yes"){
              $this->db->where("tbl_sales.waiter_id", $user_id);
          }else{
              if(isset($role) && $role!="Admin"){
                  $this->db->where("tbl_sales.user_id", $user_id);
              }
          }
      }
      //optional filters used by the running order screen
      if(isset($filters['view_user_id']) && $filters['view_user_id'] !== '' && $filters['view_user_id'] !== NULL){
          $this->db->group_start();
          $this->db->where("tbl_sales.user_id", $filters['view_user_id']);
          $this->db->or_where("tbl_sales.waiter_id", $filters['view_user_id']);
          $this->db->group_end();
      }
      if(isset($filters['table_id']) && $filters['table_id'] !== '' && $filters['table_id'] !== NULL){
          $this->db->where("tbl_sales.table_id", $filters['table_id']);
      }
      if(isset($filters['sale_no']) && $filters['sale_no'] !== '' && $filters['sale_no'] !== NULL){
          $this->db->like("tbl_sales.sale_no", $filters['sale_no']);
      }
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("(order_status='1' OR order_status='2')");
        $this->db->where("(future_sale_status='1' OR future_sale_status='3')");
      $this->db->order_by('tbl_sales.id', 'ASC');
      $this->db->where('tbl_sales.del_status', 'Live');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }

    }

    /**
     * get Running Kitchen Orders, feeds the standalone running order screen.
     *
     * Reads tbl_kitchen_sales rather than tbl_sales on purpose. A POS order is
     * written to tbl_kitchen_sales by Sale/add_kitchen_sale_by_ajax the moment it
     * is placed, while tbl_sales only receives it once the sale is completed and
     * pushed by the offline sync (push_online drains the recent_sales store only).
     * So tbl_sales is empty for orders that are still running.
     *
     * CAVEAT for future maintainers: rows here are deleted when an order is
     * cancelled (Sale::add_cancel_audit_report) and when it is completed, but the
     * completion delete only runs while the company setting pre_or_post_payment
     * equals 1. If this install is ever switched to post payment mode, completed
     * orders will stop being removed and this query will need an explicit filter.
     *
     * @access public
     * @return object
     * @param int
     * @param array
     */
    public function getRunningKitchenOrders($outlet_id, $filters = array()){
        $is_waiter = $this->session->userdata('is_waiter');
        $user_id = $this->session->userdata('user_id');
        $role = $this->session->userdata('role');

        //counts and booked tables are pulled as sub selects so the screen stays
        //one query instead of four extra queries per order
        $this->db->select("tbl_kitchen_sales.*, tbl_kitchen_sales.id as sale_id, tbl_kitchen_sales.id as sales_id,
            tbl_customers.name as customer_name,
            tbl_users.full_name as waiter_name,
            tbl_tables.name as table_name,
            (SELECT COUNT(*) FROM tbl_kitchen_sales_details ksd WHERE ksd.sales_id = tbl_kitchen_sales.id AND ksd.del_status = 'Live') as total_kitchen_type_items,
            (SELECT COUNT(*) FROM tbl_kitchen_sales_details ksd WHERE ksd.sales_id = tbl_kitchen_sales.id AND ksd.del_status = 'Live' AND ksd.cooking_status = 'Done') as total_kitchen_type_done_items,
            (SELECT COUNT(*) FROM tbl_kitchen_sales_details ksd WHERE ksd.sales_id = tbl_kitchen_sales.id AND ksd.del_status = 'Live' AND ksd.cooking_status = 'Started Cooking') as total_kitchen_type_started_cooking_items,
            (SELECT GROUP_CONCAT(DISTINCT ot_t.name ORDER BY ot_t.name SEPARATOR ', ') FROM tbl_orders_table ot JOIN tbl_tables ot_t ON ot_t.id = ot.table_id WHERE ot.sale_id = tbl_kitchen_sales.id AND ot.del_status = 'Live') as tables_booked_text", FALSE);
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_kitchen_sales.waiter_id', 'left');
        $this->db->join('tbl_tables', 'tbl_tables.id = tbl_kitchen_sales.table_id', 'left');

        //can_view_all is decided by the calling controller, never by posted input
        $can_view_all = isset($filters['can_view_all']) && $filters['can_view_all'] ? TRUE : FALSE;
        if(!$can_view_all){
            if(isset($is_waiter) && $is_waiter=="Yes"){
                $this->db->where("tbl_kitchen_sales.waiter_id", $user_id);
            }else{
                if(isset($role) && $role!="Admin"){
                    $this->db->where("tbl_kitchen_sales.user_id", $user_id);
                }
            }
        }
        if(isset($filters['view_user_id']) && $filters['view_user_id'] !== '' && $filters['view_user_id'] !== NULL){
            $this->db->group_start();
            $this->db->where("tbl_kitchen_sales.user_id", $filters['view_user_id']);
            $this->db->or_where("tbl_kitchen_sales.waiter_id", $filters['view_user_id']);
            $this->db->group_end();
        }
        //a table can be recorded on the sale itself or as a booked table row
        if(isset($filters['table_id']) && $filters['table_id'] !== '' && $filters['table_id'] !== NULL){
            $table_id = (int) $filters['table_id'];
            $this->db->group_start();
            $this->db->where("tbl_kitchen_sales.table_id", $table_id);
            $this->db->or_where("tbl_kitchen_sales.id IN (SELECT ot.sale_id FROM tbl_orders_table ot WHERE ot.del_status = 'Live' AND ot.table_id = $table_id)", NULL, FALSE);
            $this->db->group_end();
        }
        if(isset($filters['sale_no']) && $filters['sale_no'] !== '' && $filters['sale_no'] !== NULL){
            $this->db->like("tbl_kitchen_sales.sale_no", $filters['sale_no']);
        }

        $this->db->where("tbl_kitchen_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_kitchen_sales.del_status", 'Live');
        $this->db->where("(tbl_kitchen_sales.order_status='1' OR tbl_kitchen_sales.order_status='2')");
        $this->db->where("(tbl_kitchen_sales.future_sale_status='1' OR tbl_kitchen_sales.future_sale_status='3')");
        $this->db->order_by('tbl_kitchen_sales.id', 'DESC');

        $result = $this->db->get();
        return $result != false ? $result->result() : array();
    }
    /**
     * get the price tiers a company can currently sell at.
     *
     * Only is_active rows are returned, so a tier the admin switches off stops
     * being offered on the sale screen. Switching a tier off never touches the
     * price columns or the tier recorded on orders already taken, so historical
     * orders keep the price they were actually sold at.
     *
     * @access public
     * @return object
     * @param int
     */
    public function getActivePriceTiers($company_id){
        $this->db->select('*');
        $this->db->from('tbl_price_tiers');
        $this->db->where('company_id', $company_id);
        $this->db->where('is_active', 1);
        $this->db->where('del_status', 'Live');
        $this->db->order_by('sort_order', 'ASC');
        $result = $this->db->get();
        return $result != false ? $result->result() : array();
    }
    /**
     * default price tier configured against a counter, falls back to Regular
     * @access public
     * @return int
     * @param int
     */
    public function getCounterDefaultPriceTier($counter_id){
        if(!$counter_id){
            return 1;
        }
        $this->db->select('default_price_tier');
        $this->db->from('tbl_counters');
        $this->db->where('id', $counter_id);
        $this->db->where('del_status', 'Live');
        $row = $this->db->get()->row();
        return (isset($row->default_price_tier) && $row->default_price_tier) ? (int) $row->default_price_tier : 1;
    }
    /**
     * get Table Status, feeds the standalone table screen.
     *
     * Occupancy is derived from tbl_kitchen_sales, not from tbl_orders_table.
     * tbl_orders_table is only used to resolve which tables a live order sits on.
     * That matters because completing an order does NOT delete its
     * tbl_orders_table row (Sale::push_online inserts another one instead), so
     * booking rows outlive the order. Kitchen sale rows are removed on
     * completion and cancellation, so anchoring on them keeps tables freeing up.
     * Booking rows whose kitchen sale has gone are ignored.
     *
     * Note tbl_orders_table.booking_time is written by the browser and has been
     * seen hours away from server time, so occupied duration is taken from
     * tbl_kitchen_sales.date_time instead.
     *
     * @access public
     * @return array
     * @param int
     */
    public function getTableStatus($outlet_id){
        //every table for the outlet, with its area
        $this->db->select('tbl_tables.*, tbl_areas.area_name');
        $this->db->from('tbl_tables');
        $this->db->join('tbl_areas', 'tbl_areas.id = tbl_tables.area', 'left');
        $this->db->where('tbl_tables.outlet_id', $outlet_id);
        $this->db->where('tbl_tables.del_status', 'Live');
        $this->db->order_by('tbl_areas.area_name', 'ASC');
        $this->db->order_by('tbl_tables.name', 'ASC');
        $tables = $this->db->get()->result();
        if(!$tables){
            return array();
        }

        //live orders and the tables they occupy. A sale can hold several tables
        //(merged) so this can return more than one row per sale.
        $this->db->select("tbl_kitchen_sales.id as sale_id, tbl_kitchen_sales.sale_no,
            tbl_kitchen_sales.order_type, tbl_kitchen_sales.total_payable, tbl_kitchen_sales.date_time,
            tbl_kitchen_sales.waiter_id, tbl_users.full_name as waiter_name,
            tbl_customers.name as customer_name,
            tbl_orders_table.persons as persons,
            COALESCE(NULLIF(tbl_orders_table.table_id, 0), tbl_kitchen_sales.table_id) as occupied_table_id", FALSE);
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_orders_table', "tbl_orders_table.sale_id = tbl_kitchen_sales.id AND tbl_orders_table.del_status = 'Live'", 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_kitchen_sales.waiter_id', 'left');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->where('tbl_kitchen_sales.outlet_id', $outlet_id);
        $this->db->where('tbl_kitchen_sales.del_status', 'Live');
        $this->db->where("(tbl_kitchen_sales.order_status='1' OR tbl_kitchen_sales.order_status='2')");
        $this->db->where("(tbl_kitchen_sales.future_sale_status='1' OR tbl_kitchen_sales.future_sale_status='3')");
        $this->db->order_by('tbl_kitchen_sales.id', 'ASC');
        $live_orders = $this->db->get()->result();

        //group the live orders onto their tables. Orders with no table at all
        //(take away, or dine in where no table was picked) simply do not match.
        $orders_by_table = array();
        if($live_orders){
            foreach($live_orders as $live_order){
                $table_id = (int) $live_order->occupied_table_id;
                if(!$table_id){
                    continue;
                }
                if(!isset($orders_by_table[$table_id])){
                    $orders_by_table[$table_id] = array();
                }
                //The same sale can appear more than once for the same table when
                //duplicate booking rows exist (Sale::push_online inserts a second
                //row rather than replacing the first). List the sale once and keep
                //the largest recorded party size, otherwise the guest count would
                //depend on which duplicate the join happened to return first.
                $already_added = FALSE;
                foreach($orders_by_table[$table_id] as $existing){
                    if($existing->sale_id == $live_order->sale_id){
                        if((int) $live_order->persons > (int) $existing->persons){
                            $existing->persons = $live_order->persons;
                        }
                        $already_added = TRUE;
                        break;
                    }
                }
                if(!$already_added){
                    $orders_by_table[$table_id][] = $live_order;
                }
            }
        }

        foreach($tables as $table){
            $table->orders = isset($orders_by_table[$table->id]) ? $orders_by_table[$table->id] : array();
            $table->is_occupied = count($table->orders) > 0 ? 1 : 0;
            $table->total_guests = 0;
            foreach($table->orders as $table_order){
                $table->total_guests += (int) $table_order->persons;
            }
        }
        return $tables;
    }
    /**
     * get a single kitchen sale header by id, used by the running order details modal
     * @access public
     * @return object
     * @param int
     */
    public function getKitchenSaleById($sales_id){
        $this->db->select("tbl_kitchen_sales.*, tbl_customers.name as customer_name, tbl_users.full_name as waiter_name, tbl_tables.name as table_name");
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_kitchen_sales.waiter_id', 'left');
        $this->db->join('tbl_tables', 'tbl_tables.id = tbl_kitchen_sales.table_id', 'left');
        $this->db->where("tbl_kitchen_sales.id", $sales_id);
        $this->db->where("tbl_kitchen_sales.del_status", 'Live');
        return $this->db->get()->row();
    }
    public function future_sales($outlet_id){
        $today_date = date('Y-m-d');
        $this->db->select("tbl_sales.*,tbl_customers.name as customer_name,tbl_tables.name as table_name,tbl_customers.phone");
        $this->db->from('tbl_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sales.del_status", "Live");
        $this->db->where("(order_status='1' OR order_status='2')");
        $this->db->where("(future_sale_status!='1')");
        $this->db->order_by('tbl_sales.id', 'DESC');
        $result = $this->db->get();

        if($result != false){
            return $result->result();
        }else{
            return false;
        }

    }
    public function self_order_sales($outlet_id){
        $type = escape_output($_GET['type']);

        $self_order_ran_code = $this->session->userdata('self_order_ran_code');
        $online_customer_id = $this->session->userdata('online_customer_id');

        $this->db->select("tbl_kitchen_sales.*,tbl_customers.name as customer_name,tbl_customers.phone");
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->where("tbl_kitchen_sales.outlet_id", $outlet_id);
        if($type==1 && $self_order_ran_code){
            $this->db->where("tbl_kitchen_sales.self_order_ran_code", $self_order_ran_code);
        }else{
            $this->db->where("tbl_kitchen_sales.customer_id", $online_customer_id);
        }
        $this->db->where("tbl_kitchen_sales.del_status", "Live");
        $this->db->order_by('tbl_kitchen_sales.id', 'DESC');
        $result = $this->db->get();
        if($result != false){
            return $result->result();
        }else{
            return false;
        }

    }
    public function self_order_sales_admin($outlet_id){
        $role = $this->session->userdata('role');
        $user_id = $this->session->userdata('user_id');
        $this->db->select("tbl_kitchen_sales.*,tbl_customers.name as customer_name,tbl_customers.phone");
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->where("tbl_kitchen_sales.outlet_id", $outlet_id);
        if($role!="Admin"){
            $this->db->where("tbl_kitchen_sales.online_self_order_receiving_id", $user_id);
        }
        $this->db->where("tbl_kitchen_sales.del_status", "Live");
        $this->db->where("is_self_order","Yes");
        $this->db->order_by('tbl_kitchen_sales.id', 'DESC');
        $result = $this->db->get();
        if($result != false){
            return $result->result();
        }else{
            return false;
        }
    }
    public function online_order_sales_admin($outlet_id){
        $role = $this->session->userdata('role');
        $user_id = $this->session->userdata('user_id');
        $this->db->select("tbl_kitchen_sales.*,tbl_customers.name as customer_name,tbl_customers.phone");
        $this->db->from('tbl_kitchen_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_kitchen_sales.customer_id', 'left');
        $this->db->where("tbl_kitchen_sales.outlet_id", $outlet_id);
        if($role!="Admin"){
            $this->db->where("tbl_kitchen_sales.online_order_receiving_id", $user_id);
        }
        $this->db->where("tbl_kitchen_sales.del_status", "Live");
        $this->db->where("is_online_order","Yes");
        $this->db->order_by('tbl_kitchen_sales.id', 'DESC');
        $result = $this->db->get();
        if($result != false){
            return $result->result();
        }else{
            return false;
        }
    }
    /**
     * get All Tables With New Status
     * @access public
     * @return object
     * @param int
     */
    public function getAllTablesWithNewStatus($outlet_id){
      $this->db->select("*");
      $this->db->from('tbl_sales');
      $this->db->where("(order_status='1' OR order_status='2')");
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where('del_status', 'Live');
      $this->db->order_by('id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Sale By Sale Id
     * @access public
     * @return object
     * @param int
     */
    public function getSaleBySaleId($sales_id){
      $this->db->select("tbl_sales.*,w.full_name as waiter_name,tbl_customers.name as customer_name,tbl_customers.address as customer_address,tbl_tables.name as table_name,tbl_users.full_name as user_name,tbl_companies.invoice_footer as invoice_footer");
      $this->db->from('tbl_sales');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.user_id', 'left');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
      $this->db->join('tbl_companies', 'tbl_companies.id = tbl_sales.outlet_id', 'left');
      $this->db->join('tbl_users w', 'w.id = tbl_sales.waiter_id', 'left');
      $this->db->where("tbl_sales.id", $sales_id);
      $this->db->where('tbl_sales.del_status', 'Live');
      $this->db->order_by('tbl_sales.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Single Sale By Sale Id
     * @access public
     * @return object
     * @param int
     */
    public function getSingleSaleBySaleId($sales_id){
      $this->db->select("tbl_sales.*,w.full_name as waiter_name,tbl_customers.name as customer_name,tbl_users.full_name as user_name,tbl_companies.invoice_footer as invoice_footer");
      $this->db->from('tbl_sales');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.user_id', 'left');
      $this->db->join('tbl_companies', 'tbl_companies.id = tbl_sales.outlet_id', 'left');
      $this->db->join('tbl_users w', 'w.id = tbl_sales.waiter_id', 'left');
      $this->db->where("tbl_sales.id", $sales_id);
      $this->db->where('tbl_sales.del_status', 'Live');
      $this->db->order_by('tbl_sales.id', 'ASC');
      return $this->db->get()->row();
    }
    /**
     * get Holds By Outlet And User Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getHoldsByOutletAndUserId($outlet_id,$user_id){
      $this->db->select("tbl_holds.*,tbl_customers.name as customer_name,tbl_tables.name as table_name,tbl_customers.phone");
      $this->db->from('tbl_holds');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_holds.customer_id', 'left');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_holds.table_id', 'left');
      $this->db->where("tbl_holds.outlet_id", $outlet_id);
      $this->db->where("tbl_holds.user_id", $user_id);
      $this->db->where("tbl_holds.del_status", "Live");
      $this->db->order_by('tbl_holds.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Last Ten Sales By Outlet And User Id
     * @access public
     * @return object
     * @param int
     */
    public function getLastTenSalesByOutletAndUserId($outlet_id){
      $this->db->select("tbl_sales.*,tbl_customers.name as customer_name,tbl_tables.name as table_name,tbl_customers.phone");
      $this->db->from('tbl_sales');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
      $this->db->where("tbl_sales.outlet_id", $outlet_id);
      $this->db->where("tbl_sales.del_status", "Live");
      $this->db->where("(order_status='2' OR order_status='3')");
      $this->db->limit(20);
      $this->db->order_by('tbl_sales.id', 'DESC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get My Todays Sales By Outlet And User Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getMyTodaysSalesByOutletAndUserId($outlet_id,$user_id){
      $this->db->select("tbl_sales.*,tbl_customers.name as customer_name,tbl_tables.name as table_name");
      $this->db->from('tbl_sales');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
      $this->db->where("tbl_sales.outlet_id", $outlet_id);
      $this->db->where("tbl_sales.user_id", $user_id);
      $this->db->where("DATE(tbl_sales.date_time)", date('Y-m-d'));
      $this->db->where("tbl_sales.del_status", "Live");
      $this->db->where("(order_status='2' OR order_status='3')");
      $this->db->order_by('tbl_sales.id', 'DESC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get All Items From Sales Detail By Sales Id
     * @access public
     * @return object
     * @param int
     */
    public function getAllItemsFromSalesDetailBySalesId($sales_id){
      $this->db->select("tbl_sales_details.*,tbl_sales_details.id as sales_details_id,tbl_food_menus.code as code");
      $this->db->from('tbl_sales_details');
      $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
      $this->db->where("sales_id", $sales_id);
      $this->db->where('tbl_sales_details.del_status', 'Live');
      $this->db->order_by('tbl_sales_details.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    public function getAllItemsFromSalesDetailBySalesIdKitchen($sales_id){
      $this->db->select("tbl_kitchen_sales_details.*,tbl_kitchen_sales_details.id as sales_details_id,tbl_food_menus.code as code");
      $this->db->from('tbl_kitchen_sales_details');
      $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_kitchen_sales_details.food_menu_id', 'left');
      $this->db->where("sales_id", $sales_id);
      $this->db->where('tbl_kitchen_sales_details.del_status', 'Live');
      $this->db->order_by('tbl_kitchen_sales_details.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get All Items From Sales Detail By Sales Id
     * @access public
     * @return object
     * @param int
     */
    public function getAllItemsFromSalesDetailBySalesIdModify($sales_id){
      $this->db->select("tbl_sales_details.*,tbl_sales_details.id as sales_details_id,tbl_food_menus.code as code");
      $this->db->from('tbl_sales_details');
      $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
      $this->db->where("sales_id", $sales_id);
      $this->db->where("is_free_item", "0");
      $this->db->where('tbl_sales_details.del_status', 'Live');
      $this->db->order_by('tbl_sales_details.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get All Items From Sales Detail By Sales Id
     * @access public
     * @return object
     * @param int
     */
    public function getAllItemsFromSalesDetailBySalesIdModifyChild($row_id,$sale_id){
      $this->db->select("tbl_sales_details.*,tbl_sales_details.id as sales_details_id,tbl_food_menus.code as code");
      $this->db->from('tbl_sales_details');
      $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
      $this->db->where("is_free_item", $row_id);
      $this->db->where("sales_id", $sale_id);
      $this->db->where('tbl_sales_details.del_status', 'Live');
      $this->db->order_by('tbl_sales_details.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Modifiers By Sale And Sale Details Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getModifiersBySaleAndSaleDetailsId($sales_id,$sale_details_id){
      $this->db->select("tbl_sales_details_modifiers.*,tbl_modifiers.name");
      $this->db->from('tbl_sales_details_modifiers');
      $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_sales_details_modifiers.modifier_id', 'left');
      $this->db->where("tbl_sales_details_modifiers.sales_id", $sales_id);
      $this->db->where("tbl_sales_details_modifiers.sales_details_id", $sale_details_id);
      $this->db->order_by('tbl_sales_details_modifiers.id', 'ASC');
      $result = $this->db->get();
        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Modifiers By Sale And Sale Details Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getModifiersBySaleAndSaleDetailsIdKitchen($sales_id,$sale_details_id){
      $this->db->select("tbl_kitchen_sales_details_modifiers.*,tbl_modifiers.name");
      $this->db->from('tbl_kitchen_sales_details_modifiers');
      $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_kitchen_sales_details_modifiers.modifier_id', 'left');
      $this->db->where("tbl_kitchen_sales_details_modifiers.sales_id", $sales_id);
      $this->db->where("tbl_kitchen_sales_details_modifiers.sales_details_id", $sale_details_id);
      $this->db->order_by('tbl_kitchen_sales_details_modifiers.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Modifiers By Sale And Sale Details Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getModifiersBySaleAndSaleDetailsIdKitchenAuto($sales_id,$sale_details_id){
      $this->db->select("tbl_kitchen_sales_details_modifiers.*,tbl_modifiers.name");
      $this->db->from('tbl_kitchen_sales_details_modifiers');
      $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_kitchen_sales_details_modifiers.modifier_id', 'left');
      $this->db->where("tbl_kitchen_sales_details_modifiers.sales_id", $sales_id);
      $this->db->where("tbl_kitchen_sales_details_modifiers.is_print", 1);
      $this->db->where("tbl_kitchen_sales_details_modifiers.sales_details_id", $sale_details_id);
      $this->db->order_by('tbl_kitchen_sales_details_modifiers.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Number Of Holds By User And Outlet Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getNumberOfHoldsByUserAndOutletId($outlet_id,$user_id)
    {
      $this->db->select('id');
      $this->db->from('tbl_holds');
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("user_id", $user_id);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->num_rows();
    }
    /**
     * get new sale by table id
     * @access public
     * @return object
     * @param int
     */
    public function get_new_sale_by_table_id($table_id)
    {
      $this->db->select("*");
      $this->db->from('tbl_sales');
      $this->db->where("table_id", $table_id);
      $this->db->where("order_status", 1);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get hold info by hold id
     * @access public
     * @return object
     * @param int
     */
    public function get_hold_info_by_hold_id($hold_id)
    {
      $this->db->select("tbl_holds.*,tbl_users.full_name as waiter_name,tbl_customers.name as customer_name,tbl_tables.name as table_name");
      $this->db->from('tbl_holds');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_holds.customer_id', 'left');
      $this->db->join('tbl_users', 'tbl_users.id = tbl_holds.waiter_id', 'left');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_holds.table_id', 'left');
      $this->db->where("tbl_holds.id", $hold_id);
      $this->db->where('tbl_holds.del_status', 'Live');
      $this->db->order_by('tbl_holds.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get All Items From Holds Detail By Holds Id
     * @access public
     * @return object
     * @param int
     */
    public function getAllItemsFromHoldsDetailByHoldsId($hold_id)
    {
      $this->db->select("tbl_holds_details.*,tbl_holds_details.id as holds_details_id");
      $this->db->from('tbl_holds_details');
      $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_holds_details.food_menu_id', 'left');
      $this->db->where("holds_id", $hold_id);
      $this->db->where('tbl_holds_details.del_status', 'Live');
      $this->db->order_by('tbl_holds_details.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Modifiers By Hold And Holds Details Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getModifiersByHoldAndHoldsDetailsId($hold_id,$holds_details_id)
    {
      $this->db->select("tbl_holds_details_modifiers.*,tbl_modifiers.name");
      $this->db->from('tbl_holds_details_modifiers');
      $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_holds_details_modifiers.modifier_id', 'left');
      $this->db->where("tbl_holds_details_modifiers.holds_id", $hold_id);
      $this->db->where("tbl_holds_details_modifiers.holds_details_id", $holds_details_id);
      $this->db->order_by('tbl_holds_details_modifiers.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Customer Info By Id
     * @access public
     * @return object
     * @param int
     */
    public function getCustomerInfoById($customer_id)
    {
      $this->db->select("*");
      $this->db->from('tbl_customers');
      $this->db->where("id", $customer_id);
      $this->db->where('del_status', 'Live');
      $this->db->order_by('id', 'ASC');
      return $this->db->get()->row();
    }
    /**
     * get All Payment Methods
     * @access public
     * @return boolean
     * @param no
     */
    public function getAllPaymentMethods()
    {
      $company_id = $this->session->userdata('company_id');
      $this->db->select('*');
      $this->db->from('tbl_payment_methods');
      $this->db->where("company_id", $company_id);
      $this->db->where("del_status", 'Live');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get All Payment Methods
     * @access public
     * @return boolean
     * @param no
     */
    public function getAllPaymentMethodsFinalize()
    {
        $company_id = $this->session->userdata('company_id');

        $this->db->select('*');
        $this->db->from('tbl_payment_methods');
        $this->db->group_start(); // Start a group for OR condition
        $this->db->where("company_id", $company_id);
            $this->db->group_end(); // End the group
            $this->db->or_group_start(); // Start a new group for the OR condition
            $this->db->where("name", "Cash"); // Assuming the column for payment method name is `name`
            $this->db->group_end(); // End the group
        $this->db->where("del_status", 'Live'); // Apply this condition to both groups
        $this->db->order_by("order_by", 'ASC');

        $result = $this->db->get();

        if ($result->num_rows() > 0) {
            return $result->result();
        } else {
            return false;
        }
    }
    /**
     * get Summation Of Paid Purchase
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
	public function getSummationOfPaidPurchase($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid) as purchase_paid");
      $this->db->from('tbl_purchase');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date", $date);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Summation Of Supplier Payment
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSummationOfSupplierPayment($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(amount) as payment_amount");
      $this->db->from('tbl_supplier_payments');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date", $date);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Summation Of Customer Due Receive
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSummationOfCustomerDueReceive($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(amount) as receive_amount");
      $this->db->from('tbl_customer_due_receives');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date>=", $date);
      $this->db->where("date<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Expense Amount Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getExpenseAmountSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(amount) as amount");
      $this->db->from('tbl_expenses');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date", $date);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Sale Paid Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSalePaidSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Sale Due Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSaleDueSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(due_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Payable Aomount Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getPayableAomountSum($user_id,$outlet_id='', $date='')
    {
      $this->db->select("SUM(total_payable) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get SaleIn Cash Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSaleInCashSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where("payment_method_id", 3);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get SaleIn Cash Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getAllSaleByDateForRegister($date)
    {
        $user_id = $this->session->userdata('user_id');
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("tbl_sale_payments.amount as paid_amount,tbl_sale_payments.payment_id,tbl_sales.user_id,tbl_sales.outlet_id,tbl_payment_methods.name as payment_name");
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sale_payments.payment_id', 'left');
        $this->db->where("tbl_sales.user_id", $user_id);
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sales.date_time>=", $date);
        $this->db->where("tbl_sales.date_time<=", date('Y-m-d H:i:s'));
        $this->db->where('del_status', 'Live');
        return $this->db->get()->result();
    }
    public function getAllSalePayment($date,$payment_id)
    {
        $user_id = $this->session->userdata('user_id');
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("tbl_sale_payments.amount as paid_amount,tbl_sale_payments.payment_id,tbl_sales.user_id,tbl_sales.outlet_id,tbl_payment_methods.name as payment_name");
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sale_payments.payment_id', 'left');
        $this->db->where("tbl_sales.user_id", $user_id);
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sale_payments.payment_id", $payment_id);
        $this->db->where("tbl_sales.date_time>=", $date);
        $this->db->where("tbl_sales.date_time<=", date('Y-m-d H:i:s'));
        $this->db->where('del_status', 'Live');
        return $this->db->get()->result();
    }
    public function getAllPurchaseByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(paid) as total_amount");
      $this->db->from('tbl_purchase');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("added_date_time>=", $date);
      $this->db->where("added_date_time<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllDueReceiveByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount");
      $this->db->from('tbl_customer_due_receives');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("date>=", $date);
      $this->db->where("date<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllDuePaymentByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount");
      $this->db->from('tbl_supplier_payments');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("added_date_time	>=", $date);
      $this->db->where("added_date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllExpenseByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount");
      $this->db->from('tbl_expenses');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("added_date_time	>=", $date);
      $this->db->where("added_date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllSaleByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount");
      $this->db->from('tbl_sale_payments');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("date_time	>=", $date);
      $this->db->where("date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where("currency_type", null);
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllRefundByPayment($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(total_refund) as total_amount");
      $this->db->from('tbl_sales');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("refund_date_time	>=", $date);
      $this->db->where("refund_date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where("refund_payment_id", $payment_id);
      $this->db->where("del_status", "Live");
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllSaleByPaymentMultiCurrency($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount");
      $this->db->from('tbl_sale_payments');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("date_time	>=", $date);
      $this->db->where("date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where("currency_type", 1);
      $this->db->where('del_status', 'Live');
      $data =  $this->db->get()->row();
      return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllSaleByPaymentMultiCurrencyRows($date,$payment_id)
    {
      $counter_id = $this->session->userdata('counter_id');
      $outlet_id = $this->session->userdata('outlet_id');
      $this->db->select("sum(amount) as total_amount,multi_currency");
      $this->db->from('tbl_sale_payments');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("payment_id", $payment_id);
      $this->db->where("date_time	>=", $date);
      $this->db->where("date_time	<=", date('Y-m-d H:i:s'));
      $this->db->where("currency_type", 1);
      $this->db->where('del_status', 'Live');
      $this->db->group_by('multi_currency');
      $data =  $this->db->get()->result();
      return $data;
    }
    public function allSaleByDateTime($date)
    {
        $user_id = $this->session->userdata('user_id');
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("tbl_sales.paid_amount,tbl_sales.payment_method_id,tbl_sales.user_id,tbl_sales.outlet_id");
        $this->db->from('tbl_sales');
        $this->db->where("tbl_sales.user_id", $user_id);
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sales.date_time>=", $date);
        $this->db->where("tbl_sales.date_time<=", date('Y-m-d H:i:s'));
        $this->db->where('del_status', 'Live');
        return $this->db->get()->result();
    }
    /**
     * get Sale In Paypal Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSaleInPaypalSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where("payment_method_id", 5);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Sale In Card Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSaleInCardSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("date_time>=", $date);
      $this->db->where("date_time<=", date('Y-m-d H:i:s'));
      $this->db->where("payment_method_id", 4);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Sale In Stripe Sum
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getSaleInStripeSum($user_id, $outlet_id, $date)
    {
      $this->db->select("SUM(paid_amount) as amount");
      $this->db->from('tbl_sales');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("sale_date", $date);
      $this->db->where("payment_method_id", null);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get Opening Balance
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getOpeningBalance($counter_id, $outlet_id, $date)
    {
      $this->db->select("opening_balance as amount");
      $this->db->from('tbl_register');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("register_status", 1);
      $this->db->order_by('id', 'DESC');
      return $this->db->get()->row();
    }
    /**
     * get Opening Date Time
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getOpeningDateTime($counter_id, $outlet_id, $date)
    {
      $this->db->select("opening_balance_date_time as opening_date_time");
      $this->db->from('tbl_register');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("register_status", 1);
      $this->db->order_by('id', 'DESC');
      return $this->db->get()->row();
    }
    /**
     * get Opening Date Time
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getOpeningDetails($counter_id, $outlet_id, $date)
    {
      $this->db->select("opening_details");
      $this->db->from('tbl_register');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("register_status", 1);
      $this->db->order_by('id', 'DESC');
      return $this->db->get()->row();
    }
    /**
     * get Closing Date Time
     * @access public
     * @return object
     * @param int
     * @param int
     * @param string
     */
    public function getClosingDateTime($counter_id, $outlet_id, $date)
    {
      $this->db->select("closing_balance_date_time as closing_date_time");
      $this->db->from('tbl_register');
      $this->db->where("counter_id", $counter_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("register_status", 1);
      $this->db->order_by('id', 'DESC');
      return $this->db->get()->row();
    }
    /**
     * get Item Type
     * @access public
     * @return object
     * @param int
     */
    public function getItemType($item_id)
    {
      $this->db->select('bar_item as item_type');
      $this->db->from('tbl_food_menus');
      $this->db->where('id',$item_id);
      $this->db->where('del_status', 'Live');
      return $this->db->get()->row();
    }
    /**
     * get total kitchen type items
     * @access public
     * @return object
     * @param int
     */
    public function get_total_kitchen_type_items($sale_id)
    {
        $this->db->select('id');
        $this->db->from('tbl_sales_details');
        $this->db->where("sales_id", $sale_id);
        $this->db->where('del_status', 'Live');
        return $this->db->get()->num_rows();
    }
    /**
     * get total kitchen type done items
     * @access public
     * @return object
     * @param int
     */
    public function get_total_kitchen_type_done_items($sale_id)
    {
        $this->db->select('id');
        $this->db->from('tbl_sales_details');
        $this->db->where("sales_id", $sale_id);
        $this->db->where("cooking_status", "Done");
        $this->db->where('del_status', 'Live');
        return $this->db->get()->num_rows();
    }
    /**
     * get total kitchen type started cooking items
     * @access public
     * @return object
     * @param int
     */
    public function get_total_kitchen_type_started_cooking_items($sale_id)
    {
        $this->db->select('id');
        $this->db->from('tbl_sales_details');
        $this->db->where("sales_id", $sale_id);
        $this->db->where("cooking_status", "Started Cooking");
        $this->db->where('del_status', 'Live');
        return $this->db->get()->num_rows();
    }
    /**
     * get Notification By Outlet Id
     * @access public
     * @return object
     * @param int
     */
    public function getNotificationByOutletId($outlet_id)
    {
        $designation = $this->session->userdata('designation');
        $user_id = $this->session->userdata('user_id');
      $this->db->select('*');
      $this->db->from('tbl_notifications');
        if($designation=="Waiter"){
            $this->db->where("waiter_id", $user_id);
        }
      $this->db->where("outlet_id", $outlet_id);
      $this->db->order_by('id', 'DESC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Notification By Outlet Id And User Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getNotificationByOutletIdAndUserId($outlet_id,$user_id)
    {
      $this->db->select('*,tbl_notifications.id as notification_id');
      $this->db->from('tbl_notifications');
      $this->db->join('tbl_sales', 'tbl_sales.id = tbl_notifications.sale_id', 'left');
      $this->db->where("tbl_notifications.outlet_id", $outlet_id);
      $this->db->where("tbl_sales.waiter_id", $user_id);
      $this->db->order_by('tbl_notifications.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Tables By Outlet Id
     * @access public
     * @return object
     * @param int
     */
    public function getTablesByOutletId($outlet_id) {
      $this->db->select('*');
      $this->db->from('tbl_tables');
      $this->db->where("outlet_id", $outlet_id);
      $this->db->order_by('id', 'ASC');
      $this->db->where("del_status", "Live");

      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Orders Of Table By Table Id
     * @access public
     * @return object
     * @param int
     */
    public function getOrdersOfTableByTableId($table_id)
    {
      $this->db->select('*');
      $this->db->from('tbl_orders_table');
      $this->db->where("table_id", $table_id);
      $this->db->where("del_status", "Live");

      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Table Availability
     * @access public
     * @return object
     * @param int
     */
    public function getTableAvailability($outlet_id)
    {
      $this->db->select('SUM(persons) as persons_number,table_id');
      $this->db->from('tbl_orders_table');
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("del_status", "Live");
      $this->db->group_by('table_id');

      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get all assets
     * @access public
     * @return object
     * @param int
     */
    public function get_all_assets($outlet_id)
    {
      $this->db->select('*');
      $this->db->from('tbl_assets');
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("del_status", 'Live');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get Games Of Asset By Asset Id
     * @access public
     * @return object
     * @param int
     */
    public function getGamesOfAssetByAssetId($asset_id)
    {
      $this->db->select('*,tbl_games.name');
      $this->db->from('tbl_assets_games');
      $this->db->join('tbl_games', 'tbl_games.id = tbl_assets_games.game_id', 'left');
      $this->db->where("asset_id", $asset_id);

      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * get First User Information
     * @access public
     * @return object
     * @param int
     * @param string
     */
    public function getFirstUserInformationBy($outlet_id,$user_type)
    {
      $this->db->select('*');
      $this->db->from('tbl_users');
      $this->db->where("outlet_id", $outlet_id);
      $this->db->where("role", $user_type);
      $this->db->where("del_status", 'Live');
      $this->db->order_by('id', 'ASC');
      $this->db->limit(1);
      return $this->db->get()->row();
    }
    /**
     * get all tables of a sale items
     * @access public
     * @return object
     * @param int
     */
    public function get_all_tables_of_a_sale_items($sale_id)
    {
      $this->db->select('tbl_tables.name as table_name');
      $this->db->from('tbl_orders_table');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_orders_table.table_id', 'left');
      $this->db->where("sale_id", $sale_id);
      $this->db->where("tbl_orders_table.del_status", 'Live');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }

    }
    public function get_all_tables_of_a_sale_items_persons($sale_id)
    {
      $this->db->select('sum(persons) as total_persons');
      $this->db->from('tbl_orders_table');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_orders_table.table_id', 'left');
      $this->db->where("sale_id", $sale_id);
      $this->db->where("tbl_orders_table.del_status", 'Live');
      $result = $this->db->get();
        if($result != false){
            $data =  $result->row();
            if(isset($data) && $data->total_persons){
                return $data->total_persons;
            }else{
                return 1;
            }
        }else{
            return 1;
        }
    }
    /**
     * get all tables of a sale items
     * @access public
     * @return object
     * @param int
     */
    public function get_all_tables_of_a_hold_items($hold_id)
    {
      $this->db->select('tbl_holds_table.*,tbl_tables.name as table_name');
      $this->db->from('tbl_holds_table');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_holds_table.table_id', 'left');
      $this->db->where("hold_id", $hold_id);
      $result = $this->db->get();
        if($result != false){
          return $result->result();
        }else{
          return false;
        }

    }
    /**
     * get all tables of a last sale
     * @access public
     * @return object
     * @param int
     */
    public function get_all_tables_of_a_last_sale($sale_id)
    {
      $this->db->select('tbl_tables.name as table_name');
      $this->db->from('tbl_orders_table');
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_orders_table.table_id', 'left');
      $this->db->where("sale_id", $sale_id);
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }

    }
    /**
     * delete status orders table
     * @access public
     * @return object
     * @param int
     */
    public function delete_status_orders_table($sale_id)
    {
        $this->db->set('del_status', "Deleted");
        $this->db->where('sale_id', $sale_id);
        $this->db->update('tbl_orders_table');
    }
    /**
     * get Cash Method
     * @access public
     * @return object
     * @param no
     */
    public function getCashMethod()
    {
      $this->db->select('*');
      $this->db->from('tbl_payment_methods');
      $this->db->where("name", 'Cash');
      return $this->db->get()->row();
    }
    /**
     * get Running Orders By Outlet And Waiter Id
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function getRunningOrdersByOutletAndWaiterId($outlet_id,$waiter_id){
      $this->db->select("*,tbl_sales.id as sale_id,tbl_customers.name as customer_name, tbl_sales.id as sales_id,tbl_users.full_name as waiter_name,tbl_tables.name as table_name");
      $this->db->from('tbl_sales');
      $this->db->where("tbl_sales.outlet_id", $outlet_id);
      $this->db->where("tbl_sales.waiter_id", $waiter_id);
      $this->db->where("(order_status='1' OR order_status='2')");
      $this->db->join('tbl_tables', 'tbl_tables.id = tbl_sales.table_id', 'left');
      $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.waiter_id', 'left');
      $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
      $this->db->where('tbl_sales.del_status', 'Live');
      $this->db->order_by('tbl_sales.id', 'ASC');
      $result = $this->db->get();

        if($result != false){
          return $result->result();
        }else{
          return false;
        }
    }
    /**
     * custom table data return
     * @access public
     * @param int
     * @param int
     * @param int
     * @param int
     */
    public function make_query($outlet_id){
        $this->db->select("tbl_sales.*,tbl_users.full_name,tbl_customers.name as customer_name,tbl_payment_methods.name,tbl_customers.phone as customer_phone");
        $this->db->from('tbl_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.user_id', 'left');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sales.payment_method_id', 'left');
        if (!empty($_POST["search"]["value"])) {
            $this->db->like("sale_no",$_POST["search"]["value"]);
            $this->db->or_like("tbl_customers.name",$_POST["search"]["value"]);
            $this->db->or_like("tbl_customers.phone",$_POST["search"]["value"]);
            $this->db->or_like("tbl_users.full_name",$_POST["search"]["value"]);
        }
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sales.order_status", '3');
        $this->db->where("tbl_sales.del_status", "Live");
        $this->db->order_by('tbl_sales.id', 'DESC');
    }
    /**
     * return table limit row
     * @access public
     * @param int
     * @param int
     * @param int
     * @param int
     */
    public function make_datatables($outlet_id){
        $this->make_query($outlet_id);
        if(!empty($_POST["length"]) != -1){
          $this->db->limit(!empty($_POST["length"]), !empty($_POST["start"]));
        }
        return $this->db->get()->result();
    }
    /**
     * draw the datatable
     * @access public
     * @param string
     */
    public function getDrawData(){
      return !empty($_POST["draw"]);
    }
    /**
     * filtered the ajax datatable
     * @access public
     * @param int
     * @param int
     * @param int
     * @param int
     */
    public function get_filtered_data($outlet_id){
        $this->make_query($outlet_id);
        $result = $this->db->get();
        return $result->num_rows();
    }
    /**
     * return all data
     * @access public
     * @param int
     * @param int
     * @param int
     * @param int
     */
    public function get_all_data($outlet_id){
        $this->db->select("*");
        $this->db->from('tbl_sales');
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("order_status", '3');
        $this->db->where("del_status", "Live");
        return $this->db->count_all_results();
    }


    /**
     * ORDER LOOKUP (report batch R5): every order, running or completed, in one list.
     *
     * A UNION of the two order sources, which is safe because they are disjoint by
     * construction - a kitchen sale row is DELETED when the order completes, so a
     * sale_no exists in exactly one of them. Verified against live data: zero
     * sale_no values appear in both.
     *
     * READ ONLY, and deliberately thin: order no, status, table, amount. This is a
     * lookup index, not a third action surface - rows link out to the screens that
     * already own those actions (the invoice popup for completed, the Running Order
     * screen for running).
     *
     * @access public
     * @return array
     */
    public function getOrderLookup($outlet_ids, $filters = array()) {
        $outlet_list = outletScopeSqlList($outlet_ids);

        $where_run  = " ks.outlet_id IN ($outlet_list) AND ks.del_status='Live'
                        AND ks.order_status IN (1,2) AND ks.future_sale_status IN (1,3)";
        $where_done = " s.outlet_id IN ($outlet_list) AND s.del_status='Live'
                        AND s.order_status = 3";

        $b = array();
        //filters are applied to BOTH halves so a search cannot silently return only
        //one kind of order
        if (isset($filters['sale_no']) && $filters['sale_no'] !== '') {
            $like = '%' . $this->db->escape_like_str($filters['sale_no']) . '%';
            $where_run  .= " AND ks.sale_no LIKE " . $this->db->escape($like);
            $where_done .= " AND s.sale_no LIKE " . $this->db->escape($like);
        }
        if (isset($filters['user_id']) && $filters['user_id'] !== '') {
            $uid = (int) $filters['user_id'];
            $where_run  .= " AND ks.user_id = $uid";
            $where_done .= " AND s.user_id = $uid";
        }
        if (isset($filters['start_date']) && $filters['start_date'] !== '') {
            $d = $this->db->escape($filters['start_date']);
            $where_run  .= " AND ks.sale_date >= $d";
            $where_done .= " AND s.sale_date >= $d";
        }
        if (isset($filters['end_date']) && $filters['end_date'] !== '') {
            $d = $this->db->escape($filters['end_date']);
            $where_run  .= " AND ks.sale_date <= $d";
            $where_done .= " AND s.sale_date <= $d";
        }

        //Table name is resolved through tbl_orders_table, which is where a sale's
        //table booking actually lives; ks.table_id / s.table_id is only a fallback
        //because a merged order can hold several tables.
        $tbl_run  = "(SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ')
                        FROM tbl_orders_table ot JOIN tbl_tables t ON t.id = ot.table_id
                       WHERE ot.sale_no = ks.sale_no AND ot.del_status='Live')";
        $tbl_done = "(SELECT GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ')
                        FROM tbl_orders_table ot JOIN tbl_tables t ON t.id = ot.table_id
                       WHERE ot.sale_no = s.sale_no AND ot.del_status='Live')";

        $running = "SELECT ks.id AS row_id, ks.sale_no, 'running' AS order_state,
                           ks.sale_date, ks.order_time, ks.total_payable,
                           ks.user_id, $tbl_run AS table_names
                      FROM tbl_kitchen_sales ks WHERE $where_run";
        $done    = "SELECT s.id AS row_id, s.sale_no, 'completed' AS order_state,
                           s.sale_date, s.order_time, s.total_payable,
                           s.user_id, $tbl_done AS table_names
                      FROM tbl_sales s WHERE $where_done";

        //status filter chooses which halves take part, rather than filtering after
        //the union, so an unwanted half is never scanned at all
        $status = isset($filters['status']) ? $filters['status'] : '';
        if ($status === 'running') {
            $sql = $running;
        } elseif ($status === 'completed') {
            $sql = $done;
        } else {
            $sql = $running . ' UNION ALL ' . $done;
        }

        $sql = "SELECT * FROM ( $sql ) o";
        if (isset($filters['table']) && $filters['table'] !== '') {
            $sql .= " WHERE o.table_names LIKE " . $this->db->escape('%' . $this->db->escape_like_str($filters['table']) . '%');
        }
        $sql .= " ORDER BY o.sale_date DESC, o.order_time DESC";

        $q = $this->db->query($sql);
        return $q ? $q->result() : array();
    }
}

