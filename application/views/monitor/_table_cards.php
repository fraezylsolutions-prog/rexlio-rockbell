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
    foreach ($tables as $table) {
        $area_name = $table->area_name ? $table->area_name : lang('None');
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
                    echo escape_output($area_occupied) . '/' . escape_output(count($area_tables)) . ' ' . lang('occupied');
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
                    $pos_link = site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id'));
                    if ($first_order) {
                        $pos_link .= '?open_sale_no=' . urlencode($first_order->sale_no);
                    }
                    ?>
                    <div class="ts_card <?php echo $table->is_occupied ? 'ts_occupied' : 'ts_free' ?>" data-table_id="<?php echo escape_output($table->id) ?>">
                        <div class="ts_card_head">
                            <span class="ts_table_name"><?php echo escape_output($table->name) ?></span>
                            <span class="ts_badge"><?php echo $table->is_occupied ? lang('occupied') : lang('free') ?></span>
                        </div>
                        <div class="ts_card_body">
                            <div class="ts_row">
                                <span class="ts_label"><?php echo lang('seat_capacity'); ?></span>
                                <span class="ts_value"><?php echo escape_output($table->sit_capacity) ?></span>
                            </div>
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
                                            <?php echo $table_order->customer_name ? ' &middot; ' . escape_output($table_order->customer_name) : '' ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div class="ts_card_actions">
                            <a class="btn ts_btn <?php echo $table->is_occupied ? 'ts_btn_open' : '' ?>" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>">
                                <?php echo $table->is_occupied ? lang('order_details') : lang('new_order'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
