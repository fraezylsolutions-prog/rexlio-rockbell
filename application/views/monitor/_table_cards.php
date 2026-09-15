<?php
/* Table status cards, grouped by area.
   Rendered both by monitor/tables.php on first load and by Monitor::tablesAjax()
   on each poll, so the markup lives in one place. */
?>
<?php if (empty($tables)): ?>
    <div class="ts_empty"><?php echo lang('no_data_found'); ?></div>
<?php else: ?>
    <?php
    //group by area for display
    $grouped = array();
    $session_outlet_id = (int) $this->session->userdata('outlet_id');
    $session_user_id = (int) $this->session->userdata('user_id');
    $can_act_any = !empty($can_act_any);
    $can_view_all = !empty($can_view_all);
    foreach ($tables as $table) {
        $area_name = $table->area_name ? $table->area_name : lang('None');
        //several outlets on one screen: prefix the area with the outlet
        if ($can_view_all && isset($table->outlet_name) && $table->outlet_name) {
            $area_name = $table->outlet_name . ' / ' . $area_name;
        }
        if (!isset($grouped[$area_name])) {
            $grouped[$area_name] = array();
        }
        $grouped[$area_name][] = $table;
    }
    ?>
    <?php foreach ($grouped as $area_name => $area_tables): ?>
        <div class="ts_area_block">
            <h4 class="ts_area_title">
                <?php echo escape_output($area_name) ?>
                <span class="ts_area_meta">
                    <?php
                    $area_occupied = 0;
                    foreach ($area_tables as $area_table) {
                        if ($area_table->is_occupied) { $area_occupied++; }
                    }
                    //plain ints: escape_output('0') is '' and would print "/3 Occupied" for a free area
                    echo (int) $area_occupied . '/' . (int) count($area_tables) . ' ' . lang('occupied');
                    ?>
                </span>
            </h4>
            <div class="ts_card_grid">
                <?php foreach ($area_tables as $table):
                    //an occupied table deep links into the POS with its order preselected.
                    //Same limitation as the running order screen: the POS panel is rendered
                    //from that device's IndexedDB, so this only selects the order when opened
                    //on the device and user session that placed it.
                    $first_order = !empty($table->orders) ? $table->orders[0] : NULL;
                    $pos_base = site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id'));
                    $pos_link = $pos_base;
                    if ($first_order) {
                        $pos_link .= '?open_sale_no=' . urlencode($first_order->sale_no);
                    }
                    //Stage 4: actions only within the session outlet (another outlet is a
                    //different register and stock - view only); on someone else's order only
                    //with act_on_any_running_order. Each action deep-links into the POS,
                    //which adopts the order if needed and runs its own handler (ir_action).
                    $same_outlet = ((int) $table->outlet_id === $session_outlet_id);
                    $is_own = $first_order && ((int) $first_order->user_id === $session_user_id || (int) $first_order->waiter_id === $session_user_id);
                    $can_act = $same_outlet && $first_order && ($is_own || $can_act_any);
                    $actions = array('modify' => lang('modify_order_'), 'invoice' => lang('invoice'), 'split' => lang('split_bill'), 'merge' => lang('merge_table'), 'bill' => lang('bill'), 'cancel' => lang('cancel_order'));
                    ?>
                    <div class="ts_card <?php echo $table->is_occupied ? 'ts_occupied' : 'ts_free' ?>" data-table_id="<?php echo escape_output($table->id) ?>">
                        <div class="ts_card_head">
                            <span class="ts_table_name"><?php echo escape_output($table->name) ?></span>
                            <span class="ts_badge"><?php echo $table->is_occupied ? lang('occupied') : lang('free') ?></span>
                        </div>
                        <div class="ts_card_body">
                            <?php if ($table->is_occupied): ?>
                                <div class="ts_row ts_row_value">
                                    <span class="ts_label"><?php echo lang('order_value'); ?></span>
                                    <span class="ts_value ts_value_money"><?php echo escape_output(getAmtP($table->order_value)) ?></span>
                                </div>
                            <?php else: ?>
                            <div class="ts_row">
                                <span class="ts_label"><?php echo lang('seat_capacity'); ?></span>
                                <span class="ts_value"><?php echo escape_output($table->sit_capacity) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($table->is_occupied): ?>
                                <div class="ts_row">
                                    <span class="ts_label"><?php echo lang('guests'); ?></span>
                                    <span class="ts_value"><?php echo escape_output($table->total_guests) ?></span>
                                </div>
                                <?php foreach ($table->orders as $table_order): ?>
                                    <div class="ts_order">
                                        <div class="ts_order_head">
                                            <span class="ts_order_no"><?php echo escape_output($table_order->sale_no) ?></span>
                                            <span class="ts_timer" data-minutes="<?php echo escape_output($table_order->minute_difference) ?>" data-seconds="<?php echo escape_output($table_order->second_difference) ?>">
                                                <?php echo escape_output($table_order->minute_difference) ?>:<?php echo escape_output($table_order->second_difference) ?>
                                            </span>
                                        </div>
                                        <div class="ts_order_meta">
                                            <?php echo escape_output($table_order->waiter_name ? $table_order->waiter_name : '') ?>
                                            <?php echo $table_order->customer_name ? ($table_order->waiter_name ? ' &middot; ' : '') . escape_output($table_order->customer_name) : '' ?>
                                            <?php echo count($table->orders) > 1 ? ' &middot; ' . escape_output(getAmtP($table_order->total_payable)) : '' ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ts_card_actions">
                            <?php if (!$table->is_occupied): ?>
                                <?php if ($same_outlet): ?>
                                <a class="btn ts_btn" href="<?php echo $pos_base . '?ir_table_id=' . (int) $table->id . '&ir_table_name=' . urlencode($table->name) ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('new_order'); ?></a>
                                <?php else: ?>
                                <span class="ts_view_only"><?php echo lang('view_only'); ?></span>
                                <?php endif; ?>
                            <?php elseif ($can_act): ?>
                                <?php foreach ($actions as $key => $label): ?>
                                <a class="btn ts_btn ts_btn_open <?php echo $key === 'cancel' ? 'ts_btn_danger' : '' ?>" href="<?php echo $pos_link . '&ir_action=' . $key ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo $label ?></a>
                                <?php endforeach; ?>
                            <?php elseif ($same_outlet): ?>
                                <a class="btn ts_btn ts_btn_open" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('order_details'); ?></a>
                                <span class="ts_view_only"><?php echo lang('view_only'); ?></span>
                            <?php else: ?>
                                <?php //another outlet's order cannot be opened on this outlet's POS (adoption is
                                      //refused server side), so no link: the card itself is the information ?>
                                <span class="ts_view_only"><?php echo lang('view_only') . ' &middot; ' . lang('outlet') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
