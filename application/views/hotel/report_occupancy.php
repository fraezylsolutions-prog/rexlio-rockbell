<?php /* Hotel add-on (H7): Occupancy. Table 1: per period bucket - rooms, available room-nights, occupied
         room-nights, occupancy %, arrivals, departures, value, RevPAR (value / available). Table 2: the whole
         range per room category. Definitions in the footnote. */
$report_action = 'reportOccupancy';
$pct = function($occ, $avail) { return $avail > 0 ? number_format($occ * 100 / $avail, 1) . '%' : '–'; };
$revpar = function($value, $avail) { return $avail > 0 ? getAmtP($value / $avail) : '–'; }; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.5">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_occupancy'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_occupancy'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action)); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_occupancy_rate'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_occ"><?php echo $pct($total['occupied'], $total['available']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_room_nights'); ?> (<?php echo lang('hk_occupied'); ?> / <?php echo lang('hk_available'); ?>)</div><div class="hk_rp_big"><?php echo (int) $total['occupied']; ?> <span class="hk_rp_sub">/ <?php echo (int) $total['available']; ?></span></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_arrivals'); ?> / <?php echo lang('hk_departures'); ?></div><div class="hk_rp_big"><?php echo (int) $total['arrivals']; ?> <span class="hk_rp_sub">/ <?php echo (int) $total['departures']; ?></span></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_value_generated'); ?></div><div class="hk_rp_big"><?php echo getAmtP($total['value']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_revpar'); ?></div><div class="hk_rp_big" id="hk_rp_revpar"><?php echo $revpar($total['value'], $total['available']); ?></div></div>
        </div>
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
                    <thead><tr>
                        <th><?php echo lang('hk_period'); ?></th><th class="text-end"><?php echo lang('hk_days'); ?></th><th class="text-end"><?php echo lang('rooms'); ?></th>
                        <th class="text-end"><?php echo lang('hk_available'); ?></th><th class="text-end"><?php echo lang('hk_occupied'); ?></th><th class="text-end"><?php echo lang('hk_occupancy_rate'); ?></th>
                        <th class="text-end"><?php echo lang('hk_arrivals'); ?></th><th class="text-end"><?php echo lang('hk_departures'); ?></th><th class="text-end"><?php echo lang('hk_value_generated'); ?></th><th class="text-end"><?php echo lang('hk_revpar'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($per as $k => $b): ?>
                        <tr data-bucket="<?php echo $k; ?>">
                            <td data-order="<?php echo $k; ?>"><b><?php echo escape_output($b['label']); ?></b></td><td class="text-end"><?php echo (int) $b['days']; ?></td><td class="text-end"><?php echo (int) $total['rooms']; ?></td>
                            <td class="text-end"><?php echo (int) $b['available']; ?></td><td class="text-end"><?php echo (int) $b['occupied']; ?></td><td class="text-end"><b><?php echo $pct($b['occupied'], $b['available']); ?></b></td>
                            <td class="text-end"><?php echo (int) $b['arrivals']; ?></td><td class="text-end"><?php echo (int) $b['departures']; ?></td><td class="text-end"><?php echo getAmtP($b['value']); ?></td><td class="text-end"><?php echo $revpar($b['value'], $b['available']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="hk_rp_grand">
                        <th><?php echo lang('total'); ?></th><th class="text-end"><?php echo (int) $total['days']; ?></th><th class="text-end"><?php echo (int) $total['rooms']; ?></th>
                        <th class="text-end"><?php echo (int) $total['available']; ?></th><th class="text-end"><?php echo (int) $total['occupied']; ?></th><th class="text-end"><?php echo $pct($total['occupied'], $total['available']); ?></th>
                        <th class="text-end"><?php echo (int) $total['arrivals']; ?></th><th class="text-end"><?php echo (int) $total['departures']; ?></th><th class="text-end"><?php echo getAmtP($total['value']); ?></th><th class="text-end"><?php echo $revpar($total['value'], $total['available']); ?></th>
                    </tr></tfoot>
                </table>
            </div>
        </div>
        <h4 class="mt-4 mb-2"><?php echo lang('hk_by_category'); ?></h4>
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable2" class="table hk_rp_table" data-order="[[0, &quot;asc&quot;]]" data-page-length="50">
                    <thead><tr>
                        <th><?php echo lang('room_type'); ?></th><th class="text-end"><?php echo lang('rooms'); ?></th><th class="text-end"><?php echo lang('hk_available'); ?></th><th class="text-end"><?php echo lang('hk_occupied'); ?></th>
                        <th class="text-end"><?php echo lang('hk_occupancy_rate'); ?></th><th class="text-end"><?php echo lang('hk_arrivals'); ?></th><th class="text-end"><?php echo lang('hk_departures'); ?></th><th class="text-end"><?php echo lang('hk_value_generated'); ?></th><th class="text-end"><?php echo lang('hk_revpar'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($types as $tid => $t): ?>
                        <tr data-type_id="<?php echo (int) $tid; ?>">
                            <td><b><?php echo escape_output($t['name']); ?></b></td><td class="text-end"><?php echo (int) $t['rooms']; ?></td><td class="text-end"><?php echo (int) $t['available']; ?></td><td class="text-end"><?php echo (int) $t['occupied']; ?></td>
                            <td class="text-end"><b><?php echo $pct($t['occupied'], $t['available']); ?></b></td><td class="text-end"><?php echo (int) $t['arrivals']; ?></td><td class="text-end"><?php echo (int) $t['departures']; ?></td><td class="text-end"><?php echo getAmtP($t['value']); ?></td><td class="text-end"><?php echo $revpar($t['value'], $t['available']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_occupancy_note'); ?></p>
        </div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>