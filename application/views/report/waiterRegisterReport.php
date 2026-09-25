<?php
/**
 * Waiter's Register Report.
 *
 * Pick a register session, press the button, see one waiter's own takings for
 * that session - plus the orders of theirs that were voided or cancelled while
 * it was open. A waiter gets their own figures and no choice of whose; anyone
 * with the permission and an office role may pick. That decision is made in the
 * controller, not here - this view only hides a dropdown it was told to hide.
 */
$can_pick = !empty($can_pick_waiter);
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/dist/css/custom/report.css">
<?php /* The same wrapper every other report uses. Without it the content sits
         under the sidebar instead of beside it. */ ?>
<section class="main-content-wrapper">
  <section class="content-header px-0">
    <div class="d-flex align-items-center">
      <h3 class="top-left-header text-left"><?php echo lang('waiter_register_report'); ?></h3>
    </div>
  </section>
  <div class="box-wrapper">
    <div class="p-3">

        <form method="post" action="<?php echo base_url(); ?>Report/waiterRegisterReport" class="form-horizontal" id="wrr_filter">
          <div class="row">
            <div class="col-md-3">
              <label><?php echo lang('start_date'); ?></label>
              <input type="date" name="startDate" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>">
            </div>
            <div class="col-md-3">
              <label><?php echo lang('end_date'); ?></label>
              <input type="date" name="endDate" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>">
            </div>
            <div class="col-md-3" style="padding-top:24px">
              <button type="submit" name="submit" value="1" class="btn btn-default"><?php echo lang('search'); ?></button>
            </div>
          </div>
        </form>

        <hr>

        <div class="row">
          <div class="col-md-5">
            <label><?php echo lang('register'); ?></label>
            <select id="wrr_register" class="form-control">
              <option value=""><?php echo lang('select'); ?></option>
              <?php if (!empty($register_info)): foreach ($register_info as $r):
                    $open = ((int) $r->register_status === 1);
                    $label = $r->opening_balance_date_time . ' — '
                           . ($open ? lang('open') : $r->closing_balance_date_time);
              ?>
                <option value="<?php echo (int) $r->id; ?>">
                  <?php echo htmlspecialchars((isset($r->counter_name) ? $r->counter_name : ('counter ' . $r->counter_id)) . '  |  ' . $label); ?>
                </option>
              <?php endforeach; else: ?>
                <option value=""><?php echo lang('no_data_found'); ?></option>
              <?php endif; ?>
            </select>
            <span class="help-block" style="font-size:12px">
              <?php echo lang('wrr_register_hint'); ?>
            </span>
          </div>

          <?php if ($can_pick): ?>
          <div class="col-md-4">
            <label><?php echo lang('waiter'); ?></label>
            <select id="wrr_waiter" class="form-control">
              <option value=""><?php echo lang('select'); ?></option>
              <?php if (!empty($waiters)): foreach ($waiters as $w): ?>
                <option value="<?php echo (int) $w->id; ?>"><?php echo htmlspecialchars($w->full_name); ?></option>
              <?php endforeach; endif; ?>
            </select>
          </div>
          <?php else: ?>
            <input type="hidden" id="wrr_waiter" value="<?php echo (int) $own_user_id; ?>">
          <?php endif; ?>

          <div class="col-md-3" style="padding-top:24px">
            <button type="button" id="wrr_view" class="btn btn-primary"><?php echo lang('wrr_view_button'); ?></button>
          </div>
        </div>

        <div id="wrr_result" style="margin-top:22px"></div>
    </div>
  </div>
</section>

<style>
#wrr_result table{width:100%;margin-bottom:18px}
#wrr_result th,#wrr_result td{padding:6px 8px;border-bottom:1px solid #e3e3e3;font-size:13px}
#wrr_result th{text-align:left;background:#f5f5f5}
#wrr_result .num{text-align:right}
#wrr_result h4{margin:18px 0 8px;font-size:15px;font-weight:bold}
#wrr_result .wrr_head{border-left:4px solid #1F3864;padding:8px 12px;background:#f8f8f8;margin-bottom:14px}
#wrr_result .wrr_void td{color:#a11}
@media print{
  body * {visibility:hidden}
  #wrr_print_area, #wrr_print_area * {visibility:visible}
  #wrr_print_area {position:absolute;left:0;top:0;width:100%}
  .wrr_noprint {display:none !important}
}
</style>

