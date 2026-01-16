<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input        $input
 * @property CI_Output       $output
 * @property CI_Config       $config
 * @property Revisi_model    $Revisi_model
 * @property Excel_handler    $excel_handler
 */
class Revisi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Revisi_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ===== JWT ===== */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        

        /* ===== FILTER ===== */
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400, [
                'success' => false,
                'message' => 'Jenis karantina tidak valid'
            ]);
        }

        /* ===== PAGINATION ===== */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? min($perPage, 25) : 20;
        $offset  = ($page - 1) * $perPage;

        /* ===== STEP 1 ===== */
        $ids = $this->Revisi_model->getIds($filters, $perPage, $offset);

        /* ===== STEP 2 ===== */
        $rows = $this->Revisi_model->getByIds($ids, $filters['karantina']);

        /* ===== TOTAL ===== */
        $total = $this->Revisi_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage),
            ]
        ]);
    }

    public function export_excel()
{
    /* 1. Ambil Filter & Data */
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
        'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
    ];

    if (!in_array($filters['karantina'], ['H','I','T'], true)) {
        return $this->json(400, ['success' => false, 'message' => 'Jenis karantina wajib diisi']);
    }

    $ids = $this->Revisi_model->getIds($filters, 5000, 0);
    $rows = $ids ? $this->Revisi_model->getByIds($ids, $filters['karantina']) : [];

    /* 2. Setup Header Excel */
    $headers = [
        'No', 'Sumber', 'No. Aju', 'No. Dok Permohonan', 'Tgl Dok Permohonan', 
        'UPT', 'Satpel', 'No. Dokumen Revisi', 'No. Seri', 'Tgl Dokumen', 
        'Alasan Hapus/Revisi', 'Waktu Hapus', 'Penandatangan', 'Petugas Hapus'
    ];

    /* 3. Mapping Data dengan Logika IDEM */
    $exportData = [];
    $no = 1;
    $lastAju = null; // Variabel pembantu untuk mengecek baris sebelumnya

    foreach ($rows as $r) {
        // Cek apakah No Aju sama dengan baris sebelumnya
        $isIdem = ($r['no_aju'] === $lastAju);
        
        $alasanClean = str_replace(["\r", "\n", "\t"], " ", $r['alasan_delete']);

        $exportData[] = [
            $isIdem ? '' : $no++,                         // No urut hanya muncul jika bukan Idem
            $isIdem ? 'Idem' : $r['sumber'],             // Sumber
            $isIdem ? 'Idem' : $r['no_aju'],             // No Aju
            $isIdem ? 'Idem' : $r['no_dok_permohonan'],  // No Dok Permohonan
            $isIdem ? '' : $r['tgl_dok_permohonan'],      // Tgl Dok Permohonan
            $isIdem ? '' : $r['upt'],                    // UPT
            $isIdem ? '' : $r['nama_satpel'],            // Satpel
            
            // Kolom detail dokumen di bawah ini biasanya tetap muncul karena 
            // 1 No Aju bisa punya beberapa dokumen yang direvisi
            $r['no_dok'],
            $r['nomor_seri'],
            $r['tgl_dok'],
            $alasanClean,
            $r['deleted_at'],
            $r['penandatangan'],
            $r['yang_menghapus']
        ];

        // Update lastAju untuk pengecekan baris berikutnya
        $lastAju = $r['no_aju'];
    }

    /* 4. Download File */
    $title = "LAPORAN REVISI - " . $filters['karantina'];
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Laporan_Revisi_Dokumen", $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
