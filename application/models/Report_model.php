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
  # This is Report_model Model
  ###########################################################
 */
class Report_model extends CI_Model {
    /**
     * daily Summary Report
     * @access public
     * @return object
     * @param string
     * @param int
     */
    public function dailySummaryReport($selectedDate,$outlet_id='') {
        $this->db->select('*');
        $this->db->from('tbl_purchase');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $purchases = $this->db->get()->result();

        //daily sales
        $this->db->select('*');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where('sale_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('sale_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where("del_status", 'Live');
        $sales = $this->db->get()->result();


        //daily supplier due payments
        $this->db->select('*');
        $this->db->from('tbl_supplier_payments');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $supplier_due_payments = $this->db->get()->result();

        //daily customer due receives
        $this->db->select('*');
        $this->db->from('tbl_customer_due_receives');
        if ($selectedDate != '') {
            $this->db->where('only_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('only_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $customer_due_receives = $this->db->get()->result();

        //daily expenses
        $this->db->select('*');
        $this->db->from('tbl_expenses');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $expenses = $this->db->get()->result();

        //daily wastes
        $this->db->select('*');
        $this->db->from('tbl_wastes');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $wastes = $this->db->get()->result();

        $result = array();
        $result['purchases'] = $purchases;
        $result['sales'] = $sales;
        $result['supplier_due_payments'] = $supplier_due_payments;
        $result['customer_due_receives'] = $customer_due_receives;
        $result['expenses'] = $expenses;
        $result['wastes'] = $wastes;

        return $result;
    }
    public function zReportRegisters($selectedDate,$outlet_id='') {
        $this->db->select('*');
        $this->db->from('tbl_purchase');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $purchases = $this->db->get()->result();

        //daily sales
        $this->db->select('*');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where('sale_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('sale_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where("del_status", 'Live');
        $sales = $this->db->get()->result();


        //daily supplier due payments
        $this->db->select('*');
        $this->db->from('tbl_supplier_payments');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $supplier_due_payments = $this->db->get()->result();

        //daily customer due receives
        $this->db->select('*');
        $this->db->from('tbl_customer_due_receives');
        if ($selectedDate != '') {
            $this->db->where('only_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('only_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $customer_due_receives = $this->db->get()->result();

        //daily expenses
        $this->db->select('*');
        $this->db->from('tbl_expenses');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $expenses = $this->db->get()->result();

        //daily wastes
        $this->db->select('*');
        $this->db->from('tbl_wastes');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $wastes = $this->db->get()->result();

        $result = array();
        $result['purchases'] = $purchases;
        $result['sales'] = $sales;
        $result['supplier_due_payments'] = $supplier_due_payments;
        $result['customer_due_receives'] = $customer_due_receives;
        $result['expenses'] = $expenses;
        $result['wastes'] = $wastes;

        return $result;
    }
    /**
     * daily Consumption Report
     * @access public
     * @return object
     * @param string
     */
    public function dailyConsumptionReport($selectedDate) {
        $outlet_id = $this->session->userdata('outlet_id');

        //daily sale consumption of menu
        $this->db->select('*');
        $this->db->from('tbl_sale_consumptions_of_menus');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $sale_consumptions_of_menu = $this->db->get()->result();

        //daily sale consumption of menu's modifier
        $this->db->select('*');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where('sale_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('sale_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where("del_status", 'Live');
        $sale_consumption_of_menu_modifier = $this->db->get()->result();

        $result = array();
        $result['sale_consumptions_of_menu'] = $sale_consumptions_of_menu;
        $result['sale_consumption_of_menu_modifier'] = $sale_consumption_of_menu_modifier;

        return $result;
    }
    /**
     * today Summary Report
     * @access public
     * @return object
     * @param string
     */
    public function todaySummaryReport($selectedDate) {
        $outlet_id = $this->session->userdata('outlet_id');

        //purchase report
        $this->db->select('sum(paid) as total_purchase_amount');
        $this->db->from('tbl_purchase');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $purchase = $this->db->get()->result();
        //end purchase report
        //Sales report
        $this->db->select('sum(paid_amount) as total_sales_amount,sum(vat) as total_sales_vat');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where('sale_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('sale_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where("del_status", 'Live');
        $sales = $this->db->get()->result();
        //end Sales report
        //Waste report
        $this->db->select('sum(total_loss) as total_loss_amount');
        $this->db->from('tbl_wastes');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $waste = $this->db->get()->result();
        //end Waste report
        //Expense report
        $this->db->select('sum(amount) as expense_amount');
        $this->db->from('tbl_expenses');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $expense = $this->db->get()->result();

        //end expense report
        //Supplier payment report
        $this->db->select('sum(amount) as supplier_payment_amount');
        $this->db->from('tbl_supplier_payments');
        if ($selectedDate != '') {
            $this->db->where('date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $supplier_payment = $this->db->get()->result();
        //end expense report
        //Supplier payment report
        $this->db->select('sum(amount) as customer_receive_amount');
        $this->db->from('tbl_customer_due_receives');
        if ($selectedDate != '') {
            $this->db->where('only_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('only_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $customer_receive = $this->db->get()->result();
        //end Supplier payment report

        $this->db->select('sum(total_refund) as total_total_refund');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where(("DATE(refund_date_time) = '".$selectedDate."'"));
        } else {
            $today = date('Y-m-d');
            $this->db->where(("DATE(refund_date_time) = '".$today."'"));
        }
        $this->db->where('order_status', 3);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $sales_refund = $this->db->get()->result();


        $allTotal = 0;
        $allTotal = $purchase[0]->total_purchase_amount + $sales[0]->total_sales_amount + $waste[0]->total_loss_amount + $expense[0]->expense_amount + $supplier_payment[0]->supplier_payment_amount;
        $result['total_purchase_amount'] = isset($purchase[0]->total_purchase_amount) && $purchase[0]->total_purchase_amount ? getAmtP($purchase[0]->total_purchase_amount) : getAmtP(0);
        $result['total_sales_amount'] = isset($sales[0]->total_sales_amount) && $sales[0]->total_sales_amount ? getAmtP($sales[0]->total_sales_amount) : getAmtP(0);
        $result['total_sales_vat'] = isset($sales[0]->total_sales_vat) && $sales[0]->total_sales_vat ? getAmtP($sales[0]->total_sales_vat) : getAmtP(0);
        $result['total_loss_amount'] = isset($waste[0]->total_loss_amount) && $waste[0]->total_loss_amount ? getAmtP($waste[0]->total_loss_amount) : getAmtP(0);
        $result['expense_amount'] = isset($expense[0]->expense_amount) && $expense[0]->expense_amount ? getAmtP($expense[0]->expense_amount) : getAmtP(0);
        $result['supplier_payment_amount'] = isset($supplier_payment[0]->supplier_payment_amount) && $supplier_payment[0]->supplier_payment_amount ? getAmtP($supplier_payment[0]->supplier_payment_amount) : getAmtP(0);
        $result['customer_receive_amount'] = isset($customer_receive[0]->customer_receive_amount) && $customer_receive[0]->customer_receive_amount ? getAmtP($customer_receive[0]->customer_receive_amount) : getAmtP(0);
        $result['total_total_refund'] = isset($sales_refund[0]->total_total_refund) && $sales_refund[0]->total_total_refund ? getAmtP($sales_refund[0]->total_total_refund) : getAmtP(0);
        $result['allTotal'] = isset($allTotal) && $allTotal ? getAmtP($allTotal) : getAmtP(0);
        $balance = (($result['total_sales_amount'] + $result['customer_receive_amount']) - ($result['total_purchase_amount'] + $result['supplier_payment_amount'] + $result['expense_amount'] + $result['total_total_refund']));
        
        $result['balance'] = isset($balance) && $balance ? getAmtP($balance) : getAmtP(0);
        return $result;
    }
    /**
     * daily Summary Report Payment Method
     * @access public
     * @return object
     * @param string
     */
    public function dailySummaryReportPaymentMethod($selectedDate) {

        $outlet_id = $this->session->userdata('outlet_id');
        //payment method report
        $this->db->select('sum(total_payable) as total_sales_amount,payment_method_id');
        $this->db->from('tbl_sales');
        if ($selectedDate != '') {
            $this->db->where('sale_date =', $selectedDate);
        } else {
            $today = date('Y-m-d');
            $this->db->where('sale_date =', $today);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where("del_status", 'Live');
        $this->db->where('order_status', 3);
        $this->db->group_by('payment_method_id', "DESC");
        $paymentMethod = $this->db->get()->result();
        return $paymentMethod;
        //end purchase report
    }
    /**
     * today Summery Report
     * @access public
     * @return object
     * @param no
     */
    public function todaySummeryReport() {

        $outlet_id = $this->session->userdata('outlet_id');
        //payment method report
        $this->db->select('sum(tbl_sales.total_payable) as total_sales_amount, tbl_payment_methods.id,tbl_payment_methods.name');
        $this->db->from('tbl_sales');
        $today = date('Y-m-d');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sales.payment_method_id', 'left');
        $this->db->where('sale_date =', $today);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->where("tbl_sales.del_status", 'Live');
        $this->db->group_by('payment_method_id', "DESC");
        $paymentMethod = $this->db->get()->result();
        return $paymentMethod;
        //end purchase report
    }
    /**
     * get Inventory
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function getInventory($category_id = "", $ingredient_id = "", $food_id = "",$outlet_id='') {
        $company_id = $this->session->userdata('company_id');
        $where = '';
        $where1 = '';
        if($food_id!=''){
            $getFMIds = $food_id;
        }else{
            $getFMIds = getFMIds($outlet_id);
        }
        if($category_id!=''){
            $where1.= "  AND ingr_tbl.category_id = '$category_id'";
        }
        if($ingredient_id!=''){
            $where1.= "  AND i.id = '$ingredient_id'";
        }
        //get selected food menu ids
        if($food_id){
            $result = $this->db->query("SELECT ingr_tbl.*,i.id as food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name,ingr_unit_tbl2.unit_name as unit_name2, (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.id AND outlet_id=$outlet_id AND del_status='Live') total_purchase, 
        (select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.id AND outlet_id=$outlet_id AND del_status='Live') total_consumption,
        (select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.id AND outlet_id=$outlet_id AND  del_status='Live') total_modifiers_consumption,
        (select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND tbl_waste_ingredients.del_status='Live') total_waste,
        (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
        (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
        (select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND tbl_transfer_ingredients.status=1  AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
        (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
        (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2
        FROM tbl_food_menus_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.ingredient_id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id  LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id WHERE FIND_IN_SET(`food_menu_id`, '$getFMIds') AND i.company_id= '$company_id' AND i.del_status='Live' $where  GROUP BY i.ingredient_id")->result();
            return $result;
        }else{
            $result = $this->db->query("SELECT ingr_tbl.*,i.id as food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name,ingr_unit_tbl2.unit_name as unit_name2, (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.id AND outlet_id=$outlet_id AND del_status='Live') total_purchase, 
        (select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.id AND outlet_id=$outlet_id AND del_status='Live') total_consumption,
        (select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.id AND outlet_id=$outlet_id AND  del_status='Live') total_modifiers_consumption,
        (select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND tbl_waste_ingredients.del_status='Live') total_waste,
        (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
        (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
        (select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id=$outlet_id AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND tbl_transfer_ingredients.status=1  AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
        (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
        (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2

        FROM tbl_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id  LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id WHERE i.company_id= '$company_id' AND i.del_status='Live' $where1 GROUP BY i.id")->result();
            return $result;
        }
    }
    /**
     * get Inventory Alert List
     * @access public
     * @return object
     * @param no
     */
    public function getInventoryAlertList() {
        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');

        $where = '';
        $getFMIds = getFMIds($outlet_id);

        $result = $this->db->query("SELECT ingr_tbl.*,i.food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name, (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND del_status='Live') total_purchase, 
(select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND del_status='Live') total_consumption,
(select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND  del_status='Live') total_modifiers_consumption,
(select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND tbl_waste_ingredients.del_status='Live') total_waste,
(select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
(select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.ingredient_id AND outlet_id=$outlet_id AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
(select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND tbl_transfer_ingredients.status=1  AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
(select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
(select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2

FROM tbl_food_menus_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.ingredient_id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.purchase_unit_id WHERE FIND_IN_SET(`food_menu_id`, '$getFMIds') AND i.company_id= '$company_id' AND i.del_status='Live' $where  GROUP BY i.ingredient_id")->result();
        return $result;
    }
    /**
     * sale Report By Month
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    public function saleReportByMonth($startMonth = '', $endMonth = '', $user_id = '') {
        if ($startMonth || $endMonth || $user_id):
            $outlet_id = $this->session->userdata('outlet_id');
            $this->db->select('sale_date,sum(total_payable) as total_payable');
            $this->db->from('tbl_sales');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }

            if ($user_id != '') {
                $this->db->where('user_id', $user_id);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->group_by('month(sale_date)');
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * vat Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function vatReport($startDate = '', $endDate = '',$outlet_id='') {
        if ($startDate || $endDate):
            $this->db->select('sale_no,sale_vat_objects,sale_date,sum(total_payable) as total_payable,sum(vat) as total_vat');
            $this->db->from('tbl_sales');

            if ($startDate != '' && $endDate != '') {
                $this->db->where('sale_date>=', $startDate);
                $this->db->where('sale_date <=', $endDate);
            }
            if ($startDate != '' && $endDate == '') {
                $this->db->where('sale_date', $startDate);
            }
            if ($startDate == '' && $endDate != '') {
                $this->db->where('sale_date', $endDate);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->group_by('id');
            $this->db->where('order_status', 3);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    public function vatExpense($startDate = '', $endDate = '', $outlet_id = '') {
        if ($startDate || $endDate) {
            $this->db->select('e.*, s.name as supplier_name, c.name as customer_name');
            $this->db->from('tbl_expenses e');
            $this->db->join('tbl_customers c', 'c.id = e.customer_id', 'left');
            $this->db->join('tbl_suppliers s', 's.id = e.supplier_id', 'left');
            if ($startDate != '' && $endDate != '') {
                $this->db->where('e.date >=', $startDate);
                $this->db->where('e.date <=', $endDate);
            } elseif ($startDate != '') {
                $this->db->where('e.date', $startDate);
            } elseif ($endDate != '') {
                $this->db->where('e.date', $endDate);
            }
            if ($outlet_id != '') {
                $this->db->where('e.outlet_id', $outlet_id);
            }
            $this->db->where('e.del_status', "Live");
            $this->db->group_by('e.id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        }
        return [];
    }
    public function vatPurchase($startDate = '', $endDate = '', $outlet_id = '') {
        if ($startDate || $endDate) {
            $this->db->select('p.*, s.name as supplier_name, COALESCE(SUM(pi.total), 0) as total_with_tax, COALESCE(SUM(pi.tax_amount), 0) as only_tax');
            $this->db->from('tbl_purchase p');
            $this->db->join('tbl_suppliers s', 's.id = p.supplier_id', 'left');
            $this->db->join('tbl_purchase_ingredients pi', 'p.id = pi.purchase_id', 'left');
            if ($startDate != '' && $endDate != '') {
                $this->db->where('p.date >=', $startDate);
                $this->db->where('p.date <=', $endDate);
            } elseif ($startDate != '') {
                $this->db->where('p.date', $startDate);
            } elseif ($endDate != '') {
                $this->db->where('p.date', $endDate);
            }
            if ($outlet_id != '') {
                $this->db->where('p.outlet_id', $outlet_id);
            }
            $this->db->where('p.del_status', "Live");
            $this->db->group_by('p.id'); 
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        }
        return [];

    }
    public function totalTaxDiscountChargeTips($startDate = '',$outlet_id='') {
        $this->db->select('sum(vat) as total_tax,sum(total_discount_amount) as total_discount,sum(delivery_charge_actual_charge) as total_charge,sum(tips_amount_actual_charge) as total_tips');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startDate);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where('del_status', "Live");
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function totalCharge($startDate = '',$outlet_id = '',$type = '') {
        $this->db->select('sum(delivery_charge_actual_charge) as total_charge');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startDate);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('order_status', 3);
        $this->db->where('charge_type', $type);
        $this->db->where('del_status', "Live");
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    /**
     * tips Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function tipsReport($startDate = '', $endDate = '',$outlet_id='',$waiter_id = '') {
        if ($startDate || $endDate):
            $this->db->select('sale_no,sale_date,total_payable,tips_amount_actual_charge as total_tips');
            $this->db->from('tbl_sales');

            if ($startDate != '' && $endDate != '') {
                $this->db->where('sale_date>=', $startDate);
                $this->db->where('sale_date <=', $endDate);
            }
            if ($startDate != '' && $endDate == '') {
                $this->db->where('sale_date', $startDate);
            }
            if ($startDate == '' && $endDate != '') {
                $this->db->where('sale_date', $endDate);
            }
            if ($waiter_id != '') {
                $this->db->where('tbl_sales.waiter_id', $waiter_id);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->group_by('id');
            $this->db->where('order_status', 3);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * sale Report ByDate
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function saleReportByDate($startDate = '', $endDate = '', $user_id = '',$outlet_id='') {
        if ($startDate || $endDate || $user_id):
            $this->db->select('sale_date,sum(total_payable) as total_payable,sum(total_refund) as total_refund');
            $this->db->from('tbl_sales');

            if ($startDate != '' && $endDate != '') {
                $this->db->where('sale_date>=', $startDate);
                $this->db->where('sale_date <=', $endDate);
            }
            if ($startDate != '' && $endDate == '') {
                $this->db->where('sale_date', $startDate);
            }
            if ($startDate == '' && $endDate != '') {
                $this->db->where('sale_date', $endDate);
            }

            if ($user_id != '') {
                $this->db->where('user_id', $user_id);
            }
            $this->db->where('order_status', '3');
            $this->db->where('outlet_id', $outlet_id);
            $this->db->group_by('sale_date');
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * profit Loss Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function profitLossReport($start_date, $end_date,$outlet_id='') {
        if ($start_date || $end_date):
            //purchase report
            $this->db->select('sum(total_cost) as total_cost,sum(total_sale_amount) as total_sale_amount,sum(total_tax) as total_tax,date');
            $this->db->from('tbl_transfer_ingredients');
            $this->db->join('tbl_transfer', 'tbl_transfer.id = tbl_transfer_ingredients.transfer_id', 'left');
            $this->db->where('tbl_transfer.date>=', $start_date);
            $this->db->where('tbl_transfer.date <=', $end_date);
            $this->db->where('tbl_transfer_ingredients.from_outlet_id', $outlet_id);
            $this->db->where('tbl_transfer_ingredients.del_status', 'Live');
            $this->db->where('tbl_transfer_ingredients.transfer_type', '1');
            $this->db->where('tbl_transfer_ingredients.status', 1);
            $transferred_out =  $this->db->get()->result();


            $this->db->select_sum('(consumption*average_consumption_per_unit)', 'total_price');
            $this->db->from('tbl_sale_consumptions_of_menus');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_consumptions_of_menus.sales_id', 'left');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_menus.ingredient_id', 'left');
            $this->db->where('sale_date>=', $start_date);
            $this->db->where('sale_date <=', $end_date);
            $this->db->where('tbl_sales.outlet_id', $outlet_id);
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->where('tbl_sales.del_status', 'Live');
            $total_ing_cost_used =  $this->db->get()->result();


            $this->db->select_sum('(consumption*average_consumption_per_unit)', 'total_price');
            $this->db->from('tbl_sale_consumptions_of_modifiers_of_menus');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_consumptions_of_modifiers_of_menus.sales_id', 'left');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_modifiers_of_menus.ingredient_id', 'left');
            $this->db->where('sale_date>=', $start_date);
            $this->db->where('sale_date <=', $end_date);
            $this->db->where('tbl_sales.outlet_id', $outlet_id);
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->where('tbl_sales.del_status', 'Live');
            $total_modi_ing_cost_used =  $this->db->get()->result();



            //end purchase report
            //Sales report
            $this->db->select('sum(total_payable) as total_sales_amount,sum(vat) as total_sales_vat');
            $this->db->from('tbl_sales');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $sales = $this->db->get()->result();
            //end Sales report
            $this->db->select('sum(total_refund) as total_total_refund');
            $this->db->from('tbl_sales');
            if ($start_date != '' && $end_date != '') {
                $this->db->where(("DATE(refund_date_time) >= '".$start_date."'"));
                $this->db->where(("DATE(refund_date_time) <= '".$end_date."'"));
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where(("DATE(refund_date_time) = '".$start_date."'"));
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where(("DATE(refund_date_time) = '".$end_date."'"));
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $sales_refund = $this->db->get()->result();


            //Waste report
            $this->db->select('sum(total_loss) as total_loss_amount');
            $this->db->from('tbl_wastes');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }

            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $waste = $this->db->get()->result();
            //end Waste report
            //Expense report
            $this->db->select('sum(amount) as expense_amount');
            $this->db->from('tbl_expenses');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $expense = $this->db->get()->result();


            $this->db->select('sum(total_loss) as wastes_amount');
            $this->db->from('tbl_wastes');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $wastes = $this->db->get()->result();
            //end expense report
            //Supplier payment report
            $this->db->select('sum(amount) as supplier_payment_amount');
            $this->db->from('tbl_supplier_payments');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $supplier_payment = $this->db->get()->result();
            //end expense report
            //Customer payment report
            $this->db->select('sum(amount) as customer_receive_amount');
            $this->db->from('tbl_customer_due_receives');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('only_date>=', $start_date);
                $this->db->where('only_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('only_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('only_date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $customer_receive = $this->db->get()->result();
            //end Supplier payment report

            $result['profit_1'] = isset($sales[0]->total_sales_amount) && $sales[0]->total_sales_amount ? $sales[0]->total_sales_amount : 0;
            $result['profit_2'] = 0;
            $result['profit_3'] = isset($total_ing_cost_used[0]->total_price) && $total_ing_cost_used[0]->total_price ? ($total_ing_cost_used[0]->total_price + $total_modi_ing_cost_used[0]->total_price): 0;;
            $result['profit_4'] = isset($transferred_out[0]->total_cost) && $transferred_out[0]->total_cost ? $transferred_out[0]->total_cost : 0;
            $result['profit_5'] = ($result['profit_1'] + $result['profit_2']) - ($result['profit_3']+$result['profit_4']);

            $profit_6_1 = isset($transferred_out[0]->total_tax) && $transferred_out[0]->total_tax ? $transferred_out[0]->total_tax : 0;
            $profit_6 = isset($sales[0]->total_sales_vat) && $sales[0]->total_sales_vat ? ($sales[0]->total_sales_vat) : 0;
            $result['profit_6'] = $profit_6 + $profit_6_1;
            $result['profit_7'] = isset($wastes[0]->wastes_amount) && $wastes[0]->wastes_amount ? $wastes[0]->wastes_amount : '0.0';
            $result['profit_8'] = isset($expense[0]->expense_amount) && $expense[0]->expense_amount ? $expense[0]->expense_amount : '0.0';
            $result['profit_8_1'] = isset($sales_refund[0]->total_total_refund) && $sales_refund[0]->total_total_refund ? $sales_refund[0]->total_total_refund : '0.0';
            $result['profit_9'] = ($result['profit_5']) - ($profit_6 + $profit_6_1 + $result['profit_7'] + $result['profit_8'] + $result['profit_8_1']);
            return $result;
        endif;
    }
    public function profitLossReportBackup($start_date, $end_date,$outlet_id='') {
        if ($start_date || $end_date):

            //purchase report
            $this->db->select('sum(paid) as total_purchase_amount');
            $this->db->from('tbl_purchase');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $purchase = $this->db->get()->result();
            //end purchase report
            //Sales report
            $this->db->select('sum(paid_amount) as total_sales_amount,sum(vat) as total_sales_vat');
            $this->db->from('tbl_sales');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $sales = $this->db->get()->result();
            //end Sales report
            //Waste report
            $this->db->select('sum(total_loss) as total_loss_amount');
            $this->db->from('tbl_wastes');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }

            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $waste = $this->db->get()->result();
            //end Waste report
            //Expense report
            $this->db->select('sum(amount) as expense_amount');
            $this->db->from('tbl_expenses');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $expense = $this->db->get()->result();
            //end expense report
            //Supplier payment report
            $this->db->select('sum(amount) as supplier_payment_amount');
            $this->db->from('tbl_supplier_payments');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $supplier_payment = $this->db->get()->result();
            //end expense report
            //Customer payment report
            $this->db->select('sum(amount) as customer_receive_amount');
            $this->db->from('tbl_customer_due_receives');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('only_date>=', $start_date);
                $this->db->where('only_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('only_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('only_date', $end_date);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $customer_receive = $this->db->get()->result();
            //end Supplier payment report
            $allTotal = 0;
            $allTotal = $purchase[0]->total_purchase_amount + $sales[0]->total_sales_amount + $waste[0]->total_loss_amount + $expense[0]->expense_amount + $supplier_payment[0]->supplier_payment_amount;

            $gross_profit = (($sales[0]->total_sales_amount + $customer_receive[0]->customer_receive_amount) - ($purchase[0]->total_purchase_amount + $waste[0]->total_loss_amount + $expense[0]->expense_amount + $supplier_payment[0]->supplier_payment_amount));

            $net_profit = (($sales[0]->total_sales_amount + $customer_receive[0]->customer_receive_amount) - ($purchase[0]->total_purchase_amount + $waste[0]->total_loss_amount + $expense[0]->expense_amount + $supplier_payment[0]->supplier_payment_amount) - $sales[0]->total_sales_vat);

            $result['total_purchase_amount'] = isset($purchase[0]->total_purchase_amount) && $purchase[0]->total_purchase_amount ? $purchase[0]->total_purchase_amount : '0.0';
            $result['total_sales_amount'] = isset($sales[0]->total_sales_amount) && $sales[0]->total_sales_amount ? $sales[0]->total_sales_amount : '0.0';
            $result['total_sales_vat'] = isset($sales[0]->total_sales_vat) && $sales[0]->total_sales_vat ? $sales[0]->total_sales_vat : '0.0';
            $result['total_loss_amount'] = isset($waste[0]->total_loss_amount) && $waste[0]->total_loss_amount ? $waste[0]->total_loss_amount : '0.0';
            $result['expense_amount'] = isset($expense[0]->expense_amount) && $expense[0]->expense_amount ? $expense[0]->expense_amount : '0.0';
            $result['supplier_payment_amount'] = isset($supplier_payment[0]->supplier_payment_amount) && $supplier_payment[0]->supplier_payment_amount ? $supplier_payment[0]->supplier_payment_amount : '0.0';
            $result['customer_receive_amount'] = isset($customer_receive[0]->customer_receive_amount) && $customer_receive[0]->customer_receive_amount ? $customer_receive[0]->customer_receive_amount : '0.0';

            $result['net_profit'] = isset($net_profit) && $net_profit ? $net_profit : '0.0';
            $result['gross_profit'] = isset($gross_profit) && $gross_profit ? $gross_profit : '0.0';
            $result['allTotal'] = isset($allTotal) && $allTotal ? $allTotal : '0.0';
            return $result;
        endif;
    }
    /**
     * supplier Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function supplierReport($startMonth = '', $endMonth = '', $supplier_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $supplier_id):

            $this->db->select('date,grand_total,paid,due,reference_no');
            $this->db->from('tbl_purchase');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            if ($supplier_id != '') {
                $this->db->where('supplier_id', $supplier_id);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * customer Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function customerReport($startMonth = '', $endMonth = '', $customer_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $customer_id):
            $this->db->select('sale_date,total_payable,paid_amount,due_amount,sale_no');
            $this->db->from('tbl_sales');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }

            if ($customer_id != '') {
                $this->db->where('customer_id', $customer_id);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    public function availableLoyaltyPointReport($customer_id = '') {
        $this->db->select('*');
        $this->db->from('tbl_customers');
        if ($customer_id != '') {
            $this->db->where('id', $customer_id);
        }
        $this->db->where('del_status', "Live");
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function usageLoyaltyPointReport($startMonth = '', $endMonth = '', $customer_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $customer_id):
        $this->db->select('tbl_sale_payments.*,tbl_customers.name, tbl_customers.phone,tbl_sales.sale_no,tbl_sales.date_time');
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');

        if ($startMonth != '' && $endMonth != '') {
            $this->db->where('sale_date>=', $startMonth);
            $this->db->where('sale_date <=', $endMonth);
        }
        if ($startMonth != '' && $endMonth == '') {
            $this->db->where('sale_date', $startMonth);
        }
        if ($startMonth == '' && $endMonth != '') {
            $this->db->where('sale_date', $endMonth);
        }

        if ($customer_id != '') {
            $this->db->where('customer_id', $customer_id);
        }

        $this->db->where('tbl_sales.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_payments.payment_id', 5);
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->where('tbl_sale_payments.del_status', 'Live');
        $query_result = $this->db->get();
        $data = $query_result->result();
            return $data;
        endif;

    }
    /**
     * supplier Due Payment Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function supplierDuePaymentReport($startMonth = '', $endMonth = '', $supplier_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $supplier_id):
            $this->db->select('date,amount,note');
            $this->db->from('tbl_supplier_payments');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            if ($supplier_id != '') {
                $this->db->where('supplier_id', $supplier_id);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * customer Due Receive Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function customerDueReceiveReport($startMonth = '', $endMonth = '', $customer_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $customer_id):
            $this->db->select('date,amount,note');
            $this->db->from('tbl_customer_due_receives');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            if ($customer_id != '') {
                $this->db->where('customer_id', $customer_id);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * food Menu Sales
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function foodMenuSales($startMonth = '', $endMonth = '',$outlet_id='',$top_less='',$is_direct_food='') {
        if ($startMonth || $endMonth):
            $this->db->select('sum(qty) as totalQty,food_menu_id,menu_name,code,sale_date,tbl_sales.sale_no,tbl_sales.id as sale_id,tbl_food_menu_categories.category_name');
            $this->db->from('tbl_sales_details');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
            $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
            $this->db->join('tbl_food_menu_categories', 'tbl_food_menu_categories.id = tbl_food_menus.category_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }
            if ($is_direct_food != '') {
                $this->db->where('tbl_food_menus.product_type', $is_direct_food);
            }
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
            $this->db->where('tbl_sales_details.del_status', 'Live');
            $this->db->where('tbl_sales.order_status', '3');
            $this->db->group_by('tbl_sales_details.food_menu_id');
            $this->db->order_by('totalQty', $top_less);
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    public function totalFoodSales($startMonth = '', $endMonth = '',$outlet_id='',$top_less='') {
        if ($startMonth || $endMonth):
            $this->db->select('sum(qty) as totalQty,sum(menu_price_without_discount) net_sales, food_menu_id,menu_name,code,sale_date');
            $this->db->from('tbl_sales_details');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
            $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
            $this->db->where('tbl_sales_details.del_status', 'Live');
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->order_by('totalQty', $top_less);
            $this->db->group_by('tbl_sales_details.food_menu_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    public function totalFoodRefunds($startMonth = '', $endMonth = '',$outlet_id='',$top_less='') {
        if ($startMonth || $endMonth):
            $this->db->select('sum(total_refund) as total_refund');
            $this->db->from('tbl_sales');
            if ($startMonth != '' && $endMonth != '') {
                $this->db->where(("DATE(refund_date_time) >= '".$startMonth."'"));
                $this->db->where(("DATE(refund_date_time) <= '".$endMonth."'"));
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where(("DATE(refund_date_time) = '".$startMonth."'"));
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where(("DATE(refund_date_time) = '".$endMonth."'"));
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', 'Live');
            $this->db->where('order_status', 3);
            $query_result = $this->db->get();
            $result = $query_result->row();
            return $result;
        endif;
    }
    public function sub_total_foods($startMonth = '',$outlet_id='') {
        $this->db->select('sum(menu_price_without_discount) sub_total_foods,sale_date');
        $this->db->from('tbl_sales_details');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
        $this->db->where('tbl_sales_details.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function sub_total_modifiers($startMonth = '',$outlet_id='') {
        $this->db->select('sum(modifier_price) sub_total_foods,sale_date');
        $this->db->from('tbl_sales_details_modifiers');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details_modifiers.sales_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sales_details_modifiers.outlet_id', $outlet_id);
        $this->db->where('tbl_sales.order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function waiter_tips_foods($startMonth = '',$outlet_id='') {
        $this->db->select('sum(tips_amount_actual_charge) as tips_total');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('del_status', 'Live');
        $this->db->where('order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function taxes_foods($startMonth = '',$outlet_id='') {
        $this->db->select('sale_vat_objects');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('del_status', 'Live');
        $this->db->where('order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->result();

        $array_tax = array();
        foreach ($result as $value){
            foreach (json_decode($value->sale_vat_objects) as $tax){
                if((float)$tax->tax_field_amount){
                    $preview_vat_amount = isset($array_tax[$tax->tax_field_type]) && $array_tax[$tax->tax_field_type]?$array_tax[$tax->tax_field_type]:0;
                    $array_tax[$tax->tax_field_type] = $preview_vat_amount + ($tax->tax_field_amount);
                }
            }
        }
        return (Object)$array_tax;
    }
    public function delivery_charge_foods($startMonth = '',$outlet_id = '',$type = '') {
        $this->db->select('sum(delivery_charge_actual_charge) as delivery_charge');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('charge_type', $type);
        $this->db->where('del_status', 'Live');
        $this->db->where('order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function total_discount_amount_foods($startMonth = '',$outlet_id='') {
        $this->db->select('sum(total_discount_amount) as total_discount_amount_foods');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('del_status', 'Live');
        $this->db->where('order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function total_due_amount_foods($startMonth = '',$outlet_id='') {
        $this->db->select('sum(due_amount) as total_due_amount_foods');
        $this->db->from('tbl_sales');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('del_status', 'Live');
        $this->db->where('order_status', 3);
        $query_result = $this->db->get();
        $result = $query_result->row();
        return $result;
    }
    public function totalDueReceived($startMonth = '',$outlet_id='') {
        $this->db->select("sum(amount) as total_amount");
        $this->db->from('tbl_customer_due_receives');
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where('only_date', $startMonth);
        $this->db->where('del_status', 'Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function totalFoodIngSales($startMonth = '',$outlet_id='',$top_less='') {
        $this->db->select('sum(consumption) as totalQty,sum(consumption*consumption_unit_cost) cost_of_sales,tbl_ingredients.name as menu_name,tbl_units.unit_name as unit_name');
        $this->db->from('tbl_sale_consumptions_of_menus');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_consumptions_of_menus.sales_id', 'left');
        $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_menus.ingredient_id', 'left');
        $this->db->join('tbl_units', 'tbl_units.id = tbl_ingredients.unit_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sale_consumptions_of_menus.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_consumptions_of_menus.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->order_by('consumption', $top_less);
        $this->db->group_by('tbl_sale_consumptions_of_menus.ingredient_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
   
    public function getAllPurchasePaymentZreport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(paid) as total_amount,tbl_payment_methods.name,payment_id');
        $this->db->from('tbl_purchase');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_purchase.payment_id', 'left');
        $this->db->where('date', $startMonth);
        $this->db->where('tbl_purchase.outlet_id', $outlet_id);
        $this->db->where('tbl_purchase.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function getAllExpensePaymentZreport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(amount) as total_amount,tbl_payment_methods.name,payment_id');
        $this->db->from('tbl_expenses');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_expenses.payment_id', 'left');
        $this->db->where('date', $startMonth);
        $this->db->where('tbl_expenses.outlet_id', $outlet_id);
        $this->db->where('tbl_expenses.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function getAllSupplierPaymentZreport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(amount) as total_amount,tbl_payment_methods.name,payment_id');
        $this->db->from('tbl_supplier_payments');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_supplier_payments.payment_id', 'left');
        $this->db->where('date', $startMonth);
        $this->db->where('tbl_supplier_payments.outlet_id', $outlet_id);
        $this->db->where('tbl_supplier_payments.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function getAllCustomerDueReceiveZreport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(amount) as total_amount,tbl_payment_methods.name,payment_id');
        $this->db->from('tbl_customer_due_receives');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_customer_due_receives.payment_id', 'left');
        $this->db->where('only_date', $startMonth);
        $this->db->where('tbl_customer_due_receives.outlet_id', $outlet_id);
        $this->db->where('tbl_customer_due_receives.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function getAllOtherSalePaymentZReport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(amount) as total_amount,multi_currency,payment_id');
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sale_payments.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_payments.currency_type', 1);
        $this->db->where('tbl_sale_payments.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function totalModifierIngSales($startMonth = '',$outlet_id='',$top_less='') {
        $this->db->select('sum(consumption) as totalQty,sum(consumption*consumption_unit_cost) cost_of_sales,tbl_ingredients.name as menu_name,tbl_units.unit_name as unit_name');
        $this->db->from('tbl_sale_consumptions_of_modifiers_of_menus');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_consumptions_of_modifiers_of_menus.sales_id', 'left');
        $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_modifiers_of_menus.ingredient_id', 'left');
        $this->db->join('tbl_units', 'tbl_units.id = tbl_ingredients.unit_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sale_consumptions_of_modifiers_of_menus.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_consumptions_of_modifiers_of_menus.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->order_by('consumption', $top_less);
        $this->db->group_by('tbl_sale_consumptions_of_modifiers_of_menus.ingredient_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function totalFoodModifierSales($startMonth = '', $outlet_id='',$top_less='') {
        $this->db->select('sum(tbl_sales_details.qty) as totalQty,sum(modifier_price) net_sales,tbl_modifiers.name as menu_name');
        $this->db->from('tbl_sales_details_modifiers');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details_modifiers.sales_id', 'left');
        $this->db->join('tbl_sales_details', 'tbl_sales_details.id = tbl_sales_details_modifiers.sales_details_id', 'left');
        $this->db->join('tbl_modifiers', 'tbl_modifiers.id = tbl_sales_details_modifiers.modifier_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sales_details_modifiers.outlet_id', $outlet_id);
        $this->db->where('tbl_sales.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->order_by('totalQty', $top_less);
        $this->db->group_by('tbl_sales_details_modifiers.modifier_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    /**
     * food Menu Sales
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    /**
     * Sales by Category (report batch R4).
     *
     * Stays a CATEGORY-LEVEL SUMMARY: the GROUP BY food_menu_id below is the point
     * of the screen. Per-invoice fields (customer, invoice no, payment method) are
     * deliberately NOT added here - they would require breaking that aggregation,
     * and the Detailed Sale Report already answers those questions per invoice.
     *
     * @param string $user_id     seller, from tbl_sales.user_id
     * @param string $start_time  clock time HH:MM, inclusive, on tbl_sales.order_time
     * @param string $end_time    clock time HH:MM, inclusive
     * @param array  $outlet_ids  outlet scope; empty falls back to $outlet_id
     */
    //$waiter_id is appended last, and optional, so the existing positional call
    //is unaffected and an unselected waiter leaves the query exactly as it was.
    public function foodMenuSaleByCategories($startMonth = '', $endMonth = '',$outlet_id='',$cat_id='',$user_id='',$start_time='',$end_time='',$outlet_ids=array(),$waiter_id='') {
        $this->db->select('sum(qty) as totalQty,food_menu_id,menu_name,code,sale_date,tbl_food_menu_categories.category_name,tbl_food_menus.sale_price as menu_unit_price');
        $this->db->from('tbl_sales_details');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
        $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
        $this->db->join('tbl_food_menu_categories', 'tbl_food_menu_categories.id = tbl_food_menus.category_id', 'left');

        if ($startMonth != '' && $endMonth != '') {
            $this->db->where('sale_date>=', $startMonth);
            $this->db->where('sale_date <=', $endMonth);
        }
        if ($startMonth != '' && $endMonth == '') {
            $this->db->where('sale_date', $startMonth);
        }
        if ($startMonth == '' && $endMonth != '') {
            $this->db->where('sale_date', $endMonth);
        }
        if ($cat_id!= '') {
            $this->db->where('tbl_food_menus.category_id', $cat_id);
        }
        //R4: seller
        if ($user_id !== '' && $user_id !== NULL) {
            $this->db->where('tbl_sales.user_id', $user_id);
        }
        //waiter who took the order, as distinct from the user who rang it up.
        //Same column and same guard shape the Detailed Sale Report already uses,
        //so the two reports agree on what "waiter" means. Independent of the
        //seller filter above - both can be set, and neither is applied when
        //left blank, which keeps the unfiltered query identical to before.
        if ($waiter_id !== '' && $waiter_id !== NULL) {
            $this->db->where('tbl_sales.waiter_id', $waiter_id);
        }
        //R4: time-of-day range on order_time. CLOCK time, deliberately independent
        //of the date range above, which filters sale_date (the BUSINESS day).
        if ($start_time !== '' && $start_time !== NULL) {
            $this->db->where('tbl_sales.order_time >=', $start_time);
        }
        if ($end_time !== '' && $end_time !== NULL) {
            $this->db->where('tbl_sales.order_time <=', $end_time);
        }
        //R4: outlet scope as a list, so one outlet and All Outlets share the SQL
        if ($outlet_ids) {
            $this->db->where_in('tbl_sales_details.outlet_id', $outlet_ids);
        } else {
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
        }
        $this->db->where('tbl_sales_details.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->order_by('totalQty', 'DESC');
        $this->db->group_by('tbl_sales_details.food_menu_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    /**
     * food Menu Sales
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */

    /**
     * food Menu Sales
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function foodMenuSaleDetailsByCategories($startMonth = '', $endMonth = '',$outlet_id='',$cat_id='') {
        $this->db->select('sum(qty) as totalQty,food_menu_id,menu_name,code,sale_date,tbl_food_menu_categories.category_name,tbl_food_menus.sale_price');
        $this->db->from('tbl_sales_details');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
        $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');
        $this->db->join('tbl_food_menu_categories', 'tbl_food_menu_categories.id = tbl_food_menus.category_id', 'left');

        if ($startMonth != '' && $endMonth != '') {
            $this->db->where('sale_date>=', $startMonth);
            $this->db->where('sale_date <=', $endMonth);
        }
        if ($startMonth != '' && $endMonth == '') {
            $this->db->where('sale_date', $startMonth);
        }
        if ($startMonth == '' && $endMonth != '') {
            $this->db->where('sale_date', $endMonth);
        }
        if ($cat_id!= '') {
            $this->db->where('tbl_food_menus.category_id', $cat_id);
        }
        $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
        $this->db->where('tbl_sales_details.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->order_by('totalQty', 'DESC');
        $this->db->group_by('tbl_sales_details.food_menu_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }

    /**
     * consumption Menus
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function consumptionMenus($start_date = '', $end_date = '',$outlet_id='') {
        if ($start_date || $end_date):
            $this->db->select('sum(consumption*cost) as total_consumption, sum(consumption) as total_consumption_qty, ingredient_id,tbl_ingredients.name as ingredient_name,tbl_ingredients.code as ingredient_code,tbl_ingredients.purchase_price,tbl_ingredients.conversion_rate');
            $this->db->from('tbl_sales');
            $this->db->join('tbl_sale_consumptions_of_menus', 'tbl_sale_consumptions_of_menus.sales_id = tbl_sales.id', 'inner');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_menus.ingredient_id', 'inner');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('tbl_sales.outlet_id', $outlet_id);
            $this->db->where('tbl_sales.del_status', 'Live');
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->order_by('tbl_ingredients.name', 'ASC');
            $this->db->group_by('tbl_sale_consumptions_of_menus.ingredient_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * consumption Report
     * @access public
     * @return object
     * @param string
     * @param string
     */
    public function consumptionReport($start_date = '', $end_date = '') {
        if ($start_date || $end_date):
            $outlet_id = $this->session->userdata('outlet_id');
            $this->db->select('sum(consumption) as total_consumption, ingredient_id');
            $this->db->from('tbl_sale_consumptions_of_menus');
            $this->db->join('tbl_sale_consumptions', 'tbl_sale_consumptions.id = tbl_sale_consumptions_of_menus.sale_consumption_id', 'left');
            $this->db->join('tbl_sales', 'tbl_sale_consumptions.sale_id = tbl_sales.id', 'left');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_menus.ingredient_id', 'left');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->where('tbl_sale_consumptions_of_menus.outlet_id', $outlet_id);
            $this->db->where('tbl_sale_consumptions_of_menus.del_status', 'Live');
            $this->db->order_by('tbl_ingredients.name', 'ASC');
            $this->db->group_by('tbl_sale_consumptions_of_menus.ingredient_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * consumption Modifiers
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function consumptionModifiers($start_date = '', $end_date = '',$outlet_id='') {
        if ($start_date || $end_date):
            $this->db->select('sum(consumption*cost) as total_consumption, sum(consumption) as total_consumption_qty, ingredient_id,tbl_ingredients.name as ingredient_name,tbl_ingredients.code as ingredient_code,tbl_ingredients.purchase_price,tbl_ingredients.conversion_rate');
            $this->db->from('tbl_sales');
            $this->db->join('tbl_sale_consumptions_of_modifiers_of_menus', 'tbl_sale_consumptions_of_modifiers_of_menus.sales_id = tbl_sales.id', 'inner');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_sale_consumptions_of_modifiers_of_menus.ingredient_id', 'inner');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('tbl_sales.outlet_id', $outlet_id);
            $this->db->where('tbl_sales.del_status', 'Live');
            $this->db->order_by('tbl_ingredients.name', 'ASC');
            $this->db->where('tbl_sales.order_status', 3);
            $this->db->group_by('tbl_sale_consumptions_of_modifiers_of_menus.ingredient_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * detailed SaleReport
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    /**
     * Detailed Sale Report (report batch R3).
     *
     * @param string $sale_no     invoice/order number, partial match
     * @param string $due_status  '' all, 'due' outstanding, 'paid' settled
     * @param array  $outlet_ids  outlet scope; empty falls back to $outlet_id so any
     *                            caller not yet updated keeps its old behaviour
     * @param string $payment_id   payment method; matches sales that INCLUDE it
     */
    public function detailedSaleReport($startMonth = '', $endMonth = '', $user_id = '',$outlet_id='',$waiter_id='',$sale_no='',$due_status='',$outlet_ids=array(),$payment_id='') {
        //R3: the original guard returned NOTHING unless a date or user was given, so
        //searching by invoice number alone would have silently produced an empty
        //report rather than a result. The new filters have to open the gate too.
        if ($startMonth || $endMonth || $user_id || $sale_no !== '' || $due_status !== '' || $payment_id !== ''):
            $this->db->select('tbl_sales.*,tbl_users.full_name,tbl_payment_methods.name');
            $this->db->from('tbl_sales');
            $this->db->join('tbl_users', 'tbl_users.id = tbl_sales.user_id', 'left');
            $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sales.payment_method_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }

            if ($user_id != '') {
                $this->db->where('tbl_sales.user_id', $user_id);
            }
            if ($waiter_id != '') {
                $this->db->where('tbl_sales.waiter_id', $waiter_id);
            }
            //R3: invoice/order number. Partial match, so a staff member can type the
            //tail of a number off a printed receipt without the random prefix.
            if ($sale_no !== '' && $sale_no !== NULL) {
                $this->db->like('tbl_sales.sale_no', $sale_no);
            }
            //R3: due status. due_amount is NULL on older rows, so the paid branch has
            //to accept NULL as settled rather than dropping those rows entirely.
            if ($due_status === 'due') {
                $this->db->where('tbl_sales.due_amount >', 0);
            } elseif ($due_status === 'paid') {
                $this->db->group_start();
                $this->db->where('tbl_sales.due_amount <=', 0);
                $this->db->or_where('tbl_sales.due_amount IS NULL', NULL, FALSE);
                $this->db->group_end();
            }
            //R3b: payment method. Matched via a subquery on tbl_sale_payments rather
            //than a JOIN, because a split-payment sale has several payment rows and a
            //JOIN would return that sale once per row. Same pattern as the Phase B
            //purchase filters. Semantics: sales that INCLUDE this method.
            if ($payment_id !== '' && $payment_id !== NULL) {
                $this->db->where('tbl_sales.id IN (SELECT sale_id FROM tbl_sale_payments WHERE payment_id = '
                    . (int) $payment_id . " AND del_status = 'Live')", NULL, FALSE);
            }
            $this->db->where('order_status', '3');
            //R3: outlet scope. A list, so one outlet and All Outlets run identical SQL.
            if ($outlet_ids) {
                $this->db->where_in('tbl_sales.outlet_id', $outlet_ids);
            } else {
                $this->db->where('tbl_sales.outlet_id', $outlet_id);
            }
            $this->db->where('tbl_sales.del_status', 'Live');
            $this->db->order_by('sale_date', 'ASC');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * get Last Day In Date Month
     * @access public
     * @return object
     * @param string
     */
    public function getLastDayInDateMonth($month) {
        $returnValue = 0;
        if ($month == "02") {
            $returnValue = "28";
        } elseif ($month == "01" || $month == "03" || $month == "05" || $month == "07" || $month == "08" || $month == "10" || $month == "12") {
            $returnValue = "31";
        } else {
            $returnValue = "30";
        }
        return $returnValue;
    }
    /**
     * purchase Report By Month
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    public function purchaseReportByMonth($startMonth = '', $endMonth = '', $user_id = '') {
        if ($startMonth || $endMonth || $user_id):
            $outlet_id = $this->session->userdata('outlet_id');
            $this->db->select('date,sum(grand_total) as total_payable');
            $this->db->from('tbl_purchase');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }

            if ($user_id != '') {
                $this->db->where('user_id', $user_id);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->group_by('month(date)');
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * purchaseReportByDate
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function purchaseReportByDate($startDate = '', $endDate = '',$outlet_id='') {
        if ($startDate || $endDate):
            $this->db->select('*');
            $this->db->from('tbl_purchase');

            if ($startDate != '' && $endDate != '') {
                $this->db->where('date>=', $startDate);
                $this->db->where('date <=', $endDate);
            }
            if ($startDate != '' && $endDate == '') {
                $this->db->where('date', $startDate);
            }
            if ($startDate == '' && $endDate != '') {
                $this->db->where('date', $endDate);
            }
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where('del_status', "Live");
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    public function productAnalysisReportTotal($startMonth = '', $endMonth = '',$outlet_id='',$category_id='') {
        if ($startMonth || $endMonth):
            $this->db->select('sum(menu_price_with_discount) as totalSale,sum(qty) as total_qty,food_menu_id,menu_name,code,sale_date');
            $this->db->from('tbl_sales_details');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
            $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }
            $this->db->where('tbl_food_menus.category_id', $category_id);
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
            $this->db->where('tbl_sales_details.del_status', 'Live');
            $query_result = $this->db->get();
            $result = $query_result->row();
            return $result;
        endif;
    }
    public function productAnalysisReport($startMonth = '', $endMonth = '',$outlet_id='',$category_id='') {

        if ($startMonth || $endMonth):
            $this->db->select('sum(menu_price_with_discount) as totalSale,sum(qty) as total_qty,food_menu_id,menu_name,code,sale_date,total_cost,category_id');
            $this->db->from('tbl_sales_details');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
            $this->db->join('tbl_food_menus', 'tbl_food_menus.id = tbl_sales_details.food_menu_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('sale_date>=', $startMonth);
                $this->db->where('sale_date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('sale_date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('sale_date', $endMonth);
            }
            $this->db->where('tbl_food_menus.category_id', $category_id);
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
            $this->db->where('tbl_sales_details.del_status', 'Live');
            $this->db->order_by('total_qty', 'DESC');
            $this->db->group_by('tbl_sales_details.food_menu_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * purchase Report By Ingredient
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    public function purchaseReportByIngredient($startMonth = '', $endMonth = '', $ingredient_id = '') {
        if ($startMonth || $endMonth || $ingredient_id):
            $outlet_id = $this->session->userdata('outlet_id');
            $this->db->select('sum(quantity_amount) as totalQuantity_amount,ingredient_id,tbl_ingredients.name,tbl_ingredients.code,date');
            $this->db->from('tbl_purchase_ingredients');
            $this->db->join('tbl_purchase', 'tbl_purchase.id = tbl_purchase_ingredients.purchase_id', 'left');
            $this->db->join('tbl_ingredients', 'tbl_ingredients.id = tbl_purchase_ingredients.ingredient_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            if ($ingredient_id != '') {
                $this->db->where('ingredient_id', $ingredient_id);
            }
            $this->db->where('tbl_purchase.outlet_id', $outlet_id);
            $this->db->where('tbl_purchase_ingredients.del_status', 'Live');
            $this->db->order_by('date', 'ASC');
            $this->db->group_by('tbl_purchase_ingredients.ingredient_id');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * detailed Purchase Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    public function detailedPurchaseReport($startMonth = '', $endMonth = '', $user_id = '') {
        if ($startMonth || $endMonth || $user_id):
            $outlet_id = $this->session->userdata('outlet_id');
            $this->db->select('tbl_purchase.*,tbl_users.full_name');
            $this->db->from('tbl_purchase');
            $this->db->join('tbl_users', 'tbl_users.id = tbl_purchase.user_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            if ($user_id != '') {
                $this->db->where('user_id', $user_id);
            }
            $this->db->where('tbl_purchase.outlet_id', $outlet_id);
            $this->db->where('tbl_purchase.del_status', 'Live');
            $this->db->order_by('date', 'ASC');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * waste Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function wasteReport($startMonth = '', $endMonth = '', $user_id = '',$outlet_id='') {
        if ($startMonth || $endMonth || $user_id):
            $this->db->select('tbl_wastes.*,emp.full_name as EmployeedName');
            $this->db->from('tbl_wastes');
            $this->db->join('tbl_users as emp', 'emp.id = tbl_wastes.employee_id', 'left');

            if ($startMonth != '' && $endMonth != '') {
                $this->db->where('date>=', $startMonth);
                $this->db->where('date <=', $endMonth);
            }
            if ($startMonth != '' && $endMonth == '') {
                $this->db->where('date', $startMonth);
            }
            if ($startMonth == '' && $endMonth != '') {
                $this->db->where('date', $endMonth);
            }

            // if ($user_id != '') {
            //     $this->db->where('tbl_wastes.user_id', $user_id);
            // }
            if ($user_id != '') {
                $this->db->where('tbl_wastes.employee_id', $user_id);
            }
            $this->db->where('tbl_wastes.outlet_id', $outlet_id);
            $this->db->where('tbl_wastes.del_status', 'Live');
            $this->db->order_by('date', 'ASC');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * supplier Due Report
     * @access public
     * @return object
     * @param int
     */
    public function supplierDueReport($outlet_id) {

        $this->db->select('sum(due) as totalDue,supplier_id,date,name');
        $this->db->from('tbl_purchase');
        $this->db->join('tbl_suppliers', 'tbl_suppliers.id = tbl_purchase.supplier_id', 'left');
        $this->db->order_by('totalDue desc');
        $this->db->where('tbl_purchase.outlet_id', $outlet_id);
        $this->db->where('tbl_purchase.del_status', 'Live');
        $this->db->group_by('tbl_purchase.supplier_id');
        return $this->db->get()->result();
    }
    /**
     * customer Due Report
     * @access public
     * @return object
     * @param int
     */
    public function customerDueReport($outlet_id) {
        $this->db->select('sum(due_amount) as totalDue,customer_id,sale_date,name');
        $this->db->from('tbl_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->order_by('totalDue desc');
        $this->db->where('tbl_sales.outlet_id', $outlet_id);
        $this->db->where('tbl_sales.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->group_by('tbl_sales.customer_id');
        $data =  $this->db->get()->result();
        return $data;
    }
    public function customerDueReportNew($outlet_id) {
        $this->db->select('tbl_sales.customer_id as customer_id,tbl_customers.name as name');
        $this->db->from('tbl_sales');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->where('tbl_sales.outlet_id', $outlet_id);
        $this->db->where('tbl_sales.del_status', 'Live');
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->group_by('tbl_sales.customer_id');
        $data =  $this->db->get()->result();
        return $data;
    }
    /**
     * get Register Information
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     * @param int
     */
    public function getRegisterInformation($start_date,$end_date,$user_id='',$outlet_id=''){
        $this->db->select("tbl_register.*,tbl_counters.name as counter_name");
        $this->db->from('tbl_register');
        $this->db->join('tbl_counters', 'tbl_counters.id = tbl_register.counter_id', 'left');
        if($user_id!=''){
            $this->db->where("tbl_register.user_id", $user_id);
        }
        if($outlet_id!=''){
            $this->db->where("tbl_register.outlet_id", $outlet_id);
        }
        $this->db->where("DATE(tbl_register.opening_balance_date_time)>=", $start_date);
        $this->db->where("DATE(tbl_register.opening_balance_date_time)<=", $end_date);
        $this->db->order_by('tbl_register.id', 'DESC');
        return $this->db->get()->result();
    }
    /**
     * get Users
     * @access public
     * @return object
     * @param int
     */
    public function getUsers($outlet_id){
        $result = $this->db->query("SELECT * FROM tbl_users WHERE del_status='Live' AND FIND_IN_SET('$outlet_id' , outlets)")->result();
        return $result;
    }
    /**
     * expenseReport
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     * @param int
     */
    public function expenseReport($startMonth='',$endMonth='',$category_id='',$outlet_id=''){
        if($startMonth || $endMonth || $category_id):
        $this->db->select('tbl_expenses.*,emp.full_name as EmployeedName,tbl_expense_items.name as categoryName');
        $this->db->from('tbl_expenses');
        $this->db->join('tbl_users as emp', 'emp.id = tbl_expenses.employee_id','left');
        $this->db->join('tbl_expense_items', 'tbl_expense_items.id = tbl_expenses.category_id','left');

        if($startMonth!='' && $endMonth!=''){
            $this->db->where('date>=', $startMonth);
            $this->db->where('date <=', $endMonth);
        }
        if($startMonth!='' && $endMonth==''){
            $this->db->where('date', $startMonth);
        }
        if($startMonth=='' && $endMonth!=''){
            $this->db->where('date', $endMonth);
        }

        if($category_id!=''){
            $this->db->where('tbl_expenses.category_id', $category_id);
        }
        $this->db->where('tbl_expenses.outlet_id',$outlet_id);
        $this->db->where('tbl_expenses.del_status','Live');
        $this->db->order_by('date', 'ASC');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
        endif;
    }
    /**
     * kitchen Performance Report
     * @access public
     * @return object
     * @param string
     * @param string
     * @param int
     */
    public function kitchenPerformanceReport($start_date='',$end_date='',$outlet_id=''){
        if ($start_date || $end_date):
            $this->db->select('tbl_sales_details.outlet_id,tbl_sales_details.menu_name,tbl_sales.sale_date,tbl_sales.sale_no,tbl_sales.order_type,tbl_sales.order_time,tbl_sales_details.cooking_start_time,tbl_sales_details.cooking_done_time');
            $this->db->from('tbl_sales_details');
            $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sales_details.sales_id', 'left');
           
            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('tbl_sales_details.outlet_id', $outlet_id);
            $this->db->where('tbl_sales_details.del_status', 'Live');
            $this->db->where('tbl_sales.order_status', '3');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;
        endif;
    }
    /**
     * attendanceReport
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    public function attendanceReport($start_date = '', $end_date = '', $employee_id = '') {
        if ($start_date || $end_date || $employee_id):
            $this->db->select('tbl_attendance.*, emp.full_name as employee_name');
            $this->db->from('tbl_attendance');
            $this->db->join('tbl_users as emp', 'emp.id = tbl_attendance.employee_id', 'left');

            if ($start_date != '' && $end_date != '') {
                $this->db->where('date>=', $start_date);
                $this->db->where('date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('date', $end_date);
            }

            if ($employee_id != '') {
                $this->db->where('tbl_attendance.employee_id', $employee_id);
            }

            $this->db->where('tbl_attendance.del_status', 'Live');
            $this->db->order_by('date', 'ASC');
            $query_result = $this->db->get();
            $result = $query_result->result();
            return $result;

        endif;
    }

    public function auditLogReport($startDate = '', $endDate = '',$user_id='',$event_title='',$outlet_id='') {
        $this->db->select('tbl_audit_logs.*,tbl_outlets.outlet_name');
        $this->db->from('tbl_audit_logs');
        $this->db->join('tbl_outlets', 'tbl_outlets.id = tbl_audit_logs.outlet_id', 'left');
        if ($startDate != '' && $endDate != '') {
            $this->db->where('date>=', $startDate);
            $this->db->where('date<=', $endDate);
        }
        if ($startDate != '' && $endDate == '') {
            $this->db->where('date', $startDate);
        }
        if ($startDate == '' && $endDate != '') {
            $this->db->where('date', $endDate);
        }
        if($user_id!=''){
            $this->db->where('user_id', $user_id);
        }
        if($event_title!=''){
            $this->db->where('event_title',$event_title);
        }
        if($outlet_id!=''){
            $this->db->where('outlet_id',$outlet_id);
        }
        $this->db->where('tbl_audit_logs.del_status', "Live");
        $this->db->order_by("tbl_audit_logs.id", 'asc');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }

    public function getAllSalePaymentZReport($startMonth = '',$outlet_id='') {
        $this->db->select('sum(amount) as total_amount,sum(usage_point) as usage_point,tbl_payment_methods.name,payment_id');
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sale_payments.payment_id', 'left');
        $this->db->where('sale_date', $startMonth);
        $this->db->where('tbl_sale_payments.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_payments.currency_type', null);
        $this->db->where('tbl_sale_payments.del_status', 'Live');
        $this->db->group_by('payment_id');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }


    public function getAllSaleByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(amount) as total_amount, sum(usage_point) as total_usage_point");
        $this->db->from('tbl_sale_payments');
        $this->db->join('tbl_sales', 'tbl_sales.id = tbl_sale_payments.sale_id', 'left');
        $this->db->where("tbl_sale_payments.payment_id", $payment_id);
        $this->db->where('sale_date', $date);
        $this->db->where('tbl_sale_payments.outlet_id', $outlet_id);
        $this->db->where('tbl_sale_payments.currency_type', null);
        $this->db->where('tbl_sale_payments.del_status', 'Live');
        $data =  $this->db->get()->row();
        if($payment_id!=5){
            return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
        }else{
            return (isset($data->total_usage_point) && $data->total_usage_point?$data->total_usage_point:0);
        }
    }
    public function getAllSaleReturnByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(total_refund) as total_amount");
        $this->db->from('tbl_sales');
        $this->db->where("refund_payment_id", $payment_id);
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where(("DATE(refund_date_time) = '".$date."'"));
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllSalePayment($date,$payment_id)
    {
        $user_id = $this->session->userdata('user_id');
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select("tbl_sales.paid_amount,tbl_sales.payment_method_id,tbl_sales.user_id,tbl_sales.outlet_id,tbl_payment_methods.name as payment_name");
        $this->db->from('tbl_sales');
        $this->db->join('tbl_payment_methods', 'tbl_payment_methods.id = tbl_sales.payment_method_id', 'left');
        $this->db->where("tbl_sales.user_id", $user_id);
        $this->db->where("tbl_sales.outlet_id", $outlet_id);
        $this->db->where("tbl_sales.payment_method_id", $payment_id);
        $this->db->where("tbl_sales.date_time>=", $date);
        $this->db->where("tbl_sales.date_time<=", date('Y-m-d H:i:s'));
        $this->db->where('tbl_sales.order_status', 3);
        return $this->db->get()->result();
    }
    public function getAllPurchaseByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(paid) as total_amount");
        $this->db->from('tbl_purchase');
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("payment_id", $payment_id);
        $this->db->where("Date(added_date_time)", $date);
        $this->db->where('del_status','Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllDueReceiveByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(amount) as total_amount");
        $this->db->from('tbl_customer_due_receives');
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("payment_id", $payment_id);
        $this->db->where("only_date", $date);
        $this->db->where('del_status','Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllDuePaymentByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(amount) as total_amount");
        $this->db->from('tbl_supplier_payments');
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("payment_id", $payment_id);
        $this->db->where("date", $date);
        $this->db->where('del_status','Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllExpenseByPayment($date,$payment_id,$outlet_id='')
    {
        $this->db->select("sum(amount) as total_amount");
        $this->db->from('tbl_expenses');
        $this->db->where("date", $date);
        $this->db->where("outlet_id", $outlet_id);
        $this->db->where("payment_id", $payment_id);
        $this->db->where('del_status','Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
    }
    public function getAllSaleByPaymentMultiCurrency($date,$payment_id)
    {
        $user_id = $this->session->userdata('user_id');

        $this->db->select("sum(amount) as total_amount");
        $this->db->from('tbl_sale_payments');
        $this->db->where("user_id", $user_id);
        $this->db->where("payment_id", $payment_id);
        $this->db->where("date_time	>=", $date);
        $this->db->where("date_time	<=", date('Y-m-d H:i:s'));
        $this->db->where("currency_type", 1);
        $this->db->where('del_status','Live');
        $data =  $this->db->get()->row();
        return (isset($data->total_amount) && $data->total_amount?$data->total_amount:0);
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
        $this->db->where('tbl_sales.order_status', 3);
        return $this->db->get()->result();
    }
    public function getCustomerOpeningDueByDate($customer_id,$date,$outlet_id='') {
        $customer_due = $this->db->query("SELECT SUM(due_amount) as due FROM tbl_sales WHERE customer_id=$customer_id and outlet_id=$outlet_id and del_status='Live' and sale_date<'$date' ")->row();
        $customer_payment = $this->db->query("SELECT SUM(amount) as amount FROM tbl_customer_due_receives WHERE customer_id=$customer_id and outlet_id=$outlet_id and del_status='Live' and only_date<'$date'")->row();
        $remaining_due = $customer_due->due - $customer_payment->amount;
        return $remaining_due;
    }
    public function getCustomerGrantTotalByDate($customer_id,$date,$outlet_id='') {
        $purchase_info= $this->db->query("SELECT SUM(total_payable) as total,SUM(paid_amount) as paid,SUM(due_amount) as due FROM tbl_sales WHERE customer_id=$customer_id and outlet_id=$outlet_id and del_status='Live' and sale_date='$date' ")->row();
        return $purchase_info;
    }
    public function getCustomerDuePaymentByDate($customer_id,$date,$outlet_id='') {
        $supplier_payment = $this->db->query("SELECT SUM(amount) as amount FROM tbl_customer_due_receives WHERE customer_id=$customer_id and outlet_id=$outlet_id and del_status='Live' and only_date='$date'")->row();
        $due_payment =$supplier_payment->amount;
        return $due_payment;
    }

    public function getSupplierDuePaymentByDate($supplier_id,$date,$outlet_id='') {
        $supplier_payment = $this->db->query("SELECT SUM(amount) as amount FROM tbl_supplier_payments WHERE supplier_id=$supplier_id and outlet_id=$outlet_id and del_status='Live' and date='$date'")->row();
        $due_payment =$supplier_payment->amount;
        return $due_payment;
    }
    public function getSupplierGrantTotalByDate($supplier_id,$date,$outlet_id='') {
        $purchase_info= $this->db->query("SELECT SUM(grand_total) as total,SUM(paid) as paid,SUM(due) as due FROM tbl_purchase WHERE supplier_id=$supplier_id and outlet_id=$outlet_id and del_status='Live' and date='$date' ")->row();
        return $purchase_info;
    }
    public function getSupplierOpeningDueByDate($supplier_id,$date,$outlet_id='') {
        $supplier_due = $this->db->query("SELECT SUM(due) as due FROM tbl_purchase WHERE supplier_id=$supplier_id and outlet_id=$outlet_id and del_status='Live' and date<'$date' ")->row();
        $supplier_payment = $this->db->query("SELECT SUM(amount) as amount FROM tbl_supplier_payments WHERE supplier_id=$supplier_id and outlet_id=$outlet_id and del_status='Live' and date<'$date'")->row();
        $remaining_due = $supplier_due->due - $supplier_payment->amount;
        return $remaining_due;
    }
    public function transferReport($startMonth = '', $endMonth = '',$from_outlet_id='',$to_outlet_id='') {
        $this->db->select('*');
        $this->db->from('tbl_transfer');
        if ($startMonth != '' && $endMonth != '') {
            $this->db->where('received_date>=', $startMonth);
            $this->db->where('received_date <=', $endMonth);
        }
        if ($startMonth != '' && $endMonth == '') {
            $this->db->where('received_date', $startMonth);
        }
        if ($startMonth == '' && $endMonth != '') {
            $this->db->where('received_date', $endMonth);
        }
        if ($from_outlet_id!= '') {
            $this->db->where('from_outlet_id', $from_outlet_id);
        }
        if ($to_outlet_id!= '') {
            $this->db->where('to_outlet_id', $to_outlet_id);
        }
        $this->db->where('status', '1');
        $this->db->where('del_status', 'Live');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }
    public function getTotalTransaction($start_date, $end_date,$outlet_id='') {
        if ($start_date || $end_date):
            $this->db->select('count(id) as total_transaction');
            $this->db->from('tbl_sales');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $sales = $this->db->get()->row();
            return $sales;
        endif;
    }
    public function getTotalCustomer($start_date, $end_date,$outlet_id='') {
        if ($start_date || $end_date):
            //end purchase report
            //Sales report
            $this->db->select('count(id) as total_customer');
            $this->db->from('tbl_sales');
            if ($start_date != '' && $end_date != '') {
                $this->db->where('sale_date>=', $start_date);
                $this->db->where('sale_date <=', $end_date);
            }
            if ($start_date != '' && $end_date == '') {
                $this->db->where('sale_date', $start_date);
            }
            if ($start_date == '' && $end_date != '') {
                $this->db->where('sale_date', $end_date);
            }
            $this->db->where('order_status', 3);
            $this->db->where('outlet_id', $outlet_id);
            $this->db->where("del_status", 'Live');
            $sales = $this->db->get()->row();
            return $sales;
        endif;
    }
    public function productionReport($startMonth = '', $endMonth = '') {
        $outlet_id = $this->session->userdata('outlet_id');
        $this->db->select('*');
        $this->db->from('tbl_production');
        if ($startMonth != '' && $endMonth != '') {
            $this->db->where('date>=', $startMonth);
            $this->db->where('date <=', $endMonth);
        }
        if ($startMonth != '' && $endMonth == '') {
            $this->db->where('date', $startMonth);
        }
        if ($startMonth == '' && $endMonth != '') {
            $this->db->where('date', $endMonth);
        }
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('status', '1');
        $this->db->where('del_status', 'Live');
        $query_result = $this->db->get();
        $result = $query_result->result();
        return $result;
    }

    /**
     * Header row for the shared invoice-detail popup (report batch R2).
     *
     * READ ONLY, and deliberately placed in Report_model rather than Sale_model.
     * Sale_model is loaded by the live operational controllers (Sale, Monitor,
     * Waiter, Waiter_app); adding a method there to serve a report would widen a
     * surface those screens depend on. Nothing operational loads Report_model -
     * only Report and Dashboard - so a query added here cannot reach the POS.
     *
     * Completed sales only (order_status = 3), read from tbl_sales. This is NOT
     * the running-order source: Monitor::orderDetailsAjax() reads
     * tbl_kitchen_sales because its cards come from there. Mixing the two is the
     * exact mistake that made the Phase C order-details popup return an empty
     * body, so the source is pinned to whatever the calling report lists.
     *
     * @access public
     * @return object|NULL
     * @param  int $sale_id
     */
    public function getInvoiceHeaderForPopup($sale_id) {
        $this->db->select("tbl_sales.id, tbl_sales.sale_no, tbl_sales.sale_date,
                           tbl_sales.order_time, tbl_sales.date_time,
                           tbl_sales.total_items, tbl_sales.sub_total, tbl_sales.vat,
                           tbl_sales.total_discount_amount, tbl_sales.total_payable,
                           tbl_sales.paid_amount, tbl_sales.due_amount,
                           tbl_sales.outlet_id, tbl_sales.user_id, tbl_sales.waiter_id,
                           tbl_sales.customer_id,
                           seller.full_name AS seller_name,
                           waiter.full_name AS waiter_name,
                           tbl_customers.name AS customer_name", FALSE);
        $this->db->from('tbl_sales');
        $this->db->join('tbl_users seller', 'seller.id = tbl_sales.user_id', 'left');
        $this->db->join('tbl_users waiter', 'waiter.id = tbl_sales.waiter_id', 'left');
        $this->db->join('tbl_customers', 'tbl_customers.id = tbl_sales.customer_id', 'left');
        $this->db->where('tbl_sales.id', (int) $sale_id);
        $this->db->where('tbl_sales.order_status', 3);
        $this->db->where('tbl_sales.del_status', 'Live');
        $query = $this->db->get();
        if (!$query) {
            return NULL;
        }
        return $query->row();
    }


    /**
     * REGISTER DETAILS POPUP (report batch R6). READ ONLY.
     *
     * Completely independent of Sale::registerDetailCalculationToShow(), which is
     * NOT reused and NOT parameterised. That function is bound to the live closing
     * operation: it reads session('counter_id'), takes its window from the CURRENTLY
     * open register, and computes up to now(). It cannot serve an arbitrary
     * historical register, and threading session-independence through a live
     * financial path is exactly the risk we agreed not to take.
     *
     * THE DERIVATION RULE. tbl_sales has no register_id, so a register's activity is
     * derived from counter_id + outlet_id within the register's own open/close
     * window. Verified against stored ground truth: register 1 reproduces its
     * recorded {"Cash":8127} exactly.
     *
     * KNOWN LIMIT, inherent to the schema and shared by the vendor's own close-time
     * calculation: register windows can OVERLAP on the same counter (register 13
     * fully contains 14, 17 and 20). Nothing on a payment row says which register it
     * belonged to, and user_id does not help - it records who TOOK the payment, not
     * who owns the register, so adding it makes register 20 read zero. Overlapping
     * registers therefore over-count; non-overlapping ones, the normal case, are exact.
     *
     * TWO TIMESTAMPS, deliberately. Payments are scoped by tbl_sale_payments.date_time
     * (what actually hit the drawer in the window) and orders/products by
     * tbl_sales.date_time. These legitimately disagree - register 1's sale 16 was rung
     * up inside the window but paid after close - so the payment total and the product
     * total are NOT expected to tie out, and the view labels them accordingly.
     *
     * @access public
     * @return object|NULL
     * @param  int $register_id
     */
    public function getRegisterDetailsForPopup($register_id) {
        $register_id = (int) $register_id;
        $reg = $this->db->select("tbl_register.*, tbl_counters.name AS counter_name,
                                  tbl_users.full_name AS user_name, tbl_users.email_address AS user_email", FALSE)
                        ->from("tbl_register")
                        ->join("tbl_counters", "tbl_counters.id = tbl_register.counter_id", "left")
                        ->join("tbl_users", "tbl_users.id = tbl_register.user_id", "left")
                        ->where("tbl_register.id", $register_id)
                        ->get();
        if (!$reg) { return NULL; }
        $reg = $reg->row();
        if (!isset($reg->id) || !$reg->id) { return NULL; }

        $counter = (int) $reg->counter_id;
        $outlet  = (int) $reg->outlet_id;
        $open    = $reg->opening_balance_date_time;
        //an open register runs to now; a closed one to its recorded close
        $close   = ($reg->closing_balance_date_time !== NULL && $reg->closing_balance_date_time !== "")
                 ? $reg->closing_balance_date_time : date("Y-m-d H:i:s");
        $reg->window_start = $open;
        $reg->window_end   = $close;
        $reg->outlet_name  = getOutletNameById($outlet);

        $o = $this->db->escape($open);
        $c = $this->db->escape($close);

        //--- payment breakdown: Sell per method, scoped by PAYMENT timestamp ------
        $sql = "SELECT pm.id AS payment_id, pm.name AS method,
                       COALESCE(SUM(sp.amount),0) AS sell
                  FROM tbl_payment_methods pm
                  LEFT JOIN tbl_sale_payments sp
                         ON sp.payment_id = pm.id
                        AND sp.counter_id = $counter AND sp.outlet_id = $outlet
                        AND sp.date_time >= $o AND sp.date_time <= $c
                        AND sp.currency_type IS NULL AND sp.del_status = 'Live'
                 WHERE pm.del_status = 'Live'
                 GROUP BY pm.id, pm.name ORDER BY pm.id";
        $q = $this->db->query($sql);
        $reg->payments = $q ? $q->result() : array();

        //--- expense per method. tbl_expenses carries counter_id and outlet_id but is
        //dated by DAY, not datetime, so a register covering part of a day cannot be
        //split precisely. Recorded rather than presented as if it were exact.
        $sql = "SELECT payment_id, COALESCE(SUM(amount),0) AS expense
                  FROM tbl_expenses
                 WHERE counter_id = $counter AND outlet_id = $outlet
                   AND date >= DATE($o) AND date <= DATE($c)
                   AND del_status = 'Live'
                 GROUP BY payment_id";
        $q = $this->db->query($sql);
        $exp = array();
        if ($q) { foreach ($q->result() as $r) { $exp[$r->payment_id] = (float) $r->expense; } }
        foreach ($reg->payments as $p) {
            $p->expense = isset($exp[$p->payment_id]) ? $exp[$p->payment_id] : 0;
        }

        //--- order counts, scoped by SALE timestamp ------------------------------
        $sql = "SELECT COUNT(*) AS total_orders,
                       SUM(CASE WHEN order_status = 3 THEN 1 ELSE 0 END) AS completed_orders,
                       COALESCE(SUM(CASE WHEN order_status = 3 THEN total_payable ELSE 0 END),0) AS orders_value,
                       COALESCE(SUM(CASE WHEN due_amount > 0 THEN due_amount ELSE 0 END),0) AS credit_sales
                  FROM tbl_sales
                 WHERE counter_id = $counter AND outlet_id = $outlet
                   AND date_time >= $o AND date_time <= $c
                   AND del_status = 'Live'";
        $q = $this->db->query($sql);
        $counts = $q ? $q->row() : NULL;
        $reg->total_orders     = $counts ? (int) $counts->total_orders : 0;
        $reg->completed_orders = $counts ? (int) $counts->completed_orders : 0;
        $reg->orders_value     = $counts ? (float) $counts->orders_value : 0;
        $reg->credit_sales     = $counts ? (float) $counts->credit_sales : 0;

        //--- day-granular activity totals ---------------------------------------
        $day_tables = array("tbl_purchase"              => "purchase_total",
                            "tbl_customer_due_receives" => "due_receive_total",
                            "tbl_supplier_payments"     => "supplier_payment_total",
                            "tbl_expenses"              => "expense_total");
        foreach ($day_tables as $table => $prop) {
            $col = ($table === "tbl_purchase") ? "grand_total" : "amount";
            $sql = "SELECT COALESCE(SUM($col),0) AS t FROM $table
                     WHERE counter_id = $counter AND outlet_id = $outlet
                       AND date >= DATE($o) AND date <= DATE($c) AND del_status = 'Live'";
            $q = $this->db->query($sql);
            $reg->$prop = $q ? (float) $q->row()->t : 0;
        }

        //total sales as DERIVED from payments, shown beside the register's own
        //stored sale_paid_amount so any divergence is visible and explained
        $derived = 0;
        foreach ($reg->payments as $p) { $derived += (float) $p->sell; }
        $reg->derived_total_sales = $derived;
        return $reg;
    }
    /**
     * Products sold inside a register's window (report batch R6). READ ONLY.
     *
     * Scoped by tbl_sales.date_time - see the timestamp note on
     * getRegisterDetailsForPopup(). Grouped by item, and again by CATEGORY: this
     * schema has no brand concept at all (no column, no table anywhere), and
     * category is the meaningful analogue.
     *
     * @access public
     * @return array
     * @param  int $register_id
     */
    public function getRegisterProductsSold($register_id) {
        $register_id = (int) $register_id;
        $empty = array("items" => array(), "categories" => array());
        $reg = $this->db->select("counter_id, outlet_id, opening_balance_date_time, closing_balance_date_time")
                        ->from("tbl_register")->where("id", $register_id)->get();
        if (!$reg) { return $empty; }
        $reg = $reg->row();
        if (!isset($reg->counter_id)) { return $empty; }

        $counter = (int) $reg->counter_id;
        $outlet  = (int) $reg->outlet_id;
        $o = $this->db->escape($reg->opening_balance_date_time);
        $c = $this->db->escape(($reg->closing_balance_date_time !== NULL && $reg->closing_balance_date_time !== "")
             ? $reg->closing_balance_date_time : date("Y-m-d H:i:s"));

        $from = "FROM tbl_sales s
                 JOIN tbl_sales_details sd ON sd.sales_id = s.id AND sd.del_status = 'Live'
                 LEFT JOIN tbl_food_menus fm ON fm.id = sd.food_menu_id
                 LEFT JOIN tbl_food_menu_categories fmc ON fmc.id = fm.category_id
                WHERE s.counter_id = $counter AND s.outlet_id = $outlet
                  AND s.date_time >= $o AND s.date_time <= $c
                  AND s.order_status = 3 AND s.del_status = 'Live'";

        $q = $this->db->query("SELECT fm.code AS code, sd.menu_name AS product,
                                      SUM(sd.qty) AS qty,
                                      SUM(sd.menu_price_with_discount) AS total_amount
                               $from GROUP BY fm.code, sd.menu_name ORDER BY total_amount DESC");
        $items = $q ? $q->result() : array();

        $q = $this->db->query("SELECT COALESCE(fmc.category_name,'-') AS category,
                                      SUM(sd.qty) AS qty,
                                      SUM(sd.menu_price_with_discount) AS total_amount
                               $from GROUP BY category ORDER BY total_amount DESC");
        $cats = $q ? $q->result() : array();

        return array("items" => $items, "categories" => $cats);
    }
}

