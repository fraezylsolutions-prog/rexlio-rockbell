<?php /* Hotel add-on (H1): add / edit a Room (one view for both) */
$editing = isset($room) && $room;
$session_outlet = (int) $this->session->userdata('outlet_id'); ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <h3 class="top-left-header"><?php echo $editing ? lang('edit_room') : lang('add_room'); ?></h3>
    </section>
    <div class="box-wrapper">
        <?php echo form_open(base_url('Hotel/addEditRoom' . ($editing ? '/' . escape_output($encrypted_id) : ''))); ?>
        <div class="table-box">
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('outlet'); ?> <span class="required_star">*</span></label>
                            <select class="form-control select2" name="outlet_id" tabindex="1">
                                <?php $selected_outlet = (int) set_value('outlet_id', $editing ? $room->outlet_id : $session_outlet);
                                foreach ($outlets as $o): ?>
                                    <option <?php echo (int) $o->id === $selected_outlet ? 'selected' : ''; ?> value="<?php echo (int) $o->id; ?>"><?php echo escape_output($o->outlet_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (form_error('outlet_id')) { ?><div class="callout callout-danger my-2"><?php echo form_error('outlet_id'); ?></div><?php } ?>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('room_number'); ?> <span class="required_star">*</span></label>
                            <input tabindex="2" type="text" name="number" class="form-control" placeholder="101"
                                   value="<?php echo set_value('number', $editing ? $room->number : ''); ?>">
                        </div>
                        <?php if (form_error('number')) { ?><div class="callout callout-danger my-2"><?php echo form_error('number'); ?></div><?php } ?>
                        <?php if (!empty($number_error)) { ?><div class="callout callout-danger my-2" id="ir_room_number_error"><?php echo escape_output($number_error); ?></div><?php } ?>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('room_type'); ?></label>
                            <select class="form-control select2" name="room_type_id" tabindex="3">
                                <option value=""><?php echo lang('select'); ?></option>
                                <?php $selected_type = (int) set_value('room_type_id', $editing ? $room->room_type_id : 0);
                                foreach ($room_types as $t): ?>
                                    <option <?php echo (int) $t->id === $selected_type ? 'selected' : ''; ?> value="<?php echo (int) $t->id; ?>"><?php echo escape_output($t->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('floor'); ?></label>
                            <input tabindex="4" type="text" name="floor" class="form-control" placeholder="1"
                                   value="<?php echo set_value('floor', $editing ? $room->floor : ''); ?>">
                        </div>
                        <?php if (form_error('floor')) { ?><div class="callout callout-danger my-2"><?php echo form_error('floor'); ?></div><?php } ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-9">
                        <div class="form-group">
                            <label><?php echo lang('notes'); ?></label>
                            <input tabindex="5" type="text" name="notes" class="form-control" placeholder="<?php echo lang('notes'); ?>"
                                   value="<?php echo set_value('notes', $editing ? $room->notes : ''); ?>">
                        </div>
                        <?php if (form_error('notes')) { ?><div class="callout callout-danger my-2"><?php echo form_error('notes'); ?></div><?php } ?>
                    </div>
                    <?php if ($editing): ?>
                    <div class="col-sm-12 mb-2 col-md-3">
                        <label><?php echo lang('status'); ?></label>
                        <div><?php echo lang('room_' . $room->occupancy_status); ?> &middot; <?php echo lang('room_' . $room->housekeeping_status); ?></div>
                        <small class="text-muted"><?php echo lang('room_status_hint'); ?></small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <button tabindex="6" type="submit" name="submit" value="submit" class="btn bg-blue-btn"><?php echo lang('submit'); ?></button>
                        <a href="<?php echo base_url() ?>Hotel/rooms" class="btn btn-secondary ms-2"><?php echo lang('back'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</section>
