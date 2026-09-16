<?php /* Hotel add-on (H2): the Front Desk board. Rendered once from $rooms, then hotel_front_desk.js
         polls Hotel/boardAjax every 15 s and after every action. Actions are permission-gated
         twice: buttons only render for a permission the caller holds, and the controller checks
         again on every POST. */ ?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.2">
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>
    <section class="content-header">
        <div class="row align-items-center">
            <div class="col-sm-12 col-md-4">
                <h3 class="top-left-header"><?php echo lang('front_desk'); ?></h3>
            </div>
            <div class="col-sm-12 col-md-8 text-md-end">
                <span class="hk_chip hk_chip_occ"><?php echo lang('room_occupied'); ?> <b id="hk_c_occupied">0</b></span>
                <span class="hk_chip hk_chip_vac"><?php echo lang('room_vacant'); ?> <b id="hk_c_vacant">0</b></span>
                <span class="hk_chip hk_chip_dirty"><?php echo lang('hotel_needs_cleaning'); ?> <b id="hk_c_dirty">0</b></span>
                <span class="hk_chip hk_chip_ooo"><?php echo lang('room_out_of_order'); ?> <b id="hk_c_ooo">0</b></span>
                <?php if (count($outlets) > 1): ?>
                <select id="hk_outlet" class="form-control d-inline-block ms-2" style="width:auto">
                    <?php foreach ($outlets as $o): ?>
                        <option value="<?php echo (int) $o->id; ?>" <?php echo (int) $o->id === (int) $outlet_id ? 'selected' : ''; ?>><?php echo escape_output($o->outlet_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                <input type="hidden" id="hk_outlet" value="<?php echo (int) $outlet_id; ?>">
                <?php endif; ?>
                <a class="btn btn-secondary ms-2" href="<?php echo base_url() ?>Hotel/stays"><i class="fas fa-book"></i> <?php echo lang('stay_log'); ?></a>
            </div>
        </div>
    </section>

    <div class="hk_offline_banner" id="hk_offline_banner" style="display:none;">
        <i class="fas fa-exclamation-triangle"></i> <?php echo lang('offline_showing_last_known'); ?> <span id="hk_last_updated"></span>
    </div>

    <div class="box-wrapper">
        <div id="hk_board" class="hk_board"><div class="hk_empty"><?php echo lang('loading'); ?>&hellip;</div></div>
    </div>
</section>

<!-- check-in -->
<div class="modal fade" id="hkCheckinModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('check_in'); ?> &middot; <span id="hk_ci_room"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hk_ci_room_id" value="">
                <input type="hidden" id="hk_ci_confirm_dirty" value="0">
                <div class="hk_warn" id="hk_ci_warn" hidden>
                    <b><?php echo lang('hotel_dirty_warn_title'); ?></b>
                    <div id="hk_ci_warn_text"></div>
                </div>
                <div class="row">
                    <div class="col-12 mb-2"><label><?php echo lang('guest_name'); ?> <span class="required_star">*</span></label><input type="text" id="hk_ci_guest" class="form-control" maxlength="150"></div>
                    <div class="col-6 mb-2"><label><?php echo lang('phone'); ?></label><input type="text" id="hk_ci_phone" class="form-control" maxlength="50"></div>
                    <div class="col-3 mb-2"><label><?php echo lang('adults'); ?></label><input type="number" id="hk_ci_adults" class="form-control" value="1" min="1" max="20"></div>
                    <div class="col-3 mb-2"><label><?php echo lang('children'); ?></label><input type="number" id="hk_ci_children" class="form-control" value="0" min="0" max="20"></div>
                    <div class="col-6 mb-2"><label><?php echo lang('expected_checkout'); ?></label><input type="date" id="hk_ci_expected" class="form-control"></div>
                    <div class="col-6 mb-2"><label><?php echo lang('reference'); ?></label><input type="text" id="hk_ci_reference" class="form-control" maxlength="100" placeholder="<?php echo lang('reference_hint'); ?>"></div>
                    <div class="col-12 mb-2"><label><?php echo lang('notes'); ?></label><input type="text" id="hk_ci_notes" class="form-control" maxlength="250"></div>
                </div>
                <div class="text-danger" id="hk_ci_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('cancel'); ?></button>
                <button type="button" class="btn bg-blue-btn" id="hk_ci_submit"><?php echo lang('check_in'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- out of order -->
<div class="modal fade" id="hkOooModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="hk_ooo_title"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="hk_ooo_room_id" value=""><input type="hidden" id="hk_ooo_on" value="1">
                <label><?php echo lang('reason'); ?></label><input type="text" id="hk_ooo_note" class="form-control" maxlength="250">
                <div class="text-danger" id="hk_ooo_error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo lang('cancel'); ?></button>
                <button type="button" class="btn bg-blue-btn" id="hk_ooo_submit"><?php echo lang('submit'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- room history -->
<div class="modal fade" id="hkHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo lang('room'); ?> <span id="hk_hist_room"></span> &middot; <?php echo lang('history'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="hk_hist_body"></div>
        </div>
    </div>
</div>

<input type="hidden" id="hk_base_url" value="<?php echo base_url() ?>">
<input type="hidden" id="hk_can_checkin" value="<?php echo !empty($can_checkin) ? 1 : 0; ?>">
<input type="hidden" id="hk_can_checkout" value="<?php echo !empty($can_checkout) ? 1 : 0; ?>">
<input type="hidden" id="hk_can_status" value="<?php echo !empty($can_status) ? 1 : 0; ?>">
<?php foreach (array('check_in', 'check_out', 'room_vacant', 'room_occupied', 'room_out_of_order', 'room_clean', 'room_dirty', 'room_in_progress', 'room_inspected',
                     'hotel_set_out_of_order', 'hotel_back_in_service', 'history', 'hotel_no_rooms', 'hotel_since', 'hotel_confirm_checkout', 'hotel_check_in_anyway',
                     'hotel_dirty_warn_text', 'guest', 'expected_checkout', 'hotel_task_open', 'no_data_found', 'hotel_toast_checked_in', 'hotel_toast_checked_out') as $k): ?>
<input type="hidden" id="hk_lang_<?php echo $k; ?>" value="<?php echo lang($k); ?>">
<?php endforeach; ?>
<script src="<?php echo base_url(); ?>frequent_changing/js/hotel_front_desk.js?v=1.1"></script>
