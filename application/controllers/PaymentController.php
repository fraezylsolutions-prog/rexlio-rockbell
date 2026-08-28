<?php
/*
  ###########################################################
  # PRODUCT NAME: 	Door Knock
  ###########################################################
  # AUTHER:		Doorsoft
  ###########################################################
  # EMAIL:		info@doorsoft.co
  ###########################################################
  # COPYRIGHTS:		RESERVED BY Door Soft
  ###########################################################
  # WEBSITE:		http://www.doorsoft.co
  ###########################################################
  # This is PaymentController Controller
  ###########################################################
 */
if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'libraries/paypal-php-sdk/paypal/rest-api-sdk-php/sample/bootstrap.php'); // require paypal files

use PayPal\Api\ItemList;
use PayPal\Api\Payment;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Amount;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RefundRequest;
use PayPal\Api\Sale;

class PaymentController extends Cl_Controller
{
    public $_api_context;

    function  __construct()
    {
        parent::__construct();
        $this->load->model('Payment_model');
        $this->load->model('Common_model');
        $this->load->model('Frontend_model'); 
        // paypal credentials
        $this->config->load('paypal');
        // Load Stripe library
        $this->load->library('stripe_lib');
    }
 

    public function add_kitchen_sale_by_ajax(){
        /*This variable could not be escaped because this is json data*/
        $order = $this->input->post('order');
        $order_details = (json_decode($order));
        //this id will be 0 when there is new order, but will be greater then 0 when there is modification
        //on previous order
        $sale_no = $order_details->sale_no;
        $sale_d = getKitchenSaleDetailsBySaleNo($sale_no);
        $data = array();
        $data['customer_id'] = trim($order_details->customer_id);
        $data['counter_id'] = trim($order_details->counter_id);
        $item_list = array();
        $data['is_online_order'] = "Yes";
        $data['is_accept'] = 2;
        $data['online_order_receiving_id'] = getOnlineOrderReceivingId($order_details->outlet_id);
        
        $designation = $this->session->userdata('designation');

        if($designation!="Admin" && $designation!="Super Admin"){
            $data['order_receiving_id'] = '';
            $data['order_receiving_id_admin'] =$this->Frontend_model->getOrderReceivingIdAdmin();
        }
        $data['self_order_content'] = $order;
        $data['del_address'] = trim($order_details->customer_address)!="undefined"?trim($order_details->customer_address):"";
        $data['delivery_partner_id'] = trim($order_details->delivery_partner_id);
        $data['rounding_amount_hidden'] = trim($order_details->rounding_amount_hidden);
        $data['previous_due_tmp'] = trim($order_details->previous_due_tmp);
        $data['total_items'] = trim($order_details->total_items_in_cart);
        $data['sub_total'] = trim($order_details->sub_total);
        $data['charge_type'] = trim($order_details->charge_type);
        $data['vat'] = trim($order_details->total_vat);
        $data['total_payable'] = trim($order_details->total_payable);
        $data['total_item_discount_amount'] = trim($order_details->total_item_discount_amount);
        $data['sub_total_with_discount'] = trim($order_details->sub_total_with_discount);
        $data['sub_total_discount_amount'] = trim($order_details->sub_total_discount_amount);
        $data['total_discount_amount'] = trim($order_details->total_discount_amount);
        $data['delivery_charge'] = trim($order_details->delivery_charge);
        $data['delivery_charge_actual_charge'] = trim($order_details->delivery_charge_actual_charge);
        $data['tips_amount'] = trim($order_details->tips_amount);
        $data['tips_amount_actual_charge'] = trim($order_details->tips_amount_actual_charge);
        $data['sub_total_discount_value'] = trim($order_details->sub_total_discount_value);
        $data['sub_total_discount_type'] = trim($order_details->sub_total_discount_type);
        $data['orders_table_text'] = '';
        $data['waiter_id'] = trim($order_details->waiter_id);
        $data['outlet_id'] = $order_details->outlet_id;
        $data['company_id'] = 1;
        $data['sale_date'] = trim(isset($order_details->open_invoice_date_hidden) && $order_details->open_invoice_date_hidden?$order_details->open_invoice_date_hidden:date('Y-m-d'));
        $data['date_time'] = date('Y-m-d H:i:s',strtotime($order_details->date_time));
        $data['order_time'] = date("H:i:s",strtotime($order_details->order_time));
        $data['order_status'] = trim($order_details->order_status);
        $data['sale_no'] = $sale_no;
        $data['is_pickup_sale'] = 1;
        $payment_method = $this->input->post('payment_method');
        $del_status_value = "Deleted";
        if($payment_method=="cash_on_delivery"){
            $del_status_value = "Live";
        }
        
        $data['del_status'] = $del_status_value;
 
        //update customer information 
        $customer_info['address'] = preg_replace("/[\n\r]/"," ",escape_output($data['del_address'])); #remove new line from address
        $this->Common_model->updateInformation($customer_info, $data['customer_id'], "tbl_customers");

        $return_data['customer_address'] = $customer_info['address'];
        $this->session->set_userdata($return_data);

        $data['vat'] = $order_details->total_vat;
        $data['sale_vat_objects'] = json_encode($order_details->sale_vat_objects);
        $data['order_type'] = trim($order_details->order_type);
        $this->db->trans_begin();
        $sale_id = isset($sale_d->id) && $sale_d->id?$sale_d->id:'';
        if($sale_id>0){
            $data['user_id'] = $sale_d->user_id;
            $data['modified'] = 'Yes';
            $data['is_update_sender'] = 1;
            $data['is_update_receiver'] = 1;
            $data['is_update_receiver_admin'] = 1;
            $this->db->where('id', $sale_id);
            $this->db->update('tbl_kitchen_sales', $data);
            checkAndRemoveAllRemovedItem($order_details->items,$sale_id);
        }else{
            $data['user_id'] = 0;
            $data['random_code'] = trim(isset($order_details->random_code) && $order_details->random_code?$order_details->random_code:'');
            $this->db->insert('tbl_kitchen_sales', $data);
            $sale_id = $this->db->insert_id();    
        }
       
        if($sale_id>0 && count($order_details->items)>0){
            $previous_food_id = 0;
            $arr_item_id = array();
            foreach($order_details->items as $key_counter=>$item){
                $tmp_var_111 = isset($item->p_qty) && $item->p_qty && $item->p_qty!='undefined'?$item->p_qty:0;
                $tmp = $item->qty-$tmp_var_111;
                $tmp_var = 0;
                if($tmp>0){
                    $tmp_var = $tmp;
                }

                $item_data = array();
                $item_data['food_menu_id'] = $item->food_menu_id;
                $item_data['menu_name'] = $item->menu_name;
                if($item->is_free==1){
                    $item_data['is_free_item'] = $previous_food_id;
                }else{
                    $item_data['is_free_item'] = 0;
                }
                $inline_array = array();
                $inline_array['ItemName'] = $item->menu_name;
                $inline_array['Quantity'] = $item->qty;
                $inline_array['UnitPrice'] = $item->menu_unit_price;
                $item_list[] = $inline_array;

                $item_data['qty'] = $item->qty;
                $item_data['tmp_qty'] = $tmp_var;
                $item_data['menu_price_without_discount'] = $item->menu_unit_price;
                $item_data['menu_price_with_discount'] = $item->menu_unit_price;
                $item_data['menu_unit_price'] = $item->menu_unit_price;
                $item_data['menu_taxes'] = json_encode($item->item_vat);
                $item_data['menu_discount_value'] = $item->menu_discount_value;
                $item_data['discount_type'] = $item->discount_type;
                $item_data['menu_note'] = $item->item_note;
                $item_data['menu_combo_items'] = $item->menu_combo_items;
                $item_data['discount_amount'] = $item->item_discount_amount;
                $item_data['item_type'] = "Kitchen Item";
                $item_data['cooking_status'] = ($item->item_cooking_status=="")?NULL:$item->item_cooking_status;
                $item_data['cooking_start_time'] = ($item->item_cooking_start_time=="" || $item->item_cooking_start_time=="0000-00-00 00:00:00")?'0000-00-00 00:00:00':date('Y-m-d H:i:s',strtotime($item->item_cooking_start_time));
                $item_data['cooking_done_time'] = ($item->item_cooking_done_time=="" || $item->item_cooking_done_time=="0000-00-00 00:00:00")?'0000-00-00 00:00:00':date('Y-m-d H:i:s',strtotime($item->item_cooking_done_time));
                $item_data['previous_id'] = ($item->item_previous_id=="")?0:$item->item_previous_id;
                $item_data['sales_id'] = $sale_id;
                $item_data['user_id'] = 0;
                $item_data['outlet_id'] = trim($order_details->outlet_id);
                if($order_details->customer_id!=1){
                    $item_data['loyalty_point_earn'] = ($item->qty * getLoyaltyPointByFoodMenu($item->food_menu_id,''));
                }
                $item_data['del_status'] = $del_status_value;
                $item_data['cooking_status'] = 'New';

                $sales_details_id = '';
                $this->db->insert('tbl_kitchen_sales_details', $item_data);
                $sales_details_id = $this->db->insert_id();

                $previous_food_id = $sales_details_id;
                $update_previous_id = array();
                $update_previous_id['previous_id'] = $previous_food_id;
                $this->Common_model->updateInformation($update_previous_id, $sales_details_id, "tbl_kitchen_sales_details");


                $modifier_id_array = ($item->modifiers_id!="")?explode(",",$item->modifiers_id):null;
                $modifier_name_array = ($item->modifiers_name!="")?explode(",",$item->modifiers_name):null;
                $modifier_price_array = ($item->modifiers_price!="")?explode(",",$item->modifiers_price):null;
                $modifier_vat_array = (isset($item->modifier_vat) && $item->modifier_vat!="")?explode("|||",$item->modifier_vat):null;
                if(!empty($modifier_id_array)>0){
                    $i = 0;
                    foreach($modifier_id_array as $key1=>$single_modifier_id){
                        $inline_array = array();
                        $inline_array['ItemName'] = $modifier_name_array[$i];
                        $inline_array['Quantity'] = $item->qty;
                        $inline_array['UnitPrice'] = ($modifier_price_array[$i]/$item->qty);
                        $item_list[] = $inline_array;


                        $modifier_data = array();
                        $modifier_data['modifier_id'] =$single_modifier_id;
                        $modifier_data['modifier_price'] = $modifier_price_array[$i];
                        $modifier_data['food_menu_id'] = $item->food_menu_id;
                        $modifier_data['sales_id'] = $sale_id;
                        $modifier_data['sales_details_id'] = $sales_details_id;
                        $modifier_data['menu_taxes'] = isset($modifier_vat_array[$key1]) && $modifier_vat_array[$key1]?$modifier_vat_array[$key1]:'';
                        $modifier_data['user_id'] = 0;
                        $modifier_data['outlet_id'] = trim($order_details->outlet_id);
                        $modifier_data['customer_id'] =$order_details->customer_id;
                        $this->db->insert('tbl_kitchen_sales_details_modifiers', $modifier_data);

                        $i++;
                    }
                }
            }
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
        } else {
            $this->db->trans_commit();
            $return['status'] = true;
            $return['order_id'] = d($sale_id,1);

            if($payment_method=="cash_on_delivery"){
                $notification = "a new online order has been placed, Order Number is: ".$sale_no;
                $notification_data = array();
                $notification_data['notification'] = $notification;
                $notification_data['sale_id'] = $sale_id;
                $notification_data['waiter_id'] = trim_checker($order_details->waiter_id);
                $notification_data['outlet_id'] = trim($order_details->outlet_id);
                $this->db->insert('tbl_notifications', $notification_data);
            }


            echo json_encode($return);
        }
    }
  
