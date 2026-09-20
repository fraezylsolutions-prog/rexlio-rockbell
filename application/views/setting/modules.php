<?php /* Settings > Modules (H0): one switch per add-on. Business-wide; read per
         request, so a flip is live on the next request - no re-login, no deploy. */ ?>
<section class="main-content-wrapper">
    <?php if ($this->session->flashdata('exception')): ?>
        <section class="alert-wrapper"><div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <div class="alert-body"><p><i class="m-right fa fa-check"></i><?php echo escape_output($this->session->flashdata('exception')); ?></p></div></div></section>
    <?php endif; ?>
    <?php if ($this->session->flashdata('exception_1')): ?>
        <section class="alert-wrapper"><div class="alert alert-danger alert-dismissible">
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            <div class="alert-body"><p><i class="m-right fa fa-exclamation-triangle"></i><?php echo escape_output($this->session->flashdata('exception_1')); ?></p></div></div></section>
    <?php endif; ?>

    <section class="content-header">
        <h3 class="top-left-header"><?php echo lang('modules'); ?></h3>
        <p class="text-muted"><?php echo lang('modules_desc'); ?></p>
    </section>

    <div class="box-wrapper">
        <div class="table-box">
            <table class="table table-bordered" id="ir_modules_table">
                <thead>
                    <tr>
                        <th><?php echo lang('module'); ?></th>
                        <th style="width:140px"><?php echo lang('status'); ?></th>
                        <th style="width:260px"><?php echo lang('last_changed'); ?></th>
                        <th style="width:180px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php $group = ''; foreach ($registry as $key => $meta):
                    $row = isset($rows[$key]) ? $rows[$key] : NULL;
                    $kind = isset($meta['kind']) ? $meta['kind'] : 'addon';
                    $default_on = isset($meta['default']) && (int) $meta['default'] === 1;
                    $on = $row ? (int) $row->is_enabled === 1 : $default_on;
                    $missing = $row ? irModuleSchemaMissing($key) : array();
                    /* P4: add-ons first, then the behaviour switches, each under a heading */
                    if ($kind !== $group): $group = $kind; ?>
                    <tr class="ir_module_group"><td colspan="4" class="text-muted" style="font-size:12.5px; text-transform:uppercase; letter-spacing:.04em"><?php echo lang($kind === 'switch' ? 'modules_group_switches' : 'modules_group_addons'); ?></td></tr>
                    <?php endif; ?>
                    <tr data-module="<?php echo escape_output($key); ?>">
                        <td>
                            <b><?php echo lang($meta['label']); ?></b>
                            <div class="text-muted" style="font-size:12.5px"><?php echo lang($meta['desc']); ?></div>
                        </td>
                        <td>
                            <?php if (!$row && $default_on): ?>
                                <span class="badge bg-success ir_module_state"><?php echo lang('module_on'); ?></span>
                                <div class="text-muted" style="font-size:12px"><?php echo lang('module_default_until_installed'); ?> <code><?php echo escape_output($meta['migration']); ?></code></div>
                            <?php elseif (!$row): ?>
                                <span class="badge bg-secondary"><?php echo lang('module_not_installed'); ?></span>
                            <?php elseif ($missing && !$on): ?>
                                <span class="badge bg-warning text-dark ir_module_schema"><?php echo lang('module_schema_missing_badge'); ?></span>
                                <div class="text-muted" style="font-size:12px"><?php echo lang('module_schema_missing'); ?> <code><?php echo escape_output($meta['migration']); ?></code><br><?php echo escape_output(implode(', ', $missing)); ?></div>
                            <?php elseif ($on): ?>
                                <span class="badge bg-success ir_module_state"><?php echo lang('module_on'); ?></span>
                            <?php else: ?>
                                <span class="badge bg-secondary ir_module_state"><?php echo lang('module_off'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted" style="font-size:12.5px">
                            <?php if ($row && $row->updated_at): ?>
                                <?php echo escape_output($row->updated_at); ?>
                                <?php echo isset($user_names[$row->updated_by]) ? ' &middot; ' . escape_output($user_names[$row->updated_by]) : ''; ?>
                            <?php else: ?>&mdash;<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row && ($on || !$missing)): ?>
                            <?php echo form_open(base_url() . 'setting/modules', array('class' => 'ir_module_form')); ?>
                                <input type="hidden" name="module_key" value="<?php echo escape_output($key); ?>">
                                <input type="hidden" name="enabled" value="<?php echo $on ? '0' : '1'; ?>">
                                <button type="submit" name="submit" value="toggle" class="btn w-100 <?php echo $on ? 'btn-secondary' : 'bg-blue-btn'; ?>">
                                    <?php echo $on ? lang('switch_off') : lang('switch_on'); ?>
                                </button>
                            <?php echo form_close(); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="text-muted" style="font-size:12.5px"><?php echo lang('modules_note'); ?></p>
        </div>
    </div>
</section>
