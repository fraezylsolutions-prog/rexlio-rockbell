<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/report.css">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header text-left"><?php echo lang('order_lookup'); ?>
            <small class="text-muted">&nbsp;|&nbsp;<?php echo escape_output($outlet_scope_label); ?></small>
        </h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('order_lookup'); ?>" data-id_name="datatable">
    </section>

    <div class="box-wrapper">
        <?php echo form_open(base_url() . 'Monitor/orderLookup') ?>
        <div class="row">
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="1" type="text" name="startDate" readonly class="form-control customDatepicker"
                           placeholder="<?php echo lang('start_date'); ?>"
                           value="<?php echo escape_output($filters['start_date']); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="2" type="text" name="endDate" readonly class="form-control customDatepicker"
                           placeholder="<?php echo lang('end_date'); ?>"
                           value="<?php echo escape_output($filters['end_date']); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="3" type="text" name="sale_no" class="form-control"
                           placeholder="<?php echo lang('sale_no'); ?>"
                           value="<?php echo escape_output($filters['sale_no']); ?>">
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="4" class="form-control select2 ir_w_100" name="status">
                        <option <?php echo $filters['status'] === '' ? 'selected' : ''; ?> value=""><?php echo lang('all'); ?> <?php echo lang('status'); ?></option>
                        <option <?php echo $filters['status'] === 'running' ? 'selected' : ''; ?> value="running"><?php echo lang('running_order'); ?></option>
                        <option <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?> value="completed"><?php echo lang('completed'); ?></option>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="5" class="form-control select2 ir_w_100" name="user_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('user'); ?></option>
                        <?php foreach ($users as $u): ?>
                            <option <?php echo ((string) $filters['user_id'] === (string) $u->id) ? 'selected' : ''; ?> value="<?php echo escape_output($u->id) ?>"><?php echo escape_output($u->full_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <input tabindex="6" type="text" name="table_name" class="form-control"
                           placeholder="<?php echo lang('table'); ?>"
                           value="<?php echo escape_output($filters['table']); ?>">
                </div>
            </div>
            <?php if ($show_outlet_filter): ?>
            <div class="col-sm-12 mb-3 col-md-4 col-lg-2">
                <div class="form-group">
                    <select tabindex="7" class="form-control select2 ir_w_100" name="outlet_id">
                        <option <?php echo ($outlet_scope['selected'] === 'all') ? 'selected' : ''; ?> value="all"><?php echo lang('all_outlets') ?></option>
                        <?php foreach ($scope_outlets as $o): ?>
                            <option <?php echo ($outlet_scope['selected'] === (string) $o->id) ? 'selected' : ''; ?> value="<?php echo escape_output($o->id) ?>"><?php echo escape_output($o->outlet_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-sm-12 mb-3 col-md-2">
                <div class="form-group">
                    <button type="submit" name="submit" value="submit" class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                </div>
            </div>
        </div>
        <?php echo form_close() ?>

        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table">
                    <thead>
                    <tr>
                        <th class="ir_w2_txt_center"><?php echo lang('sn'); ?></th>
                        <th><?php echo lang('sale_no'); ?></th>
                        <th><?php echo lang('status'); ?></th>
                        <th><?php echo lang('date'); ?></th>
                        <th><?php echo lang('table'); ?></th>
                        <th><?php echo lang('amount'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php
                    $i = 1;
                    if (!empty($orders)):
                        foreach ($orders as $o):
                            $is_running = ($o->order_state === 'running');
                            /* Running orders deep-link into the POS exactly as the Phase C cards
                               do - same ?open_sale_no= mechanism, and the same limitation: it can
                               only open an order held in THIS device's IndexedDB under the user
                               who placed it. Completed orders open the shared invoice popup
                               instead, which is server side and has no such constraint. */
                            $pos_link = site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id'))
                                      . '?open_sale_no=' . urlencode($o->sale_no);
                            ?>
                            <tr>
                                <td class="ir_txt_center"><?php echo escape_output($i++); ?></td>
                                <td>
                                    <?php if ($is_running): ?>
                                        <a href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo escape_output($o->sale_no) ?></a>
                                    <?php else: ?>
                                        <a href="#" class="ir_invoice_link" data-sale_id="<?php echo escape_output($o->row_id) ?>"><?php echo escape_output($o->sale_no) ?></a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_running): ?>
                                        <span class="badge bg-warning text-dark"><?php echo lang('running_order'); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-success"><?php echo lang('completed'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo escape_output(date($this->session->userdata('date_format'), strtotime($o->sale_date))); ?>
                                    <small class="text-muted"><?php echo escape_output($o->order_time); ?></small>
                                </td>
                                <td><?php echo escape_output($o->table_names ? $o->table_names : '-'); ?></td>
                                <td><?php echo escape_output(getAmt($o->total_payable)); ?></td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<!-- DataTables -->
<script src="<?php echo base_url(); ?>assets/datatable_custom/jquery-3.3.1.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/dataTables.buttons.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.colVis.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.html5.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/buttons.print.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/jszip.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/pdfmake.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/vfs_fonts.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/newDesign/js/forTable.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/custom_report.js?v=1.1"></script>
<?php /* R2 shared invoice-detail popup, reused unchanged for completed rows. */ ?>
<?php $this->load->view('report/_invoice_details_modal'); ?>
<script src="<?php echo base_url(); ?>frequent_changing/js/report_invoice_popup.js?v=1.2"></script>
