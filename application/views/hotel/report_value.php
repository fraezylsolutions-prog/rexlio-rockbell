<?php /* Hotel add-on (H6): Value Generated. Rows = room categories (every live type, zeros included),
         columns = the buckets of the selected view over the range, row / column / grand totals.
         Counted on the check-in date - the value is booked at check-in (decision 2a). */
$report_action = 'reportValue';
$nb = count($buckets); ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.3">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_value'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_value'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action)); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_value_generated'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_grand"><?php echo getAmtP($grand['amount']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_stays'); ?></div><div class="hk_rp_big"><?php echo (int) $grand['stays']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('nights'); ?></div><div class="hk_rp_big"><?php echo (int) $grand['nights']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_avg_per_stay'); ?></div><div class="hk_rp_big"><?php echo getAmtP($grand['stays'] ? $grand['amount'] / $grand['stays'] : 0); ?></div></div>
        </div>
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
                    <thead>
                        <tr>
                            <th><?php echo lang('room_type'); ?></th>
                            <?php foreach ($buckets as $k => $label): ?><th class="text-end"><?php echo escape_output($label); ?></th><?php endforeach; ?>
                            <th class="text-end hk_rp_total"><?php echo lang('total'); ?></th>
                            <th class="text-end"><?php echo lang('hk_stays'); ?></th>
                            <th class="text-end"><?php echo lang('nights'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($types as $tid => $t): ?>
                        <tr data-type_id="<?php echo (int) $tid; ?>">
                            <td><b><?php echo escape_output($t['name']); ?></b></td>
                            <?php foreach ($buckets as $k => $label): $c = isset($t['cells'][$k]) ? $t['cells'][$k] : null; ?>
                                <td class="text-end"><?php if ($c): ?><?php echo getAmtP($c['amount']); ?><div class="hk_rp_sub"><?php echo (int) $c['stays']; ?> &middot; <?php echo (int) $c['nights']; ?>n</div><?php else: ?><span class="hk_rp_zero">&ndash;</span><?php endif; ?></td>
                            <?php endforeach; ?>
                            <td class="text-end hk_rp_total"><b><?php echo getAmtP($t['amount']); ?></b></td>
                            <td class="text-end"><?php echo (int) $t['stays']; ?></td>
                            <td class="text-end"><?php echo (int) $t['nights']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="hk_rp_grand">
                            <th><?php echo lang('grand_total'); ?></th>
                            <?php foreach ($buckets as $k => $label): ?><th class="text-end"><?php echo getAmtP($columns[$k]['amount']); ?></th><?php endforeach; ?>
                            <th class="text-end hk_rp_total"><?php echo getAmtP($grand['amount']); ?></th>
                            <th class="text-end"><?php echo (int) $grand['stays']; ?></th>
                            <th class="text-end"><?php echo (int) $grand['nights']; ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_value_report_note'); ?></p>
        </div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>