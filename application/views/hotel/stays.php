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
                            <th><?php echo lang('reference'); ?></th>
                            <th><?php echo lang('added_by'); ?></th>
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
                            <td><?php echo escape_output($s->reference); ?></td>
                            <td><?php echo escape_output($s->in_by); ?><?php echo $s->out_by ? ' / ' . escape_output($s->out_by) : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>