    public function paymentError() {
        $this->session->set_flashdata('error', lang('payment_fail'));
        redirect('checkout');
    }

    public function paymentSuccess() {
        $order_id_p = d($this->session->userdata('order_id_p'),2);
        $order_id_p_redirect = $this->session->userdata('order_id_p');
        $order_amount_p = $this->session->userdata('order_amount_p');
        $order_trans_p = $this->session->userdata('order_trans_p');
        
        if($order_id_p){
            $data_payment = array(
                'customer_id' => "",
                'order_id' => $order_id_p,
                'payment_type' => "PayPal",
                'card_type' => "",
                'amount' => $order_amount_p,
                'payment_date' => date("Y-m-d"),
                'details' => "",
                'txn_id' => $order_trans_p,
            );
            

            //update success order row
            $data = array();
            $data['del_status'] = "Live"; 
            $data['online_payment_details'] = json_encode($data_payment); 
            $this->Common_model->updateInformation($data, $order_id_p, "tbl_kitchen_sales");

            $this->session->unset_userdata('order_id_p');
            $this->session->unset_userdata('order_amount_p');
            $this->session->unset_userdata('order_trans_p');
        }

        $this->session->set_flashdata('success', lang('payment_success'));
        redirect('order-success/'.$order_id_p_redirect);
    }

