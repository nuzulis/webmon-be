<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property CI_Config       $config
 * @property Kwitansi_model $Kwitansi_model
 * @property Excel_handler    $excel_handler
 */
class Kwitansi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Kwitansi_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ================= JWT GUARD ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, 'Unauthorized');
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, 'Token tidak valid');
        }

        $filters = [
            'karantina'   => $this->input->get('karantina'),
            'permohonan'  => $this->input->get('permohonan'),
            'upt'         => $this->input->get('upt') ?: 'all', 
            'start_date'  => $this->input->get('start_date'),
            'end_date'    => $this->input->get('end_date'),
            'berdasarkan' => $this->input->get('berdasarkan'),
            'page'        => $this->input->get('page') ?: 1,
            'per_page'    => $this->input->get('per_page') ?: 10,
        ];
    $data = $this->Kwitansi_model->fetch($filters);

    $totalCount = (int) $this->Kwitansi_model->countAll($filters);
    $perPage    = (int) ($filters['per_page'] ?? 10);
    $currentPage = (int) ($filters['page'] ?? 1);

    return $this->json([
        'success' => true,
        'data'    => $data,
        'meta'    => [
            'total'      => $totalCount,
            'page'       => $currentPage,
            'per_page'   => $perPage,
            'total_page' => $totalCount > 0 ? ceil($totalCount / $perPage) : 0
        ]
    ], 200);
    }

    public function export_excel()
    {
       
        $filters = [
            'karantina'   => $this->input->get('karantina'),
            'permohonan'  => $this->input->get('permohonan'),
            'upt'         => $this->input->get('upt') ?: 'all',
            'start_date'  => $this->input->get('start_date'),
            'end_date'    => $this->input->get('end_date'),
            'berdasarkan' => $this->input->get('berdasarkan'),
        ];

       
        $data = $this->Kwitansi_model->getAll($filters);

    if (empty($data)) {
        die("Data tidak ditemukan untuk periode ini.");
    }

if (empty($data)) {
    return $this->jsonRes(200, [
        'debug_info' => 'Data kosong',
        'filters_sent' => $filters,
        'endpoint' => 'https://simponi.karantinaindonesia.go.id/epnbp/laporan/webmon'
    ]);
}
        if (empty($data)) {
            return $this->jsonRes(404, ['success' => false, 'message' => "Data Kwitansi tidak ditemukan"]);
    }

        $headers = [
            'Nomor', 'UPT', 'Satpel', 'Karantina', 
            'Nomor Kwitansi', 'Tanggal Kwitansi', 'Jenis Permohonan', 
            'Nama Wajib Bayar', 'Tipe Bayar', 'Total PNBP', 'Kode Billing', 
            'NTPN', 'NTB', 'Tanggal Billing', 'Tanggal Setor', 'Bank'
        ];

        $exportData = [];
        $no = 1;
        foreach ($data as $item) {
            $exportData[] = [
                $no++,
                $item['nama_upt'],
                $item['nama_satpel'],
                $item['jenis_karantina'],
                $item['nomor'],
                $item['tanggal'],
                $item['jenis_permohonan'],
                $item['nama_wajib_bayar'],
                $item['tipe_bayar'],
                $item['total_pnbp'],
                "'" . $item['kode_bill'],
                $item['ntpn'],
                $item['ntb'],
                $item['date_bill'],
                $item['date_setor'],
                $item['bank']
            ];
        }

        /* 5. Build Info Berdasarkan Filter */
        $labelBerdasarkan = match ($filters['berdasarkan']) {
            'D' => 'Tgl Dokumen',
            'K', 'Q' => 'Tgl Kuitansi',
            'B' => 'Tgl Bayar',
            default => 'Tgl Setor'
        };

        $title = "LAPORAN PNBP (BERDASARKAN " . strtoupper($labelBerdasarkan) . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $data);

        $this->logActivity("EXPORT EXCEL: KWITANSI PNBP PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("Kwitansi_PNBP", $headers, $exportData, $reportInfo);
    }

   
}
