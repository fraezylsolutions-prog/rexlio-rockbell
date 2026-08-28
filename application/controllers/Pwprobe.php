<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/* SCRATCH-ONLY probe used by the verification harness. Refuses to respond
   outside the testing environment: it reports the bound database and session,
   which is not something an unauthenticated caller should be able to read on a
   production install. */
class Pwprobe extends CI_Controller {
    public function which(){
        if (ENVIRONMENT !== 'testing') { show_404(); }
        echo json_encode(array('environment'=>ENVIRONMENT,'database'=>$this->db->database,
            'user_id'=>$this->session->userdata('user_id'),'role'=>$this->session->userdata('role'),
            'outlet_id'=>$this->session->userdata('outlet_id')));
    }
}
