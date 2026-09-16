<?php /* Hotel add-on (H1): Rooms list. Statuses are shown, not edited here - the Front Desk (H2)
         and the Housekeeping board (H3) own them. */ ?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <div class="row">
            <div class="col-sm-12 col-md-6">
                <h2 class="top-left-header"><?php echo lang('rooms'); ?></h2>
                <input type="hidden" class="datatable_name" data-title="<?php echo lang('rooms'); ?>" data-id_name="datatable">
            </div>
            <div class="col-sm-12 col-md-6 text-end">
                <?php if (!empty($can_add)): ?>
                <a class="btn bg-blue-btn" href="<?php echo base_url() ?>Hotel/addEditRoom"><i class="fas fa-plus"></i> <?php echo lang('add_room'); ?></a>
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
                            <th class="ir_w_15"><?php echo lang('outlet'); ?></th>
                            <th class="ir_w_10"><?php echo lang('room_number'); ?></th>
                            <th class="ir_w_10"><?php echo lang('floor'); ?></th>
                            <th class="ir_w_15"><?php echo lang('room_type'); ?></th>
                            <th class="ir_w_10"><?php echo lang('occupancy'); ?></th>
                            <th class="ir_w_10"><?php echo lang('housekeeping'); ?></th>
                            <th class="ir_w_15"><?php echo lang('guest'); ?></th>
                            <th class="ir_w_1 ir_txt_center not-export-col"><?php echo lang('actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $occ_class = array('vacant' => 'bg-success', 'occupied' => 'bg-danger', 'out_of_order' => 'bg-dark');
                        $hk_class = array('clean' => 'bg-success', 'inspected' => 'bg-primary', 'dirty' => 'bg-warning text-dark', 'in_progress' => 'bg-info text-dark');
                        $i = count($rooms); foreach ($rooms as $value): ?>
                        <tr data-room_id="<?php echo (int) $value->id; ?>">
                            <td class="ir_txt_center"><?php echo escape_output($i--); ?></td>
                            <td><?php echo escape_output($value->outlet_name) ?></td>
                            <td><b><?php echo escape_output($value->number) ?></b></td>
                            <td><?php echo escape_output($value->floor) ?></td>
                            <td><?php echo escape_output($value->type_name) ?></td>
                            <td><span class="badge <?php echo isset($occ_class[$value->occupancy_status]) ? $occ_class[$value->occupancy_status] : 'bg-secondary'; ?> ir_room_occ"><?php echo lang('room_' . $value->occupancy_status); ?></span></td>
                            <td><span class="badge <?php echo isset($hk_class[$value->housekeeping_status]) ? $hk_class[$value->housekeeping_status] : 'bg-secondary'; ?> ir_room_hk"><?php echo lang('room_' . $value->housekeeping_status); ?></span></td>
                            <td><?php echo escape_output($value->guest_name) ?></td>
                            <td>
                                <div class="btn_group_wrap">
                                    <?php if (!empty($can_update)): ?>
                                    <a class="btn btn-warning" href="<?php echo base_url() ?>Hotel/addEditRoom/<?php echo escape_output($this->custom->encrypt_decrypt($value->id, 'encrypt')) ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo lang('edit'); ?>"><i class="far fa-edit"></i></a>
                                    <?php endif; ?>
                                    <?php if (!empty($can_delete)): ?>
                                    <a class="delete btn btn-danger" href="<?php echo base_url() ?>Hotel/deleteRoom/<?php echo escape_output($this->custom->encrypt_decrypt($value->id, 'encrypt')) ?>" data-bs-toggle="tooltip" data-bs-original-title="<?php echo lang('delete'); ?>"><i class="fa-regular fa-trash-can"></i></a>
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
