<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input             $input
 * @property CI_Session           $session
 * @property KwitansiBatal_model  $KwitansiBatal_model
 * @property Excel_handler        $excel_handler
 */
class KwitansiBatal extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBatal_model');
        $this->load->library('session');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'karantina'   => $this->input->get('karantina', TRUE),
            'permohonan'  => $this->input->get('permohonan', TRUE),
            'upt'         => $this->input->get('upt', TRUE) ?: 'all',
            'start_date'  => $this->input->get('start_date', TRUE),
            'end_date'    => $this->input->get('end_date', TRUE),
            'berdasarkan' => $this->input->get('berdasarkan', TRUE),
            'search'      => $this->input->get('search', TRUE),
            'sort_by'     => $this->input->get('sort_by', TRUE),
            'sort_order'  => $this->input->get('sort_order', TRUE),
        ];

        $this->applyScope($filters);

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;

        try {
            $ids   = $this->KwitansiBatal_model->getIds($filters, $perPage, $offset);
            $data  = $this->KwitansiBatal_model->getByIds($ids);
            $total = $this->KwitansiBatal_model->countAll($filters);

            return $this->json([
                'success' => true,
                'data'    => $data,
                'meta'    => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'total_page' => (int) ceil($total / $perPage)
                ]
            ], 200);
        } catch (Exception $e) {
            log_message('error', 'KwitansiBatal Error: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function export_excel()
    {
        $filters = [
            'karantina'   => $this->input->get('karantina', TRUE),
            'permohonan'  => $this->input->get('permohonan', TRUE),
            'upt'         => $this->input->get('upt', TRUE) ?: 'all',
            'start_date'  => $this->input->get('start_date', TRUE),
            'end_date'    => $this->input->get('end_date', TRUE),
            'berdasarkan' => $this->input->get('berdasarkan', TRUE),
            'search'      => $this->input->get('search', TRUE),
        ];
        $rows = $this->KwitansiBatal_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'UPT', 'Satpel', 'Karantina', 'Nomor Kuitansi', 
            'Tanggal', 'Permohonan', 'Wajib Bayar', 'Total PNBP', 
            'Kode Billing', 'NTPN', 'Alasan Batal', 'Tanggal Batal'
        ];

        $exportData = [];
        $no = 1;
        
        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['upt'],
                $r['satpel'],
                $r['jenis_karantina'],
                $r['nomor'],
                $r['tanggal'],
                $r['jenis_permohonan'],
                $r['wajib_bayar'],
                $r['total_pnbp'],
                "'" . $r['kode_bill'],
                $r['ntpn'],
                $r['alasan_hapus'],
                $r['deleted_at']
            ];
        }

        $title = "LAPORAN KUITANSI BATAL";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
        $reportInfo['sub_judul'] = "Biro Umum dan Keuangan";
        
        $this->logActivity("EXPORT EXCEL: Kuitansi Batal Periode " . $filters['start_date']);
        
        return $this->excel_handler->download("Kuitansi_Batal", $headers, $exportData, $reportInfo);
    }
}