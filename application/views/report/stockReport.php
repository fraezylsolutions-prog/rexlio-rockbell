<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/report.css">

<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header text-left"><?php echo lang('stock_report'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('stock_report'); ?>" data-id_name="datatable">
    </section>

    <div class="my-2">
        <h4>
            <?php echo lang('outlet'); ?>: <?php echo escape_output($outlet_scope_label) ?>
            &nbsp;|&nbsp;
            <?php echo lang('report_date'); ?>
            <?php echo escape_output(date($this->session->userdata('date_format'), strtotime($start_date))); ?>
            -
            <?php echo escape_output(date($this->session->userdata('date_format'), strtotime($end_date))); ?>
        </h4>
    </div>

    <div class="box-wrapper">
        <?php echo form_open(base_url() . 'Report/stockReport', $arrayName = array('id' => 'stockReportFilter')) ?>
        <div class="row mb-3">
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group">
                    <input tabindex="1" type="text" id="stock_start_date" name="startDate" readonly
                           class="form-control customDatepicker" placeholder="<?php echo lang('start_date'); ?>"
                           value="<?php echo escape_output($start_date); ?>">
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group">
                    <input tabindex="2" type="text" id="stock_end_date" name="endDate" readonly
                           class="form-control customDatepicker" placeholder="<?php echo lang('end_date'); ?>"
                           value="<?php echo escape_output($end_date); ?>">
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group">
                    <select tabindex="3" class="form-control select2 ir_w_100" name="outlet_id">
                        <?php if ($show_outlet_filter): ?>
                            <option <?php echo ($outlet_scope['selected'] === 'all') ? 'selected' : ''; ?> value="all"><?php echo lang('all_outlets') ?></option>
                        <?php endif; ?>
                        <?php foreach (getAllOutlestByAssign() as $single_outlet): ?>
                            <option <?php echo ($outlet_scope['selected'] === (string) $single_outlet->id) ? 'selected' : ''; ?> value="<?php echo escape_output($single_outlet->id) ?>"><?php echo escape_output($single_outlet->outlet_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group">
                    <select tabindex="4" class="form-control select2 ir_w_100" name="category_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('category'); ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option <?php echo set_select('category_id', $category->id); ?> value="<?php echo escape_output($category->id) ?>"><?php echo escape_output($category->category_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group">
                    <select tabindex="5" class="form-control select2 ir_w_100" name="ingredient_id">
                        <option value=""><?php echo lang('all'); ?> <?php echo lang('item'); ?></option>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <option <?php echo set_select('ingredient_id', $ingredient->id); ?> value="<?php echo escape_output($ingredient->id) ?>"><?php echo escape_output($ingredient->name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-sm-12 col-md-4 col-lg-2 mb-3">
                <div class="form-group d-flex">
                    <button tabindex="6" type="submit" name="submit" value="submit"
                            class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
                    <a class="btn btn-secondary w-100 ms-2" href="<?php echo base_url() ?>Report/stockReport"><?php echo lang('reset'); ?></a>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col-sm-12">
                <button type="button" class="btn btn-outline-secondary stock_preset" data-preset="today"><?php echo lang('daily'); ?></button>
                <button type="button" class="btn btn-outline-secondary stock_preset" data-preset="week"><?php echo lang('weekly'); ?></button>
                <button type="button" class="btn btn-outline-secondary stock_preset" data-preset="month"><?php echo lang('monthly'); ?></button>
            </div>
        </div>
        <?php echo form_close(); ?>

        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table table-striped">
                    <thead>
                        <tr>
                            <th class="ir_w2_txt_center"><?php echo lang('sn'); ?></th>
                            <th class="ir_w_8"><?php echo lang('code'); ?></th>
                            <th class="ir_w_20"><?php echo lang('name'); ?></th>
                            <th class="ir_w_12"><?php echo lang('category'); ?></th>
                            <th class="ir_w_8"><?php echo lang('unit'); ?></th>
                            <th class="ir_w_10"><?php echo lang('opening_balance'); ?></th>
                            <th class="ir_w_10"><?php echo lang('total_in'); ?></th>
                            <th class="ir_w_10"><?php echo lang('total_out'); ?></th>
                            <th class="ir_w_10"><?php echo lang('closing_balance'); ?></th>
                            <th class="ir_w_5 not-export-col"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sn = 1;
                    $sum_opening = 0; $sum_in = 0; $sum_out = 0; $sum_closing = 0;
                    if (isset($ledger) && $ledger):
                        foreach ($ledger as $item_id => $row):
                            $sum_opening += $row['opening'];
                            $sum_in += $row['total_in'];
                            $sum_out += $row['total_out'];
                            $sum_closing += $row['closing'];
                            ?>
                            <tr>
                                <td class="ir_txt_center"><?php echo escape_output($sn++); ?></td>
                                <td><?php echo escape_output($row['item']->code) ?></td>
                                <td><?php echo escape_output($row['item']->name) ?></td>
                                <td><?php echo escape_output($row['item']->category_name) ?></td>
                                <td><?php echo escape_output($row['item']->consumption_unit_name) ?></td>
                                <td><?php echo escape_output(getAmtP($row['opening'])) ?></td>
                                <td><?php echo escape_output(getAmtP($row['total_in'])) ?></td>
                                <td><?php echo escape_output(getAmtP($row['total_out'])) ?></td>
                                <td><b><?php echo escape_output(getAmtP($row['closing'])) ?></b></td>
                                <td class="ir_txt_center not-export-col">
                                    <a href="javascript:void(0)" class="btn btn-unique stock_detail_toggle"
                                       data-row="stock_detail_<?php echo escape_output($item_id) ?>">
                                        <i class="far fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <tr class="ir_display_none stock_detail_row stock_detail_<?php echo escape_output($item_id) ?>">
                                <td colspan="10">
                                    <b><?php echo lang('movement_breakdown'); ?></b>
                                    <table class="table ir-width-100">
                                        <thead>
                                            <tr>
                                                <th><?php echo lang('opening_balance'); ?></th>
                                                <th><?php echo lang('purchases'); ?></th>
                                                <th><?php echo lang('production'); ?></th>
                                                <th><?php echo lang('transfer'); ?> (+)</th>
                                                <th><?php echo lang('adjustment'); ?> (+)</th>
                                                <th><?php echo lang('sales'); ?></th>
                                                <th><?php echo lang('waste'); ?></th>
                                                <th><?php echo lang('transfer'); ?> (-)</th>
                                                <th><?php echo lang('adjustment'); ?> (-)</th>
                                                <th><?php echo lang('closing_balance'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><?php echo escape_output(getAmtP($row['opening'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['purchase_in'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['production_in'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['transfer_in'] + $row['received_in'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['adjust_plus'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['sale_out'] + $row['modifier_out'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['waste_out'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['transfer_out'] + $row['received_out'])) ?></td>
                                                <td><?php echo escape_output(getAmtP($row['adjust_minus'])) ?></td>
                                                <td><b><?php echo escape_output(getAmtP($row['closing'])) ?></b></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <small>
                                        <?php echo lang('stock_report_unit_note'); ?>
                                        <?php echo escape_output($row['item']->consumption_unit_name) ?>
                                        (<?php echo lang('conversion_rate'); ?>: <?php echo escape_output($row['conversion_rate']) ?>)
                                    </small>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endif;
                    ?>
                        <tr>
                            <td colspan="5" class="ir_txt_right"><b><?php echo lang('total'); ?></b></td>
                            <td><b><?php echo escape_output(getAmtP($sum_opening)); ?></b></td>
                            <td><b><?php echo escape_output(getAmtP($sum_in)); ?></b></td>
                            <td><b><?php echo escape_output(getAmtP($sum_out)); ?></b></td>
                            <td><b><?php echo escape_output(getAmtP($sum_closing)); ?></b></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- deliberately separated from the audit totals above -->
        <div class="table-box mt-4">
            <div class="table-responsive">
                <h4><?php echo lang('non_audit_stock_impact'); ?></h4>
                <p class="mb-2"><small><?php echo lang('non_audit_stock_impact_note'); ?></small></p>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th class="ir_w2_txt_center"><?php echo lang('sn'); ?></th>
                            <th><?php echo lang('code'); ?></th>
                            <th><?php echo lang('name'); ?></th>
                            <th><?php echo lang('unit'); ?></th>
                            <th><?php echo lang('open_orders_consumption'); ?></th>
                            <th><?php echo lang('refunded_sales_consumption'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sn2 = 1;
                    if (isset($unposted) && $unposted):
                        foreach ($unposted as $row2):
                            ?>
                            <tr>
                                <td class="ir_txt_center"><?php echo escape_output($sn2++); ?></td>
                                <td><?php echo escape_output($row2['item']->code) ?></td>
                                <td><?php echo escape_output($row2['item']->name) ?></td>
                                <td><?php echo escape_output($row2['item']->consumption_unit_name) ?></td>
                                <td><?php echo escape_output(getAmtP($row2['open_orders'])) ?></td>
                                <td><?php echo escape_output(getAmtP($row2['refunded'])) ?></td>
                            </tr>
                            <?php
                        endforeach;
                    else:
                        ?>
                        <tr><td colspan="6"><?php echo lang('no_data_found'); ?></td></tr>
                        <?php
                    endif;
                    ?>
                    </tbody>
                </table>
                <p><small><?php echo lang('cancelled_orders_note'); ?></small></p>
            </div>
        </div>
    </div>
</section>

<script>
    $(document).on('click', '.stock_detail_toggle', function () {
        $('.' + $(this).attr('data-row')).toggleClass('ir_display_none');
    });
    $(document).on('click', '.stock_preset', function () {
        var preset = $(this).attr('data-preset');
        var now = new Date();
        var start, end;
        var fmt = function (d) {
            var m = ('0' + (d.getMonth() + 1)).slice(-2);
            var day = ('0' + d.getDate()).slice(-2);
            return d.getFullYear() + '-' + m + '-' + day;
        };
        if (preset === 'today') {
            start = end = now;
        } else if (preset === 'week') {
            // Monday to Sunday of the current week
            var offset = (now.getDay() + 6) % 7;
            start = new Date(now.getFullYear(), now.getMonth(), now.getDate() - offset);
            end = new Date(start.getFullYear(), start.getMonth(), start.getDate() + 6);
        } else {
            start = new Date(now.getFullYear(), now.getMonth(), 1);
            end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        }
        $('#stock_start_date').val(fmt(start));
        $('#stock_end_date').val(fmt(end));
        $('#stockReportFilter').submit();
    });
</script>

<!-- DataTables -->
<script src="<?php echo base_url(); ?>assets/datatable_custom/jquery-3.3.1.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/dataTables.buttons.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/buttons.flash.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/jszip.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/pdfmake.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/js/dataTable/dataTables.bootstrap4.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/vfs_fonts.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/buttons.html5.min.js"></script>
<script src="<?php echo base_url(); ?>assets/datatable_custom/buttons.print.min.js"></script>
<script src="<?php echo base_url(); ?>frequent_changing/newDesign/js/forTable.js"></script>
