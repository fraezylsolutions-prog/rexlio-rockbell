<?php /* Hotel add-on (H8): Stays report - one row per stay with its value, subtotals by period / room
         category / staff. Counted on the check-in date; cancelled stays excluded. */
$report_action = 'reportStays';
$sub = function($title, $rows, $total, $labelKey) { ?>
    <div class="hk_rp_subbox">
        <div class="hk_rp_label"><?php echo $title; ?></div>
        <table class="table table-sm hk_rp_table hk_rp_sub_table">
            <thead><tr><th><?php echo $labelKey; ?></th><th class="text-end"><?php echo lang('hk_stays'); ?></th><th class="text-end"><?php echo lang('nights'); ?></th><th class="text-end"><?php echo lang('amount'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $name => $r): if (isset($r['label'])) { $name = $r['label']; } ?>
                <tr><td><?php echo escape_output($name); ?></td><td class="text-end"><?php echo (int) $r['stays']; ?></td><td class="text-end"><?php echo (int) $r['nights']; ?></td><td class="text-end"><?php echo getAmtP($r['amount']); ?></td></tr>
            <?php endforeach; if (!$rows): ?><tr><td colspan="4" class="hk_muted"><?php echo lang('no_data_found'); ?></td></tr><?php endif; ?>
            </tbody>
            <tfoot><tr class="hk_rp_grand"><th><?php echo lang('total'); ?></th><th class="text-end"><?php echo (int) $total['stays']; ?></th><th class="text-end"><?php echo (int) $total['nights']; ?></th><th class="text-end"><?php echo getAmtP($total['amount']); ?></th></tr></tfoot>
        </table>
    </div>
<?php }; ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.6">
<section class="main-content-wrapper">
    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('hk_report_stays'); ?></h3>
        <input type="hidden" class="datatable_name" data-title="<?php echo lang('hk_report_stays'); ?> <?php echo escape_output($filters['from'] . ' - ' . $filters['to']); ?>" data-id_name="datatable">
    </section>
    <div class="box-wrapper">
        <?php $this->view('hotel/_report_filter', array('filters' => $filters, 'report_action' => $report_action, 'extra_filters' => $filters['extra_filters'])); ?>
        <div class="hk_rp_cards">
            <div class="hk_rp_card hk_rp_card_main"><div class="hk_rp_label"><?php echo lang('hk_value_generated'); ?> &middot; <?php echo escape_output($filters['from']); ?> &rarr; <?php echo escape_output($filters['to']); ?></div><div class="hk_rp_big" id="hk_rp_grand"><?php echo getAmtP($total['amount']); ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_stays'); ?></div><div class="hk_rp_big"><?php echo (int) $total['stays']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('nights'); ?></div><div class="hk_rp_big"><?php echo (int) $total['nights']; ?></div></div>
            <div class="hk_rp_card"><div class="hk_rp_label"><?php echo lang('hk_variances'); ?></div><div class="hk_rp_big" id="hk_rp_variances"><?php echo (int) $variances; ?></div></div>
        </div>
        <div class="hk_rp_subs">
            <?php $sub(lang('hk_by_period'), $by_bucket, $total, lang('hk_period')); $sub(lang('hk_by_category'), $by_type, $total, lang('room_type')); $sub(lang('hk_by_staff'), $by_staff, $total, lang('hk_checked_in_by')); ?>
        </div>
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table hk_rp_table" data-page-length="50">
                    <thead><tr>
                        <th><?php echo lang('check_in'); ?></th><th><?php echo lang('check_out'); ?></th><?php if (count($filters['outlets']) > 1): ?><th><?php echo lang('outlet'); ?></th><?php endif; ?>
                        <th><?php echo lang('room'); ?></th><th><?php echo lang('room_type'); ?></th><th><?php echo lang('guest'); ?></th><th><?php echo lang('status'); ?></th>
                        <th class="text-end"><?php echo lang('rate_per_night'); ?></th><th class="text-end"><?php echo lang('nights'); ?></th><th class="text-end"><?php echo lang('actual_nights'); ?></th><th class="text-end"><?php echo lang('amount'); ?></th>
                        <th><?php echo lang('hk_checked_in_by'); ?></th><th><?php echo lang('reference'); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($stays as $s): $var = $s->actual_nights !== NULL && (int) $s->actual_nights !== (int) $s->nights; ?>
                        <tr data-stay_id="<?php echo (int) $s->id; ?>">
                            <td data-order="<?php echo escape_output($s->checkin_at); ?>"><?php echo escape_output($s->checkin_at); ?></td><td><?php echo escape_output($s->checkout_at); ?></td>
                            <?php if (count($filters['outlets']) > 1): ?><td><?php echo escape_output($s->outlet_name); ?></td><?php endif; ?>
                            <td><b><?php echo escape_output($s->room_number); ?></b></td><td><?php echo escape_output($s->type_name); ?></td><td><?php echo escape_output($s->guest_name); ?></td>
                            <td><span class="badge <?php echo $s->status === 'in_house' ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo lang('stay_' . $s->status); ?></span></td>
                            <td class="text-end"><?php echo $s->rate !== NULL ? getAmtP($s->rate) : ''; ?></td><td class="text-end"><?php echo (int) $s->nights; ?></td>
                            <td class="text-end"><?php echo $s->actual_nights !== NULL ? (int) $s->actual_nights : ''; ?><?php echo $var ? ' <span class="badge bg-warning text-dark hk_variance">' . lang('hk_variance') . '</span>' : ''; ?></td>
                            <td class="text-end"><b><?php echo getAmtP($s->amount); ?></b></td><td><?php echo escape_output($s->in_by); ?></td><td><?php echo escape_output($s->reference); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr class="hk_rp_grand"><th colspan="<?php echo 7 + (count($filters['outlets']) > 1 ? 1 : 0); ?>" class="text-end"><?php echo lang('total'); ?></th><th class="text-end"><?php echo (int) $total['nights']; ?></th><th></th><th class="text-end"><?php echo getAmtP($total['amount']); ?></th><th colspan="2"></th></tr></tfoot>
                </table>
            </div>
            <p class="text-muted mt-2" style="font-size:12px"><?php echo lang('hk_stays_report_note'); ?></p>
        </div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>