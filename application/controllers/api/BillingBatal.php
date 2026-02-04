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
    $filters = $this->_get_filters();
    $page = (int) ($this->input->get('page') ?: 1);
    $per_page = (int) ($this->input->get('per_page') ?: 10);

    try {
        $all_rows = $this->BillingBatal_model->fetch($filters);
        $total_data = count($all_rows);
        $offset = ($page - 1) * $per_page;
        $sliced_rows = array_slice($all_rows, $offset, $per_page);
        $total_page = ($total_data > 0) ? ceil($total_data / $per_page) : 1;
        return $this->jsonRes(200, [
            'success' => true,
            'data'    => $sliced_rows,
            'meta'    => [
                'total'      => $total_data,
                'page'       => $page,
                'per_page'   => $per_page,
                'total_page' => (int) $total_page
            ]
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
    $filters = [
        'karantina'  => $this->input->get('karantina', true),
        'start_date' => $this->input->get('start_date', true),
        'end_date'   => $this->input->get('end_date', true),
        'upt_id'     => $this->input->get('upt', true) ?: 'all',
    ];

    $this->applyScope($filters);
    $filters['upt'] = $filters['upt_id'] ?? 'all'; 
    
    return $filters;
}
}