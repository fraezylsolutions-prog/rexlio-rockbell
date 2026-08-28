<?php
/**
 * Shared invoice-detail popup (report batch R2).
 *
 * Include this once near the end of any report view whose rows carry a sale id,
 * then render the invoice number as:
 *
 *     <a href="#" class="ir_invoice_link" data-sale_id="<?= $value->id ?>">SALE-NO</a>
 *
 * and include frequent_changing/js/report_invoice_popup.js after it.
 *
 * Modelled on the Phase C running-order modal (#roOrderDetailsModal) but given its
 * own id, because the two are fed by DIFFERENT sources: that one reads
 * tbl_kitchen_sales (live orders), this one reads tbl_sales (completed). Sharing
 * one modal id between them is how a popup ends up silently showing nothing.
 *
 * Language strings are passed through hidden inputs rather than being written into
 * the JS, so the script stays a static asset and the labels stay translatable.
 */
?>
<div class="modal fade" id="irInvoiceDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('invoice_details'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ir_invoice_details_body"></div>
        </div>
    </div>
</div>
<?php /* base_url is NOT a JS global in this app - the Phase C screens pass it through
         a hidden input and the report scripts must do the same, or the AJAX URL
         resolves against "undefined". */ ?>
<input type="hidden" id="ir_inv_base_url"       value="<?php echo base_url(); ?>">
<input type="hidden" id="ir_inv_lang_no_data"   value="<?php echo escape_output(lang('no_data_found')); ?>">
<input type="hidden" id="ir_inv_lang_loading"   value="<?php echo escape_output(lang('please_wait')); ?>">
<input type="hidden" id="ir_inv_lang_invoice"   value="<?php echo escape_output(lang('sale_no')); ?>">
<input type="hidden" id="ir_inv_lang_date"      value="<?php echo escape_output(lang('date')); ?>">
<input type="hidden" id="ir_inv_lang_time"      value="<?php echo escape_output(lang('time')); ?>">
<input type="hidden" id="ir_inv_lang_seller"    value="<?php echo escape_output(lang('seller')); ?>">
<input type="hidden" id="ir_inv_lang_waiter"    value="<?php echo escape_output(lang('waiter')); ?>">
<input type="hidden" id="ir_inv_lang_customer"  value="<?php echo escape_output(lang('customer')); ?>">
<input type="hidden" id="ir_inv_lang_outlet"    value="<?php echo escape_output(lang('outlet')); ?>">
<input type="hidden" id="ir_inv_lang_item"      value="<?php echo escape_output(lang('item')); ?>">
<input type="hidden" id="ir_inv_lang_code"      value="<?php echo escape_output(lang('code')); ?>">
<input type="hidden" id="ir_inv_lang_qty"       value="<?php echo escape_output(lang('qty')); ?>">
<input type="hidden" id="ir_inv_lang_price"     value="<?php echo escape_output(lang('unit_price')); ?>">
<input type="hidden" id="ir_inv_lang_total"     value="<?php echo escape_output(lang('total')); ?>">
<input type="hidden" id="ir_inv_lang_subtotal"  value="<?php echo escape_output(lang('subtotal')); ?>">
<input type="hidden" id="ir_inv_lang_discount"  value="<?php echo escape_output(lang('discount')); ?>">
<input type="hidden" id="ir_inv_lang_vat"       value="<?php echo escape_output(lang('vat')); ?>">
<input type="hidden" id="ir_inv_lang_payable"   value="<?php echo escape_output(lang('g_total')); ?>">
<input type="hidden" id="ir_inv_lang_payment"   value="<?php echo escape_output(lang('payment_method')); ?>">
<input type="hidden" id="ir_inv_lang_due"       value="<?php echo escape_output(lang('due')); ?>">
