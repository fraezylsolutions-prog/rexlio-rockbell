<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/table_status.css?v=7.9">

<?php
$occupied_count = 0;
foreach ($tables as $table) {
    if ($table->is_occupied) { $occupied_count++; }
}
$total_count = count($tables);
?>

<section class="main-content-wrapper">
    <section class="content-header">
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <h3 class="top-left-header"><?php echo lang('table_status'); ?></h3>
            </div>
            <div class="col-sm-12 col-md-6 text-end">
                <?php //plain ints: escape_output('0') is '' and a zero count is common once the Stage 4 filters narrow the list ?>
                <span class="ts_summary ts_summary_occupied"><?php echo lang('occupied'); ?>: <span id="ts_occupied_count"><?php echo (int) $occupied_count; ?></span></span>
                <span class="ts_summary ts_summary_free"><?php echo lang('free'); ?>: <span id="ts_free_count"><?php echo (int) ($total_count - $occupied_count); ?></span></span>
                <a href="<?php echo site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id')); ?>"
                   class="btn ts_close_btn" id="ts_close_screen">
                    <i class="fas fa-times"></i> <?php echo lang('back_to_sale_screen'); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- shown only when a background refresh cannot reach the server, so stale
         cards are never mistaken for live ones -->
    <div class="ts_offline_banner" id="ts_offline_banner" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo lang('offline_showing_last_known'); ?> <span id="ts_last_updated"></span>
    </div>

    <div class="box-wrapper">
        <?php if (!empty($can_view_all)): ?>
        <!-- Stage 4: outlet / user / date filters, handled server side in Monitor::tables() and
             carried by the poll (table_status.js). Only rendered for a caller who may view all. -->
        <?php echo form_open(base_url() . 'Monitor/tables', array('id' => 'tableStatusFilter')) ?>
        <div class="row mb-3">
            <div class="col-sm-12 mb-3 col-md-3">
                <select class="form-control select2 ir_w_100" name="outlet_id" id="ts_outlet_id">
                    <option value=""><?php echo lang('all'); ?> <?php echo lang('outlet'); ?></option>
                    <?php foreach ($outlets as $outlet): ?>
                        <option <?php echo ((string) $filters['outlet_id'] === (string) $outlet->id) ? 'selected' : ''; ?> value="<?php echo escape_output($outlet->id) ?>"><?php echo escape_output($outlet->outlet_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-12 mb-3 col-md-3">
                <select class="form-control select2 ir_w_100" name="view_user_id" id="ts_view_user_id">
                    <option value=""><?php echo lang('all'); ?> <?php echo lang('user'); ?></option>
                    <?php foreach ($users as $user): ?>
                        <option <?php echo ((string) $filters['view_user_id'] === (string) $user->id) ? 'selected' : ''; ?> value="<?php echo escape_output($user->id) ?>"><?php echo escape_output($user->full_name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-12 mb-3 col-md-3">
                <input type="date" name="sale_date" id="ts_sale_date" class="form-control" value="<?php echo escape_output($filters['sale_date']) ?>">
            </div>
            <div class="col-sm-12 mb-3 col-md-3">
                <div class="form-group d-flex">
                    <button type="submit" name="submit" value="submit" class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                    <a class="btn btn-secondary w-100 ms-2" href="<?php echo base_url() ?>Monitor/tables"><?php echo lang('reset'); ?></a>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
        <?php endif; ?>
        <div id="ts_card_area">
            <?php $this->view('monitor/_table_cards', array('tables' => $tables, 'can_act_any' => !empty($can_act_any), 'can_view_all' => !empty($can_view_all))); ?>
        </div>
    </div>
</section>

<input type="hidden" id="ts_base_url" value="<?php echo base_url() ?>">

<script src="<?php echo base_url(); ?>frequent_changing/js/table_status.js?v=7.9"></script>
