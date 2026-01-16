<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input            $input
 * @property CI_Output           $output
 * @property CI_Config           $config
 * @property PeriksaFisik_model  $PeriksaFisik_model
 * @property Excel_handler         $excel_handler
 */
class PeriksaFisik extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaFisik_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* =============================
         * JWT
         * ============================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        

        /* =============================
         * FILTER
         * ============================= */
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        /* =============================
         * PAGINATION
         * ============================= */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = min(max((int)$this->input->get('per_page'), 1), 25);
        $offset  = ($page - 1) * $perPage;

        /* =============================
         * STEP 1
         * ============================= */
        $ids = $this->PeriksaFisik_model->getIds($filters, $perPage, $offset);

        /* =============================
         * STEP 2
         * ============================= */
        $rows = $this->PeriksaFisik_model->getByIds($ids);

        /* =============================
         * TOTAL
         * ============================= */
        $total = $this->PeriksaFisik_model->countAll($filters);

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
        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        // 1. Ambil Data (Step 1 & 2)
        $ids = $this->PeriksaFisik_model->getIds($filters, 5000, 0);
        $rows = $this->PeriksaFisik_model->getByIds($ids, true);

        // 2. Persiapan Header Excel
        $headers = [
            'No', 'No. Permohonan', 'Tgl Permohonan', 'No. P1B (Fisik)', 'Tgl P1B',
            'UPT / Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
            'Komoditas', 'HS Code', 'Volume', 'Satuan'
        ];

        // 3. Mapping Data dengan Logika Idem
        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['no_p1b'],
                $isIdem ? '' : $r['tgl_p1b'],
                $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['asal'] . ' - ' . $r['kota_asal'],
                $isIdem ? '' : $r['tujuan'] . ' - ' . $r['kota_tujuan'],
                // Detail Barang
                $r['tercetak'],
                $r['hs'],
                number_format($r['volume'], 3, ",", "."),
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMERIKSAAN FISIK & KESEHATAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_PeriksaFisik", $headers, $exportData, $reportInfo);
    }

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
