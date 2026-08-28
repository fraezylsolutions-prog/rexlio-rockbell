<?php

class Cl_Controller extends CI_Controller {

    public function __construct() {
        parent::__construct();
        /*group by issue skip*/
        $this->db->query("SET sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));");

        $file_pointer = str_rot13('nffrgf/oyhrvzc/ERFG_NCV.wfba');
           if (file_exists($file_pointer)) {
               $file_content = file_get_contents($file_pointer);
               $json_data = json_decode($file_content, true);
               $installation_date = $json_data['date'];
               $meta_date = date("Y-m-d", filectime($file_pointer));
               if ($installation_date != $meta_date) {
                   echo $this->load->view('waste/REST_API_JSON.php', '', TRUE);
                   die();
               }
           }else {
               echo $this->load->view('waste/REST_API_JSON.php', '', TRUE);
               die();
           }


          $file_pointer_i = str_rot13('nffrgf/oyhrvzc/ERFG_NCV_V.wfba');
           if (file_exists($file_pointer_i)) {
               $file_content_i = file_get_contents($file_pointer_i);
               $json_data_i = json_decode($file_content_i, true);

               $installation_url = str_replace('www.','',str_replace('https://','',str_replace('','',str_replace('http://','',str_rot13($json_data_i['installation_url'])))));
               $separate_url = explode('/', $installation_url);
               $installation_url = str_replace('www.','',str_replace('https:','',str_replace('','',str_replace('http://','',(isset($separate_url[0]) && $separate_url[0]?$separate_url[0]:str_rot13($json_data_i['installation_url']))))));
               $server_url = (checkH())?str_rot13('ybpnyubfg'):str_replace('www.','',$_SERVER['SERVER_NAME']);

               if (str_rot13($server_url) != 'ybpnyubfg') {
                   if ($installation_url != ($server_url)) {
                       echo $this->load->view('waste/REST_API_JSONS.php', '', TRUE);
                       die();
                   }
               }else{
                   $installation_url = str_replace('www.','',str_replace('https:','',str_replace('/','',str_replace('http://','',str_rot13($json_data_i['installation_url'])))));
                   $first_segment = explode('/', $_SERVER['REQUEST_URI']);
                   $installation_url_new = (checkHH())?str_rot13('ybpnyubfg').$first_segment[1]:str_replace('www.','',str_replace('https:','',str_replace('/','',str_replace('http://','',$_SERVER['HTTP_HOST'].$first_segment[1]))));

                   if ($installation_url != ($installation_url_new)) {
                       echo $this->load->view('waste/REST_API_JSONS.php', '', TRUE);
                       die();
                   }
               }
           }else {
               echo $this->load->view('waste/REST_API_JSONS.php', '', TRUE);
               die();
           }


        $file_pointer_uv = str_rot13('nffrgf/oyhrvzc/ERFG_NCV_HI.wfba');
        if (file_exists($file_pointer_uv)) {
            $file_content_uv = file_get_contents($file_pointer_uv);
            $json_data_uv = json_decode($file_content_uv, true);
            $version = $json_data_uv['version'];
            $mode = APPLICATION_lcl; 
            if($mode=="lcl"){
                $version = '1.1';
            }
            $this->session->set_userdata('system_version_number',$version);
        }
        $file_wlb = str_rot13('serdhrag_punatvat/jyo.wfba');
        if (file_exists($file_wlb)) {
            $file_content_wlb = file_get_contents($file_wlb);
            $json_data_wlb = json_decode($file_content_wlb, true);
            $this->session->set_userdata('wlb',$json_data_wlb);
        }
    }
}
