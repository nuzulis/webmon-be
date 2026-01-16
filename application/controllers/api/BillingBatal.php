<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property BillingBatal_model $BillingBatal_model
 * @property Excel_handler $excel_handler
 */
class BillingBatal extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('BillingBatal_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        // validateJwt() sudah dipanggil otomatis di MY_Controller __construct 
        // untuk controller yang tidak ada di whitelist

        $filters = $this->_get_filters();

        try {
            $rows = $this->BillingBatal_model->fetch($filters);
            
            return $this->jsonRes(200, [
                'success' => true,
                'data'    => $rows, // Model sudah menormalisasi data
                'meta'    => ['total' => count($rows)]
            ]);
        } catch (Exception $e) {
            return $this->jsonRes(500, ['success' => false, 'message' => $e->getMessage()]);
        }
    }

    
    
    public function export_excel()
{
    $filters = $this->_get_filters();
    $rows = $this->BillingBatal_model->fetch($filters);

    if (empty($rows)) {
        return $this->jsonRes(404, ['success' => false, 'message' => 'Data tidak ditemukan']);
    }

    // Header sesuai tabel native
    $headers = [
        'No', 'UPT', 'Karantina', 'Total PNBP', 'Kode Billing', 
        'Status Bill', 'NTPN', 'NTB', 'Dibuat Tanggal', 
        'Alasan Batal', 'Tanggal Batal'
    ];

    $exportData = [];
    $no = 1;
    foreach ($rows as $r) {
        $exportData[] = [
            $no++,
            $r['nama_upt'],
            $r['karantina'],
            $r['total_bill'],
            $r['kode_bill'],
            $r['status_bill'],
            $r['ntpn'],
            $r['ntb'],
            $r['created_at'],
            $r['alasan_hapus'],
            $r['deleted_at']
        ];
    }

    $reportInfo = $this->buildReportHeader("LAPORAN BILLING BATAL", $filters, $rows);
    $reportInfo['sub_judul'] = "Biro Umum dan Keuangan";
    $this->logActivity("EXPORT EXCEL: Billing Batal Periode " . $filters['start_date'] . " s/d " . $filters['end_date']);

    $this->excel_handler->download("Bill_Batal", $headers, $exportData, $reportInfo);
}

    private function _get_filters()
{
    // Mengambil input dari GET request
    $filters = [
        'karantina'  => $this->input->get('karantina', true),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
        'upt_id'     => $this->input->get('upt', true) ?: 'all', // Pastikan key 'upt_id' dibuat di sini
    ];

    // applyScope akan memvalidasi upt_id berdasarkan login user (Admin Pusat vs UPT)
    $this->applyScope($filters);
    
    // Sinkronisasi untuk model BillingBatal yang menggunakan key 'upt'
    $filters['upt'] = $filters['upt_id'] ?? 'all'; 
    
    return $filters;
}
}