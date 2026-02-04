<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property Penugasan_model $Penugasan_model
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property CI_Config       $config
 * @property Excel_handler    $excel_handler
 */
class Penugasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penugasan_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ================= JWT GUARD ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }

        $page = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? $perPage : 10;

    /* ===== FILTER ===== */
    $filters = [
        'upt'        => $this->input->get('upt', true),
        'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
        'petugas'    => $this->input->get('petugas', true),
        'search'     => $this->input->get('search', true),
    ];

    /* ===== DATA ===== */
    $result = $this->Penugasan_model->getPaginated($filters, $page, $perPage);

        return $this->json([
        'success' => true,
        'data'    => $result['data'],
        'meta'    => [
            'page'       => $page,
            'per_page'   => $perPage,
            'total'      => $result['total'],
            'total_page' => ceil($result['total'] / $perPage),
        ]
    ], 200);}

    public function export_excel()
{
    $filters = [
        'upt'        => $this->input->get('upt', true),
        'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
        'petugas'    => $this->input->get('petugas', true),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
    ];

    $rows = $this->Penugasan_model->getForExport($filters);

    $headers = [
        'No',
        'Nomor Surtug', 'Tgl Surtug',
        'No Permohonan', 'Tgl Permohonan',
        'UPT', 'Satpel',
        'Nama Petugas', 'NIP Petugas',
        'Jenis Tugas',
        'Negara Asal', 'Daerah Asal',
        'Negara Tujuan', 'Daerah Tujuan',
        'Komoditas', 'Nama Tercetak', 'HS Code',
        'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4',
        'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8',
        'Satuan'
    ];

    $exportData = [];
    $no = 1;
    $lastSurtug = null;

    foreach ($rows as $r) {
        $isIdem = ($r['nomor_surtug'] === $lastSurtug);

        $exportData[] = [
            $isIdem ? '' : $no++,
            $isIdem ? 'Idem' : $r['nomor_surtug'],
            $isIdem ? '' : $r['tgl_surtug'],
            $isIdem ? '' : $r['no_dok_permohonan'],
            $isIdem ? '' : $r['tgl_dok_permohonan'],
            $isIdem ? '' : $r['upt'],
            $isIdem ? '' : $r['satpel'],
            $r['nama_petugas'],
            $r['nip_petugas'],
            $r['jenis_tugas'],
            $r['negara_asal'], $r['daerah_asal'],
            $r['negara_tujuan'], $r['daerah_tujuan'],
            $r['nama_komoditas'], 
            $r['nama_umum_tercetak'], 
            $r['kode_hs'],
            $r['volumeP1'], $r['volumeP2'], $r['volumeP3'], $r['volumeP4'],
            $r['volumeP5'], $r['volumeP6'], $r['volumeP7'], $r['volumeP8'],
            $r['nama_satuan']
        ];

        $lastSurtug = $r['nomor_surtug'];
    }

    $title = "LAPORAN PENUGASAN PETUGAS KARANTINA";
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download(
        "Laporan_Penugasan_" . date('Ymd'),
        $headers,
        $exportData,
        $reportInfo
    );
}
}