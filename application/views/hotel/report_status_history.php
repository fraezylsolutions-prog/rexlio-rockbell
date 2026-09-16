<?php /* Hotel add-on (H11): Room Status History - the log rows of the range, newest first, by room / kind. */
$report_action = 'reportStatusHistory';
$fmt = function($l) { if ($l->status_kind === 'value') { return ($l->from_status !== NULL ? getAmtP($l->from_status) : '–') . ' → ' . getAmtP($l->to_status); } return ($l->from_status ? lang('room_' . $l->from_status) : '–') . ' → ' . lang('room_' . $l->to_status); };
$kindLabel = function($k) { return $k === 'value' ? lang('hotel_log_value') : lang($k); };
$on = array(); foreach ($filters['outlets'] as $o) { $on[(int) $o->id] = $o->outlet_name; } ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.6">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_history'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_history'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action, 'extra_filters' => $filters['extra_filters'])); ?>
        <div class="table-box"><div class="table-responsive">
            <table id="datatable" class="table hk_rp_table" data-order="[[0, &quot;desc&quot;]]" data-page-length="50">
                <thead><tr><th><?php echo lang('date'); ?></th><?php if (count($filters['outlets']) > 1): ?><th><?php echo lang('outlet'); ?></th><?php endif; ?><th><?php echo lang('room'); ?></th><th><?php echo lang('room_type'); ?></th><th><?php echo lang('hk_kind'); ?></th><th><?php echo lang('hk_change'); ?></th><th><?php echo lang('notes'); ?></th><th><?php echo lang('added_by'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $l): ?>
                    <tr data-kind="<?php echo escape_output($l->status_kind); ?>"><td><?php echo escape_output($l->created_at); ?></td><?php if (count($filters['outlets']) > 1): ?><td><?php echo escape_output(isset($on[(int) $l->outlet_id]) ? $on[(int) $l->outlet_id] : ''); ?></td><?php endif; ?><td><b><?php echo escape_output($l->room_number); ?></b></td><td><?php echo escape_output($l->type_name); ?></td>
                        <td><?php echo $kindLabel($l->status_kind); ?></td><td><?php echo escape_output($fmt($l)); ?></td><td><?php echo escape_output($l->note); ?></td><td><?php echo escape_output($l->user_name); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo count($rows); ?> <?php echo lang('hk_rows'); ?></p>
        </div></div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>
