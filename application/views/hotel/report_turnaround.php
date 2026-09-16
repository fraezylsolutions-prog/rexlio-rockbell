<?php /* Hotel add-on (H11): Room Turnaround - per check-out, how long the room stayed dirty and how long
         until it was inspected; slowest first; averages per category. */
$report_action = 'reportTurnaround';
$dur = function($m) { if ($m === NULL) { return '–'; } $m = (int) $m; if ($m < 60) { return $m . 'm'; } if ($m < 1440) { return floor($m / 60) . 'h ' . str_pad($m % 60, 2, '0', STR_PAD_LEFT) . 'm'; } return floor($m / 1440) . 'd ' . floor(($m % 1440) / 60) . 'h'; }; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.6">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_turnaround'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_turnaround'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action)); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_checkouts'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_n"><?php echo (int) $total['n']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_dirty'); ?></div><div class="hk_rp_big" id="hk_rp_dirty"><?php echo $dur($total['dirty_avg']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_max_dirty'); ?></div><div class="hk_rp_big"><?php echo $dur($total['dirty_max']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_inspect'); ?></div><div class="hk_rp_big" id="hk_rp_inspect"><?php echo $dur($total['inspect_avg']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_still_dirty'); ?></div><div class="hk_rp_big" id="hk_rp_open"><?php echo (int) $total['open']; ?></div></div>
        </div>
        <div class="table-box"><div class="table-responsive">
            <table id="datatable2" class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
                <thead><tr><th><?php echo lang('room_type'); ?></th><th class="text-end"><?php echo lang('hk_checkouts'); ?></th><th class="text-end"><?php echo lang('hk_avg_dirty'); ?></th><th class="text-end"><?php echo lang('hk_max_dirty'); ?></th><th class="text-end"><?php echo lang('hk_avg_inspect'); ?></th><th class="text-end"><?php echo lang('hk_still_dirty'); ?></th></tr></thead>
                <tbody><?php foreach ($cats as $name => $g): ?><tr><td><b><?php echo escape_output($name); ?></b></td><td class="text-end"><?php echo (int) $g['n']; ?></td><td class="text-end"><?php echo $dur($g['dirty_avg']); ?></td><td class="text-end"><?php echo $dur($g['dirty_max']); ?></td><td class="text-end"><?php echo $dur($g['inspect_avg']); ?></td><td class="text-end"><?php echo (int) $g['open']; ?></td></tr><?php endforeach; ?></tbody>
                <tfoot><tr class="hk_rp_grand"><th><?php echo lang('total'); ?></th><th class="text-end"><?php echo (int) $total['n']; ?></th><th class="text-end"><?php echo $dur($total['dirty_avg']); ?></th><th class="text-end"><?php echo $dur($total['dirty_max']); ?></th><th class="text-end"><?php echo $dur($total['inspect_avg']); ?></th><th class="text-end"><?php echo (int) $total['open']; ?></th></tr></tfoot>
            </table>
        </div></div>
        <h4 class="mt-4 mb-2"><?php echo lang('hk_slowest_first'); ?></h4>
        <div class="table-box"><div class="table-responsive">
            <table id="datatable" class="table hk_rp_table" data-order="[[4, &quot;desc&quot;]]" data-page-length="50">
                <thead><tr><th><?php echo lang('room'); ?></th><th><?php echo lang('room_type'); ?></th><th><?php echo lang('guest'); ?></th><th><?php echo lang('check_out'); ?></th><th class="text-end"><?php echo lang('hk_dirty_for'); ?></th><th><?php echo lang('hk_clean_at'); ?></th><th class="text-end"><?php echo lang('hk_inspect_after'); ?></th><th><?php echo lang('hk_inspected_at'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr class="<?php echo $r['open'] ? 'hk_row_open' : ''; ?>"><td><b><?php echo escape_output($r['room']); ?></b></td><td><?php echo escape_output($r['type']); ?></td><td><?php echo escape_output($r['guest']); ?></td><td><?php echo escape_output($r['checkout_at']); ?></td>
                        <td class="text-end" data-order="<?php echo (int) $r['dirty_min']; ?>"><?php echo $dur($r['dirty_min']); ?><?php echo $r['open'] ? ' <span class="badge bg-warning text-dark">' . lang('hk_still_dirty') . '</span>' : ''; ?></td>
                        <td><?php echo escape_output($r['clean_at']); ?></td><td class="text-end" data-order="<?php echo (int) $r['inspect_min']; ?>"><?php echo $dur($r['inspect_min']); ?></td><td><?php echo escape_output($r['inspected_at']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_turnaround_note'); ?></p>
        </div></div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>
