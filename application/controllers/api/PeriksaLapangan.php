<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input            $input
 * @property CI_Output           $output
 * @property CI_Config           $config
 * @property PeriksaLapangan_model  $PeriksaLapangan_model
 * @property Excel_handler         $excel_handler
 */
class PeriksaLapangan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaLapangan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        // JWT
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success'=>false,'message'=>'Unauthorized']);
        }

        

        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', true))),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400, [
                'success'=>false,
                'message'=>'Jenis karantina tidak valid'
            ]);
        }

        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        // STEP 1
        $ids = $this->PeriksaLapangan_model->getIds($filters, $perPage, $offset);

        // STEP 2
        $rows = $this->PeriksaLapangan_model->getByIds($ids);

        $total = $this->PeriksaLapangan_model->countAll($filters);

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
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper(trim($this->input->get('karantina', true))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', true))),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
        ];

        // 1. Ambil Data
        $ids = $this->PeriksaLapangan_model->getIds($filters, 5000, 0);
        $rows = $this->PeriksaLapangan_model->getByIds($ids);

        // 2. Header Excel
        $headers = [
            'No', 
            'UPT / Satpel', 
            'No. Permohonan', 'Tgl Permohonan', 
            'No. P1A', 'Tgl P1A',
            'Target', 'Metode',
            'Waktu Mulai', 'Waktu Selesai',
            'Durasi (Menit)', 'Durasi (Text)',
            'Status', 'Keterangan'
        ];

        // 3. Mapping Data
        $exportData = [];
        $no = 1;
        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['upt'] . ' - ' . $r['nama_satpel'],
                $r['no_dok_permohonan'],
                $r['tgl_dok_permohonan'],
                $r['no_p1a'],
                $r['tgl_p1a'],
                $r['target'] ?? '-',
                $r['metode'] ?? '-',
                $r['mulai'] ?? '-',
                $r['selesai'] ?? '-',
                $r['durasi_menit'],
                $r['durasi_text'],
                $r['status_proses'],
                $r['keterangan'] ?? '-'
            ];
        }

        $title = "LAPORAN PEMERIKSAAN LAPANGAN (OFFICER)";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_PeriksaLapangan", $headers, $exportData, $reportInfo);
    }

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json','utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
