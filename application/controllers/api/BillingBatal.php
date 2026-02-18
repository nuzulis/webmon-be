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
        $this->load->library('Excel_handler'); 
    }

    public function index()
    {
        $filters = $this->_get_filters();
        $page = (int) ($this->input->get('page') ?: 1);
        $per_page = (int) ($this->input->get('per_page') ?: 10);

        try {
            $all_rows = $this->BillingBatal_model->getIds($filters, 100000, 0);
            $total_data = count($all_rows);
            $offset = ($page - 1) * $per_page;
            $sliced_rows = array_slice($all_rows, $offset, $per_page);
            
            return $this->json([
                'success' => true,
                'data'    => $sliced_rows,
                'meta'    => [
                    'page'       => $page,
                    'per_page'   => $per_page,
                    'total'      => $total_data,
                    'total_page' => ($total_data > 0) ? ceil($total_data / $per_page) : 1
                ]
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
            'search'     => $this->input->get('search', true),
            'sort_by'    => $this->input->get('sort_by', true),
            'sort_order' => $this->input->get('sort_order', true),
        ];
        
        $this->applyScope($filters);
        return $filters;
    }
}