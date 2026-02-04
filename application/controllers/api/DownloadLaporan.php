<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class DownloadLaporan extends CI_Controller {

    public function __construct() {
        parent::__construct();
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
    }

    public function download_laporan() {
        $input = json_decode($this->input->raw_input_stream, true);
        
        $kodeUpt    = $input['upt'] ?? '';
        $bulan      = $input['bulan'] ?? '';
        $karantina  = $input['karantina'] ?? '';
        $permohonan = $input['permohonan'] ?? '';
        $upt = $this->db->get_where('master_upt', ['kode_upt' => $kodeUpt])->row();

        if (!$upt) {
            echo json_encode(['status' => 'error', 'message' => 'UPT tidak ditemukan']);
            return;
        }

        $regionalNames = [1 => 'SUMATERA', 3 => 'JAWA', 5 => 'BALINUSRA', 6 => 'KALIMANTAN', 7 => 'SULAWESI', 9 => 'PAPUA'];
        $regId = $upt->regional;
        $regionalTag = $regionalNames[$regId] ?? 'UNKNOWN';
        $regionalFolder = ($regId == 5) ? 'BALI NUSRA' : $regionalTag;

        $uptFormatted = $this->_format_upt($upt->nama);
        $fileNamePattern = "{$bulan}_{$permohonan}_{$karantina}_{$regionalTag}_{$uptFormatted}.xls";
        
        $targetPath = FCPATH . "laporan/" . $regionalFolder . "/";
        $files = glob($targetPath . "*{$fileNamePattern}*");

        if ($files) {
            $url = base_url("laporan/" . rawurlencode($regionalFolder) . "/" . basename($files[0]));
            echo json_encode(['status' => 'success', 'fileUrl' => $url]);
        } else {
            echo json_encode(['status' => 'error', 'message' => "File $fileNamePattern tidak ditemukan"]);
        }
    }

    public function get_regional_list() {
        $kodeUpt = $this->input->get('kodeUpt');
        $this->db->select('kode_upt, nama');
        $this->db->where('regional', "(SELECT regional FROM master_upt WHERE kode_upt = '$kodeUpt')", FALSE);
        $data = $this->db->get('master_upt')->result();
        echo json_encode($data);
    }

    private function _format_upt($nama) {
        $prefix = (stripos($nama, 'Balai Besar') !== false) ? 'BBKHIT' : 'BKHIT';
        $junk = ['Balai Besar', 'Balai', 'Karantina', 'Hewan', 'Ikan', 'dan', 'Tumbuhan', ',', '  '];
        $clean = str_ireplace($junk, '', $nama);
        return $prefix . '_' . str_replace(' ', '_', trim($clean));
    }
}