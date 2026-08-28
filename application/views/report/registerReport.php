 <link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/report.css">
<?php

    $show_register_report = "";
    if(isset($register_info) && count($register_info)>0){
        
        $i = 1;
        $html_p = '';
        foreach($register_info as $single_register_info){
            $payment_methods_sale = json_decode($single_register_info->payment_methods_sale);
            $html_p = '';
            $j=0;
            $total_used_payment = 0;
            if(isset($payment_methods_sale) && $payment_methods_sale){
                foreach ($payment_methods_sale as $key=>$value){
                    $total_used_payment++;
                }
            }
            if(isset($payment_methods_sale) && $payment_methods_sale){
                foreach ($payment_methods_sale as $key=>$value){
                    $html_p .= $key.": ".getAmtPCustom($value);
                    if($j < ($total_used_payment -1)){
                        $html_p .= ", ";
                    }
                    $j++;
                }
            }
            $html_others = '';
            if(isset($single_register_info->others_currency) && $single_register_info->others_currency){

                $others_details = json_decode($single_register_info->others_currency);
                foreach ($others_details as $key=>$vl){
                    $html_others .= $vl->payment_name.": ".($vl->amount);
                    if($key < (sizeof($others_details) -1)){
                        $html_others .= ", ";
                    }
                }
            }

            $show_register_report .= "<tr>";
            $show_register_report .= '<td>'.$i.'</td>';
            $show_register_report .= '<td>'.$single_register_info->counter_name.'</td>';
            $show_register_report .= '<td>'.$single_register_info->opening_balance_date_time.'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->opening_balance).'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->sale_paid_amount).'</td>';
            $show_register_report .= '<td>'.getAmtP($single_register_info->refund_amount).'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->customer_due_receive).'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->total_purchase).'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->total_expense).'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->total_due_payment).'</td>';
            $show_register_report .= '<td>'.$html_others.'</td>';
            $show_register_report .= '<td>'.$single_register_info->closing_balance_date_time.'</td>';
            $show_register_report .= '<td>'.getAmtPCustom($single_register_info->closing_balance).'</td>';
            $show_register_report .= '<td>'.$html_p.'</td>';
            /* R6: read-only details popup. No Close action here - closing a
               register stays a live operational task elsewhere in the app. */
            $show_register_report .= '<td class="ir_txt_center not-export-col ir_register_action">'
                . '<button type="button" class="btn btn-sm bg-blue-btn ir_register_view" data-register_id="'
                . escape_output($single_register_info->id) . '">' . lang('view') . '</button></td>';
            $show_register_report .= "</tr>";        
            $i++;
        }
    }
    $user_option = '';
    foreach($users as $single_user){
        $user_option .= '<option value="'.$single_user->id.'">'.$single_user->full_name.'</option>';
    }

?>

