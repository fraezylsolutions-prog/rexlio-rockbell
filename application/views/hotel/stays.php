<?php /* Hotel add-on (H2): the check-in / check-out log with filters and a CSV export of the same rows */
$qs = array('outlet_id' => $filters['outlet_id'], 'status' => $filters['status'], 'room_id' => $filters['room_id'] ? $filters['room_id'] : '', 'date_from' => $filters['date_from'], 'date_to' => $filters['date_to'], 'guest' => $filters['guest']); ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <h2 class="top-left-header"><?php echo lang('stay_log'); ?></h2>
                <input type="hidden" class="datatable_name" data-title="<?php echo lang('stay_log'); ?>" data-id_name="datatable">
            </div>
            <div class="col-sm-12 col-md-6 text-end">
                <a class="btn btn-secondary" href="<?php echo base_url() ?>Hotel/frontDesk"><i class="fas fa-th-large"></i> <?php echo lang('front_desk'); ?></a>
                <a class="btn bg-blue-btn ms-1" id="ir_stays_export" href="<?php echo base_url() ?>Hotel/stays?<?php echo http_build_query(array_merge($qs, array('export' => 'csv'))); ?>"><i class="fas fa-file-csv"></i> <?php echo lang('export_csv'); ?></a>
            </div>
        </div>
    </section>
    <div class="box-wrapper">
        <?php echo form_open(base_url() . 'Hotel/stays', array('id' => 'ir_stays_filter', 'method' => 'get')); ?>
        <div class="row mb-3">
            <?php if (count($outlets) > 1): ?>
            <div class="col-sm-12 mb-2 col-md-2">
                <select class="form-control" name="outlet_id"><option value=""><?php echo lang('all'); ?> <?php echo lang('outlet'); ?></option>
                    <?php foreach ($outlets as $o): ?><option value="<?php echo (int) $o->id; ?>" <?php echo (string) $filters['outlet_id'] === (string) $o->id ? 'selected' : ''; ?>><?php echo escape_output($o->outlet_name); ?></option><?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-sm-12 mb-2 col-md-2">
                <select class="form-control" name="status"><option value=""><?php echo lang('all'); ?> <?php echo lang('status'); ?></option>
                    <option value="in_house" <?php echo $filters['status'] === 'in_house' ? 'selected' : ''; ?>><?php echo lang('stay_in_house'); ?></option>
                    <option value="checked_out" <?php echo $filters['status'] === 'checked_out' ? 'selected' : ''; ?>><?php echo lang('stay_checked_out'); ?></option>
                </select>
            </div>
            <div class="col-sm-12 mb-2 col-md-2">
                <select class="form-control" name="room_id"><option value=""><?php echo lang('all'); ?> <?php echo lang('rooms'); ?></option>
                    <?php foreach ($rooms as $r): ?><option value="<?php echo (int) $r->id; ?>" <?php echo (int) $filters['room_id'] === (int) $r->id ? 'selected' : ''; ?>><?php echo escape_output($r->number . (count($outlets) > 1 ? ' - ' . $r->outlet_name : '')); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-12 mb-2 col-md-2"><input type="date" name="date_from" class="form-control" value="<?php echo escape_output($filters['date_from']); ?>"></div>
            <div class="col-sm-12 mb-2 col-md-2"><input type="date" name="date_to" class="form-control" value="<?php echo escape_output($filters['date_to']); ?>"></div>
            <div class="col-sm-12 mb-2 col-md-2"><input type="text" name="guest" class="form-control" placeholder="<?php echo lang('guest'); ?> / <?php echo lang('reference'); ?>" value="<?php echo escape_output($filters['guest']); ?>"></div>
            <div class="col-sm-12 mb-2 col-md-12 d-flex">
                <button type="submit" class="btn bg-blue-btn"><?php echo lang('submit'); ?></button>
                <a class="btn btn-secondary ms-2" href="<?php echo base_url() ?>Hotel/stays"><?php echo lang('reset'); ?></a>
            </div>
        </div>
        <?php echo form_close(); ?>
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table">
                    <thead>
                        <tr>
                            <th class="ir_w_1"><?php echo lang('sn'); ?></th>
                            <?php if (count($outlets) > 1): ?><th><?php echo lang('outlet'); ?></th><?php endif; ?>
                            <th><?php echo lang('room'); ?></th>
                            <th><?php echo lang('guest'); ?></th>
                            <th><?php echo lang('phone'); ?></th>
                            <th><?php echo lang('check_in'); ?></th>
                            <th><?php echo lang('expected_checkout'); ?></th>
                            <th><?php echo lang('check_out'); ?></th>
                            <th><?php echo lang('status'); ?></th>
                            <th><?php echo lang('room_type'); ?></th>
                            <th class="text-end"><?php echo lang('rate_per_night'); ?></th>
                            <th class="text-end"><?php echo lang('nights'); ?></th>
                            <th class="text-end"><?php echo lang('amount'); ?></th>
                            <th><?php echo lang('reference'); ?></th>
                            <th><?php echo lang('added_by'); ?></th>
                            <?php if (!empty($can_value)): ?><th class="ir_w_1"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = count($stays); foreach ($stays as $s): ?>
                        <tr>
                            <td class="ir_txt_center"><?php echo $i--; ?></td>
                            <?php if (count($outlets) > 1): ?><td><?php echo escape_output($s->outlet_name); ?></td><?php endif; ?>
                            <td><b><?php echo escape_output($s->room_number); ?></b></td>
                            <td><?php echo escape_output($s->guest_name); ?> <?php echo (int) $s->adults . ((int) $s->children ? '+' . (int) $s->children : ''); ?></td>
                            <td><?php echo escape_output($s->guest_phone); ?></td>
                            <td><?php echo escape_output($s->checkin_at); ?></td>
                            <td><?php echo escape_output($s->expected_checkout); ?></td>
                            <td><?php echo escape_output($s->checkout_at); ?></td>
                            <td><span class="badge <?php echo $s->status === 'in_house' ? 'bg-danger' : 'bg-secondary'; ?>"><?php echo lang('stay_' . $s->status); ?></span></td>
                            <td><?php echo escape_output($s->type_name); ?></td>
                            <td class="text-end"><?php echo $s->rate !== NULL ? getAmtP($s->rate) : ''; ?></td>
                            <td class="text-end"><?php echo $s->nights !== NULL ? (int) $s->nights : ''; ?><?php /* H5: a check-out shorter or longer than expected is the variance a manager settles */
                                if ($s->actual_nights !== NULL && (int) $s->actual_nights !== (int) $s->nights): ?> <span class="badge bg-warning text-dark" title="<?php echo lang('actual_nights'); ?>"><?php echo (int) $s->actual_nights; ?></span><?php endif; ?></td>
                            <td class="text-end"><b><?php echo $s->amount !== NULL ? getAmtP($s->amount) : ''; ?></b><?php echo $s->value_note ? '<div class="text-muted" style="font-size:11px">' . escape_output($s->value_note) . '</div>' : ''; ?></td>
                            <td><?php echo escape_output($s->reference); ?></td>
                            <td><?php echo escape_output($s->in_by); ?><?php echo $s->out_by ? ' / ' . escape_output($s->out_by) : ''; ?></td>
                            <?php if (!empty($can_value)): ?><td><button type="button" class="btn btn-sm btn-secondary hk_stay_value" data-stay_id="<?php echo (int) $s->id; ?>" data-room="<?php echo escape_output($s->room_number); ?>" data-checkin="<?php echo substr($s->checkin_at, 0, 10); ?>" data-expected="<?php echo escape_output($s->expected_checkout); ?>" data-rate="<?php echo (float) $s->rate; ?>" data-amount="<?php echo (float) $s->amount; ?>" title="<?php echo lang('hotel_value_edit'); ?>"><i class="fas fa-coins"></i></button></td><?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php $total = 0; foreach ($stays as $s) { $total += (float) $s->amount; } ?>
                    <tfoot><tr><th colspan="<?php echo (count($outlets) > 1 ? 1 : 0) + 11; ?>" class="text-end"><?php echo lang('total'); ?></th><th class="text-end"><?php echo getAmtP($total); ?></th><th colspan="<?php echo 2 + (!empty($can_value) ? 1 : 0); ?>"></th></tr></tfoot>
                </table>
            </div>
        </div>
    </div>
