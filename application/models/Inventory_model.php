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
  # This is Inventory_model Model
  ###########################################################
 */
class Inventory_model extends CI_Model {

    /**
     * get Data By Cat Id
     * @access public
     * @return object
     * @param int
     * @param string
     */
    public function getDataByCatId($cat_id, $table_name) {
        $this->db->select("id,name,code");
        $this->db->from($table_name);
        $this->db->where("category_id", $cat_id);
        $this->db->order_by("name", "ASC");
        $this->db->where("del_status", 'Live');
        return $this->db->get()->result();
    }
    /**
     * get Inventory
     * @access public
     * @return object
     * @param string
     * @param string
     * @param string
     */
    /**
     * get Inventory
     *
     * @param string $category_id
     * @param string $ingredient_id
     * @param string $food_id
     * @param array  $outlet_ids Phase F: outlets to aggregate over. Empty means
     *                           "the outlet in session", preserving the original
     *                           single-outlet behaviour for any caller that has
     *                           not been updated.
     */
    public function getInventory($category_id = "", $ingredient_id = "", $food_id = "", $outlet_ids = array()) {

        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');

        //Phase F: every ledger subquery below matched a single outlet_id. They now
        //match a LIST, so one outlet and "All Outlets" run through identical SQL.
        //Transfers net to zero across a complete set (verified in the F0 harness:
        //250 purchased - 100 out + 100 in - 10 sold = 240 company wide). Over a
        //PARTIAL set - a user who can see some outlets but not others - a transfer
        //leaving the visible set shows as an out with no matching in, which is the
        //correct reading: that stock is no longer theirs to see.
        $outlet_list = $outlet_ids ? outletScopeSqlList($outlet_ids) : (int) $outlet_id;

        $where = '';
        $where1 = '';
        if($food_id!=''){
            $food = foodMenuRow($food_id);
            if($food->product_type==2){
                $getFMIds = $food->combo_ids;
            }else{
                $getFMIds = $food_id;
            }
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
            $result = $this->db->query("SELECT ingr_tbl.*,i.food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name, ingr_unit_tbl2.unit_name as unit_name2, 
                    (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND del_status='Live') total_purchase, 
                    (select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND del_status='Live') total_consumption,
                    (select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND  del_status='Live') total_modifiers_consumption,
                    (select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND tbl_waste_ingredients.del_status='Live') total_waste,
                    (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
                    (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.ingredient_id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
                    (select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
                    (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND  tbl_transfer_ingredients.status=1 AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
                    (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
                    (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
                    (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2
                    FROM tbl_food_menus_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.ingredient_id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id  LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id WHERE FIND_IN_SET(`food_menu_id`, '$getFMIds') AND i.company_id= '$company_id' AND i.del_status='Live' $where  GROUP BY i.ingredient_id")->result();
            return $result;
        }else{
            $result = $this->db->query("SELECT ingr_tbl.*,i.id as food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name, ingr_unit_tbl2.unit_name as unit_name2, 
                    (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND del_status='Live') total_purchase, 
                    (select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND del_status='Live') total_consumption,
                    (select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  del_status='Live') total_modifiers_consumption,
                    (select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND tbl_waste_ingredients.del_status='Live') total_waste,
                    (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
                    (select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
                    (select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
                    (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND tbl_transfer_ingredients.status=1  AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
                    (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
                    (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
                    (select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2
                    FROM tbl_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id  WHERE i.company_id= '$company_id' AND i.del_status='Live' $where1 GROUP BY i.id")->result();

            return $result;
        }

    }
    public function getCurrentInventory($ingredient_id) {

        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');

        $where1 = '';
        if($ingredient_id!=''){
            $where1.= "  AND i.id = '$ingredient_id'";
        }
        $value = $this->db->query("SELECT ingr_tbl.*,i.id as food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name, ingr_unit_tbl2.unit_name as unit_name2, (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.id AND outlet_id=$outlet_id AND del_status='Live') total_purchase, 
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
        FROM tbl_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id  WHERE i.company_id= '$company_id' AND i.del_status='Live' $where1 GROUP BY i.id")->row();

        $conversion_rate = (int)$value->conversion_rate?$value->conversion_rate:1;
        $new_stock = 0;
        if($value->id):
            $totalStock = stockClosingBalance($value);
            if($value->conversion_rate==0 || $value->conversion_rate==''){
                $total_sale_unit = isset($value->conversion_rate) && (int)$value->conversion_rate?(int)($totalStock/1):'0';
            }else{
                $total_sale_unit = isset($value->conversion_rate) && (int)$value->conversion_rate?(int)($totalStock/$value->conversion_rate):'0';
            }
            $new_stock = (float)($total_sale_unit.".".$totalStock%$conversion_rate);
        endif;
        $return_array = array();
        $return_array['total_stock'] = $new_stock;
        $return_array['stock_unit'] = $value->unit_name2;
        return $return_array;

    }
    public function getInventoryFoodMenu($food_id = "",$category_id='') {

        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');
        $where = '';
        $where1 = '';
        if($food_id!=''){
            $getFMIds = $food_id;
        }else{
            $getFMIds = getFMIds($outlet_id);
        }
        if($category_id!=''){
            $where1.= "  AND i.category_id = '$category_id'";
        }
        //get selected food menu ids
        $result = $this->db->query("SELECT i.*,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND  tbl_transfer_ingredients.status=1 AND tbl_transfer_ingredients.transfer_type=2) total_transfer_plus_2,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=2) total_transfer_minus_2,
        (select SUM(qty) from tbl_sales_details  where food_menu_id=i.id AND outlet_id=$outlet_id AND del_status='Live') sale_total
         FROM tbl_food_menus i  WHERE FIND_IN_SET(`id`, '$getFMIds') AND i.company_id= '$company_id' AND i.del_status='Live' $where1")->result();
        return $result;
    }
    public function checkInventory($food_id = "") {

        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');
        //get selected food menu ids
        $result = $this->db->query("SELECT i.*,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND  tbl_transfer_ingredients.status=1 AND tbl_transfer_ingredients.transfer_type=2) total_transfer_plus_2,
        (select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id=$outlet_id AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=2) total_transfer_minus_2,
        (select SUM(qty) from tbl_sales_details  where food_menu_id=i.id AND outlet_id=$outlet_id AND del_status='Live') sale_total
         FROM tbl_food_menus i  WHERE id='$food_id' AND i.company_id= '$company_id' AND i.del_status='Live'")->row();
        return $result;
    }
    /**
     * get Inventory Alert List
     * @access public
     * @return object
     * @param no
     */
    /**
     * get Inventory Alert List
     *
     * @param array $outlet_ids Phase F: outlets to aggregate over. Empty keeps the
     *                          original single-outlet (session) behaviour.
     */
    public function getInventoryAlertList($outlet_ids = array()) {
        $outlet_id = $this->session->userdata('outlet_id');
        $company_id = $this->session->userdata('company_id');
        $outlet_list = $outlet_ids ? outletScopeSqlList($outlet_ids) : (int) $outlet_id;

        $where = '';

        $result = $this->db->query("SELECT ingr_tbl.*,i.id as food_menu_id,ingr_cat_tbl.category_name,ingr_unit_tbl.unit_name,ingr_unit_tbl2.unit_name as unit_name2, (select SUM(quantity_amount) from tbl_purchase_ingredients where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND del_status='Live') total_purchase, 
(select SUM(consumption) from tbl_sale_consumptions_of_menus where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND del_status='Live') total_consumption,
(select SUM(consumption) from tbl_sale_consumptions_of_modifiers_of_menus where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  del_status='Live') total_modifiers_consumption,
(select SUM(waste_amount) from tbl_waste_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND tbl_waste_ingredients.del_status='Live') total_waste,
(select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Plus') total_consumption_plus,
(select SUM(consumption_amount) from tbl_inventory_adjustment_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_inventory_adjustment_ingredients.del_status='Live' AND  tbl_inventory_adjustment_ingredients.consumption_status='Minus') total_consumption_minus,
(select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
(select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND  tbl_transfer_ingredients.status=1 AND tbl_transfer_ingredients.transfer_type=1) total_transfer_plus,
(select SUM(quantity_amount) from tbl_production_ingredients  where ingredient_id=i.id AND outlet_id IN ($outlet_list) AND  tbl_production_ingredients.del_status='Live' AND tbl_production_ingredients.status=1) total_production,
(select SUM(quantity_amount) from tbl_transfer_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_ingredients.del_status='Live' AND (tbl_transfer_ingredients.status=1) AND tbl_transfer_ingredients.transfer_type=1) total_transfer_minus,
(select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND to_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND  tbl_transfer_received_ingredients.status=1) total_transfer_plus_2,
(select SUM(quantity_amount) from tbl_transfer_received_ingredients  where ingredient_id=i.id AND from_outlet_id IN ($outlet_list) AND  tbl_transfer_received_ingredients.del_status='Live' AND (tbl_transfer_received_ingredients.status=1)) total_transfer_minus_2

FROM tbl_ingredients i  LEFT JOIN (select * from tbl_ingredients where del_status='Live') ingr_tbl ON ingr_tbl.id = i.id LEFT JOIN (select * from tbl_ingredient_categories where del_status='Live') ingr_cat_tbl ON ingr_cat_tbl.id = ingr_tbl.category_id LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl ON ingr_unit_tbl.id = ingr_tbl.unit_id  LEFT JOIN (select * from tbl_units where del_status='Live') ingr_unit_tbl2 ON ingr_unit_tbl2.id = ingr_tbl.purchase_unit_id WHERE  i.company_id= '$company_id' AND i.del_status='Live' $where  GROUP BY i.id")->result();

        return $result;
    }
    /**
     * COMMITTED STOCK: quantity already promised to placed but uncompleted orders.
     *
     * Phase F item 14. Stock deduction already works, but only at sale COMPLETION:
     * consumption rows are written by Save(), add_sale_by_ajax_split() and
     * push_online(), and NOT by add_kitchen_sale_by_ajax(). So between placement and
     * payment the ledger still counts that stock as on hand - in a lounge with tabs
     * open for hours, a large and persistent overstatement. This closes the timing
     * gap WITHOUT touching the audit ledger: committed is computed separately and
     * subtracted at display time, so cancellation needs no reversal (a cancelled
     * order simply stops being counted once its rows are gone).
     *
     * Mirrors the three branches of the consumption writer in Sale::push_online()
     * (Sale.php:2393 / 2409 / 2425) exactly, including one deliberate quirk:
     *
     *   THE MISSING del_status. The writer looks up tbl_food_menus_ingredients and
     *   tbl_ingredients(food_id) with NO del_status filter, so a soft deleted recipe
     *   line still consumes stock. That omission is reproduced here ON PURPOSE. If
     *   this query filtered del_status and the writer did not, committed and actual
     *   consumption would disagree and available stock would drift permanently.
     *   It is recorded as a separate defect - fix both together or neither.
     *   Combos are the exception: the writer DOES filter del_status on
     *   tbl_combo_food_menus, so this does too.
     *
     * "Live order" is defined exactly as the Running Order and Table screens define
     * it (Sale_model:520-521, 613-614): order_status IN (1,2) and future_sale_status
     * IN (1,3). Future dated orders (future_sale_status = 2) are therefore NOT
     * committed - an order for next week must not hold today's stock.
     *
     * @access public
     * @return array ingredient_id => committed quantity, in CONSUMPTION units
     * @param  array  $outlet_ids      Phase F2 outlet scope; empty means the session outlet
     * @param  string $exclude_sale_no Phase F5: omit this order's own contribution.
     *        add_kitchen_sale_by_ajax() handles MODIFICATIONS as well as new orders, and
     *        an existing running order is already counted here. Checking a modification
     *        against a committed figure that includes the order itself would double count
     *        it and refuse a legitimate edit - reducing an order's quantity would fail
     *        the stock check. Pass the sale_no being written to take it out of the total.
     */
    public function getCommittedStock($outlet_ids = array(), $exclude_sale_no = '') {
        $outlet_list = $outlet_ids
            ? outletScopeSqlList($outlet_ids)
            : (int) $this->session->userdata('outlet_id');

        $exclude = '';
        if ($exclude_sale_no !== '' && $exclude_sale_no !== NULL) {
            $exclude = " AND ks.sale_no <> " . $this->db->escape($exclude_sale_no);
        }

        //shared predicate: which ORDERS count. Distinct from the recipe lookup
        //quirk above - this is about row existence, not about the writer's omission.
        $live = "ksd.outlet_id IN ($outlet_list)
                 AND ksd.del_status = 'Live' AND ks.del_status = 'Live'
                 AND ks.order_status IN (1,2) AND ks.future_sale_status IN (1,3)"
                 . $exclude;

        $sql = "SELECT ingredient_id, SUM(committed_qty) AS committed_qty FROM (

                    /* product_type 1: recipe based */
                    SELECT fmi.ingredient_id AS ingredient_id,
                           ksd.qty * fmi.consumption AS committed_qty
                      FROM tbl_kitchen_sales_details ksd
                      JOIN tbl_kitchen_sales ks           ON ks.id = ksd.sales_id
                      JOIN tbl_food_menus fm              ON fm.id = ksd.food_menu_id
                      JOIN tbl_food_menus_ingredients fmi ON fmi.food_menu_id = ksd.food_menu_id
                     WHERE $live AND fm.product_type = 1

                    UNION ALL

                    /* product_type 3: direct sale product, consumed 1:1 */
                    SELECT i.id, ksd.qty
                      FROM tbl_kitchen_sales_details ksd
                      JOIN tbl_kitchen_sales ks ON ks.id = ksd.sales_id
                      JOIN tbl_food_menus fm    ON fm.id = ksd.food_menu_id
                      JOIN tbl_ingredients i    ON i.food_id = ksd.food_menu_id
                     WHERE $live AND fm.product_type = 3

                    UNION ALL

                    /* everything else: combo, expanded through each member's recipe */
                    SELECT fmi.ingredient_id,
                           (ksd.qty * cfm.quantity) * fmi.consumption
                      FROM tbl_kitchen_sales_details ksd
                      JOIN tbl_kitchen_sales ks           ON ks.id = ksd.sales_id
                      JOIN tbl_food_menus fm              ON fm.id = ksd.food_menu_id
                      JOIN tbl_combo_food_menus cfm       ON cfm.food_menu_id = ksd.food_menu_id
                                                         AND cfm.del_status = 'Live'
                      JOIN tbl_food_menus_ingredients fmi ON fmi.food_menu_id = cfm.added_food_menu_id
                     WHERE $live AND fm.product_type NOT IN (1,3)

                ) committed
                GROUP BY ingredient_id";

        $query = $this->db->query($sql);
        $out = array();
        if ($query) {
            foreach ($query->result() as $row) {
                $out[$row->ingredient_id] = (float) $row->committed_qty;
            }
        }
        return $out;
    }
    /**
     * RECIPE REQUIREMENTS: how much of each item one unit of a food menu consumes.
     *
     * Same three branches as getCommittedStock(), and the same deliberate del_status
     * omission, so "what a sale will consume" and "what an open order has committed"
     * are computed by one consistent rule. Feeds the per item availability figure
     * that reaches window.items on the sale screen.
     *
     * A food menu with NO rows here consumes nothing and can never be constrained by
     * stock - a real limitation of the F5 block, not a bug in this query.
     *
     * @access public
     * @return array food_menu_id => array(ingredient_id => qty consumed per unit)
     * @param  int $company_id
     */
    public function getRecipeRequirements($company_id) {
        $company_id = (int) $company_id;
        $sql = "SELECT food_menu_id, ingredient_id, SUM(qty_per_unit) AS qty_per_unit FROM (

                    SELECT fm.id AS food_menu_id, fmi.ingredient_id AS ingredient_id,
                           fmi.consumption AS qty_per_unit
                      FROM tbl_food_menus fm
                      JOIN tbl_food_menus_ingredients fmi ON fmi.food_menu_id = fm.id
                     WHERE fm.company_id = $company_id AND fm.product_type = 1

                    UNION ALL

                    SELECT fm.id, i.id, 1
                      FROM tbl_food_menus fm
                      JOIN tbl_ingredients i ON i.food_id = fm.id
                     WHERE fm.company_id = $company_id AND fm.product_type = 3

                    UNION ALL

                    SELECT fm.id, fmi.ingredient_id, cfm.quantity * fmi.consumption
                      FROM tbl_food_menus fm
                      JOIN tbl_combo_food_menus cfm       ON cfm.food_menu_id = fm.id
                                                         AND cfm.del_status = 'Live'
                      JOIN tbl_food_menus_ingredients fmi ON fmi.food_menu_id = cfm.added_food_menu_id
                     WHERE fm.company_id = $company_id AND fm.product_type NOT IN (1,3)

                ) req
                GROUP BY food_menu_id, ingredient_id";

        $query = $this->db->query($sql);
        $out = array();
        if ($query) {
            foreach ($query->result() as $row) {
                if (!isset($out[$row->food_menu_id])) {
                    $out[$row->food_menu_id] = array();
                }
                $out[$row->food_menu_id][$row->ingredient_id] = (float) $row->qty_per_unit;
            }
        }
        return $out;
    }
    /**
     * get All By Company Id For Dropdown
     * @access public
     * @return object
     * @param int
     * @param string
     */
    public function getAllByCompanyIdForDropdown($company_id, $table_name) {
        $result = $this->db->query("SELECT *
          FROM $table_name
          WHERE company_id=$company_id AND del_status = 'Live'
          ORDER BY name ASC")->result();
        return $result;
    }
    /**
     * get the item list the stock report runs over
     * @access public
     * @return object
     * @param int
     * @param array
     */
    public function getStockReportItems($company_id, $filters = array()) {
        $sql = "SELECT i.id, i.code, i.name, i.category_id, i.conversion_rate,
                       cat.category_name,
                       cons_unit.unit_name AS consumption_unit_name,
                       purch_unit.unit_name AS purchase_unit_name
                  FROM tbl_ingredients i
                  LEFT JOIN tbl_ingredient_categories cat ON cat.id = i.category_id
                  LEFT JOIN tbl_units cons_unit  ON cons_unit.id  = i.unit_id
                  LEFT JOIN tbl_units purch_unit ON purch_unit.id = i.purchase_unit_id
                 WHERE i.company_id = ? AND i.del_status = 'Live'";
        $binds = array($company_id);
        if (isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== NULL) {
            $sql .= " AND i.category_id = ?";
            $binds[] = $filters['category_id'];
        }
        if (isset($filters['ingredient_id']) && $filters['ingredient_id'] !== '' && $filters['ingredient_id'] !== NULL) {
            $sql .= " AND i.id = ?";
            $binds[] = $filters['ingredient_id'];
        }
        $sql .= " ORDER BY i.name ASC";
        return $this->db->query($sql, $binds)->result();
    }
    /**
     * run one ledger source and return per ingredient opening/period sums.
     * opening  = everything strictly before the period start
     * period   = everything inside the period
     * @access private
     * @return array
     */
    private function stockLedgerSource($date_expr, $from_and_where, $qty_expr, $start_date, $end_date, $binds) {
        $sql = "SELECT src.ingredient_id AS iid,
                       SUM(CASE WHEN $date_expr <  ? THEN $qty_expr ELSE 0 END) AS opening_qty,
                       SUM(CASE WHEN $date_expr >= ? AND $date_expr <= ? THEN $qty_expr ELSE 0 END) AS period_qty
                  $from_and_where AND $date_expr <= ?
                 GROUP BY src.ingredient_id";
        $all_binds = array_merge(array($start_date, $start_date, $end_date), $binds, array($end_date));
        $rows = $this->db->query($sql, $all_binds)->result();
        $out = array();
        foreach ($rows as $row) {
            $out[$row->iid] = array(
                'opening' => (float) $row->opening_qty,
                'period'  => (float) $row->period_qty,
            );
        }
        return $out;
    }
    /**
     * get Stock Ledger: opening balance, period movements and closing balance
     * per ingredient, for any historical date range.
     *
     * Everything is returned in CONSUMPTION units. Purchases, transfers and
     * production are stored in purchase units so they are multiplied by
     * conversion_rate; consumption, waste and adjustments are already in
     * consumption units.
     *
     * Audit numbers count completed sales only (tbl_sales.order_status = 3),
     * matching the consumption and profit/loss reports. Open orders are
     * reported separately by getUnpostedStockImpact().
     *
     * @access public
     * @return array
     * @param string
     * @param string
     * @param int
     * @param int
     * @param array
     */
    public function getStockLedger($start_date, $end_date, $outlet_ids, $company_id, $filters = array()) {
        $outlet_in = outletScopeSqlList($outlet_ids);
        $items = $this->getStockReportItems($company_id, $filters);

        //one grouped query per ledger source, so cost does not grow with the
        //number of items on the report
        $purchase_in = $this->stockLedgerSource(
            'p.date', "FROM tbl_purchase_ingredients src
                       JOIN tbl_purchase p ON p.id = src.purchase_id
                      WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND p.del_status = 'Live'",
            'src.quantity_amount', $start_date, $end_date, array());

        $sale_out = $this->stockLedgerSource(
            's.sale_date', "FROM tbl_sale_consumptions_of_menus src
                            JOIN tbl_sales s ON s.id = src.sales_id
                           WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live'
                             AND s.del_status = 'Live' AND s.order_status = 3",
            'src.consumption', $start_date, $end_date, array());

        $modifier_out = $this->stockLedgerSource(
            's.sale_date', "FROM tbl_sale_consumptions_of_modifiers_of_menus src
                            JOIN tbl_sales s ON s.id = src.sales_id
                           WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live'
                             AND s.del_status = 'Live' AND s.order_status = 3",
            'src.consumption', $start_date, $end_date, array());

        $waste_out = $this->stockLedgerSource(
            'w.date', "FROM tbl_waste_ingredients src
                       JOIN tbl_wastes w ON w.id = src.waste_id
                      WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND w.del_status = 'Live'",
            'src.waste_amount', $start_date, $end_date, array());

        $adjust_plus = $this->stockLedgerSource(
            'a.date', "FROM tbl_inventory_adjustment_ingredients src
                       JOIN tbl_inventory_adjustment a ON a.id = src.inventory_adjustment_id
                      WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND a.del_status = 'Live'
                        AND src.consumption_status = 'Plus'",
            'src.consumption_amount', $start_date, $end_date, array());

        $adjust_minus = $this->stockLedgerSource(
            'a.date', "FROM tbl_inventory_adjustment_ingredients src
                       JOIN tbl_inventory_adjustment a ON a.id = src.inventory_adjustment_id
                      WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND a.del_status = 'Live'
                        AND src.consumption_status = 'Minus'",
            'src.consumption_amount', $start_date, $end_date, array());

        $transfer_in = $this->stockLedgerSource(
            't.date', "FROM tbl_transfer_ingredients src
                       JOIN tbl_transfer t ON t.id = src.transfer_id
                      WHERE src.to_outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND t.del_status = 'Live'
                        AND src.transfer_type = 1 AND src.status = 1",
            'src.quantity_amount', $start_date, $end_date, array());

        $transfer_out = $this->stockLedgerSource(
            't.date', "FROM tbl_transfer_ingredients src
                       JOIN tbl_transfer t ON t.id = src.transfer_id
                      WHERE src.from_outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND t.del_status = 'Live'
                        AND src.transfer_type = 1 AND src.status = 1",
            'src.quantity_amount', $start_date, $end_date, array());

        //the receiving side is dated by when it was received, not when it was sent
        $received_in = $this->stockLedgerSource(
            'COALESCE(t.received_date, t.date)', "FROM tbl_transfer_received_ingredients src
                       JOIN tbl_transfer t ON t.id = src.transfer_id
                      WHERE src.to_outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND t.del_status = 'Live'
                        AND src.status = 1",
            'src.quantity_amount', $start_date, $end_date, array());

        $received_out = $this->stockLedgerSource(
            'COALESCE(t.received_date, t.date)', "FROM tbl_transfer_received_ingredients src
                       JOIN tbl_transfer t ON t.id = src.transfer_id
                      WHERE src.from_outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND t.del_status = 'Live'
                        AND src.status = 1",
            'src.quantity_amount', $start_date, $end_date, array());

        $production_in = $this->stockLedgerSource(
            'pr.date', "FROM tbl_production_ingredients src
                        JOIN tbl_production pr ON pr.id = src.production_id
                       WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live' AND pr.del_status = 'Live'
                         AND src.status = 1",
            'src.quantity_amount', $start_date, $end_date, array());

        $result = array();
        foreach ($items as $item) {
            $id = $item->id;
            //conversion_rate converts purchase units into consumption units.
            //guard only against 0/empty, a fractional rate such as 0.7 is valid
            $conv = ($item->conversion_rate === NULL || $item->conversion_rate == 0) ? 1 : (float) $item->conversion_rate;

            $row = array();
            $row['item'] = $item;
            $row['conversion_rate'] = $conv;

            $opening_in  = $this->pickQty($purchase_in, $id, 'opening') * $conv;
            $opening_in += $this->pickQty($transfer_in, $id, 'opening') * $conv;
            $opening_in += $this->pickQty($received_in, $id, 'opening') * $conv;
            $opening_in += $this->pickQty($production_in, $id, 'opening') * $conv;
            $opening_in += $this->pickQty($adjust_plus, $id, 'opening');

            $opening_out  = $this->pickQty($sale_out, $id, 'opening');
            $opening_out += $this->pickQty($modifier_out, $id, 'opening');
            $opening_out += $this->pickQty($waste_out, $id, 'opening');
            $opening_out += $this->pickQty($adjust_minus, $id, 'opening');
            $opening_out += $this->pickQty($transfer_out, $id, 'opening') * $conv;
            $opening_out += $this->pickQty($received_out, $id, 'opening') * $conv;

            $row['opening'] = $opening_in - $opening_out;

            //period movements, kept separate so the detail row reconciles
            $row['purchase_in']   = $this->pickQty($purchase_in, $id, 'period') * $conv;
            $row['transfer_in']   = $this->pickQty($transfer_in, $id, 'period') * $conv;
            $row['received_in']   = $this->pickQty($received_in, $id, 'period') * $conv;
            $row['production_in'] = $this->pickQty($production_in, $id, 'period') * $conv;
            $row['adjust_plus']   = $this->pickQty($adjust_plus, $id, 'period');

            $row['sale_out']      = $this->pickQty($sale_out, $id, 'period');
            $row['modifier_out']  = $this->pickQty($modifier_out, $id, 'period');
            $row['waste_out']     = $this->pickQty($waste_out, $id, 'period');
            $row['adjust_minus']  = $this->pickQty($adjust_minus, $id, 'period');
            $row['transfer_out']  = $this->pickQty($transfer_out, $id, 'period') * $conv;
            $row['received_out']  = $this->pickQty($received_out, $id, 'period') * $conv;

            $row['total_in'] = $row['purchase_in'] + $row['transfer_in'] + $row['received_in']
                             + $row['production_in'] + $row['adjust_plus'];
            $row['total_out'] = $row['sale_out'] + $row['modifier_out'] + $row['waste_out']
                              + $row['adjust_minus'] + $row['transfer_out'] + $row['received_out'];

            $row['closing'] = $row['opening'] + $row['total_in'] - $row['total_out'];

            $result[$id] = $row;
        }
        return $result;
    }
    /**
     * read one bucket out of a ledger source result
     * @access private
     * @return float
     */
    private function pickQty($source, $ingredient_id, $bucket) {
        return isset($source[$ingredient_id]) ? (float) $source[$ingredient_id][$bucket] : 0;
    }
    /**
     * get Unposted Stock Impact: stock movement that is deliberately NOT part
     * of the audit totals, shown separately for reference.
     *
     *  - open orders : consumption already deducted from the live Inventory
     *                  screen but excluded from the audit numbers because the
     *                  order is not completed (order_status != 3)
     *  - refunded    : stock consumed by completed sales that were later
     *                  refunded. Refunds never reverse consumption rows, so
     *                  this stock stays consumed
     *
     * Cancelled/deleted orders cannot appear here: deleting a sale hard deletes
     * its consumption rows, so no ledger trace survives.
     *
     * @access public
     * @return array
     * @param string
     * @param string
     * @param int
     * @param int
     * @param array
     */
    public function getUnpostedStockImpact($start_date, $end_date, $outlet_ids, $company_id, $filters = array()) {
        $outlet_in = outletScopeSqlList($outlet_ids);
        $items = $this->getStockReportItems($company_id, $filters);

        $open_orders = $this->stockLedgerSource(
            's.sale_date', "FROM tbl_sale_consumptions_of_menus src
                            JOIN tbl_sales s ON s.id = src.sales_id
                           WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live'
                             AND s.del_status = 'Live' AND s.order_status != 3",
            'src.consumption', $start_date, $end_date, array());

        $open_modifiers = $this->stockLedgerSource(
            's.sale_date', "FROM tbl_sale_consumptions_of_modifiers_of_menus src
                            JOIN tbl_sales s ON s.id = src.sales_id
                           WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live'
                             AND s.del_status = 'Live' AND s.order_status != 3",
            'src.consumption', $start_date, $end_date, array());

        $refunded = $this->stockLedgerSource(
            's.sale_date', "FROM tbl_sale_consumptions_of_menus src
                            JOIN tbl_sales s ON s.id = src.sales_id
                           WHERE src.outlet_id IN ($outlet_in) AND src.del_status = 'Live'
                             AND s.del_status = 'Live' AND s.order_status = 3
                             AND s.total_refund > 0",
            'src.consumption', $start_date, $end_date, array());

        $result = array();
        foreach ($items as $item) {
            $id = $item->id;
            $open = $this->pickQty($open_orders, $id, 'period') + $this->pickQty($open_modifiers, $id, 'period');
            $refund = $this->pickQty($refunded, $id, 'period');
            if ($open == 0 && $refund == 0) {
                continue;
            }
            $result[$id] = array(
                'item' => $item,
                'open_orders' => $open,
                'refunded' => $refund,
            );
        }
        return $result;
    }

}

?>
