<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Master extends CI_Controller {

    public function __construct() {
        parent::__construct();
        // CORS Headers
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') exit;
    }
public function get_lookup($table_name) {
    $allowed_tables = [
        'master_negara', 'master_pelabuhan', 'master_kota_kab', 
        'master_satuan', 'master_jenis_media_pembawa', 
        'master_moda_alat_angkut', 'master_dokumen_karantina',
        'master_pegawai', 'master_jenis_dokumen', 'master_rekomendasi',
        'master_stuff_kontainer', 'master_tipe_kontainer', 'master_ukuran_kontainer'
    ];

    if (!in_array($table_name, $allowed_tables)) {
        return $this->output->set_status_header(403)->set_output(json_encode(['message' => 'Forbidden']));
    }

    $this->db->select('id');
    
    // Penyesuaian nama kolom secara otomatis
    if ($table_name == 'master_ukuran_kontainer') {
        $this->db->select('ukuran as nama'); // Ambil kolom 'ukuran' sebagai 'nama'
    } else if ($table_name == 'master_tipe_kontainer') {
        $this->db->select('tipe as nama');   // Ambil kolom 'tipe' sebagai 'nama'
    } else if ($table_name == 'master_pegawai') {
        $this->db->select('nama_pegawai as nama');
    } else {
        $this->db->select('nama');
    }

    $data = $this->db->get($table_name)->result_array();
    return $this->output->set_content_type('application/json')->set_output(json_encode($data));
}
}