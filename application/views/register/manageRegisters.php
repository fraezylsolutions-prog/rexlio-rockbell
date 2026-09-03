<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/report.css">
<?php
/*
 * Admin Register Management (oversight screen).
 *
 * Lists every OPEN register this user may see (getAccessibleOutletIds - same
 * company-and-assignment scoping as the R6 register-details popup), oldest
 * opening first, so the most-abandoned register is the one an admin notices
 * first rather than the one buried at the bottom.
 *
 * This screen is READ here - it only renders what the controller already
 * fetched. All writing happens through the Force Close modal's own AJAX call,
 * never from this page directly.
 */
$rows_html = '';
$i = 1;
if (isset($open_registers) && $open_registers) {
    foreach ($open_registers as $reg) {
        $opened_ts = strtotime($reg->opening_balance_date_time);
        $days_open = $opened_ts ? round((time() - $opened_ts) / 86400, 1) : 0;
        $stale_cls = ($days_open >= 1) ? 'text-danger fw-bold' : '';

        $rows_html .= '<tr>';
        $rows_html .= '<td>' . $i . '</td>';
        $rows_html .= '<td>' . escape_output($reg->user_name) . '</td>';
        $rows_html .= '<td>' . escape_output($reg->outlet_name) . '</td>';
        $rows_html .= '<td>' . escape_output($reg->counter_name) . '</td>';
        $rows_html .= '<td>' . escape_output($reg->opening_balance_date_time) . '</td>';
        $rows_html .= '<td class="' . $stale_cls . '">' . $days_open . '</td>';
        $rows_html .= '<td>' . getAmtPCustom($reg->opening_balance) . '</td>';
        $rows_html .= '<td><button type="button" class="btn btn-sm btn-danger ir_force_close_btn" data-register_id="'
                    . (int) $reg->id . '">' . lang('force_close_register') . '</button></td>';
        $rows_html .= '</tr>';
        $i++;
    }
}
?>
<section class="content-header">
    <h1><?php echo lang('manage_registers'); ?></h1>
</section>
<section class="content">
    <div class="card">
        <div class="card-body">
            <p class="text-muted"><?php echo lang('manage_registers_note'); ?></p>
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="ir_open_registers_table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?php echo lang('user'); ?></th>
                            <th><?php echo lang('outlet'); ?></th>
                            <th><?php echo lang('counter'); ?></th>
                            <th><?php echo lang('opened_at'); ?></th>
                            <th><?php echo lang('days_open'); ?></th>
                            <th><?php echo lang('opening_balance'); ?></th>
                            <th><?php echo lang('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="ir_open_registers_tbody">
                        <?php if ($rows_html) { echo $rows_html; } else { ?>
                        <tr><td colspan="8" class="text-center text-muted"><?php echo lang('no_open_registers'); ?></td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<!-- Force Close modal: filled by AJAX (forceCloseRegisterCalc), submitted by AJAX
     (forceCloseRegister). Nothing here writes on its own. -->
<div class="modal fade" id="irForceCloseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('force_close_register'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ir_force_close_body">
                <p class="text-muted"><?php echo lang('please_wait'); ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="ir_force_close_submit" style="display:none;">
                    <?php echo lang('force_close_register'); ?>
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('cancel'); ?></button>
            </div>
        </div>
    </div>
</div>

<input type="hidden" id="ir_fc_base_url"        value="<?php echo base_url(); ?>">
<input type="hidden" id="ir_fc_lang_no_data"    value="<?php echo escape_output(lang('no_data_found')); ?>">
<input type="hidden" id="ir_fc_lang_loading"    value="<?php echo escape_output(lang('please_wait')); ?>">
<input type="hidden" id="ir_fc_lang_user"       value="<?php echo escape_output(lang('user')); ?>">
<input type="hidden" id="ir_fc_lang_outlet"     value="<?php echo escape_output(lang('outlet')); ?>">
<input type="hidden" id="ir_fc_lang_counter"    value="<?php echo escape_output(lang('counter')); ?>">
<input type="hidden" id="ir_fc_lang_opened_at"  value="<?php echo escape_output(lang('opened_at')); ?>">
<input type="hidden" id="ir_fc_lang_opening_balance" value="<?php echo escape_output(lang('opening_balance')); ?>">
<input type="hidden" id="ir_fc_lang_method"     value="<?php echo escape_output(lang('payment_method')); ?>">
<input type="hidden" id="ir_fc_lang_sale"       value="<?php echo escape_output(lang('sale')); ?>">
<input type="hidden" id="ir_fc_lang_purchase"   value="<?php echo escape_output(lang('purchase')); ?>">
<input type="hidden" id="ir_fc_lang_due_receive" value="<?php echo escape_output(lang('customer_due_receive')); ?>">
<input type="hidden" id="ir_fc_lang_due_payment" value="<?php echo escape_output(lang('due_payment')); ?>">
<input type="hidden" id="ir_fc_lang_expense"    value="<?php echo escape_output(lang('expense')); ?>">
<input type="hidden" id="ir_fc_lang_refund"     value="<?php echo escape_output(lang('refund')); ?>">
<input type="hidden" id="ir_fc_lang_closing"    value="<?php echo escape_output(lang('closing_balance')); ?>">
<input type="hidden" id="ir_fc_lang_system_computed" value="<?php echo escape_output(lang('force_close_system_computed')); ?>">
<input type="hidden" id="ir_fc_lang_counted_amount"  value="<?php echo escape_output(lang('force_close_counted_amount')); ?>">
<input type="hidden" id="ir_fc_lang_counted_amount_help" value="<?php echo escape_output(lang('force_close_counted_amount_help')); ?>">
<input type="hidden" id="ir_fc_lang_reason"     value="<?php echo escape_output(lang('force_close_reason')); ?>">
<input type="hidden" id="ir_fc_lang_reason_placeholder" value="<?php echo escape_output(lang('force_close_reason_placeholder')); ?>">
<input type="hidden" id="ir_fc_lang_confirm_note" value="<?php echo escape_output(lang('force_close_confirm_note')); ?>">
<input type="hidden" id="ir_fc_lang_counted_amount_required" value="<?php echo escape_output(lang('force_close_counted_amount_required')); ?>">
<input type="hidden" id="ir_fc_lang_reason_required" value="<?php echo escape_output(lang('force_close_reason_required')); ?>">
<input type="hidden" id="ir_fc_lang_success"    value="<?php echo escape_output(lang('force_close_success')); ?>">
<input type="hidden" id="ir_fc_lang_already_closed" value="<?php echo escape_output(lang('register_already_closed')); ?>">
<input type="hidden" id="ir_fc_lang_discrepancy" value="<?php echo escape_output(lang('discrepancy')); ?>">

<script type="text/javascript" src="<?php echo base_url(); ?>frequent_changing/js/register_force_close.js?v=1.0"></script>
