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
  # This is Ingredient Controller
  ###########################################################
 */

defined('BASEPATH') OR exit('No direct script access allowed');

class Ingredient extends Cl_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->library('excel'); //load PHPExcel library
        $this->load->model('Common_model');
        $this->load->model('Master_model');
        $this->load->library('form_validation');
        $this->Common_model->setDefaultTimezone();

        if (!$this->session->has_userdata('user_id')) {
            redirect('Authentication/index');
        }

        //start check access function
        $segment_2 = $this->uri->segment(2);
        $segment_3 = $this->uri->segment(3);
        $controller = "217";
        $function = "";

        if($segment_2=="ingredients"){
            $function = "view";
        }elseif($segment_2=="addEditIngredient" && $segment_3){
            $function = "update";
        }elseif($segment_2=="addEditIngredient"){
            $function = "add";
        }elseif($segment_2=="bulkQtyItem" || $segment_2=="downloadItemQty"
                || $segment_2=="ExcelDataUpdateItemQty"){
            /* Separate permissions: creating items, restating stock and repricing
               are three different privileges. A storekeeper who may correct a stock
               count is not automatically someone who may change purchase costs. */
            $function = "bulk_qty_item";
        }elseif($segment_2=="bulkPriceItem" || $segment_2=="downloadItemPrices"
                || $segment_2=="ExcelDataUpdateItemPrices"){
            $function = "bulk_price_item";
        }elseif($segment_2=="deleteIngredient"){
            $function = "delete";
        }elseif($segment_2=="uploadingredients" || $segment_2=="uploadFoodMenuingredient" || $segment_2=="ExcelDataAddIngredints" || $segment_2=="downloadPDF"){
            $function = "upload_ingredient";
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
     * ingredients info
     * @access public
     * @return void
     * @param no
     */
    public function ingredients() {
        $company_id = $this->session->userdata('company_id');
        $data = array();

        //filters posted from the ingredient list screen, empty value means "all"
        $filters = array();
        $filters['keyword'] = trim((string) $this->input->post('keyword'));
        $filters['category_id'] = $this->input->post('category_id');
        $filters['purchase_unit_id'] = $this->input->post('purchase_unit_id');
        $filters['unit_id'] = $this->input->post('unit_id');
        $filters['is_direct_food'] = $this->input->post('is_direct_food');

        //Phase F: outlet scope for the Stock Qty column. Same validated helper the
        //inventory, alert list and stock report screens use, so a posted outlet the
        //user has no claim to falls back to their own rather than being honoured.
        $scope = resolveOutletScope($this->input->post('outlet_scope'));
        $data['outlet_scope'] = $scope;
        $data['scope_outlets'] = getAllOutlestByAssign();
        $data['show_outlet_filter'] = count(getAccessibleOutletIds()) > 1;

        $data['filters'] = $filters;
        $data['categories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_ingredient_categories');
        $data['units'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_units');
        $data['ingredients'] = $this->Common_model->getPreMadeIngredients($company_id, "Plain Ingredient", $filters);

        //Stock per item for the chosen scope. Reuses Inventory_model::getInventory()
        //rather than growing another copy of the eleven source stock query - the
        //duplication that made the closing balance drift between screens in the
        //first place. Indexed by ingredient id for O(1) lookup in the view.
        $this->load->model('Inventory_model');
        $data['stock_by_item'] = array();
        foreach ($this->Inventory_model->getInventory('', '', '', $scope['ids']) as $stock_row) {
            if (isset($stock_row->id) && $stock_row->id) {
                $data['stock_by_item'][$stock_row->id] = $stock_row;
            }
        }

        //Unit selling price. Ingredients are consumed by recipes and have no sale
        //price of their own; only a DIRECT SALE product item (is_direct_food = 2)
        //is sellable, and its price lives on the food menu it was generated from,
        //reached through tbl_ingredients.food_id. Recipe ingredients show blank.
        $data['sale_price_by_food_menu'] = $this->Common_model->getFoodMenuSalePriceMap($company_id);
        $data['main_content'] = $this->load->view('master/ingredient/ingredients', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * upload ingredients
     * @access public
     * @return void
     * @param no
     */
    public function uploadingredients() {
        $company_id = $this->session->userdata('company_id');

        $data = array();
        $data['ingredients'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_ingredients");
        $data['main_content'] = $this->load->view('master/ingredient/uploadingredients', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * upload Food Menu ingredient
     * @access public
     * @return void
     * @param no
     */
    public function uploadFoodMenuingredient() {
        $company_id = $this->session->userdata('company_id');
        $data = array();
        $data['foodMenus'] = $this->Common_model->getAllByCompanyId($company_id, "tbl_food_menus");
        $data['foodMenuCategories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, "tbl_food_menu_categories");
        $data['main_content'] = $this->load->view('master/foodMenu/uploadsfoodMenusingrediend', $data, TRUE);
        $this->load->view('userHome', $data);
    }
     /**
     * delete Ingredient
     * @access public
     * @return void
     * @param int
     */
    public function deleteIngredient($id) {
        $id = $this->custom->encrypt_decrypt($id, 'decrypt');

        $this->Common_model->deleteStatusChange($id, "tbl_ingredients");

        $this->session->set_flashdata('exception',lang('delete_success'));
        redirect('ingredient/ingredients');
    }
     /**
     * add/Edit Ingredient
     * @access public
     * @return void
     * @param int
     */
    public function addEditIngredient($encrypted_id = "") {
        $id = $this->custom->encrypt_decrypt($encrypted_id, 'decrypt');
        $company_id = $this->session->userdata('company_id');
        if (htmlspecialcharscustom($this->input->post('submit'))) {
            $this->form_validation->set_rules('name', lang('name'), 'required|max_length[50]');
            $this->form_validation->set_rules('category_id', lang('category'), 'required');
            $this->form_validation->set_rules('purchase_price', lang('purchase_price'), 'required|numeric|max_length[15]');
            $this->form_validation->set_rules('alert_quantity',lang('alert_quantity'), 'required|numeric|max_length[15]');
            $this->form_validation->set_rules('code',lang('unit'), 'required');
            $this->form_validation->set_rules('purchase_unit_id',lang('unit'), 'required');
            $this->form_validation->set_rules('consumption_unit_id',lang('unit'), 'required');
            $this->form_validation->set_rules('conversion_rate',lang('unit'), 'required');
            if ($this->form_validation->run() == TRUE) {
                $fmc_info = array();
                $fmc_info['name'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('name')));
                $fmc_info['code'] = htmlspecialcharscustom($this->input->post($this->security->xss_clean('code')));
                $fmc_info['category_id'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('category_id')));
                $fmc_info['purchase_price'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('purchase_price')));
                $fmc_info['alert_quantity'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('alert_quantity')));
                $fmc_info['unit_id'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('consumption_unit_id')));
                $fmc_info['purchase_unit_id'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('purchase_unit_id')));
                $fmc_info['consumption_unit_cost'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('consumption_unit_cost')));
                $fmc_info['average_consumption_per_unit'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('consumption_unit_cost')));
                $fmc_info['conversion_rate'] =htmlspecialcharscustom($this->input->post($this->security->xss_clean('conversion_rate')));
                $fmc_info['user_id'] = $this->session->userdata('user_id');
                $fmc_info['company_id'] = $this->session->userdata('company_id');
                if ($id == "") {
                    $id = $this->Common_model->insertInformation($fmc_info, "tbl_ingredients");
                    $this->session->set_flashdata('exception', lang('insertion_success'));
                } else {
                    $this->Common_model->updateInformation($fmc_info, $id, "tbl_ingredients");
                    $this->session->set_flashdata('exception',lang('update_success'));
                }
                //set average cost for profit loss report
                setAverageCost($id);
                redirect('ingredient/ingredients');
            } else {
                if ($id == "") {
                    $data = array();
                    $data['categories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_ingredient_categories');
                    $data['units'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_units');
                    $data['autoCode'] = $this->Master_model->generateIngredientCode();
                    $data['main_content'] = $this->load->view('master/ingredient/addIngredient', $data, TRUE);
                    $this->load->view('userHome', $data);
                } else {
                    $data = array();
                    $data['encrypted_id'] = $encrypted_id;
                    $data['categories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_ingredient_categories');
                    $data['autoCode'] = $this->Master_model->generateIngredientCode();
                    $data['units'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_units');
                    $data['ingredient_information'] = $this->Common_model->getDataById($id, "tbl_ingredients");
                    $data['main_content'] = $this->load->view('master/ingredient/editIngredient', $data, TRUE);
                    $this->load->view('userHome', $data);
                }
            }
        } else {
            if ($id == "") {
                $data = array();
                $data['categories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_ingredient_categories');
                $data['units'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_units');
                $data['autoCode'] = $this->Master_model->generateIngredientCode();
                $data['main_content'] = $this->load->view('master/ingredient/addIngredient', $data, TRUE);
                $this->load->view('userHome', $data);
            } else {
                $data = array();
                $data['encrypted_id'] = $encrypted_id;
                $data['categories'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_ingredient_categories');
                $data['autoCode'] = $this->Master_model->generateIngredientCode();
                $data['units'] = $this->Common_model->getAllByCompanyIdForDropdown($company_id, 'tbl_units');
                $data['ingredient_information'] = $this->Common_model->getDataById($id, "tbl_ingredients");
                $data['main_content'] = $this->load->view('master/ingredient/editIngredient', $data, TRUE);
                $this->load->view('userHome', $data);
            }
        }
    }
     /**
     * download file
     * @access public
     * @return void
     * @param string
     */
    public function downloadPDF($file = "") {
        $file = $file.".xlsx";
        $this->load->helper('download');
        $data = file_get_contents("asset/sample/" . $file); // Read the file's
        $name = $file;
        force_download($name, $data);
    }
     /**
     * Excel Data Add Ingredints
     * @access public
     * @return void
     * @param no
     */
    public function ExcelDataAddIngredints() {
        $company_id = $this->session->userdata('company_id');
        if ($_FILES['userfile']['name'] != "") {
            if ($_FILES['userfile']['name'] == "Ingredient_Upload.xlsx") {
                //Path of files were you want to upload on localhost (C:/xampp/htdocs/ProjectName/uploads/excel/)
                $configUpload['upload_path'] = FCPATH . 'asset/excel/';
                $configUpload['allowed_types'] = 'xls|xlsx';
                $configUpload['max_size'] = '5000';
                $this->load->library('upload', $configUpload);
                if ($this->upload->do_upload('userfile')) {
                    $upload_data = $this->upload->data(); //Returns array of containing all of the data related to the file you uploaded.
                    $file_name = $upload_data['file_name']; //uploded file name
                    $extension = $upload_data['file_ext'];    // uploded file extension
                    //$objReader =PHPExcel_IOFactory::createReader('Excel5');     //For excel 2003
                    $objReader = PHPExcel_IOFactory::createReader('Excel2007'); // For excel 2007
                    //Set to read only
                    $objReader->setReadDataOnly(true);
                    //Load excel file
                    $objPHPExcel = $objReader->load(FCPATH . 'asset/excel/' . $file_name);
                    $totalrows = $objPHPExcel->setActiveSheetIndex(0)->getHighestRow();   //Count Numbe of rows avalable in excel
                    $objWorksheet = $objPHPExcel->setActiveSheetIndex(0);
                    //loop from first data untill last data
                    /* Raised from 54 (~50 items), which made a real stock list a repetitive
                       multi-file chore. Still bounded: PHPExcel holds the whole sheet
                       in memory, so an unbounded file is a DoS on our own process. */
                    if ($totalrows > 2 && $totalrows <= 2003) {
                        $arrayerror = '';
                        for ($i = 4; $i <= $totalrows; $i++) {
                            $ingredint_name = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(0, $i)->getValue()));
                            $ingredint_code = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(1, $i)->getValue())); //Excel Column 1
                            $ingredint_category = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(2, $i)->getValue())); //Excel Column 2
                            $ingredint_purchase_unit = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(3, $i)->getValue())); //Excel Column 3
                            $ingredint_unit = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(4, $i)->getValue())); //Excel Column 3
                            $conversion_rate = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(5, $i)->getValue())); //Excel Column 3
                            $ingredint_perchaseprice = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(6, $i)->getValue())); //Excel Column 5
                            $ingredint_alertqty = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(7, $i)->getValue())); //Excel Column 4


                            if ($ingredint_name == '') {
                                if ($arrayerror == '') {
                                    $arrayerror.=" Row Number $i column A required";
                                } else {
                                    $arrayerror.="<br> Row Number $i column A required";
                                }
                            }

                            if ($ingredint_code == '') {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column B required";
                                } else {
                                    $arrayerror.="<br> Row Number $i column B required";
                                }
                            }

                            if ($ingredint_category == '') {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column C required";
                                } else {
                                    $arrayerror.="<br> $i Row Number column C required";
                                }
                            }

                            if ($ingredint_purchase_unit == '') {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column D required";
                                } else {
                                    $arrayerror.="<br> Row Number $i column D required";
                                }
                            }
                            if ($ingredint_unit == '') {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column E required";
                                } else {
                                    $arrayerror.="<br> Row Number $i column E required";
                                }
                            }


                            /* 0 is numeric and non-empty, so it passes the check below and
                               then reaches $purchase_price / $conversion_rate in the insert
                               loop - a fatal DivisionByZeroError on PHP 8, thrown after
                               earlier rows are already committed. Rejected up front instead. */
                            if ($conversion_rate !== '' && is_numeric($conversion_rate)
                                && (float) $conversion_rate <= 0) {
                                $arrayerror .= ($arrayerror == '' ? '' : '<br>')
                                    . "Row Number $i column F: conversion_rate must be greater than zero";
                            }
                            if ($conversion_rate == '' || !is_numeric($conversion_rate)) {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column F required or can not be text";
                                } else {
                                    $arrayerror.="<br> Row Number $i column F required or can not be text";
                                }
                            }


                            if ($ingredint_perchaseprice == '' || !is_numeric($ingredint_perchaseprice)) {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column G required or can not be text";
                                } else {
                                    $arrayerror.="<br> Row Number $i column G required or can not be text";
                                }
                            }
                            if ($ingredint_alertqty == '' || !is_numeric($ingredint_alertqty)) {
                                if ($arrayerror == '') {
                                    $arrayerror.="Row Number $i column H required or can not be text";
                                } else {
                                    $arrayerror.="<br> Row Number $i column H required  or can not be text";
                                }
                            }
                        }
                        if ($arrayerror == '') {
                            /* All or nothing. Without this a mid-loop failure leaves some
                               items created and the rest not, with no clean point to retry
                               from - and the operator cannot tell which is which. */
                            $this->db->trans_begin();

                            for ($i = 4; $i <= $totalrows; $i++) {
                                $ingredint_name = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(0, $i)->getValue()));
                                $ingredint_code = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(1, $i)->getValue())); //Excel Column 1
                                $ingredint_category = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(2, $i)->getValue())); //Excel Column 2
                                $ingredint_purchase_unit = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(3, $i)->getValue())); //Excel Column 3
                                $ingredint_unit = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(4, $i)->getValue())); //Excel Column 3
                                $conversion_rate = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(5, $i)->getValue())); //Excel Column 3
                                $ingredint_perchaseprice = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(6, $i)->getValue())); //Excel Column 5
                                $ingredint_alertqty = htmlspecialcharscustom(trim_checker($objWorksheet->getCellByColumnAndRow(7, $i)->getValue())); //Excel Column 4

                                $ingredint_unit = $this->get_unit_id($ingredint_unit);
                                $ingredint_purchase_unit = $this->get_unit_id($ingredint_purchase_unit);
                                $ingredint_category = $this->get_cat_id($ingredint_category);

                                $consumption_unit_cost  = $ingredint_perchaseprice/$conversion_rate;

                                $fmc_info = array();
                                $fmc_info['name'] = $ingredint_name;
                                $fmc_info['code'] = $ingredint_code;
                                $fmc_info['category_id'] = $ingredint_category;
                                $fmc_info['purchase_price'] = $ingredint_perchaseprice;
                                $fmc_info['alert_quantity'] = $ingredint_alertqty;
                                $fmc_info['purchase_unit_id'] = $ingredint_purchase_unit;
                                $fmc_info['unit_id'] = $ingredint_unit;
                                $fmc_info['conversion_rate'] = $conversion_rate;
                                $fmc_info['consumption_unit_cost'] = $consumption_unit_cost;
                                $fmc_info['average_consumption_per_unit'] = $consumption_unit_cost;
                                $fmc_info['user_id'] = $this->session->userdata('user_id');
                                $fmc_info['company_id'] = $this->session->userdata('company_id');
                                $this->Common_model->insertInformation($fmc_info, "tbl_ingredients");
                            }
                            if ($this->db->trans_status() === FALSE) {
                                $this->db->trans_rollback();
                                unlink(FCPATH . 'asset/excel/' . $file_name);
                                $this->session->set_flashdata('exception_err', 'Import failed, nothing was created.');
                                redirect('ingredient/uploadingredients');
                            }
                            $this->db->trans_commit();
                            unlink(FCPATH . 'asset/excel/' . $file_name); //File Deleted After uploading in database .
                            $this->session->set_flashdata('exception', 'Imported successfully!');
                            redirect('ingredient/ingredients');
                        } else {
                            unlink(FCPATH . 'asset/excel/' . $file_name); //File Deleted After uploading in database .
                            $this->session->set_flashdata('exception_err', "Required Data Missing:$arrayerror");
                        }
                    } else {
                        unlink(FCPATH . 'asset/excel/' . $file_name); //File Deleted After uploading in database .
                        $this->session->set_flashdata('exception_err', "Entry is more than 50 or No entry found.");
                    }
                } else {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('exception_err', "$error");
                }
            } else {
                $this->session->set_flashdata('exception_err', "We can not accept other files, please download the sample file 'Ingredient_Upload.xlsx', fill it up properly and upload it or rename the file name as 'Ingredient_Upload.xlsx' then fill it.");
            }
        } else {
            $this->session->set_flashdata('exception_err', 'File is required');
        }
        redirect('ingredient/uploadingredients');
    }
     /**
     * get unit by id
     * @access public
     * @return int
     * @param int
     */
    public function get_unit_id($ingredint_unit) {
        $company_id = $this->session->userdata('company_id');
        $user_id = $this->session->userdata('user_id');
        $id = $this->db->query("SELECT id FROM tbl_units WHERE company_id=$company_id and unit_name='" . $ingredint_unit . "'")->row('id');
        if ($id != '') {
            return $id;
        } else {
            $data = array('unit_name' => $ingredint_unit, 'company_id' => $company_id);
            $query = $this->db->insert('tbl_units', $data);
            $id = $this->db->insert_id();
            return $id;
        }
    }
     /**
     * get category id
     * @access public
     * @return int
     * @param string
     */
    public function get_cat_id($ingredint_category) {
        $company_id = $this->session->userdata('company_id');
        $user_id = $this->session->userdata('user_id');
        $id = $this->db->query("SELECT id FROM tbl_ingredient_categories WHERE user_id=$user_id and company_id=$company_id and category_name='" . $ingredint_category . "'")->row('id');
        if ($id != '') {
            return $id;
        } else {
            $data = array('category_name' => $ingredint_category, 'user_id' => $user_id, 'company_id' => $company_id);
            $query = $this->db->insert('tbl_ingredient_categories', $data);
            $id = $this->db->insert_id();
            return $id;
        }
    }


    /* ====================================================================
       Item bulk tooling.

       Unlike Food Menu, Items carry NO per-outlet override and NO outlet
       assignment list - tbl_outlets has only food_menus / food_menu_prices /
       delivery_price, all food-menu specific, and every ingredient-to-outlet
       table is a transaction or recipe table. Items are company-wide master
       data whose STOCK is per-outlet purely through the ledger. So the price
       tool is global and needs no outlet selector, and no reconcile step
       exists or is needed.
       ==================================================================== */

    /**
     * Current closing balance per item for one outlet, in CONSUMPTION units.
     *
     * Reuses Inventory_model::getInventory() and the shared stockClosingBalance()
     * helper rather than adding another copy of the eleven-term ledger query -
     * the Phase F consolidation exists precisely so this figure has one
     * definition. Keyed by ingredient id.
     *
     * @param  int $outlet_id
     * @return array
     */
    /**
     * Read a cell as a trimmed string WITHOUT treating 0 as empty.
     *
     * trim_checker() cannot be used for a value cell: it is written as
     * "isset($v) && $v ? trim($v) : ''", and 0 is falsy in PHP, so a genuine
     * zero comes back as '' and is then read as "leave unchanged". That made
     * it impossible to zero an item's stock or set a price to 0 - the two
     * cases most likely to be entered deliberately.
     *
     * @param  mixed $raw
     * @return string
     */
    private function ir_cell($raw) {
        return ($raw === null) ? '' : trim((string) $raw);
    }

    private function itemClosingBalances($outlet_id) {
        $this->load->model('Inventory_model');
        $rows = $this->Inventory_model->getInventory('', '', '', array((int) $outlet_id));
        $out = array();
        if ($rows) {
            foreach ($rows as $row) {
                $id = isset($row->id) ? (int) $row->id : 0;
                if ($id) {
                    $out[$id] = (float) stockClosingBalance($row);
                }
            }
        }
        return $out;
    }

    /**
     * Bulk Quantity Update screen (Item).
     * @access public
     * @return void
     */
    public function bulkQtyItem() {
        $company_id = $this->session->userdata('company_id');
        $data = array();
        $data['accessible_outlets'] = $this->db->query(
            "SELECT id, outlet_name FROM tbl_outlets
              WHERE company_id = ? AND del_status = 'Live' ORDER BY outlet_name",
            array($company_id))->result();
        $data['main_content'] = $this->load->view('master/ingredient/bulkQtyItem', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * Download current stock per item for one outlet.
     * @access public
     * @return void
     */
    public function downloadItemQty() {
        $company_id = $this->session->userdata('company_id');
        $outlet_id = (int) $this->input->post('outlet_id');
        if (!$outlet_id || !canEnterOutlet($outlet_id)) {
            $this->session->set_flashdata('exception_err', lang('menu_not_permit_access'));
            redirect('ingredient/bulkQtyItem');
        }
        $outlet = $this->db->query("SELECT id, outlet_name FROM tbl_outlets WHERE id = ?",
            array($outlet_id))->row();
        $balances = $this->itemClosingBalances($outlet_id);
        $items = $this->db->query(
            "SELECT i.id, i.code, i.name, u.unit_name
               FROM tbl_ingredients i
               LEFT JOIN tbl_units u ON u.id = i.unit_id
              WHERE i.company_id = ? AND i.del_status = 'Live'
              ORDER BY i.name", array($company_id))->result();

        $this->load->library('excel');
        $xls = new PHPExcel();
        $sheet = $xls->setActiveSheetIndex(0);
        $sheet->setTitle('Stock');
        $sheet->setCellValue('A1', 'Item Bulk Quantity Update');
        /* The outlet is written INTO the file and re-authorised on upload, so a
           file cut for one site cannot be applied to another by picking the wrong
           dropdown entry - stock is per outlet and that mistake is invisible. */
        $sheet->setCellValue('A2', 'OUTLET_ID=' . $outlet->id . ' | ' . $outlet->outlet_name
            . '  ||  Do not edit column A or this line. Quantities are in the CONSUMPTION unit shown.'
            . '  Enter the NEW total you want on hand; the system works out the difference.'
            . '  Leave New Quantity blank to leave that item unchanged.');
        $headers = array('Item ID *', 'Code', 'Item Name', 'Consumption Unit',
                         'Current Quantity', 'New Quantity');
        foreach ($headers as $c => $h) {
            $sheet->setCellValueByColumnAndRow($c, 3, $h);
        }
        $row = 4;
        foreach ($items as $it) {
            $cur = isset($balances[(int) $it->id]) ? $balances[(int) $it->id] : 0;
            $sheet->setCellValueByColumnAndRow(0, $row, $it->id);
            $sheet->setCellValueByColumnAndRow(1, $row, $it->code);
            $sheet->setCellValueByColumnAndRow(2, $row, $it->name);
            $sheet->setCellValueByColumnAndRow(3, $row, $it->unit_name);
            $sheet->setCellValueByColumnAndRow(4, $row, round($cur, 4));
            /* New Quantity is deliberately left EMPTY rather than prefilled with the
               current value: prefilling would make an accidental save rewrite every
               item's stock, and a blank column makes it obvious what you changed. */
            $row++;
        }
        foreach (range('A', 'F') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Item_Quantity_Update.xlsx"');
        header('Cache-Control: max-age=0');
        PHPExcel_IOFactory::createWriter($xls, 'Excel2007')->save('php://output');
        exit;
    }

    /**
     * Apply an edited quantity file as ONE inventory adjustment.
     * @access public
     * @return void
     */
    public function ExcelDataUpdateItemQty() {
        $company_id = $this->session->userdata('company_id');
        if (!isset($_FILES['userfile']) || $_FILES['userfile']['name'] == '') {
            $this->session->set_flashdata('exception_err', 'File is required');
            redirect('ingredient/bulkQtyItem');
        }
        $configUpload['upload_path'] = FCPATH . 'asset/excel/';
        $configUpload['allowed_types'] = 'xls|xlsx';
        $configUpload['max_size'] = '5000';
        $this->load->library('upload', $configUpload);
        if (!$this->upload->do_upload('userfile')) {
            $this->session->set_flashdata('exception_err', $this->upload->display_errors());
            redirect('ingredient/bulkQtyItem');
        }
        $upload_data = $this->upload->data();
        $path = FCPATH . 'asset/excel/' . $upload_data['file_name'];

        $reader = PHPExcel_IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->setActiveSheetIndex(0);
        $totalrows = $sheet->getHighestRow();

        $outlet_id = 0;
        if (preg_match('/OUTLET_ID=(\d+)/', (string) $sheet->getCell('A2')->getValue(), $mm)) {
            $outlet_id = (int) $mm[1];
        }
        if (!$outlet_id || !canEnterOutlet($outlet_id)) {
            @unlink($path);
            $this->session->set_flashdata('exception_err',
                'This file does not carry a valid outlet marker, or you do not have access to that '
                . 'outlet. Please download a fresh file from this screen.');
            redirect('ingredient/bulkQtyItem');
        }
        if ($totalrows < 4) {
            @unlink($path);
            $this->session->set_flashdata('exception_err', 'No data rows found.');
            redirect('ingredient/bulkQtyItem');
        }

        /* Balances are read at APPLY time, not taken from the file. The Current
           Quantity column is context for the person editing; trusting it would let
           a stale download silently undo sales made since it was taken. */
        $balances = $this->itemClosingBalances($outlet_id);

        $errors = '';
        $lines = array();
        for ($i = 4; $i <= $totalrows; $i++) {
            $item_id = trim_checker($sheet->getCellByColumnAndRow(0, $i)->getValue());
            if ($item_id === '') {
                continue;
            }
            if (!ctype_digit((string) $item_id)) {
                $errors .= ($errors == '' ? '' : '<br>') . "Row $i: column A must be the Item ID";
                continue;
            }
            $target = $this->ir_cell($sheet->getCellByColumnAndRow(5, $i)->getValue());
            if ($target === '') {
                continue;   // blank New Quantity = leave unchanged
            }
            if (!is_numeric($target) || (float) $target < 0) {
                $errors .= ($errors == '' ? '' : '<br>')
                    . "Row $i column F: New Quantity must be a number of 0 or more";
                continue;
            }
            $exists = $this->db->query(
                "SELECT id FROM tbl_ingredients WHERE id = ? AND company_id = ? AND del_status = 'Live'",
                array((int) $item_id, $company_id))->row();
            if (!$exists) {
                @$errors .= ($errors == '' ? '' : '<br>') . "Row $i: Item ID $item_id not found";
                continue;
            }
            $current = isset($balances[(int) $item_id]) ? $balances[(int) $item_id] : 0;
            $delta = (float) $target - $current;
            if (abs($delta) < 0.00005) {
                continue;   // already at the requested figure
            }
            $lines[] = array('id' => (int) $item_id, 'delta' => $delta,
                             'current' => $current, 'target' => (float) $target);
        }
        if ($errors !== '') {
            @unlink($path);
            $this->session->set_flashdata('exception_err', "Nothing was changed. $errors");
            redirect('ingredient/bulkQtyItem');
        }
        if (!$lines) {
            @unlink($path);
            $this->session->set_flashdata('exception',
                'No changes to apply - every New Quantity was blank or already matched.');
            redirect('ingredient/bulkQtyItem');
        }

        $this->db->trans_begin();
        /* ONE adjustment header per upload, so the whole run is a single auditable
           document that can be reversed in one action rather than item by item. */
        $ref = 'BULKQTY-' . date('YmdHis');
        $this->Common_model->insertInformation(array(
            'reference_no' => $ref,
            'date'         => date('Y-m-d'),
            'note'         => 'Bulk quantity update from file ' . $upload_data['orig_name']
                              . ' by user ' . $this->session->userdata('user_id')
                              . ' on ' . date('Y-m-d H:i:s') . '. ' . count($lines) . ' item(s).',
            'user_id'      => $this->session->userdata('user_id'),
            'outlet_id'    => $outlet_id,
            'del_status'   => 'Live',
        ), 'tbl_inventory_adjustment');
        $adj_id = $this->db->insert_id();

        $up = 0; $down = 0;
        foreach ($lines as $ln) {
            /* consumption_amount is in CONSUMPTION units and is applied by
               stockClosingBalance() WITHOUT the conversion_rate multiplier, unlike
               purchases and transfers. The delta is therefore written as-is. */
            $this->Common_model->insertInformation(array(
                'ingredient_id'          => $ln['id'],
                'consumption_amount'     => abs($ln['delta']),
                'inventory_adjustment_id'=> $adj_id,
                'consumption_status'     => $ln['delta'] > 0 ? 'Plus' : 'Minus',
                'outlet_id'              => $outlet_id,
                'del_status'             => 'Live',
            ), 'tbl_inventory_adjustment_ingredients');
            if ($ln['delta'] > 0) { $up++; } else { $down++; }
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('exception_err', 'Update failed, nothing was changed.');
        } else {
            $this->db->trans_commit();
            $this->session->set_flashdata('exception',
                "Stock updated on " . count($lines) . " item(s) ($up increased, $down decreased). "
                . "Recorded as adjustment $ref - delete that adjustment to reverse this whole run.");
        }
        @unlink($path);
        redirect('ingredient/bulkQtyItem');
    }

    /**
     * Bulk Price Update screen (Item). Global - Items have no per-outlet price.
     * @access public
     * @return void
     */
    public function bulkPriceItem() {
        $data = array();
        $data['main_content'] = $this->load->view('master/ingredient/bulkPriceItem', $data, TRUE);
        $this->load->view('userHome', $data);
    }

    /**
     * Download current purchase prices.
     * @access public
     * @return void
     */
    public function downloadItemPrices() {
        $company_id = $this->session->userdata('company_id');
        $items = $this->db->query(
            "SELECT i.id, i.code, i.name, i.purchase_price, i.conversion_rate,
                    i.consumption_unit_cost, pu.unit_name AS purchase_unit
               FROM tbl_ingredients i
               LEFT JOIN tbl_units pu ON pu.id = i.purchase_unit_id
              WHERE i.company_id = ? AND i.del_status = 'Live'
              ORDER BY i.name", array($company_id))->result();

        $this->load->library('excel');
        $xls = new PHPExcel();
        $sheet = $xls->setActiveSheetIndex(0);
        $sheet->setTitle('Prices');
        $sheet->setCellValue('A1', 'Item Bulk Price Update');
        $sheet->setCellValue('A2', 'Do not edit column A. Purchase Price is per PURCHASE unit and is '
            . 'the same at every outlet - Items have no per-outlet price. '
            . 'Leave Purchase Price blank to leave that item unchanged. '
            . 'Consumption Unit Cost is shown for reference and is recalculated automatically.');
        $headers = array('Item ID *', 'Code', 'Item Name', 'Purchase Unit',
                         'Conversion Rate', 'Purchase Price', 'Consumption Unit Cost (auto)');
        foreach ($headers as $c => $h) {
            $sheet->setCellValueByColumnAndRow($c, 3, $h);
        }
        $row = 4;
        foreach ($items as $it) {
            $sheet->setCellValueByColumnAndRow(0, $row, $it->id);
            $sheet->setCellValueByColumnAndRow(1, $row, $it->code);
            $sheet->setCellValueByColumnAndRow(2, $row, $it->name);
            $sheet->setCellValueByColumnAndRow(3, $row, $it->purchase_unit);
            $sheet->setCellValueByColumnAndRow(4, $row, $it->conversion_rate);
            $sheet->setCellValueByColumnAndRow(5, $row, $it->purchase_price);
            $sheet->setCellValueByColumnAndRow(6, $row, $it->consumption_unit_cost);
            $row++;
        }
        foreach (range('A', 'G') as $c) {
            $sheet->getColumnDimension($c)->setAutoSize(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Item_Price_Update.xlsx"');
        header('Cache-Control: max-age=0');
        PHPExcel_IOFactory::createWriter($xls, 'Excel2007')->save('php://output');
        exit;
    }

    /**
     * Apply an edited price file.
     * @access public
     * @return void
     */
    public function ExcelDataUpdateItemPrices() {
        $company_id = $this->session->userdata('company_id');
        if (!isset($_FILES['userfile']) || $_FILES['userfile']['name'] == '') {
            $this->session->set_flashdata('exception_err', 'File is required');
            redirect('ingredient/bulkPriceItem');
        }
        $configUpload['upload_path'] = FCPATH . 'asset/excel/';
        $configUpload['allowed_types'] = 'xls|xlsx';
        $configUpload['max_size'] = '5000';
        $this->load->library('upload', $configUpload);
        if (!$this->upload->do_upload('userfile')) {
            $this->session->set_flashdata('exception_err', $this->upload->display_errors());
            redirect('ingredient/bulkPriceItem');
        }
        $upload_data = $this->upload->data();
        $path = FCPATH . 'asset/excel/' . $upload_data['file_name'];

        $reader = PHPExcel_IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->setActiveSheetIndex(0);
        $totalrows = $sheet->getHighestRow();
        if ($totalrows < 4) {
            @unlink($path);
            $this->session->set_flashdata('exception_err', 'No data rows found.');
            redirect('ingredient/bulkPriceItem');
        }

        $errors = '';
        $parsed = array();
        for ($i = 4; $i <= $totalrows; $i++) {
            $item_id = trim_checker($sheet->getCellByColumnAndRow(0, $i)->getValue());
            if ($item_id === '') {
                continue;
            }
            if (!ctype_digit((string) $item_id)) {
                $errors .= ($errors == '' ? '' : '<br>') . "Row $i: column A must be the Item ID";
                continue;
            }
            $price = $this->ir_cell($sheet->getCellByColumnAndRow(5, $i)->getValue());
            if ($price === '') {
                continue;   // blank = leave unchanged
            }
            if (!is_numeric($price) || (float) $price < 0) {
                $errors .= ($errors == '' ? '' : '<br>')
                    . "Row $i column F: Purchase Price must be a number of 0 or more";
                continue;
            }
            $item = $this->db->query(
                "SELECT id, conversion_rate, purchase_price FROM tbl_ingredients
                  WHERE id = ? AND company_id = ? AND del_status = 'Live'",
                array((int) $item_id, $company_id))->row();
            if (!$item) {
                $errors .= ($errors == '' ? '' : '<br>') . "Row $i: Item ID $item_id not found";
                continue;
            }
            /* Same divisor guard as the importer: a stored conversion_rate of 0
               would make the recalculation below a fatal DivisionByZeroError. */
            if (!is_numeric($item->conversion_rate) || (float) $item->conversion_rate <= 0) {
                $errors .= ($errors == '' ? '' : '<br>')
                    . "Row $i: Item ID $item_id has a conversion rate of "
                    . "0, so its consumption unit cost cannot be recalculated. Fix the item first.";
                continue;
            }
            /* Skip rows whose price is unchanged, so re-uploading an unedited file
               writes nothing at all. Without this the recalculation below fires on
               every row and quietly rewrites consumption_unit_cost wherever the
               stored value was merely rounded (25/0.7 stored as 35.71, recomputed
               35.7143) - changing valuation data the operator never touched. Any
               such pre-existing rounding drift is left alone deliberately; correcting
               it is a separate decision, not a side effect of a price upload. */
            if (abs((float) $price - (float) $item->purchase_price) < 0.00005) {
                continue;
            }
            $parsed[] = array('id' => (int) $item_id, 'price' => (float) $price,
                              'conv' => (float) $item->conversion_rate);
        }
        if ($errors !== '') {
            @unlink($path);
            $this->session->set_flashdata('exception_err', "Nothing was changed. $errors");
            redirect('ingredient/bulkPriceItem');
        }

        if (!$parsed) {
            @unlink($path);
            $this->session->set_flashdata('exception',
                'No changes to apply - every Purchase Price was blank or already matched.');
            redirect('ingredient/bulkPriceItem');
        }
        $this->db->trans_begin();
        $changed = 0;
        foreach ($parsed as $p) {
            /* purchase_price is not standalone: consumption_unit_cost is derived
               from it and feeds stock valuation, and the item form and importer
               both keep average_consumption_per_unit in step with it. Updating the
               price alone would leave valuation quoting the old cost. */
            $unit_cost = $p['price'] / $p['conv'];
            $this->db->where('id', $p['id']);
            $this->db->update('tbl_ingredients', array(
                'purchase_price'               => $p['price'],
                'consumption_unit_cost'        => $unit_cost,
                'average_consumption_per_unit' => $unit_cost,
            ));
            $changed++;
        }
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            $this->session->set_flashdata('exception_err', 'Update failed, nothing was changed.');
        } else {
            $this->db->trans_commit();
            $this->session->set_flashdata('exception',
                "Purchase price updated on $changed item(s); consumption unit cost recalculated.");
        }
        @unlink($path);
        redirect('ingredient/bulkPriceItem');
    }

}
