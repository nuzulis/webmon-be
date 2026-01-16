<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property CI_DB_query_builder $db
 * @property Transaksi_model $Transaksi_model
 * @property Excel_handler    $excel_handler
 * 
 */
class Transaksi extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Transaksi_model');
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
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? min($perPage, 25) : 20;

        $offset  = ($page - 1) * $perPage;

        /* ===== STEP 1 ===== */
        $ids = $this->Transaksi_model->getIds($filters, $perPage, $offset);

        /* ===== STEP 2 ===== */
        $rows = empty($ids)
            ? []
            : $this->Transaksi_model->getByIds($ids, $filters['karantina']);

        /* ===== TOTAL ===== */
        $total = $this->Transaksi_model->countAll($filters);

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
    $today = date('Y-m-d');
    
    $rawKarantina = strtoupper(trim($this->input->get('karantina', TRUE)));
    $karantina = (strlen($rawKarantina) > 1) ? substr($rawKarantina, -1) : $rawKarantina;

    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => $karantina,
        'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
        'start_date' => $today . ' 00:00:00',
        'end_date'   => $today . ' 23:59:59'
    ];

    /* 2. Ambil Data (Limit 5000) */
    // getIds akan menggunakan filter tanggal hari ini yang sudah kita set di atas
    $ids = $this->Transaksi_model->getIds($filters, 5000, 0);
    $rows = $ids ? $this->Transaksi_model->getByIds($ids, $filters['karantina']) : [];

    /* 3. Setup Header Excel */
    $headers = [
        'No', 'Sumber', 'No. Aju', 'Tgl Aju', 'No. Dokumen', 'Tgl Dokumen',
        'UPT', 'Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
        'Tempat Periksa', 'Tgl Periksa', 'Komoditas', 'HS Code', 'Volume', 'Satuan'
    ];

    /* 4. Mapping Data dengan Logika IDEM */
    $exportData = [];
    $no = 1;
    $lastAju = null;

    foreach ($rows as $r) {
        $isIdem = ($r['no_aju'] === $lastAju);

        $exportData[] = [
            $isIdem ? '' : $no++, 
            $isIdem ? 'Idem' : $r['sumber'],
            $isIdem ? 'Idem' : $r['no_aju'],
            $r['tgl_aju'],
            $isIdem ? 'Idem' : $r['no_dok'],
            $r['tgl_dok'],
            $r['upt'],
            $r['satpel'],
            $r['pengirim'],
            $r['penerima'],
            $r['asal_kota'],
            $r['tujuan_kota'],
            $r['tempat_periksa'],
            $r['tgl_periksa'],
            str_replace('<br>', "\n", $r['komoditas']),
            str_replace('<br>', "\n", $r['hs']),
            str_replace('<br>', "\n", $r['volume']),
            str_replace('<br>', "\n", $r['satuan'])
        ];

        $lastAju = $r['no_aju'];
    }

    /* 5. Download File */
    $title = "LAPORAN TRANSAKSI HARI INI (" . date('d F Y') . ") - " . ($filters['karantina'] ?: 'ALL');
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Transaksi_Hari_Ini_" . date('Ymd'), $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