    public function payment()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'GET'){
            redirect('/');
        }
         
        $check_stripe = isset($_POST['check_stripe']) && escape_output($_POST['check_stripe'])?escape_output($_POST['check_stripe']):'';
        if($check_stripe=="yes"){
            $order_id_str = escape_output($_POST['order_id_str']);
            $data = array();
            $data['total_payable_str'] = escape_output($_POST['total_payable_str']);
            $data['description'] = escape_output($_POST['item_description_str']);
            $data['order_id_str'] = $order_id_str;


            $data['header_content'] = $this->load->view('frontend/header_section_menu2', $data, TRUE);
            $data['main_content'] = $this->load->view('frontend/stripe', $data, TRUE);
            $this->load->view('frontend/website_layout', $data);

        }else{
            $config_for_paypal = $this->Payment_model->paymentConfig('paypal');
            $this->_api_context = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    $config_for_paypal[1], $config_for_paypal[2]
                )
            );
            $data_config_array = $this->config->item('settings');
            $data_config_array['mode'] = $config_for_paypal[0];
            $this->_api_context->setConfig($data_config_array);
            $payer['payment_method'] = 'paypal';
            //for check last order complete before payment
            $order_id_p = htmlspecialchars($this->input->post('order_id_p'));
            
           

            $item1["name"] = "".htmlspecialchars($this->input->post('item_name'))."";
            $item1["sku"] = isset($item_number) && $item_number?$item_number:1;  // Similar to `item_number` in Classic API
            $item1["description"] = htmlspecialchars($this->input->post('item_description'));
            $item1["currency"] ="USD";
            $item1["quantity"] =1;
            $item1["price"] = htmlspecialchars($this->input->post('item_price'));
            $itemList = new ItemList();
            $itemList->setItems(array($item1));
            $details['tax'] = 0;
            $details['subtotal'] = htmlspecialchars($this->input->post('item_price'));
            $amount['currency'] = "USD";
            $amount['total'] = htmlspecialchars($this->input->post('item_price'));
            $amount['details'] = $details;
            $transaction['description'] ='Payment description';
            $transaction['amount'] = $amount;
            $transaction['invoice_number'] = uniqid();


            $login_session['order_id_p'] = $order_id_p;
            $login_session['order_amount_p'] = $amount;
            $login_session['order_trans_p'] = $transaction['invoice_number'];
            $this->session->set_userdata($login_session);


            $transaction['item_list'] = $itemList;
            $baseUrl = base_url();
            $redirectUrls = new RedirectUrls(); 
            $redirectUrls->setReturnUrl($baseUrl."payment-success")->setCancelUrl($baseUrl."payment-fail");
            $payment = new Payment();
            $payment->setIntent("sale")
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions(array($transaction));

            try {
                $payment->create($this->_api_context);
            } catch (Exception $ex) {
                // NOTE: PLEASE DO NOT USE RESULTPRINTER CLASS IN YOUR ORIGINAL CODE. FOR SAMPLE ONLY
                echo "Payment Configuration Error";exit; 
            }
            foreach($payment->getLinks() as $link) {
                if($link->getRel() == 'approval_url') {
                    $redirect_url = $link->getHref();
                    break;
                }
            }

            if(isset($redirect_url)) {
                /** redirect to paypal **/
                redirect($redirect_url);
            }

            $this->session->set_flashdata('error', lang('payment_fail'));
            redirect('checkout');
        }
    }

    public function stripePayment(){
        if ($_SERVER['REQUEST_METHOD'] === 'GET'){
            redirect('/');
        }
 
        // If payment form is submitted with token
        if(htmlspecialchars($this->input->post('stripeToken'))){
            $order_id_str = escape_output($_POST['order_id_str']);
            $this->session->set_userdata('order_id_str', $order_id_str);

            // Retrieve stripe token and user info from the posted form data
            $postData = $this->input->post();
            // Make payment
            $paymentID = $this->paymentStripeData($postData);
            // If payment successful
            if($paymentID){
                redirect('payment-success');
            }else{
                redirect('payment-fail');
            }
        }
    }
    public function updateOrderSuccess(){
        if ($_SERVER['REQUEST_METHOD'] === 'GET'){
            redirect('/');
        }
            // Insert tansaction data into the database
            $data_payment = array(
                'customer_id' => "",
                'order_id' => $_POST['last_order_id'],
                'payment_type' => "Rezorpay",
                'card_type' => "",
                'amount' => $_POST['total_amount'],
                'payment_date' => date("Y-m-d"),
                'details' => "",
                'txn_id' => $_POST['razorpay_payment_id'],
            );

            
            //update success order row
            $data = array();
            $data['del_status'] = "Live"; 
            $data['online_payment_details'] = json_encode($data_payment); 
            $this->Common_model->updateInformation($data, $_POST['last_order_id'], "tbl_kitchen_sales");

            $return['status'] = "Success";
            echo json_encode($return);

    }

    public  function paymentStripeData($postData){
        // If post data is not empty
        if(!empty($postData)){
            // Retrieve stripe token and user info from the submitted form data
            $token  = $postData['stripeToken'];
            $email = $postData['email'];
            $price = $postData['payable_amount'];
            $description = $postData['description'];

            // Add customer to stripe
            $customer = $this->stripe_lib->addCustomer($email, $token);

            if($customer){
                // Charge a credit or a debit card
                $charge = $this->stripe_lib->createCharge($customer->id, $description, $price);
          
                if($charge){
                    // Check whether the charge is successful
                    if($charge['amount_refunded'] == 0 && empty($charge['failure_code']) && $charge['paid'] == 1 && $charge['captured'] == 1){
                        // Transaction details
                        $brand =  $charge['payment_method_details']['card']['brand'];
                        $type = $charge['payment_method_details']['type'];

                        $transactionID = $charge['balance_transaction'];
                        $paidAmount = $charge['amount'];
                        $paidAmount = ($paidAmount/100);
                        $payment_status = $charge['status'];
                        // If the order is successful
                        if($payment_status == 'succeeded'){
                            // Insert tansaction data into the database
                            $data_payment = array(
                                'customer_id' => $this->session->userdata('c_id'),
                                'order_id' => d(escape_output($this->session->userdata('order_id_str')),2),
                                'payment_type' => "Stripe",
                                'card_type' => $brand."-".$type,
                                'amount' => $paidAmount,
                                'payment_date' => date("Y-m-d"),
                                'details' => $description,
                                'txn_id' => $transactionID
                            );
                            
                            //update success order row
                            $data = array();
                            $data['del_status'] = "Live"; 
                            $data['online_payment_details'] = json_encode($data_payment); 

                            $this->Common_model->updateInformation($data, d(escape_output($this->session->userdata('order_id_str')),2), "tbl_kitchen_sales");

                            return true;;
                        }
                    }
                }
            }
        }else{

        }
        return false;
    }
}