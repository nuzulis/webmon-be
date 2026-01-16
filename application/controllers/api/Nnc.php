<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property Nnc_model  $Nnc_model
 * @property Excel_handler $excel_handler
 */
class Nnc extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Nnc_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    /**
     * GET /api/nnc
     */
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

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)
        ) {
            return $this->json(400, [
                'success' => false,
                'message' => 'Jenis karantina tidak valid (H | I | T)'
            ]);
        }

        /* ===== PAGINATION ===== */
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? min($perPage, 25) : 20;

        $offset  = ($page - 1) * $perPage;

        /* ===== STEP 1 ===== */
        $ids = $this->Nnc_model->getIds($filters, $perPage, $offset);

        /* ===== STEP 2 ===== */
        $rows = empty($ids)
            ? []
            : $this->Nnc_model->getByIds($ids, $filters['karantina']);

        /* ===== TOTAL ===== */
        $total = $this->Nnc_model->countAll($filters);

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
    // ... (kode filter sama dengan index) ...
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
        'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
    ];

    // Ambil semua data tanpa limit untuk export
    $ids = $this->Nnc_model->getIds($filters, 5000, 0); 
    $rows = $this->Nnc_model->getByIds($ids);

    $headers = [
        'No', 'Pengajuan via', 'No. NNC', 'Tgl NNC', 'Satpel', 'Tujuan NNC',
        'Pengirim', 'Penerima', 'Asal', 'Tujuan', 'Komoditas', 'Volume', 
        'Volume P6', 'Satuan', 'NATURE OF NON-COMPLIANCE', 
        'DISPOSITION OF THE CONSIGNMENT', 'Details', 'No. K-1.1', 'Tgl K-1.1', 'Petugas'
    ];

    $exportData = [];
    $no = 1;
    $lastId = null;

    foreach ($rows as $r) {
        // Cek apakah ini masih dokumen yang sama dengan baris sebelumnya
        $isIdem = ($r['id'] === $lastId);
        
        // Olah pesan Nature of Non-Compliance (Specify 1-5)
        $messages = [];
        $labels = [
            1 => 'Prohibited goods: ',
            2 => 'Problem with documentation (specify): ',
            3 => 'The goods were infected/infested/contaminated (specify): ',
            4 => 'Non-compliance food safety (specify): ',
            5 => 'Non-compliance other SPS (specify): '
        ];
        foreach (range(1, 5) as $i) {
            if (!empty($r["specify$i"])) $messages[] = $labels[$i] . $r["specify$i"];
        }

        $exportData[] = [
            // HANYA KOLOM NOMOR YANG IDEM
            $isIdem ? '' : $no++,                                      
            
            // KOLOM LAINNYA TETAP MUNCUL (TIDAK IDEM)
            isset($r['tssm_id']) ? 'SSM' : 'PTK',
            $r['nomor_nnc'],
            $r['tgl_nnc'],
            ($r['upt'] . ' - ' . $r['nama_satpel']),
            $r['kepada'],
            $r['nama_pengirim'],
            $r['nama_penerima'],
            ($r['asal'] . ' - ' . $r['kota_asal']),
            ($r['tujuan'] . ' - ' . $r['kota_tujuan']),
            $r['komoditas_single'],
            $r['volume_single'],
            $r['volume_p6_single'],
            $r['satuan_single'],
            implode("\n", $messages),
            "The {$r['consignment']} lot was: {$r['information']}",
            $r['consignment_detil'],
            $r['no_dok_permohonan'],
            $r['tgl_dok_permohonan'],
            $r['petugas'],
        ];

        $lastId = $r['id'];
    }

    $title = "LAPORAN NOTIFICATION OF NON-COMPLIANCE " . strtoupper($filters['karantina']);
    $reportInfo = $this->buildReportHeader($title, $filters, $rows);

    $this->load->library('excel_handler');
    return $this->excel_handler->download("Laporan_NNC", $headers, $exportData, $reportInfo);
}


    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
