<?php /* Hotel add-on (H11): Out of Order - every spell from the occupancy log: when, by whom, why, back in
         service, duration; room-days lost per category. */
$report_action = 'reportOutOfOrder';
$hrs = function($h) { return $h >= 48 ? number_format($h / 24, 1) . 'd' : number_format($h, 1) . 'h'; }; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.6">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_ooo'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_ooo'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action)); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_ooo_spells'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_n"><?php echo (int) $total['n']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_room_days_lost'); ?></div><div class="hk_rp_big" id="hk_rp_days"><?php echo number_format($total['hours'] / 24, 1); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_still_out'); ?></div><div class="hk_rp_big" id="hk_rp_open"><?php echo (int) $total['open']; ?></div></div>
        </div>
        <div class="table-box"><div class="table-responsive">
            <table id="datatable2" class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
                <thead><tr><th><?php echo lang('room_type'); ?></th><th class="text-end"><?php echo lang('hk_ooo_spells'); ?></th><th class="text-end"><?php echo lang('hk_room_days_lost'); ?></th><th class="text-end"><?php echo lang('hk_still_out'); ?></th></tr></thead>
                <tbody><?php foreach ($cats as $name => $g): ?><tr><td><b><?php echo escape_output($name); ?></b></td><td class="text-end"><?php echo (int) $g['n']; ?></td><td class="text-end"><?php echo number_format($g['hours'] / 24, 1); ?></td><td class="text-end"><?php echo (int) $g['open']; ?></td></tr><?php endforeach; ?></tbody>
                <tfoot><tr class="hk_rp_grand"><th><?php echo lang('total'); ?></th><th class="text-end"><?php echo (int) $total['n']; ?></th><th class="text-end"><?php echo number_format($total['hours'] / 24, 1); ?></th><th class="text-end"><?php echo (int) $total['open']; ?></th></tr></tfoot>
            </table>
        </div></div>
        <h4 class="mt-4 mb-2"><?php echo lang('hk_ooo_spells'); ?></h4>
        <div class="table-box"><div class="table-responsive">
            <table id="datatable" class="table hk_rp_table" data-order="[[2, &quot;desc&quot;]]" data-page-length="50">
                <thead><tr><th><?php echo lang('room'); ?></th><th><?php echo lang('room_type'); ?></th><th><?php echo lang('hk_taken_out'); ?></th><th><?php echo lang('reason'); ?></th><th><?php echo lang('added_by'); ?></th><th><?php echo lang('hotel_back_in_service'); ?></th><th><?php echo lang('added_by'); ?></th><th class="text-end"><?php echo lang('hk_duration'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr><td><b><?php echo escape_output($r['room']); ?></b></td><td><?php echo escape_output($r['type']); ?></td><td><?php echo escape_output($r['start']); ?></td><td><?php echo escape_output($r['reason']); ?></td><td><?php echo escape_output($r['start_by']); ?></td>
                        <td><?php echo $r['end'] ? escape_output($r['end']) : '<span class="badge bg-warning text-dark">' . lang('hk_still_out') . '</span>'; ?><?php echo $r['end_note'] ? '<div class="hk_muted">' . escape_output($r['end_note']) . '</div>' : ''; ?></td><td><?php echo escape_output($r['end_by']); ?></td>
                        <td class="text-end" data-order="<?php echo $r['hours']; ?>"><?php echo $hrs($r['hours']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_ooo_note'); ?></p>
        </div></div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>
