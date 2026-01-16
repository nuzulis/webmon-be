<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Perlakuan_model   $Perlakuan_model
 * @property Excel_handler     $excel_handler
 */
class Perlakuan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Perlakuan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* =============================
         * JWT GUARD
         * ============================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false]);
        }
        

        /* =============================
         * FILTER
         * ============================= */
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
        ];

        /* =============================
         * PAGINATION
         * ============================= */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* =============================
         * STEP 1 — AMBIL ID
         * ============================= */
        $ids = $this->Perlakuan_model
            ->getIds($filters, $perPage, $offset);

        /* =============================
         * STEP 2 — DATA
         * ============================= */
        $rows = [];
        if ($ids) {
            $rows = $this->Perlakuan_model
                ->getByIds($ids, $filters['karantina']);
        }

        /* =============================
         * TOTAL
         * ============================= */
        $total = $this->Perlakuan_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage)
            ]
        ]);
    }

    public function export_excel()
{
    /* =============================
     * 1. PREPARE FILTERS & DATA
     * ============================= */
    $filters = [
        'upt'        => $this->input->get('upt'),
        'karantina'  => strtoupper($this->input->get('karantina')),
        'permohonan' => strtoupper($this->input->get('permohonan')),
        'start_date' => $this->input->get('start_date'),
        'end_date'   => $this->input->get('end_date'),
    ];

    $ids = $this->Perlakuan_model->getIds($filters, 5000, 0);
    
    // Pastikan model mengembalikan data flat (satu baris per komoditas) 
    // agar logika IDEM bisa berjalan.
    $rows = [];
    if ($ids) {
        $rows = $this->Perlakuan_model->getByIds($ids, $filters['karantina']);
    }

    /* =============================
     * 2. DEFINE HEADERS
     * ============================= */
    $headers = [
        'No', 'No. P4', 'Tgl P4', 'Satpel', 
        'Tempat Perlakuan', 'Pengirim', 'Penerima', 
        'Komoditas', 'Volume', 'Satuan',
        'Alasan', 'Tipe Perlakuan', 'Mulai', 'Selesai', 
        'Rekomendasi', 'Operator'
    ];

    $exportData = [];
    $no = 1;
    $lastId = null;

    foreach ($rows as $r) {
        $isIdem = ($r['id'] === $lastId);

        $exportData[] = [
            $isIdem ? '' : $no++,
            $isIdem ? 'Idem' : $r['no_p4'],
            $isIdem ? '' : $r['tgl_p4'],
            $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
            $isIdem ? '' : $r['lokasi_perlakuan'],
            $isIdem ? '' : $r['nama_pengirim'],
            $isIdem ? '' : $r['nama_penerima'],
            // Data Komoditas (Selalu Muncul per baris)
            $r['komoditas'],
            $r['volume'],
            $r['satuan'],
            // Data Teknis Perlakuan (Biasanya sama per dokumen, beri IDEM)
            $isIdem ? 'Idem' : $r['alasan_perlakuan'],
            $isIdem ? '' : $r['tipe'],
            $isIdem ? '' : $r['mulai'],
            $isIdem ? '' : $r['selesai'],
            $isIdem ? '' : $r['rekom'],
            $isIdem ? '' : $r['nama_operator']
        ];

        $lastId = $r['id'];
    }

    $title = "LAPORAN TINDAKAN PERLAKUAN (" . ($filters['karantina'] ?: 'ALL') . ")";
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Laporan_Perlakuan", $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
