<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input              $input
 * @property PelepasanBlmByr_model $PelepasanBlmByr_model
 * @property Excel_handler         $excel_handler
 */
class PelepasanBlmByr extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PelepasanBlmByr_model');
        $this->load->library('excel_handler');
    }

    public function index()
{
    $filters = [
        'upt'        => $this->input->post('upt', TRUE),
        'karantina'  => $this->input->post('karantina', TRUE) ?: 'T',
        'start_date' => $this->input->post('start_date', TRUE),
        'end_date'   => $this->input->post('end_date', TRUE),
        'search'     => $this->input->post('search', TRUE),
        'sort_by'    => $this->input->post('sort_by', TRUE),
        'sort_order' => $this->input->post('sort_order', TRUE),
    ];

    $page    = max((int) $this->input->post('page'), 1);
    $perPage = (int) $this->input->post('per_page') ?: 10;
    $offset  = ($page - 1) * $perPage;

    $ids   = $this->PelepasanBlmByr_model->getIds($filters, $perPage, $offset);
    $data  = $this->PelepasanBlmByr_model->getByIds($ids);
    $total = $this->PelepasanBlmByr_model->countAll($filters);

    return $this->json([
        'success' => true,
        'data'    => $data,
        'meta'    => [
            'page'       => $page,
            'per_page'   => $perPage,
            'total'      => $total,
            'total_page' => $total > 0 ? (int) ceil($total / $perPage) : 0,
        ]
    ], 200);
}

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->post('upt', TRUE),
            'karantina'  => $this->input->post('karantina', TRUE) ?: 'T',
            'start_date' => $this->input->post('start_date', TRUE),
            'end_date'   => $this->input->post('end_date', TRUE),
        ];

        $rows = $this->PelepasanBlmByr_model->getFullData($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'No. Aju', 'No. Sertifikat', 'Tgl Sertifikat', 'No. Seri',
            'UPT', 'Pemohon', 'Pengirim', 'Penerima',
            'No. Kuitansi', 'Tgl Kuitansi', 'Total PNBP',
            'Kode Billing', 'Status Pembayaran'
        ];

        $exportData = [];
        $no = 1;

        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['no_aju'],
                $r['dok'],
                $r['tgl_dok'],
                $r['nomor_seri'],
                $r['upt_id'],
                $r['nama_pemohon'],
                $r['nama_pengirim'],
                $r['nama_penerima'],
                $r['no_kuitansi'] ?: '-',
                $r['tgl_kuitansi'] ?: '-',
                (float) $r['total_pnbp'],
                $r['kode_bill'] ?: '-',
                'BELUM BAYAR'
            ];
        }

        $title = "LAPORAN PELEPASAN BELUM BAYAR PNBP (" . strtoupper($filters['karantina']) . ")";

        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        return $this->excel_handler->download(
            "Pelepasan_Belum_Bayar_" . date('Ymd_His'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}