</section>
<?php if (!empty($can_value)): ?>
<!-- H5: edit a stay's value from the log (a manager settling a variance later) -->
<div class="modal fade" id="hkValueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('hotel_value_edit'); ?> &middot; <span id="hk_ev_room"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hk_ev_stay_id" value=""><input type="hidden" id="hk_ev_checkin" value="">
                <div class="row">
                    <div class="col-6 mb-2"><label><?php echo lang('expected_checkout'); ?></label><input type="date" id="hk_ev_expected" class="form-control"></div>
                    <div class="col-6 mb-2"><label><?php echo lang('rate_per_night'); ?></label><input type="number" min="0" step="0.01" id="hk_ev_rate" class="form-control"></div>
                    <div class="col-6 mb-2"><label><?php echo lang('nights'); ?></label><input type="text" id="hk_ev_nights" class="form-control" readonly></div>
                    <div class="col-6 mb-2"><label><?php echo lang('amount'); ?></label><input type="number" min="0" step="0.01" id="hk_ev_amount" class="form-control"></div>
                    <div class="col-12 mb-2"><label><?php echo lang('hotel_value_note'); ?></label><input type="text" id="hk_ev_note" class="form-control" maxlength="250"></div>
                </div>
                <div class="text-muted" style="font-size:12px"><?php echo lang('hotel_value_hint'); ?></div>
                <div class="text-danger" id="hk_ev_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('cancel'); ?></button>
                <button type="button" class="btn bg-blue-btn" id="hk_ev_submit"><?php echo lang('submit'); ?></button>
            </div>
        </div>
    </div>
</div>
<input type="hidden" id="hk_base_url" value="<?php echo base_url() ?>">
<script src="<?php echo base_url(); ?>frequent_changing/js/hotel_stays.js?v=1.0"></script>
<?php endif; ?>
<?php $this->view('common/footer_js') ?>
