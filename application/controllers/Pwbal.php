<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/* SCRATCH-ONLY probe. Returns each item's closing balance for one outlet using
   the PRODUCT'S OWN Inventory_model + stockClosingBalance(), so the verification
   suite never re-implements the eleven-term ledger formula - re-implementing it
   would only prove the test agrees with itself.
   Refuses to respond outside the testing environment. */
class Pwbal extends CI_Controller {
    public function balances(){
        if (ENVIRONMENT !== 'testing') { show_404(); }
        $outlet_id = (int) $this->input->get('outlet_id');
        $this->load->model('Inventory_model');
        $rows = $this->Inventory_model->getInventory('', '', '', array($outlet_id));
        $out = array();
        if ($rows) {
            foreach ($rows as $row) {
                if (isset($row->id)) { $out[(int)$row->id] = (float) stockClosingBalance($row); }
            }
        }
        echo json_encode($out);
    }
}
