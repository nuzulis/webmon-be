<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input                  $input
 * @property CI_Session                $session
 * @property KwitansiBelumBayar_model  $KwitansiBelumBayar_model
 * @property Excel_handler             $excel_handler
 */
class KwitansiBelumBayar extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBelumBayar_model');
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
            'berdasarkan' => $this->input->get('berdasarkan', TRUE) ?: '',
            'search'      => $this->input->get('search', TRUE),
            'sort_by'     => $this->input->get('sort_by', TRUE),
            'sort_order'  => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->KwitansiBelumBayar_model->getIds($filters, $perPage, $offset);
        $data  = $this->KwitansiBelumBayar_model->getByIds($ids);
        $total = $this->KwitansiBelumBayar_model->countAll($filters);

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
    }

    public function export_excel()
    {
        $filters = [
            'karantina'   => $this->input->get('karantina', TRUE),
            'permohonan'  => $this->input->get('permohonan', TRUE),
            'upt'         => $this->input->get('upt', TRUE) ?: 'all',
            'start_date'  => $this->input->get('start_date', TRUE),
            'end_date'    => $this->input->get('end_date', TRUE),
            'berdasarkan' => $this->input->get('berdasarkan', TRUE) ?: 'tanggal_aju',
            'search'      => $this->input->get('search', TRUE),
        ];
        $data = $this->KwitansiBelumBayar_model->getFullData($filters);

        if (empty($data)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'UPT', 'Satpel', 'Karantina', 
            'Nomor Kuitansi', 'Tanggal Kuitansi', 'No Aju', 'Jenis Permohonan', 
            'Wajib Bayar', 'Total PNBP', 'Kode Billing', 'Expired Billing', 'Tipe Bayar'
        ];

        $exportData = [];
        $no = 1;
        
        foreach ($data as $item) {
            $satpelPospel = trim(($item['nama_satpel'] ?? '') . ' - ' . ($item['nama_pospel'] ?? ''));

            $exportData[] = [
                $no++,
                $item['nama_upt'] ?? '',
                $satpelPospel,
                $item['jenis_karantina'] ?? '',
                $item['nomor'] ?? '',
                $item['tanggal'] ?? '',
                $item['no_aju'] ?? '',
                $item['jenis_permohonan'] ?? '',
                $item['nama_wajib_bayar'] ?? '',
                $item['total_pnbp'] ?? 0,
                "'" . ($item['kode_bill'] ?? ''), 
                $item['expired_date'] ?? '',
                $item['tipe_bayar'] ?? ''
            ];
        }

        $title = "LAPORAN PNBP BELUM BAYAR (UNPAID)";
        $reportInfo = $this->buildReportHeader($title, $filters, $data);

        $this->logActivity("EXPORT EXCEL: KWITANSI BELUM BAYAR PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("PNBP_Belum_Bayar", $headers, $exportData, $reportInfo);
    }
}