<section class="main-content-wrapper">


    <section class="content-header px-0">
        <div class="d-flex align-items-center">
            <h3 class="top-left-header text-left">
                <?php echo lang('register_report'); ?>
                <input type="hidden" class="datatable_name" data-id_name="datatable">

            </h3>
            <?php if(isLMni() && isset($outlet_id)):?>
                <p class="mx-2 txt-color-grey my-0"> <?php echo lang('outlet'); ?>: <?php echo escape_output(getOutletNameById($outlet_id))?></p>
            <?php endif;?>
        </div>
        <h4 class="ir_txtCenter_mt0 txt-color-grey"><?php
            if (isset($user_id) && $user_id):
                echo "User: " . userName($user_id) . "</span>";
            endif;
            ?>
        </h4>
        <h4 class="txt-color-grey"><?= isset($start_date) && $start_date && isset($end_date) && $end_date ? lang('date').": " . date($this->session->userdata('date_format'), strtotime($start_date)) . " - " . date($this->session->userdata('date_format'), strtotime($end_date)) : '' ?><?= isset($start_date) && $start_date && !$end_date ? lang('date').": " . date($this->session->userdata('date_format'), strtotime($start_date)) : '' ?><?= isset($end_date) && $end_date && !$start_date ? lang('date').": " . date($this->session->userdata('date_format'), strtotime($end_date)) : '' ?>
        </h4>      
    </section>

    
    <div class="box-wrapper">
    <div class="test-filter-modals mb-2">
        <div class="row">
            <div class="col-sm-12 mb-2 col-md-4 col-lg-2">
                <?php echo form_open(base_url() . 'Report/registerReport') ?>
                <div class="form-group">
                    <input tabindex="1" type="text" id="" name="startDate" readonly class="form-control customDatepicker"
                        placeholder="<?php echo lang('start_date'); ?>" value="<?php echo set_value('startDate'); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-2 col-md-4 col-lg-2">

                <div class="form-group">
                    <input tabindex="2" type="text" id="endMonth" name="endDate" readonly
                        class="form-control customDatepicker" placeholder="<?php echo lang('end_date'); ?>"
                        value="<?php echo set_value('endDate'); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-2 col-md-4 col-lg-2">

                <div class="form-group">
                    <select tabindex="2" class="form-control select2 ir_w_100" id="user_id" name="user_id">
                        <option value=""><?php echo lang('user'); ?></option>
                        <?php
                        foreach ($users as $value) {
                            ?>
                        <option <?php echo set_select('user_id',$value->id) ?> value="<?php echo escape_output($value->id) ?>"><?php echo escape_output($value->full_name) ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <?php if(isLMni()): ?>
                <div class="col-sm-12 mb-2 col-md-4 col-lg-2">
                        <div class="form-group">
                            <select tabindex="2" class="form-control select2 ir_w_100" id="outlet_id" name="outlet_id">
                                <?php
                                $outlets = getAllOutlestByAssign();
                                foreach ($outlets as $value):
                                    ?>
                                    <option <?= set_select('outlet_id',$value->id)?>  value="<?php echo escape_output($value->id) ?>"><?php echo escape_output($value->outlet_name) ?></option>
                                    <?php
                                endforeach;
                                ?>
                            </select>
                        </div>
                </div>
            <?php endif; ?>
            <div class="col-sm-12 mb-2 col-md-4 col-lg-2">
                <div class="form-group">
                    <button type="submit" name="submit" value="submit"
                        class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                </div>
            </div>
        </div>
    </div>
        <div class="table-box">
                <!-- /.box-header -->
                <div class="table-responsive">

                    <table id="datatable" class="table">
                        <thead>
                            <tr>
                                <th class="title" class="ir_w_5"><?php echo lang('sn'); ?></th>
                                <th class="title" class="ir_w_10"><?php echo lang('counter'); ?></th>
                                <th class="title" class="ir_w_10"><?php echo lang('opening_date_time'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('opening_balance'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('sale'); ?>
                                    (<?php echo lang('paid_amount'); ?>)</th>
                                <th class="title" class="ir_w_15"><?php echo lang('refund_amount'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('customer_due_receive'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('purchase'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('expense'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('due_payment'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('others_currency'); ?></th>
                                <th class="title" class="ir_w_10"><?php echo lang('closing_date_time'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('closing_balance'); ?></th>
                                <th class="title" class="ir_w_15"><?php echo lang('sale_in_payment_method'); ?></th>
                                <th class="title ir_w_5 ir_txt_center not-export-col ir_register_action"><?php echo lang('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            /*This variable could not be escaped because this is base url content*/
                            echo ($show_register_report);
                            ?>
                        </tbody>
                       
                    </table>
                </div>
                <!-- /.box-body -->
        </div>
    </div>

   
</section>
<!-- DataTables -->
<script src="<?php echo base_url(); ?>assets/datatable_custom/jquery-3.3.1.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url(); ?>assets/bower_components/datatables.net-bs/js/dataTables.bootstrap.min.js">
</script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/dataTables.buttons.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.colVis.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.html5.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.print.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/jszip.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/pdfmake.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/vfs_fonts.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/newDesign/js/forTable.js"></script>

<script src="<?php echo base_url(); ?>frequent_changing/js/custom_report_no_sorting.js?v=1.1"></script>

<style id="ir_register_view_fix">
/* R6 hit-target fix.
   Symptom: the View button rendered and worked when clicked programmatically, but a
   real mouse click never reached it and the cursor stayed an arrow - i.e. the button
   was visible and functional but was not the element under the pointer.
   The DataTables config on this page sets no scrollX/fixedHeader, so there is no
   scroll clone; the cause is something painting above the last column of a table that
   is now 15 columns wide. These three declarations address that class directly:
     - position/z-index  lifts the cell out from under an overlapping sibling
     - pointer-events    defeats a `none` inherited from an ancestor
     - cursor            restores the affordance, and is the visible tell that this
                         rule is actually being applied */
#datatable td.ir_register_action,
#datatable th.ir_register_action {
    position: relative;
    z-index: 5;
}
.ir_register_view {
    position: relative;
    z-index: 6;
    pointer-events: auto !important;
    cursor: pointer !important;
    white-space: nowrap;
}
</style>
<?php /* R6: register details popup. Read only - it calls Report/registerDetailsAjax,
         which selects and returns. Sale::registerDetailCalculationToShow() is not
         involved: that belongs to the live register-closing flow. */ ?>
<div class="modal fade" id="irRegisterDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('register_details'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ir_register_details_body"></div>
            <div class="modal-footer">
                <button type="button" class="btn bg-blue-btn" id="ir_register_print"><?php echo lang('print'); ?></button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="ir_reg_base_url"      value="<?php echo base_url(); ?>">
<input type="hidden" id="ir_reg_lang_no_data"  value="<?php echo escape_output(lang('no_data_found')); ?>">
<input type="hidden" id="ir_reg_lang_loading"  value="<?php echo escape_output(lang('please_wait')); ?>">
<input type="hidden" id="ir_reg_lang_counter"  value="<?php echo escape_output(lang('counter')); ?>">
<input type="hidden" id="ir_reg_lang_outlet"   value="<?php echo escape_output(lang('outlet')); ?>">
<input type="hidden" id="ir_reg_lang_user"     value="<?php echo escape_output(lang('user')); ?>">
<input type="hidden" id="ir_reg_lang_email"    value="<?php echo escape_output(lang('email')); ?>">
<input type="hidden" id="ir_reg_lang_method"   value="<?php echo escape_output(lang('payment_method')); ?>">
<input type="hidden" id="ir_reg_lang_sell"     value="<?php echo escape_output(lang('sale')); ?>">
<input type="hidden" id="ir_reg_lang_expense"  value="<?php echo escape_output(lang('expense')); ?>">
<input type="hidden" id="ir_reg_lang_summary"  value="<?php echo escape_output(lang('summary')); ?>">
<input type="hidden" id="ir_reg_lang_derived"  value="<?php echo escape_output(lang('total_sales_derived')); ?>">
<input type="hidden" id="ir_reg_lang_atclose"  value="<?php echo escape_output(lang('total_sales_at_close')); ?>">
<input type="hidden" id="ir_reg_lang_orders"   value="<?php echo escape_output(lang('total_orders')); ?>">
<input type="hidden" id="ir_reg_lang_completed" value="<?php echo escape_output(lang('completed')); ?>">
<input type="hidden" id="ir_reg_lang_credit"   value="<?php echo escape_output(lang('credit_sales')); ?>">
<input type="hidden" id="ir_reg_lang_purchase" value="<?php echo escape_output(lang('purchase')); ?>">
<input type="hidden" id="ir_reg_lang_duerecv"  value="<?php echo escape_output(lang('customer_due_receive')); ?>">
<input type="hidden" id="ir_reg_lang_supplier" value="<?php echo escape_output(lang('supplier_payment')); ?>">
<input type="hidden" id="ir_reg_lang_products" value="<?php echo escape_output(lang('products_sold')); ?>">
<input type="hidden" id="ir_reg_lang_bycat"    value="<?php echo escape_output(lang('products_sold_by_category')); ?>">
<input type="hidden" id="ir_reg_lang_code"     value="<?php echo escape_output(lang('code')); ?>">
<input type="hidden" id="ir_reg_lang_product"  value="<?php echo escape_output(lang('product')); ?>">
<input type="hidden" id="ir_reg_lang_category" value="<?php echo escape_output(lang('category')); ?>">
<input type="hidden" id="ir_reg_lang_qty"      value="<?php echo escape_output(lang('qty')); ?>">
<input type="hidden" id="ir_reg_lang_total"    value="<?php echo escape_output(lang('total')); ?>">
<input type="hidden" id="ir_reg_lang_daynote"  value="<?php echo escape_output(lang('register_day_granular_note')); ?>">
<script src="<?php echo base_url(); ?>frequent_changing/js/register_details_popup.js?v=1.0"></script>
