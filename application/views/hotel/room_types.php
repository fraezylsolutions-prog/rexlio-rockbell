<?php /* Hotel add-on (H1): Room Types list */ ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <h2 class="top-left-header"><?php echo lang('room_types'); ?></h2>
                <input type="hidden" class="datatable_name" data-title="<?php echo lang('room_types'); ?>" data-id_name="datatable">
            </div>
            <div class="col-sm-12 col-md-6 text-end">
                <?php if (!empty($can_add)): ?>
                <a class="btn bg-blue-btn" href="<?php echo base_url() ?>Hotel/addEditRoomType"><i class="fas fa-plus"></i> <?php echo lang('add_room_type'); ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <div class="box-wrapper">
        <div class="table-box">
            <div class="table-responsive">
                <table id="datatable" class="table">
                    <thead>
                        <tr>
                            <th class="ir_w_1"><?php echo lang('sn'); ?></th>
                            <th class="ir_w_25"><?php echo lang('room_type'); ?></th>
                            <th class="ir_w_15"><?php echo lang('base_rate'); ?></th>
                            <th class="ir_w_30"><?php echo lang('description'); ?></th>
                            <th class="ir_w_10"><?php echo lang('rooms'); ?></th>
                            <th class="ir_w_1 ir_txt_center not-export-col"><?php echo lang('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = count($room_types); foreach ($room_types as $value): ?>
                        <tr>
                            <td class="ir_txt_center"><?php echo escape_output($i--); ?></td>
                            <td><?php echo escape_output($value->name) ?></td>
                            <td><?php echo $value->base_rate !== NULL ? escape_output(getAmtP($value->base_rate)) : '&mdash;'; ?></td>
                            <td><?php echo escape_output($value->description) ?></td>
                            <td><?php echo (int) $value->rooms_count; ?></td>
                            <td>
                                <div class="btn_group_wrap">
                                    <?php if (!empty($can_update)): ?>
                                    <a class="btn btn-warning" href="<?php echo base_url() ?>Hotel/addEditRoomType/<?php echo escape_output($this->custom->encrypt_decrypt($value->id, 'encrypt')) ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo lang('edit'); ?>"><i class="far fa-edit"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($can_delete)): ?>
                                    <a class="delete btn btn-danger" href="<?php echo base_url() ?>Hotel/deleteRoomType/<?php echo escape_output($this->custom->encrypt_decrypt($value->id, 'encrypt')) ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo lang('delete'); ?>"><i class="fa-regular fa-trash-can"></i></a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
<?php $this->view('common/footer_js') ?>
