<?php
/* Card grid for the running order screen.
   Rendered both by monitor/running_orders.php on first load and by
   Monitor::runningOrdersAjax() on each poll, so the markup lives in one place. */
?>
<?php if (empty($orders)): ?>
    <div class="ro_empty"><?php echo lang('no_data_found'); ?></div>
<?php else: ?>
    <?php foreach ($orders as $order):
        $sale_id = $order->sale_id;
        //Deliberately NOT Sale/POS/user/outlet/sale_id. That third segment feeds
        //tbl_sales.self_order_content into the POS and only carries a cart payload
        //for self/online orders; for an ordinary order it is empty and the POS
        //start up breaks.
        //Instead pass the order number as a query string. The POS reads it and
        //selects the matching card in its own running order panel, which is
        //rendered from that device's IndexedDB.
        $pos_link = site_url('Sale/POS/' . $this->session->userdata('user_id') . '/' . $this->session->userdata('outlet_id'))
                  . '?open_sale_no=' . urlencode($order->sale_no);
        //a sale can occupy several merged tables, so prefer the joined list of
        //booked table names, then the sale's own table, then nothing
        if (isset($order->tables_booked_text) && $order->tables_booked_text) {
            $table_text = $order->tables_booked_text;
        } elseif (isset($order->orders_table_text) && $order->orders_table_text) {
            $table_text = $order->orders_table_text;
        } elseif (isset($order->table_name) && $order->table_name) {
            $table_text = $order->table_name;
        } else {
            $table_text = lang('None');
        }
        ?>
        <div class="ro_card" data-sale_id="<?php echo escape_output($sale_id) ?>" data-sale_no="<?php echo escape_output($order->sale_no) ?>">
            <div class="ro_card_head">
                <span class="ro_order_no"><?php echo escape_output($order->sale_no) ?></span>
                <span class="ro_timer" data-minutes="<?php echo escape_output($order->minute_difference) ?>" data-seconds="<?php echo escape_output($order->second_difference) ?>">
                    <?php echo escape_output($order->minute_difference) ?>:<?php echo escape_output($order->second_difference) ?>
                </span>
            </div>
            <div class="ro_card_body">
                <div class="ro_row"><span class="ro_label"><?php echo lang('customer'); ?></span><span class="ro_value"><?php echo escape_output($order->customer_name ? $order->customer_name : '') ?></span></div>
                <div class="ro_row"><span class="ro_label"><?php echo lang('waiter'); ?></span><span class="ro_value"><?php echo escape_output($order->waiter_name ? $order->waiter_name : '') ?></span></div>
                <div class="ro_row"><span class="ro_label"><?php echo lang('table'); ?></span><span class="ro_value"><?php echo escape_output($table_text) ?></span></div>
                <div class="ro_row"><span class="ro_label"><?php echo lang('kitchen_status'); ?></span><span class="ro_value"><?php echo escape_output($order->total_kitchen_type_done_items) ?>/<?php echo escape_output($order->total_kitchen_type_items) ?></span></div>
            </div>
            <div class="ro_card_actions">
                <button type="button" class="btn ro_btn ro_btn_details" data-sale_id="<?php echo escape_output($sale_id) ?>"><?php echo lang('order_details'); ?></button>
                <a class="btn ro_btn ro_btn_pos" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('modify_order_'); ?></a>
                <a class="btn ro_btn ro_btn_pos" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('kot_tooltip'); ?></a>
                <a class="btn ro_btn ro_btn_pos" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('invoice'); ?></a>
                <a class="btn ro_btn ro_btn_pos" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('bill'); ?></a>
                <a class="btn ro_btn ro_btn_danger" href="<?php echo $pos_link ?>" title="<?php echo lang('open_in_pos'); ?>"><?php echo lang('cancel_order'); ?></a>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