<script>
(function ($) {
  var money = function (v) {
    var n = Number(v || 0);
    return n.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
  };
  var esc = function (s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  };

  $('#wrr_view').on('click', function () {
    var register_id = $('#wrr_register').val();
    var waiter_id = $('#wrr_waiter').val();
    var box = $('#wrr_result');
    if (!register_id) { box.html('<div class="alert alert-warning"><?php echo lang('wrr_pick_register'); ?></div>'); return; }

    box.html('<p class="text-muted"><?php echo lang('please_wait'); ?></p>');
    $.ajax({
      url: '<?php echo base_url(); ?>Report/waiterRegisterReportAjax',
      method: 'POST',
      dataType: 'json',
      data: {register_id: register_id, waiter_id: waiter_id},
      success: function (d) {
        if (!d || !d.found) {
          box.html('<div class="alert alert-danger"><?php echo lang('no_data_found'); ?></div>');
          return;
        }
        var h = '<div id="wrr_print_area">';
        h += '<div class="wrr_head"><b>' + esc(d.waiter_name) + '</b><br>'
           + esc(d.outlet_name) + ' &middot; ' + esc(d.counter_name)
           + '<br><?php echo lang('register'); ?>: ' + esc(d.window_start) + ' &rarr; '
           + esc(d.window_end) + (d.is_open ? ' <b>(<?php echo lang('open'); ?>)</b>' : '')
           + '<br><small><?php echo lang('opened_by'); ?>: ' + esc(d.opened_by) + '</small></div>';

        h += '<h4><?php echo lang('payment_method'); ?></h4><table><thead><tr>'
           + '<th><?php echo lang('payment_method'); ?></th><th class="num"><?php echo lang('amount'); ?></th>'
           + '</tr></thead><tbody>';
        $.each(d.payments || [], function (i, p) {
          h += '<tr><td>' + esc(p.method) + '</td><td class="num">' + money(p.sell) + '</td></tr>';
        });
        h += '<tr><th><?php echo lang('total'); ?></th><th class="num">' + money(d.total_sales) + '</th></tr>';
        h += '</tbody></table>';

        h += '<h4><?php echo lang('summary'); ?></h4><table><tbody>'
           + '<tr><td><?php echo lang('total_orders'); ?></td><td class="num">' + esc(d.total_orders) + '</td></tr>'
           + '<tr><td><?php echo lang('completed_orders'); ?></td><td class="num">' + esc(d.completed_orders) + '</td></tr>'
           + '<tr><td><?php echo lang('total_items'); ?></td><td class="num">' + esc(d.items_sold) + '</td></tr>'
           + '<tr><td><?php echo lang('orders_value'); ?></td><td class="num">' + money(d.orders_value) + '</td></tr>'
           + '<tr><td><?php echo lang('credit_sales'); ?></td><td class="num">' + money(d.credit_sales) + '</td></tr>'
           + '</tbody></table>';

        h += '<h4><?php echo lang('products_sold'); ?></h4><table><thead><tr>'
           + '<th><?php echo lang('item'); ?></th><th class="num"><?php echo lang('qty'); ?></th>'
           + '<th class="num"><?php echo lang('amount'); ?></th></tr></thead><tbody>';
        if (!(d.items || []).length) { h += '<tr><td colspan="3"><?php echo lang('no_data_found'); ?></td></tr>'; }
        $.each(d.items || [], function (i, it) {
          h += '<tr><td>' + esc(it.menu_name) + '</td><td class="num">' + esc(it.qty)
             + '</td><td class="num">' + money(it.value) + '</td></tr>';
        });
        h += '</tbody></table>';

        if ((d.categories || []).length) {
          h += '<h4><?php echo lang('category'); ?></h4><table><thead><tr>'
             + '<th><?php echo lang('category'); ?></th><th class="num"><?php echo lang('qty'); ?></th>'
             + '<th class="num"><?php echo lang('amount'); ?></th></tr></thead><tbody>';
          $.each(d.categories, function (i, c) {
            h += '<tr><td>' + esc(c.category) + '</td><td class="num">' + esc(c.qty)
               + '</td><td class="num">' + money(c.value) + '</td></tr>';
          });
          h += '</tbody></table>';
        }

        /* Every completed order, one line each: what it was, how it was paid and
           what it came to. Sits above the voided section so the report reads as
           what was sold, then what was not. */
        h += '<h4><?php echo lang('wrr_completed_orders_list'); ?></h4><table><thead><tr>'
           + '<th><?php echo lang('order_no'); ?></th><th><?php echo lang('date'); ?></th>'
           + '<th><?php echo lang('payment_method'); ?></th>'
           + '<th class="num"><?php echo lang('amount'); ?></th></tr></thead><tbody>';
        if (!(d.orders || []).length) {
          h += '<tr><td colspan="4"><?php echo lang('wrr_no_orders'); ?></td></tr>';
        }
        var orders_total = 0;
        $.each(d.orders || [], function (i, o) {
          orders_total += Number(o.total_payable || 0);
          h += '<tr><td>' + esc(o.sale_no) + '</td><td>' + esc(o.date_time)
             + '</td><td>' + (o.methods ? esc(o.methods) : '&mdash;')
             + '</td><td class="num">' + money(o.total_payable) + '</td></tr>';
        });
        if ((d.orders || []).length) {
          h += '<tr><th colspan="3"><?php echo lang('total'); ?></th>'
             + '<th class="num">' + money(orders_total) + '</th></tr>';
        }
        h += '</tbody></table>';

        h += '<h4><?php echo lang('wrr_voided'); ?></h4><table><thead><tr>'
           + '<th><?php echo lang('date'); ?></th><th><?php echo lang('order_no'); ?></th>'
           + '<th><?php echo lang('type'); ?></th><th><?php echo lang('reason'); ?></th>'
           + '<th class="num"><?php echo lang('amount'); ?></th><th><?php echo lang('by'); ?></th>'
           + '</tr></thead><tbody>';
        if (!(d.voids || []).length) {
          h += '<tr><td colspan="6"><?php echo lang('wrr_no_voids'); ?></td></tr>';
        }
        $.each(d.voids || [], function (i, v) {
          h += '<tr class="wrr_void"><td>' + esc(v.date_time) + '</td><td>' + esc(v.sale_no)
             + '</td><td>' + esc(v.event) + '</td><td>' + esc(v.reason)
             + '</td><td class="num">' + (v.value ? esc(v.value) : '') + '</td><td>' + esc(v.by_user) + '</td></tr>';
        });
        h += '</tbody></table>';
        h += '</div>';
        h += '<p class="wrr_noprint"><button type="button" class="btn btn-default" id="wrr_print">'
           + '<i class="fa fa-print"></i> <?php echo lang('print'); ?></button></p>';
        box.html(h);
      },
      error: function () {
        box.html('<div class="alert alert-danger"><?php echo lang('something_went_wrong'); ?></div>');
      }
    });
  });

  $(document).on('click', '#wrr_print', function () { window.print(); });
})(jQuery);
</script>
