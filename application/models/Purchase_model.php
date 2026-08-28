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
  # This is Purchase_model Model
  ###########################################################
 */
class Purchase_model extends CI_Model {
 /**
     * generate Purchase Ref No
     * @access public
     * @return string
     * @param int
     */
    public function generatePurRefNo($outlet_id) {
        $purchase_count = $this->db->query("SELECT count(id) as purchase_count
               FROM tbl_purchase where outlet_id=$outlet_id")->row('purchase_count');
        $ingredient_code = str_pad($purchase_count + 1, 6, '0', STR_PAD_LEFT);
        return $ingredient_code;
    }
 /**
     * get Ingredient List With Unit And Price
     * @access public
     * @return object
     * @param int
     */
    public function getIngredientListWithUnitAndPrice($company_id) {
        $result = $this->db->query("SELECT tbl_ingredients.id, tbl_ingredients.name, tbl_ingredients.code, tbl_ingredients.purchase_price, tbl_ingredients.consumption_unit_cost, tbl_units.unit_name
          FROM tbl_ingredients 
          left JOIN tbl_units ON tbl_ingredients.purchase_unit_id = tbl_units.id
          WHERE tbl_ingredients.company_id=$company_id AND tbl_ingredients.del_status = 'Live' AND tbl_ingredients.ing_type = 'Plain Ingredient'  
          ORDER BY tbl_ingredients.name ASC")->result();
        return $result;
    }
    /**
     * get Purchase Ingredients
     * @access public
     * @return object
     * @param int
     */
    public function getPurchaseIngredients($id) {
        $this->db->select("*");
        $this->db->from("tbl_purchase_ingredients");
        $this->db->order_by('id', 'ASC');
        $this->db->where("purchase_id", $id);
        $this->db->where("del_status", 'Live');
        return $this->db->get()->result();
    }
    /**
     * get Filtered Purchases, shared by the purchase list and the purchase report
     * so both screens filter identically
     * @access public
     * @return object
     * @param int
     * @param array
     */
    public function getFilteredPurchases($outlet_id, $filters = array()) {
        $this->db->select('*');
        $this->db->from('tbl_purchase');
        $this->db->where('outlet_id', $outlet_id);
        $this->db->where('del_status', "Live");

        $start_date = isset($filters['start_date']) ? $filters['start_date'] : '';
        $end_date = isset($filters['end_date']) ? $filters['end_date'] : '';
        //date semantics kept as the purchase report has always had them:
        //both dates given = range, only one date given = that exact day
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

        if (isset($filters['supplier_id']) && $filters['supplier_id'] !== '' && $filters['supplier_id'] !== NULL) {
            $this->db->where('supplier_id', $filters['supplier_id']);
        }
        //there is no payment status column, status is derived from the due amount
        if (isset($filters['payment_status']) && $filters['payment_status'] === 'due') {
            $this->db->where('due >', 0);
        }
        if (isset($filters['payment_status']) && $filters['payment_status'] === 'paid') {
            $this->db->group_start();
            $this->db->where('due <=', 0);
            $this->db->or_where('due IS NULL', NULL, FALSE);
            $this->db->group_end();
        }
        //category and item live on the purchase lines, a purchase can have many
        //lines so match with a subquery to keep one row per purchase
        if (isset($filters['ingredient_id']) && $filters['ingredient_id'] !== '' && $filters['ingredient_id'] !== NULL) {
            $ingredient_id = (int) $filters['ingredient_id'];
            $this->db->where("tbl_purchase.id IN (SELECT pi.purchase_id FROM tbl_purchase_ingredients pi WHERE pi.del_status = 'Live' AND pi.ingredient_id = $ingredient_id)", NULL, FALSE);
        }
        if (isset($filters['category_id']) && $filters['category_id'] !== '' && $filters['category_id'] !== NULL) {
            $category_id = (int) $filters['category_id'];
            $this->db->where("tbl_purchase.id IN (SELECT pi.purchase_id FROM tbl_purchase_ingredients pi JOIN tbl_ingredients ing ON ing.id = pi.ingredient_id WHERE pi.del_status = 'Live' AND ing.del_status = 'Live' AND ing.category_id = $category_id)", NULL, FALSE);
        }

        $this->db->order_by('id', "DESC");
        return $this->db->get()->result();
    }

}

