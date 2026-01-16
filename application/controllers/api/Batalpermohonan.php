<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input                   $input
 * @property CI_Output                  $output
 * @property CI_Config                  $config
 * @property BatalPermohonan_model      $BatalPermohonan_model
 * @property Excel_handler              $excel_handler
 */
class BatalPermohonan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('BatalPermohonan_model');
        $this->load->library('excel_handler');
    }

    /**
     * API untuk List Data (React Table)
     */
    public function index()
    {
        // JWT divalidasi otomatis oleh MY_Controller::__construct()
        // Jika gagal, MY_Controller langsung memanggil deny()

        /* =============================
         * FILTER
         * ============================= */
        $filters = [
            'upt_id'     => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        // Terapkan scope wilayah (Pusat vs UPT)
        $this->applyScope($filters);

        /* =============================
         * PAGINATION
         * ============================= */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 50) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* =============================
         * DATA FETCHING
         * ============================= */
        $ids   = $this->BatalPermohonan_model->getIds($filters, $perPage, $offset);
        $rows  = $this->BatalPermohonan_model->getByIds($ids);
        $total = $this->BatalPermohonan_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage)
            ]
        ]);
    }

    public function export_excel()
{
    $filters = [
        'upt_id'     => $this->input->get('upt', TRUE),
        'karantina'  => $this->input->get('karantina', TRUE),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
    ];

    $this->applyScope($filters);
    $rows = $this->BatalPermohonan_model->getFullData($filters);

    // Persiapkan Informasi Header Laporan
    $reportInfo = [
        'judul'      => "PEMBATALAN PERMOHONAN " . strtoupper($filters['karantina']),
        'upt'        => ($filters['upt_id'] === 'all' || empty($filters['upt_id'])) ? 'SEMUA UPT' : ($rows[0]['upt'] ?? 'UPT TERPILIH'),
        'periode'    => "PERIODE " . ($filters['start_date'] ?? '-') . " S/D " . ($filters['end_date'] ?? '-'),
        'pencetak'   => "Waktu Cetak: " . date('Y-m-d H:i:s') . " | Oleh: " . ($this->user['nama'] ?? 'Admin')
    ];
    $this->logActivity("EXPORT EXCEL: Batal Permohonan Periode " . $filters['start_date'] . " s/d " . $filters['end_date']);

    $exportData = [];
    foreach ($rows as $index => $r) {
        $exportData[] = [
            $index + 1,
            isset($r['tssm_id']) ? 'SSM' : 'PTK',
            $r['no_dok_permohonan'],
            $r['tgl_dok_permohonan'],
            ($r['upt'] ?? '') . " - " . ($r['nama_satpel'] ?? ''),
            $r['nama_pengirim'],
            $r['pembatal'],
            $r['alasan_batal'],
            $r['tgl_batal']
        ];
    }

    $headers = ['No', 'Via', 'No Dokumen', 'Tgl Dokumen', 'Satpel', 'Pengirim', 'Petugas Batal', 'Alasan', 'Waktu Batal'];

    $this->load->library('excel_handler');
    $reportInfo = $this->buildReportHeader("LAPORAN PEMBATALAN PERMOHONAN", $filters, $rows);
    $this->excel_handler->download("Batal_Permohonan", $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}