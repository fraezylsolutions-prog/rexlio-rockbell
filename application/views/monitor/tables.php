<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/table_status.css?v=7.8">

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
                <span class="ts_summary ts_summary_occupied"><?php echo lang('occupied'); ?>: <span id="ts_occupied_count"><?php echo escape_output($occupied_count); ?></span></span>
                <span class="ts_summary ts_summary_free"><?php echo lang('free'); ?>: <span id="ts_free_count"><?php echo escape_output($total_count - $occupied_count); ?></span></span>
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
        <div id="ts_card_area">
            <?php $this->view('monitor/_table_cards', array('tables' => $tables)); ?>
        </div>
    </div>
</section>

<input type="hidden" id="ts_base_url" value="<?php echo base_url() ?>">

<script src="<?php echo base_url(); ?>frequent_changing/js/table_status.js?v=7.8"></script>
