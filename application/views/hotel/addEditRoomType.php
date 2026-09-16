<?php /* Hotel add-on (H1): add / edit a Room Type (one view for both) */
$editing = isset($room_type) && $room_type; ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <h3 class="top-left-header"><?php echo $editing ? lang('edit_room_type') : lang('add_room_type'); ?></h3>
    </section>
    <div class="box-wrapper">
        <?php echo form_open(base_url('Hotel/addEditRoomType' . ($editing ? '/' . escape_output($encrypted_id) : ''))); ?>
        <div class="table-box">
            <div class="box-body">
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-4">
                        <div class="form-group">
                            <label><?php echo lang('room_type'); ?> <span class="required_star">*</span></label>
                            <input tabindex="1" type="text" name="name" class="form-control" placeholder="<?php echo lang('room_type'); ?>"
                                   value="<?php echo set_value('name', $editing ? $room_type->name : ''); ?>">
                        </div>
                        <?php if (form_error('name')) { ?><div class="callout callout-danger my-2"><?php echo form_error('name'); ?></div><?php } ?>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-4">
                        <div class="form-group">
                            <label><?php echo lang('base_rate'); ?></label>
                            <input tabindex="2" type="text" name="base_rate" class="form-control" placeholder="0.00"
                                   value="<?php echo set_value('base_rate', $editing && $room_type->base_rate !== NULL ? $room_type->base_rate : ''); ?>">
                            <small class="text-muted"><?php echo lang('base_rate_hint'); ?></small>
                        </div>
                        <?php if (form_error('base_rate')) { ?><div class="callout callout-danger my-2"><?php echo form_error('base_rate'); ?></div><?php } ?>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-4">
                        <div class="form-group">
                            <label><?php echo lang('description'); ?></label>
                            <input tabindex="3" type="text" name="description" class="form-control" placeholder="<?php echo lang('description'); ?>"
                                   value="<?php echo set_value('description', $editing ? $room_type->description : ''); ?>">
                        </div>
                        <?php if (form_error('description')) { ?><div class="callout callout-danger my-2"><?php echo form_error('description'); ?></div><?php } ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12">
                        <button tabindex="4" type="submit" name="submit" value="submit" class="btn bg-blue-btn"><?php echo lang('submit'); ?></button>
                        <a href="<?php echo base_url() ?>Hotel/roomTypes" class="btn btn-secondary ms-2"><?php echo lang('back'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>
</section>
