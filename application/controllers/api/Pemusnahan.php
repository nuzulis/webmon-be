<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Pemusnahan_model  $Pemusnahan_model
 * @property Excel_handler      $excel_handler
 */
class Pemusnahan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pemusnahan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
{
    $auth = $this->input->get_request_header('Authorization', TRUE);
    if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth)) {
        return $this->json(null, 401);
    }
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
        'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
        'search'     => $this->input->get('search', true),
    ];

    if (!empty($filters['karantina']) && !in_array($filters['karantina'], ['H','I','T'], true)) {
        return $this->json(null, 400);
    }

    $page    = max((int)$this->input->get('page'), 1);
    $perPage = 10;
    $offset  = ($page - 1) * $perPage;
    $rows  = $this->Pemusnahan_model->getList($filters, $perPage, $offset);
    $total = $this->Pemusnahan_model->countAll($filters);

    return $this->json([
        'success' => true,
        'data'    => $rows,
        'meta'    => [
            'page'       => $page,
            'per_page'   => $perPage,
            'total'      => $total,
            'total_page' => (int) ceil($total / $perPage),
        ]
    ], 200);
}


    public function export_excel()
{
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
        'search'     => $this->input->get('search', true),
    ];

    $rows = $this->Pemusnahan_model->getExportByFilter($filters);

    if (empty($rows)) {
        return $this->json(['success' => false, 'message' => 'Data kosong'], 404);
    }
        $headers = [
            'Pengajuan via', 'Nomor Dokumen', 'Tgl Dokumen', 'Satpel', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Alasan Pemusnahan', 'Tempat Pemusnahan', 'Metode Pemusnahan',
            'Petugas', 'Komoditas', 'Nama Tercetak', 'Kode HS', 'Volume P0', 'Volume P7', 'Satuan'
        ];


        $exportData = [];
        $lastId = null;
        $no = 1; 

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            
            $exportData[] = [
                $isIdem ? '' : $no++, 
                $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'),
                $isIdem ? '' : $r['nomor'],
                $isIdem ? '' : $r['tgl_p7'],
                $isIdem ? '' : $r['nama_upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['negara_asal'] . ' - ' . $r['kota_kab_asal'],
                $isIdem ? '' : $r['negara_tujuan'] . ' - ' . $r['kota_kab_tujuan'],
                $isIdem ? '' : $r['alasan_string'],
                $isIdem ? '' : $r['tempat'],
                $isIdem ? '' : $r['metode'],
                $isIdem ? '' : $r['petugas'],
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                $r['volume'],
                $r['p7'],
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

         $title = "LAPORAN PEMUSNAHAN " . $filters['karantina'];
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
    return $this->excel_handler->download("Laporan_Pemusnahan", $headers, $exportData, $reportInfo);
    }
   
}
