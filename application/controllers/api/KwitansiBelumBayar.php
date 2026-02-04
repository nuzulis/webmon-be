<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input             $input
 * @property CI_Output            $output
 * @property KwitansiBelumBayar_model $KwitansiBelumBayar_model
 * @property Excel_handler        $excel_handler
 */
class KwitansiBelumBayar extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBelumBayar_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
    'karantina'   => $this->input->get('karantina'),
    'permohonan'  => $this->input->get('permohonan'),
    'upt'         => $this->input->get('upt') ?: 'all',
    'start_date'  => $this->input->get('start_date'),
    'end_date'    => $this->input->get('end_date'),
    'berdasarkan' => $this->input->get('berdasarkan') ?: '',
    'page'        => $this->input->get('page') ?: 1,
    'per_page'    => $this->input->get('per_page') ?: 10,
];

        $data = $this->KwitansiBelumBayar_model->fetch($filters);
        $totalCount = $this->KwitansiBelumBayar_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total'      => (int)$totalCount,
                'page'       => (int)$filters['page'],
                'per_page'   => (int)$filters['per_page'],
                'total_page' => $totalCount > 0 ? ceil($totalCount / $filters['per_page']) : 0
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
            'berdasarkan' => $this->input->get('berdasarkan') ?: 'tanggal_aju',
        ];
        $data = $this->KwitansiBelumBayar_model->countAll($filters) > 0 
                ? $this->KwitansiBelumBayar_model->fetch(array_merge($filters, ['per_page' => 99999, 'page' => 1]))
                : [];

        if (empty($data)) {
            die("Data tidak ditemukan untuk periode ini.");
        }
        $headers = [
            'No', 'UPT', 'Satpel', 'Karantina', 
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
                $item['jenis_kegiatan'] ?? '',
                $item['nomor'] ?? '',
                $item['tanggal'] ?? '',
                $item['no_aju'] ?? '',
                $item['jenis_permohonan'] ?? '',
                $item['nama_wajib_bayar'] ?? '',
                $item['total_pnbp'] ?? 0,
                "'" . ($item['kode_bill'] ?? ''), 
                $item['tgl_exp_billing'] ?? '',
                $item['tipe_bayar'] ?? ''
            ];
        }

        $title = "LAPORAN PNBP BELUM BAYAR (UNPAID)";
        $reportInfo = $this->buildReportHeader($title, $filters, $data);

        $this->logActivity("EXPORT EXCEL: KWITANSI BELUM BAYAR PERIODE {$filters['start_date']}");

        return $this->excel_handler->download("PNBP_Belum_Bayar", $headers, $exportData, $reportInfo);
    }
}