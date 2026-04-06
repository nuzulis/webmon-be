<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
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
        $this->load->library('Excel_handler'); 
    }

    public function index()
    {
        $filters = $this->_get_filters();

        try {
            $data = $this->BillingBatal_model->getAll($filters);
            return $this->json([
                'success' => true,
                'data'    => $data,
            ], 200);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function export_excel()
    {
        $filters = $this->_get_filters();
        $rows = $this->BillingBatal_model->getFullData($filters);

        if (empty($rows)) {
            return $this->output
                ->set_status_header(404)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Data tidak ditemukan']));
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
        if (!isset($this->excel_handler)) {
            $this->load->library('Excel_handler');
        }

        $this->load->library('excel_handler');
        
        $reportInfo = [
            'judul' => "LAPORAN BILLING BATAL",
            'sub_judul' => "Biro Umum dan Keuangan",
            'filter_info' => "Periode: " . ($filters['start_date'] ?? '-') . " s/d " . ($filters['end_date'] ?? '-')
        ];
        
        $this->logActivity("EXPORT EXCEL: Billing Batal");
        $this->excel_handler->download("Bill_Batal", $headers, $exportData, $reportInfo);
    }

    private function _get_filters()
    {
        $filters = [
            'upt_id'     => $this->input->get('upt', true),
            'karantina'  => $this->input->get('karantina', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        $this->applyScope($filters);
        return $filters;
    }
}