<?php /* Hotel add-on (H6): the filter bar every hotel report shares - outlet, view (Day / Week / Month /
         Year), date range, quick presets (today / this week / this month / this year) that fill the
         dates and pick the matching view client-side. GET so a report can be bookmarked. */ ?>
<form method="get" action="<?php echo base_url() . 'Hotel/' . $report_action; ?>" id="hk_report_form" class="row g-2 align-items-end mb-3">
    <?php if (count($filters['outlets']) > 1): ?>
    <div class="col-sm-6 col-md-3 col-lg-2">
        <label class="form-label mb-1"><?php echo lang('outlet'); ?></label>
        <select name="outlet_id" class="form-control">
            <option value="all" <?php echo $filters['outlet_id'] === 'all' ? 'selected' : ''; ?>><?php echo lang('all_outlets'); ?></option>
            <?php foreach ($filters['outlets'] as $o): ?><option value="<?php echo (int) $o->id; ?>" <?php echo $filters['outlet_id'] === (string) $o->id ? 'selected' : ''; ?>><?php echo escape_output($o->outlet_name); ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php else: ?><input type="hidden" name="outlet_id" value="<?php echo escape_output($filters['outlet_id']); ?>"><?php endif; ?>
    <div class="col-sm-6 col-md-3 col-lg-2">
        <label class="form-label mb-1"><?php echo lang('view'); ?></label>
        <select name="view" id="hk_rp_view" class="form-control">
            <?php foreach (array('day' => lang('hk_view_day'), 'week' => lang('hk_view_week'), 'month' => lang('hk_view_month'), 'year' => lang('hk_view_year')) as $k => $label): ?>
                <option value="<?php echo $k; ?>" <?php echo $filters['view'] === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-sm-6 col-md-3 col-lg-2">
        <label class="form-label mb-1"><?php echo lang('start_date'); ?></label>
        <input type="date" name="start_date" id="hk_rp_from" class="form-control" value="<?php echo escape_output($filters['from']); ?>">
    </div>
    <div class="col-sm-6 col-md-3 col-lg-2">
        <label class="form-label mb-1"><?php echo lang('end_date'); ?></label>
        <input type="date" name="end_date" id="hk_rp_to" class="form-control" value="<?php echo escape_output($filters['to']); ?>">
    </div>
    <?php /* report-specific filters (H8+): each item is array(name, label, options[value => label], selected) */
    if (!empty($extra_filters)): foreach ($extra_filters as $x): ?>
    <div class="col-sm-6 col-md-3 col-lg-2">
        <label class="form-label mb-1"><?php echo $x['label']; ?></label>
        <select name="<?php echo $x['name']; ?>" class="form-control">
            <option value=""><?php echo lang('all'); ?></option>
            <?php foreach ($x['options'] as $v => $label): ?><option value="<?php echo escape_output($v); ?>" <?php echo (string) $x['selected'] === (string) $v && $x['selected'] !== '' ? 'selected' : ''; ?>><?php echo escape_output($label); ?></option><?php endforeach; ?>
        </select>
    </div>
    <?php endforeach; endif; ?>
    <div class="col-sm-12 col-md-6 col-lg-3">
        <label class="form-label mb-1 d-block">&nbsp;</label>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary hk_rp_preset" data-preset="today"><?php echo lang('today'); ?></button>
            <button type="button" class="btn btn-outline-secondary hk_rp_preset" data-preset="week"><?php echo lang('hk_this_week'); ?></button>
            <button type="button" class="btn btn-outline-secondary hk_rp_preset" data-preset="month"><?php echo lang('this_month'); ?></button>
            <button type="button" class="btn btn-outline-secondary hk_rp_preset" data-preset="year"><?php echo lang('hk_this_year'); ?></button>
        </div>
    </div>
    <div class="col-sm-12 col-md-2 col-lg-1">
        <label class="form-label mb-1 d-block">&nbsp;</label>
        <button type="submit" class="btn bg-blue-btn w-100"><?php echo lang('submit'); ?></button>
    </div>
    <?php if (!empty($filters['capped'])): ?><div class="col-12 text-muted" style="font-size:12px"><?php echo lang('hk_range_capped'); ?></div><?php endif; ?>
</form>
<input type="hidden" id="hk_today" value="<?php echo date('Y-m-d'); ?>">
<script src="<?php echo base_url(); ?>frequent_changing/js/hotel_reports.js?v=1.0"></script>