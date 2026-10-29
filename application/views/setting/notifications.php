<?php /* Settings > Notifications (N4.5). One screen, four blocks:
         1. the switches        - master first, then one per channel
         2. general             - test mode, the test recipient, retry limits
         3. the channels        - provider settings, keys masked unless permitted
         4. the rules           - which event reaches whom, on which channel
         plus the last 25 outbox rows, because the first question after a missed
         alert is always "was it even queued?" and it should not need a DBA. */
$master_row = isset($rows['notifications']) ? $rows['notifications'] : NULL;
$master_on  = $master_row ? ((int) $master_row->is_enabled === 1) : FALSE;
$mask       = isset($sms_cfg['api_key']) ? irNotifyMaskSecret($sms_cfg['api_key']) : '';
$has_key    = isset($sms_cfg['api_key']) && $sms_cfg['api_key'] !== '';
?>
<section class="main-content-wrapper">
    <?php $this->view('hotel/_flash'); ?>

    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('notifications'); ?></h3>
        <p class="text-muted"><?php echo lang('notify_desc'); ?></p>
    </section>

    <?php if (!$can_update): ?>
        <div class="callout callout-warning my-2"><?php echo lang('notify_read_only'); ?></div>
    <?php endif; ?>

    <!-- ------------------------------------------------- 1. the switches -->
    <div class="box-wrapper">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_switches'); ?></h5>
                <p class="text-muted" style="font-size:12.5px"><?php echo lang('notify_switches_hint'); ?></p>
                <?php if (!$master_on): ?>
                    <div class="callout callout-danger my-2"><?php echo lang('notify_master_off_banner'); ?></div>
                <?php endif; ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th><?php echo lang('module'); ?></th>
                            <th style="width:110px"><?php echo lang('status'); ?></th>
                            <th style="width:170px"><?php echo lang('notify_driver'); ?></th>
                            <th style="width:150px"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    /* master first, then the channels in screen order, so the one
                       switch that overrules the rest is never buried in the list */
                    $switch_keys = array_merge(array('notifications'), array_map(function ($c) { return 'notify_' . $c; }, $channels));
                    foreach ($switch_keys as $key):
                        if (!isset($registry[$key])) { continue; }
                        $meta = $registry[$key];
                        $row  = isset($rows[$key]) ? $rows[$key] : NULL;
                        $on   = $row ? ((int) $row->is_enabled === 1) : FALSE;
                        $is_master = ($key === 'notifications');
                        $channel = $is_master ? '' : substr($key, strlen('notify_'));
                        $driver_ready = $is_master ? TRUE : (irNotifyDriver($channel) !== NULL);
                    ?>
                        <tr<?php echo $is_master ? ' class="table-light"' : ''; ?>>
                            <td>
                                <b><?php echo lang($meta['label']); ?></b>
                                <div class="text-muted" style="font-size:12.5px"><?php echo lang($meta['desc']); ?></div>
                            </td>
                            <td>
                                <?php if (!$row): ?>
                                    <span class="badge bg-secondary"><?php echo lang('module_not_installed'); ?></span>
                                <?php elseif ($on): ?>
                                    <span class="badge bg-success"><?php echo lang('module_on'); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo lang('module_off'); ?></span>
                                <?php endif; ?>
                                <?php if ($on && !$is_master && !$master_on): ?>
                                    <div class="text-muted" style="font-size:12px"><?php echo lang('notify_held_by_master'); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="font-size:12.5px">
                                <?php if ($is_master): ?>
                                    &mdash;
                                <?php elseif ($driver_ready): ?>
                                    <span class="text-success"><?php echo lang('notify_driver_ready'); ?></span>
                                <?php else: ?>
                                    <?php echo lang('notify_driver_pending'); ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($row && $can_update): ?>
                                <?php echo form_open(base_url() . 'setting/notifications'); ?>
                                    <input type="hidden" name="ir_action" value="toggle">
                                    <input type="hidden" name="module_key" value="<?php echo escape_output($key); ?>">
                                    <input type="hidden" name="enabled" value="<?php echo $on ? '0' : '1'; ?>">
                                    <button type="submit" class="btn w-100 <?php echo $on ? 'btn-secondary' : 'bg-blue-btn'; ?>">
                                        <?php echo $on ? lang('switch_off') : lang('switch_on'); ?>
                                    </button>
                                <?php echo form_close(); ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- --------------------------------------------------- 2. general -->
    <div class="box-wrapper">
        <?php echo form_open(base_url() . 'setting/notifications'); ?>
        <input type="hidden" name="ir_action" value="general">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_general'); ?></h5>
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-4">
                        <div class="form-group">
                            <label><?php echo lang('notify_test_recipient'); ?></label>
                            <input type="text" name="test_recipient" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   placeholder="you@example.com, 08031234567"
                                   value="<?php echo escape_output($general['test_recipient']); ?>">
                            <small class="text-muted"><?php echo lang('notify_test_recipient_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_max_attempts'); ?></label>
                            <input type="number" min="1" max="10" name="max_attempts" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo (int) $general['max_attempts']; ?>">
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_hold_expiry'); ?></label>
                            <input type="number" min="1" max="720" name="hold_expiry_hours" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo (int) $general['hold_expiry_hours']; ?>">
                            <small class="text-muted"><?php echo lang('notify_hold_expiry_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_ack_expiry_hours'); ?></label>
                            <?php /* N5(a): capped at 168 (a week) in the controller too - the
                                     browser is not where this is enforced. */ ?>
                            <input type="number" min="1" max="168" name="ack_expiry_hours" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo (int) (isset($general['ack_expiry_hours']) ? $general['ack_expiry_hours'] : 72); ?>">
                            <small class="text-muted"><?php echo lang('notify_ack_expiry_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_batch_size'); ?></label>
                            <input type="number" min="1" max="500" name="batch_size" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo (int) $general['batch_size']; ?>">
                            <small class="text-muted"><?php echo lang('notify_batch_size_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_test_mode'); ?></label>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="test_mode" value="1" id="ir_test_mode"
                                       <?php echo !empty($general['test_mode']) ? 'checked' : ''; ?> <?php echo $can_update ? '' : 'disabled'; ?>>
                                <label class="form-check-label" for="ir_test_mode"><?php echo lang('notify_test_mode_on'); ?></label>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!empty($general['test_mode'])): ?>
                    <div class="callout callout-warning my-2"><?php echo lang('notify_test_mode_banner'); ?></div>
                <?php endif; ?>
                <?php if ($can_update): ?>
                    <button type="submit" class="btn bg-blue-btn"><?php echo lang('submit'); ?></button>
                <?php endif; ?>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>

    <!-- ------------------------------------------------ 3. SMS provider -->
    <div class="box-wrapper">
        <?php echo form_open(base_url() . 'setting/notifications'); ?>
        <input type="hidden" name="ir_action" value="sms">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_sms_settings'); ?></h5>
                <p class="text-muted" style="font-size:12.5px"><?php echo lang('notify_sms_settings_hint'); ?></p>
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('notify_provider'); ?></label>
                            <select name="provider" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>>
                                <?php $p = isset($sms_cfg['provider']) ? $sms_cfg['provider'] : ''; ?>
                                <option value="" <?php echo $p === '' ? 'selected' : ''; ?>><?php echo lang('notify_provider_none'); ?></option>
                                <option value="termii" <?php echo $p === 'termii' ? 'selected' : ''; ?>>Termii</option>
                                <option value="legacy" <?php echo $p === 'legacy' ? 'selected' : ''; ?>><?php echo lang('notify_provider_legacy'); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-3">
                        <div class="form-group">
                            <label><?php echo lang('notify_sender_id'); ?></label>
                            <input type="text" name="sender_id" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo escape_output(isset($sms_cfg['sender_id']) ? $sms_cfg['sender_id'] : ''); ?>">
                            <small class="text-muted"><?php echo lang('notify_sender_id_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_route'); ?></label>
                            <?php $route = (isset($sms_cfg['route']) && $sms_cfg['route'] === 'generic') ? 'generic' : 'dnd'; ?>
                            <select name="route" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>>
                                <option value="dnd" <?php echo $route === 'dnd' ? 'selected' : ''; ?>><?php echo lang('notify_route_dnd'); ?></option>
                                <option value="generic" <?php echo $route === 'generic' ? 'selected' : ''; ?>><?php echo lang('notify_route_generic'); ?></option>
                            </select>
                            <small class="text-muted"><?php echo lang('notify_route_hint'); ?></small>
                        </div>
                    </div>
                    <div class="col-sm-12 mb-2 col-md-2">
                        <div class="form-group">
                            <label><?php echo lang('notify_country_code'); ?></label>
                            <input type="text" name="country_code" class="form-control" <?php echo $can_update ? '' : 'disabled'; ?>
                                   value="<?php echo escape_output(isset($sms_cfg['country_code']) ? $sms_cfg['country_code'] : '234'); ?>">
                            <small class="text-muted"><?php echo lang('notify_country_code_hint'); ?></small>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-12 mb-2 col-md-6">
                        <div class="form-group">
                            <label><?php echo lang('notify_api_key'); ?></label>
                            <?php /* The key is never printed in full unless the signed-in
                                     role holds view_credentials. Either way an unchanged
                                     box means "leave it alone" - see the controller. */ ?>
                            <input type="text" name="api_key" class="form-control" autocomplete="off" <?php echo $can_update ? '' : 'disabled'; ?>
                                   placeholder="<?php echo $has_key ? escape_output(lang('notify_api_key_set')) : escape_output(lang('notify_api_key_empty')); ?>"
                                   value="<?php echo ($has_key && $can_see_keys) ? escape_output($sms_cfg['api_key']) : ''; ?>">
                            <small class="text-muted">
                                <?php if (!$has_key): ?>
                                    <?php echo lang('notify_api_key_empty'); ?>
                                <?php elseif ($can_see_keys): ?>
                                    <?php echo lang('notify_api_key_visible'); ?>
                                <?php else: ?>
                                    <?php echo lang('notify_api_key_masked'); ?> <code><?php echo escape_output($mask); ?></code>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <?php if ($has_key && $can_update): ?>
                    <div class="col-sm-12 mb-2 col-md-6">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" name="clear_api_key" value="1" id="ir_clear_key">
                                <label class="form-check-label" for="ir_clear_key"><?php echo lang('notify_api_key_clear'); ?></label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($can_update): ?>
                    <button type="submit" class="btn bg-blue-btn"><?php echo lang('submit'); ?></button>
                <?php endif; ?>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>

    <!-- ----------------------------------------------- send a test now -->
    <?php if ($can_update): ?>
    <div class="box-wrapper">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_send_test'); ?></h5>
                <p class="text-muted" style="font-size:12.5px"><?php echo lang('notify_send_test_hint'); ?></p>
                <?php foreach ($channels as $c):
                    if (irNotifyDriver($c) === NULL) { continue; } ?>
                    <?php echo form_open(base_url() . 'setting/notificationTest', array('class' => 'd-inline-block me-2 mb-2')); ?>
                        <input type="hidden" name="channel" value="<?php echo escape_output($c); ?>">
                        <button type="submit" class="btn btn-secondary" <?php echo irNotifyChannelOn($c) ? '' : 'disabled'; ?>>
                            <?php echo lang('notify_send_test_via'); ?> <?php echo escape_output(strtoupper($c)); ?>
                        </button>
                    <?php echo form_close(); ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ----------------------------------------------------- 4. rules -->
    <div class="box-wrapper">
        <?php /* ONE form around the whole grid. Not per row: a <form> is not valid
                 inside a <tr>, so the parser lifts it out of the table and the
                 inputs submit nothing - which looks exactly like a save that
                 silently did nothing. One form also means setting up six channels
                 for an event is one Save, not six. */ ?>
        <?php echo form_open(base_url() . 'setting/notifications'); ?>
        <input type="hidden" name="ir_action" value="rules">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_rules'); ?></h5>
                <p class="text-muted" style="font-size:12.5px"><?php echo lang('notify_rules_hint'); ?></p>
                <p class="text-muted" style="font-size:12.5px">
                    <?php echo lang('notify_roles_available'); ?>
                    <?php $bits = array(); foreach ($roles as $r) { $bits[] = 'role:' . (int) $r->id . ' = ' . $r->role_name; }
                          echo escape_output(implode(' - ', $bits)); ?>
                </p>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:120px"><?php echo lang('notify_channel'); ?></th>
                            <th><?php echo lang('notify_recipients'); ?></th>
                            <th style="width:90px"><?php echo lang('notify_on'); ?></th>
                            <th style="width:90px"><?php echo lang('notify_critical'); ?></th>
                            <th style="width:120px"><?php echo lang('notify_escalate'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($events as $event_key => $event): ?>
                        <tr class="table-light">
                            <td colspan="5">
                                <b><?php echo escape_output($event['label']); ?></b>
                                <code class="ms-2" style="font-size:12px"><?php echo escape_output($event_key); ?></code>
                            </td>
                        </tr>
                        <?php foreach ($channels as $c):
                            $k = $event_key . '|' . $c;
                            /* ':' and not '~': CodeIgniter sanitises POST keys through
                               _clean_input_keys(), which allows only [a-z0-9:_/|-] and
                               quietly rewrites anything else to an empty key - which
                               collapsed the entire grid into one blank entry. */
                            $field = $event_key . ':' . $c;
                            $rule = isset($rules[$k]) ? $rules[$k] : NULL;
                            $on = $rule ? ((int) $rule['row']->is_enabled === 1) : FALSE;
                            $crit = $rule ? ((int) $rule['row']->is_critical === 1) : FALSE;
                            /* N5(b): 0 means no ladder. isset() because a site whose
                               migration has not run has no such column and must still
                               render the screen. */
                            $esc = ($rule && isset($rule['row']->escalate_after_minutes))
                                 ? (int) $rule['row']->escalate_after_minutes : 0;
                            /* a channel that is switched off is still listed: the rule is
                               worth setting up before the switch is flipped, and hiding
                               the row is how a venue ends up wondering why nothing
                               arrives after turning a channel on */
                        ?>
                        <tr>
                            <td>
                                <?php echo escape_output(strtoupper($c)); ?>
                                <?php if (!irNotifyChannelOn($c)): ?>
                                    <span class="badge bg-secondary ms-1"><?php echo lang('module_off'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="text" name="rec[<?php echo escape_output($field); ?>]"
                                       class="form-control form-control-sm" <?php echo $can_update ? '' : 'disabled'; ?>
                                       placeholder="<?php echo escape_output(lang('notify_recipients_placeholder')); ?>"
                                       value="<?php echo $rule ? escape_output($rule['recipients']) : ''; ?>">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="en[<?php echo escape_output($field); ?>]" value="1"
                                       <?php echo $on ? 'checked' : ''; ?> <?php echo $can_update ? '' : 'disabled'; ?>>
                            </td>
                            <td class="text-center">
                                <input type="checkbox" name="cr[<?php echo escape_output($field); ?>]" value="1"
                                       <?php echo $crit ? 'checked' : ''; ?> <?php echo $can_update ? '' : 'disabled'; ?>>
                            </td>
                            <td>
                                <?php /* N5(b): minutes between rungs. 0 (the default, and
                                         every rule that already exists) means tell everyone
                                         at once. Above 0 means the recipients are a ladder
                                         in the order they are typed above, and the next one
                                         is only told if nobody has acknowledged. Only does
                                         anything on a CRITICAL rule - there is nothing to
                                         acknowledge otherwise. */ ?>
                                <input type="number" min="0" max="1440" step="1"
                                       name="esc[<?php echo escape_output($field); ?>]"
                                       class="form-control form-control-sm" <?php echo $can_update ? '' : 'disabled'; ?>
                                       value="<?php echo $esc; ?>">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if ($can_update): ?>
                    <button type="submit" class="btn bg-blue-btn"><?php echo lang('notify_save_rules'); ?></button>
                <?php endif; ?>
                <p class="text-muted mt-2" style="font-size:12.5px"><?php echo lang('notify_rules_note'); ?></p>
            </div>
        </div>
        <?php echo form_close(); ?>
    </div>

    <!-- ------------------------------------------------ recent sends -->
    <div class="box-wrapper">
        <div class="table-box">
            <div class="box-body">
                <h5><?php echo lang('notify_recent'); ?></h5>
                <p class="text-muted" style="font-size:12.5px">
                    <?php foreach (array('pending', 'sent', 'failed', 'held', 'expired') as $st): ?>
                        <span class="me-3"><?php echo escape_output($st); ?>:
                            <b><?php echo isset($counts[$st]) ? (int) $counts[$st] : 0; ?></b></span>
                    <?php endforeach; ?>
                </p>
                <?php if (!$recent): ?>
                    <p class="text-muted"><?php echo lang('notify_recent_empty'); ?></p>
                <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:70px">#</th>
                            <th><?php echo lang('notify_event'); ?></th>
                            <th style="width:90px"><?php echo lang('notify_channel'); ?></th>
                            <th><?php echo lang('notify_recipients'); ?></th>
                            <th style="width:100px"><?php echo lang('status'); ?></th>
                            <th style="width:160px"><?php echo lang('notify_when'); ?></th>
                            <th><?php echo lang('notify_last_error'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recent as $out):
                        $badge = 'bg-secondary';
                        if ($out->status === 'sent')    { $badge = 'bg-success'; }
                        if ($out->status === 'failed')  { $badge = 'bg-danger'; }
                        if ($out->status === 'pending') { $badge = 'bg-warning text-dark'; }
                    ?>
                        <tr>
                            <td><?php echo (int) $out->id; ?></td>
                            <td style="font-size:12.5px"><?php echo escape_output($out->event_key); ?></td>
                            <td><?php echo escape_output(strtoupper($out->channel)); ?></td>
                            <td style="font-size:12.5px">
                                <?php echo escape_output($out->actual_recipient); ?>
                                <?php /* in test mode these differ, and the difference is the
                                         whole point: it proves the routing as well as the send */
                                      if ($out->intended_recipient !== $out->actual_recipient): ?>
                                    <div class="text-muted"><?php echo lang('notify_intended_for'); ?> <?php echo escape_output($out->intended_recipient); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge; ?>"><?php echo escape_output($out->status); ?></span>
                                <?php if ((int) $out->test_mode === 1): ?>
                                    <div><span class="badge bg-info text-dark"><?php echo lang('notify_test_badge'); ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="font-size:12.5px">
                                <?php echo escape_output($out->sent_at ? $out->sent_at : $out->created_at); ?>
                                <?php if ((int) $out->attempts > 0): ?>
                                    <div><?php echo lang('notify_attempts'); ?>: <?php echo (int) $out->attempts; ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted" style="font-size:12px"><?php echo escape_output($out->last_error); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                <p class="text-muted" style="font-size:12.5px"><?php echo lang('notify_worker_note'); ?></p>
            </div>
        </div>
    </div>
</section>
