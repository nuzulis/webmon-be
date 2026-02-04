<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input                $input
 * @property CI_Output               $output
 * @property CI_Config               $config
 * @property PenggunaJasa_model      $PenggunaJasa_model
 * @property Excel_handler              $excel_handler
 */ 
class PenggunaJasa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PenggunaJasa_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
{
    $page = $this->input->get('page') ?: 1;
    $per_page = $this->input->get('per_page') ?: 10;
    $upt = $this->input->get('upt');
    $permohonan = $this->input->get('permohonan');

    $offset = ($page - 1) * $per_page;
    $data = $this->PenggunaJasa_model->get_list_data($per_page, $offset, $upt, $permohonan);
    $total = $this->PenggunaJasa_model->get_total_count($upt, $permohonan);
    $total_page = ceil($total / $per_page);

    return $this->jsonRes(200, [
        'success' => true,
        'data'    => $data,
        'meta'    => [
            'page'       => (int)$page,
            'per_page'   => (int)$per_page,
            'total'      => (int)$total,
            'total_page' => (int)$total_page
        ]
    ]);
}
    
    public function detail()
{
    $input = json_decode(file_get_contents("php://input"), true);
    $id = $input['id'] ?? null;

    if (!$id) return $this->jsonRes(400, ['success' => false, 'message' => 'ID tidak valid']);

    
    
    $profil = $this->PenggunaJasa_model->get_profil_lengkap($id);

$history = [];
if ($profil) {
    $history = $this->PenggunaJasa_model->get_history_ptk($profil['id']);
}

    return $this->jsonRes(200, [
        'success' => true,
        'data' => [
            'profil'  => $profil,
            'history' => $history
        ]
    ]);
}

    public function export_csv()
{
    set_time_limit(0);
    ini_set('memory_limit', '256M');

    $upt = $this->input->get('upt', true);
    $permohonan = $this->input->get('permohonan', true);

    $filters = [
        'upt'        => ($upt === 'all' || empty($upt)) ? null : $upt,
        'permohonan' => ($permohonan === 'all' || empty($permohonan)) ? null : $permohonan,
    ];
    $rows = $this->PenggunaJasa_model->getList($filters, true);

    if (empty($rows)) {
        die("Data tidak ditemukan.");
    }
    $filename = "Laporan_PenggunaJasa_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    $output = fopen('php://output', 'w');
    fputcsv($output, [
        'No', 'Nama Pemohon', 'Jenis Perusahaan', 'Nama Perusahaan', 
        'Identitas', 'Nomor Identitas', 'NITKU', 'UPT Registrasi', 
        'Lingkup Aktivitas', 'Rerata Frekuensi', 'Daftar Komoditas', 
        'Tempat Karantina', 'Status Kepemilikan', 'Email', 
        'Nomor Registrasi', 'Tanggal Registrasi', 'Status Blokir'
    ]);
    $no = 1;
    foreach ($rows as $r) {
        $lingkupArr = json_decode($r['lingkup_aktifitas'], true) ?: [];
        $lingkupTxt = implode("; ", array_column($lingkupArr, 'activity'));
        $komoditasArr = json_decode($r['daftar_komoditas'], true) ?: [];
        $komoditasTxt = implode("; ", array_filter($komoditasArr, function($v) { 
            return !empty($v); 
        }));

        fputcsv($output, [
            $no++,
            $r['pemohon'],
            $r['jenis_perusahaan'],
            $r['nama_perusahaan'],
            $r['jenis_identitas'],
            $r['nomor_identitas'],
            $r['nitku'],
            $r['upt'],
            $lingkupTxt ?: '-',
            $r['rerata_frekuensi'],
            $komoditasTxt ?: '-',
            ($r['tempat_karantina'] == 1 ? 'Internal' : 'Luar'),
            $r['status_kepemilikan'],
            $r['email'],
            $r['nomor_registrasi'],
            $r['tgl_registrasi'],
            ($r['blockir'] == 1 ? 'Terblokir' : 'Aktif')
        ]);
        flush(); 
    }

    fclose($output);
    exit;
}

}