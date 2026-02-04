<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;
    }

   public function get_lookup($table_name)
{
    $allowed_tables = [
        'master_upt',
        'master_pegawai',
        'master_negara',
        'master_pelabuhan',
        'master_kota_kab',
        'master_satuan',
        'master_jenis_media_pembawa'
    ];

    if (!in_array($table_name, $allowed_tables)) {
        return $this->output
            ->set_status_header(403)
            ->set_output(json_encode(['message' => 'Forbidden']));
    }

    
    if ($table_name === 'master_upt') {

        $user_upt = $this->input->get('upt');

        $this->db->select('MIN(id) as id, nama, kode_upt');
        $this->db->from('master_upt');
        $this->db->where_not_in('id', ['1000', '1001', '1002']);

        if ($user_upt !== '1000' && $user_upt && $user_upt !== 'Semua') {
            $prefix = substr($user_upt, 0, 2);
            $this->db->where("LEFT(id,2) =", $prefix);
        }

        $this->db->select('MIN(id) as id, nama, kode_upt');
        $this->db->group_by(['kode_upt', 'nama']);
        $this->db->order_by('nama', 'ASC');

    }

    elseif ($table_name === 'master_pegawai') {

        $this->db->select('id, nama');
        $this->db->from('master_pegawai');

        $selected_upt = $this->input->get('upt');
        if ($selected_upt && $selected_upt !== 'Semua' && $selected_upt !== '1000') {
            $prefix = substr($selected_upt, 0, 2);
            $this->db->where("LEFT(upt_id, 2) =", $prefix);
        }
    }
    else {
        $this->db->select('id, nama');
        $this->db->from($table_name);
        $this->db->order_by('nama', 'ASC');
    }

    $query = $this->db->get();
    $data  = $query->result_array();

    return $this->output
        ->set_content_type('application/json')
        ->set_output(json_encode($data));
}
}