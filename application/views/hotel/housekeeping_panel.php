<?php /* Hotel add-on (H3): the Housekeeping board. A standalone full-screen page on the Kitchen Panel
         pattern (own <html>, no sidebar - a tablet on the floor), polled every 10 s through
         Hotel/tasksAjax by hotel_housekeeping.js. Sections: My tasks / Pool / Others (assign holders)
         / Done - awaiting verification (verify holders). */
$company = getCompanyInfo();
$site_name = isset($company->name) && $company->name ? $company->name : 'Rexlio';
$user_name = $this->session->userdata('full_name'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escape_output($site_name); ?> &middot; <?php echo lang('housekeeping_board'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/bower_components/font-awesome/v5/all.min.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/common.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/hotel.css?v=1.4">
    <script src="<?php echo base_url(); ?>frequent_changing/bar_panel/js/jquery-3.3.1.min.js"></script>
</head>
<body class="hkp_body">
    <div class="hkp_top">
        <div>
            <div class="hkp_title"><i class="fas fa-broom"></i> <?php echo lang('housekeeping_board'); ?></div>
            <div class="hkp_sub"><?php echo escape_output(isset($outlet->outlet_name) ? $outlet->outlet_name : ''); ?> &middot; <?php echo escape_output($user_name); ?> &middot; <span id="hkp_clock"></span></div>
        </div>
        <div class="hkp_chips">
            <span class="hk_chip"><?php echo lang('hk_mine'); ?> <b id="hkp_c_mine">0</b></span>
            <span class="hk_chip"><?php echo lang('hk_pool'); ?> <b id="hkp_c_pool">0</b></span>
            <?php if (!empty($can_assign)): ?><span class="hk_chip"><?php echo lang('hk_others'); ?> <b id="hkp_c_others">0</b></span><?php endif; ?>
            <?php if (!empty($can_verify)): ?><span class="hk_chip"><?php echo lang('hk_done'); ?> <b id="hkp_c_done">0</b></span><?php endif; ?>
        </div>
        <div class="hkp_actions">
            <?php if (count($outlets) > 1): ?>
            <select id="hk_outlet" class="hkp_select">
                <?php foreach ($outlets as $o): ?><option value="<?php echo (int) $o->id; ?>" <?php echo (int) $o->id === (int) $outlet_id ? 'selected' : ''; ?>><?php echo escape_output($o->outlet_name); ?></option><?php endforeach; ?>
            </select>
            <?php else: ?><input type="hidden" id="hk_outlet" value="<?php echo (int) $outlet_id; ?>"><?php endif; ?>
            <?php if (!empty($can_assign)): ?><button type="button" class="hkp_btn" id="hkp_new_task"><i class="fas fa-plus"></i> <?php echo lang('hk_new_task'); ?></button><?php endif; ?>
            <?php if (!empty($can_front_desk)): ?><a class="hkp_btn" href="<?php echo base_url(); ?>Hotel/frontDesk"><i class="fas fa-concierge-bell"></i> <?php echo lang('front_desk'); ?></a><?php endif; ?>
            <a class="hkp_btn hkp_btn_ghost" href="<?php echo base_url(); ?>Authentication/logOut"><i class="fas fa-sign-out-alt"></i> <?php echo lang('logout'); ?></a>
        </div>
    </div>
    <div class="hk_offline_banner" id="hk_offline_banner" style="display:none;"><i class="fas fa-exclamation-triangle"></i> <?php echo lang('offline_showing_last_known'); ?> <span id="hk_last_updated"></span></div>
    <div class="hkp_main" id="hkp_main"><div class="hk_empty"><?php echo lang('loading'); ?>&hellip;</div></div>

    <?php if (!empty($can_assign)): ?>
    <!-- new task -->
    <div class="hkp_overlay" id="hkp_new_overlay" hidden>
        <div class="hkp_dialog">
            <div class="hkp_dialog_head"><b><?php echo lang('hk_new_task'); ?></b><button type="button" class="hkp_close" id="hkp_new_close">&times;</button></div>
            <label><?php echo lang('room'); ?></label>
            <select id="hkp_nt_room" class="hkp_select hkp_w100">
                <?php foreach ($rooms as $r): ?><option value="<?php echo (int) $r->id; ?>"><?php echo escape_output($r->number . ($r->floor ? ' (' . $r->floor . ')' : '')); ?> &middot; <?php echo lang('room_' . $r->housekeeping_status); ?></option><?php endforeach; ?>
            </select>
            <label><?php echo lang('hk_task_type'); ?></label>
            <select id="hkp_nt_type" class="hkp_select hkp_w100">
                <?php foreach (array('cleaning', 'turndown', 'inspection', 'maintenance') as $t): ?><option value="<?php echo $t; ?>"><?php echo lang('hotel_task_' . $t); ?></option><?php endforeach; ?>
            </select>
            <label><?php echo lang('hk_assign_to'); ?></label>
            <select id="hkp_nt_user" class="hkp_select hkp_w100"><option value="0"><?php echo lang('hk_pool'); ?></option>
                <?php foreach ($staff as $u): ?><option value="<?php echo (int) $u->id; ?>"><?php echo escape_output($u->full_name); ?> (<?php echo escape_output($u->role_name); ?>)</option><?php endforeach; ?>
            </select>
            <label><?php echo lang('notes'); ?></label>
            <input type="text" id="hkp_nt_note" class="hkp_input hkp_w100" maxlength="250">
            <div class="hkp_err" id="hkp_nt_error"></div>
            <div class="hkp_dialog_foot"><button type="button" class="hkp_btn hkp_btn_primary" id="hkp_nt_submit"><?php echo lang('submit'); ?></button></div>
        </div>
    </div>
    <!-- assign -->
    <div class="hkp_overlay" id="hkp_assign_overlay" hidden>
        <div class="hkp_dialog">
            <div class="hkp_dialog_head"><b><?php echo lang('hk_assign_to'); ?> &middot; <span id="hkp_as_room"></span></b><button type="button" class="hkp_close" id="hkp_as_close">&times;</button></div>
            <input type="hidden" id="hkp_as_task" value="">
            <select id="hkp_as_user" class="hkp_select hkp_w100"><option value="0"><?php echo lang('hk_pool'); ?></option>
                <?php foreach ($staff as $u): ?><option value="<?php echo (int) $u->id; ?>"><?php echo escape_output($u->full_name); ?> (<?php echo escape_output($u->role_name); ?>)</option><?php endforeach; ?>
            </select>
            <div class="hkp_err" id="hkp_as_error"></div>
            <div class="hkp_dialog_foot"><button type="button" class="hkp_btn hkp_btn_primary" id="hkp_as_submit"><?php echo lang('submit'); ?></button></div>
        </div>
    </div>
    <?php endif; ?>

    <input type="hidden" id="hk_base_url" value="<?php echo base_url() ?>">
    <?php foreach (array('hk_mine', 'hk_pool', 'hk_others', 'hk_done', 'hk_start', 'hk_finish', 'hk_verify', 'hk_assign', 'hk_cancel', 'hk_no_tasks', 'hk_confirm_cancel', 'hotel_since',
                         'hotel_task_cleaning', 'hotel_task_turndown', 'hotel_task_inspection', 'hotel_task_maintenance', 'room_vacant', 'room_occupied', 'room_out_of_order',
                         'hk_status_pending', 'hk_status_in_progress', 'hk_status_done', 'hk_by', 'hk_awaiting_verification') as $k): ?>
    <input type="hidden" id="hk_lang_<?php echo $k; ?>" value="<?php echo lang($k); ?>">
    <?php endforeach; ?>
    <script src="<?php echo base_url(); ?>frequent_changing/js/hotel_housekeeping.js?v=1.0"></script>
</body>
</html>
