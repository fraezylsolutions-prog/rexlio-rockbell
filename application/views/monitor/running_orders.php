<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/running_orders.css?v=7.8">

<section class="main-content-wrapper">
    <section class="content-header">
        <div class="row">
            <div class="col-sm-12 col-md-8">
                <h3 class="top-left-header"><?php echo lang('running_order'); ?></h3>
            </div>
            <div class="col-sm-12 col-md-4 text-end">
                <span class="ro_count_badge"><span id="ro_total_count"><?php echo count($orders); ?></span></span>
                <a href="<?php echo site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id')); ?>"
                   class="btn ro_close_btn" id="ro_close_screen">
                    <i class="fas fa-times"></i> <?php echo lang('back_to_sale_screen'); ?>
                </a>
            </div>
        </div>
    </section>

    <!-- shown only when a background refresh cannot reach the server, so stale
         cards are never mistaken for live ones -->
    <div class="ro_offline_banner" id="ro_offline_banner" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i>
        <?php echo lang('offline_showing_last_known'); ?> <span id="ro_last_updated"></span>
    </div>

    <div class="box-wrapper">
        <!-- filters, handled server side in Monitor::runningOrders() -->
        <?php echo form_open(base_url() . 'Monitor/runningOrders', $arrayName = array('id' => 'runningOrderFilter')) ?>
        <div class="row mb-3">
            <div class="col-sm-12 mb-3 col-md-4 col-lg-3">
                <div class="form-group">
                    <select tabindex="1" class="form-control select2 ir_w_100" name="table_id" id="ro_table_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('table'); ?></option>
                        <?php foreach ($tables as $table): ?>
                            <option <?php echo set_select('table_id', $table->id); ?> value="<?php echo escape_output($table->id) ?>"><?php echo escape_output($table->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-3">
                <div class="form-group">
                    <input tabindex="2" type="text" name="sale_no" id="ro_sale_no" class="form-control"
                           placeholder="<?php echo lang('order_number'); ?>" value="<?php echo set_value('sale_no'); ?>">
                </div>
            </div>
            <?php if ($can_view_all_users): ?>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-3">
                <div class="form-group">
                    <select tabindex="3" class="form-control select2 ir_w_100" name="view_user_id" id="ro_view_user_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('user'); ?></option>
                        <?php foreach ($users as $user): ?>
                            <option <?php echo set_select('view_user_id', $user->id); ?> value="<?php echo escape_output($user->id) ?>"><?php echo escape_output($user->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-3">
                <div class="form-group d-flex">
                    <button tabindex="4" type="submit" name="submit" value="submit"
                            class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                    <a class="btn btn-secondary w-100 ms-2" href="<?php echo base_url() ?>Monitor/runningOrders"><?php echo lang('reset'); ?></a>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>

        <div class="ro_card_grid" id="ro_card_grid">
            <?php $this->view('monitor/_running_order_cards', array('orders' => $orders)); ?>
        </div>
    </div>
</section>

<!-- order details modal, filled inline from Monitor::orderDetailsAjax() -->
<div class="modal fade" id="roOrderDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('order_details'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="ro_details_body"></div>
        </div>
    </div>
</div>

<input type="hidden" id="ro_base_url" value="<?php echo base_url() ?>">
<input type="hidden" id="ro_lang_none" value="<?php echo lang('None'); ?>">
<input type="hidden" id="ro_lang_no_data" value="<?php echo lang('no_data_found'); ?>">

<script src="<?php echo base_url(); ?>frequent_changing/js/running_orders.js?v=7.8"></script>
