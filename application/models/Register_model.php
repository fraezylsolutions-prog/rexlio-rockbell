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
  # This is Register_model Model
  ###########################################################
 */
class Register_model extends CI_Model {

     /**
     * get Menu Access Of This User
     * @access public
     * @return object
     * @param no
     */
    public function getMenuAccessOfThisUser()
    {
        $user_id = $this->session->userdata('user_id');
        $this->db->select('*');
        $this->db->from('tbl_user_menu_access');
        $this->db->where("user_id", $user_id);
        $this->db->order_by('id', 'ASC');
        return $this->db->get()->result();
    }
     /**
     * checkAccess
     * @access public
     * @return boolean
     * @param array
     */
    public function checkAccess($records){
        $result = false;
        foreach($records as $single_record){
            if($single_record->menu_id==1 || ($single_record->menu_id>=14 && $single_record->menu_id<=18))
            {
                $result = true;
            }
        }
        return $result;
    }
     /**
     * check Register
     * @access public
     * @return object
     * @param int
     * @param int
     */
    public function checkRegister($user_id, $outlet_id)
    {
      $this->db->select("register_status as status");
      $this->db->from('tbl_register');
      $this->db->where("user_id", $user_id);
      $this->db->where("outlet_id", $outlet_id);
      $this->db->order_by('id', 'DESC');
      return $this->db->get()->row(); 
    }
     /**
     * Admin Register Management (force-close). All OPEN registers across every
     * outlet the caller may see, oldest opening first - the longest-abandoned
     * register is the one most worth an admin's attention, so it surfaces first
     * rather than being buried at the bottom of a status-sorted list.
     * @access public
     * @return array
     * @param array $outlet_ids
     */
    public function getAllOpenRegisters($outlet_ids)
    {
        if (empty($outlet_ids)) {
            return array();
        }
        $this->db->select("tbl_register.*, tbl_counters.name AS counter_name," .
                          " tbl_outlets.outlet_name AS outlet_name, tbl_users.full_name AS user_name", FALSE);
        $this->db->from('tbl_register');
        $this->db->join('tbl_counters', 'tbl_counters.id = tbl_register.counter_id', 'left');
        $this->db->join('tbl_outlets', 'tbl_outlets.id = tbl_register.outlet_id', 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_register.user_id', 'left');
        $this->db->where_in('tbl_register.outlet_id', $outlet_ids);
        $this->db->where('tbl_register.register_status', 1);
        $this->db->order_by('tbl_register.opening_balance_date_time', 'ASC');
        return $this->db->get()->result();
    }
     /**
     * Single register row for the force-close calculation/write. tbl_register has
     * no del_status column - unlike most tables here, rows are never soft-deleted,
     * so the generic getAllByCustomRowId() helper (which always filters
     * del_status='Live') cannot be used for this table.
     * @access public
     * @return object|null
     * @param int $id
     */
    public function getRegisterById($id)
    {
        $this->db->select("tbl_register.*, tbl_counters.name AS counter_name," .
                          " tbl_outlets.outlet_name AS outlet_name, tbl_users.full_name AS user_name", FALSE);
        $this->db->from('tbl_register');
        $this->db->join('tbl_counters', 'tbl_counters.id = tbl_register.counter_id', 'left');
        $this->db->join('tbl_outlets', 'tbl_outlets.id = tbl_register.outlet_id', 'left');
        $this->db->join('tbl_users', 'tbl_users.id = tbl_register.user_id', 'left');
        $this->db->where('tbl_register.id', (int) $id);
        return $this->db->get()->row();
    }
}

