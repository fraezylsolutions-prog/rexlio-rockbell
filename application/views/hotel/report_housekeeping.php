<?php /* Hotel add-on (H9): Housekeeping productivity. Tasks created in the range; per attendant (the
         export table), then by task type, by room category and by period. Minutes are averages over the
         tasks that have both timestamps. */
$report_action = 'reportHousekeeping';
$dur = function($m) { if ($m === NULL) { return '–'; } if ($m < 60) { return (int) $m . 'm'; } return floor($m / 60) . 'h ' . str_pad((int) ($m % 60), 2, '0', STR_PAD_LEFT) . 'm'; };
$head = function($first) { ?><thead><tr><th><?php echo $first; ?></th><th class="text-end"><?php echo lang('hk_created'); ?></th><th class="text-end"><?php echo lang('hk_done_count'); ?></th><th class="text-end"><?php echo lang('hotel_verified'); ?></th><th class="text-end"><?php echo lang('hk_cancelled'); ?></th><th class="text-end"><?php echo lang('hk_open'); ?></th><th class="text-end"><?php echo lang('hk_avg_wait'); ?></th><th class="text-end"><?php echo lang('hk_avg_work'); ?></th><th class="text-end"><?php echo lang('hk_avg_check'); ?></th></tr></thead><?php };
$cells = function($g) use ($dur) { ?><td class="text-end"><?php echo (int) $g['created']; ?></td><td class="text-end"><b><?php echo (int) $g['done']; ?></b></td><td class="text-end"><?php echo (int) $g['verified']; ?></td><td class="text-end"><?php echo (int) $g['cancelled']; ?></td><td class="text-end"><?php echo (int) $g['open']; ?></td><td class="text-end"><?php echo $dur($g['wait_avg']); ?></td><td class="text-end"><?php echo $dur($g['work_avg']); ?></td><td class="text-end"><?php echo $dur($g['check_avg']); ?></td><?php };
$block = function($title, $rows, $first, $id = '') use ($head, $cells, $total) { ?>
    <h4 class="mt-4 mb-2"><?php echo $title; ?></h4>
    <div class="table-box"><div class="table-responsive">
        <table <?php echo $id ? 'id="' . $id . '"' : ''; ?> class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
            <?php $head($first); ?>
            <tbody><?php foreach ($rows as $name => $g): ?><tr><td><b><?php echo escape_output(isset($g['label']) ? $g['label'] : $name); ?></b></td><?php $cells($g); ?></tr><?php endforeach; ?></tbody>
            <tfoot><tr class="hk_rp_grand"><th><?php echo lang('total'); ?></th><?php $cells($total); ?></tr></tfoot>
        </table>
    </div></div>
<?php }; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.6">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_housekeeping'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_housekeeping'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action, 'extra_filters' => $filters['extra_filters'])); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_tasks_done'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_done"><?php echo (int) $total['done']; ?> <span class="hk_rp_sub">/ <?php echo (int) $total['created']; ?></span></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hotel_verified'); ?></div><div class="hk_rp_big"><?php echo (int) $total['verified']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_wait'); ?></div><div class="hk_rp_big" id="hk_rp_wait"><?php echo $dur($total['wait_avg']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_work'); ?></div><div class="hk_rp_big" id="hk_rp_work"><?php echo $dur($total['work_avg']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_check'); ?></div><div class="hk_rp_big" id="hk_rp_check"><?php echo $dur($total['check_avg']); ?></div></div>
        </div>
        <?php $block(lang('hk_by_attendant'), $by_staff, lang('hk_attendant'), 'datatable'); $block(lang('hk_by_task_type'), $by_type, lang('hk_task_type')); $block(lang('hk_by_category'), $by_cat, lang('room_type')); $block(lang('hk_by_period'), $by_bucket, lang('hk_period')); ?>
        <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_housekeeping_report_note'); ?></p>
    </div>
</section>
<?php $this->view('common/footer_js') ?>