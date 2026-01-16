<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output  
 * @property CI_Config         $config
 * @property Tangkapan_model $Tangkapan_model
 * @property Excel_handler      $excel_handler
 */
class Tangkapan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Tangkapan_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ================= JWT ================= */
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false]);
        }

        

        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
        ];

        /* ================= PAGINATION ================= */
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* ================= STEP 1 ================= */
        $ids = $this->Tangkapan_model->getIds($filters, $perPage, $offset);

        /* ================= STEP 2 ================= */
        $rows = $ids
            ? $this->Tangkapan_model->getByIds($ids, $filters['karantina'])
            : [];

        /* ================= TOTAL ================= */
        $total = $this->Tangkapan_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ],
        ]);
    }
    public function export_excel()
{
    /* 1. Ambil Filter */
    $filters = [
        'upt'        => $this->input->get('upt'),
        'karantina'  => strtoupper($this->input->get('karantina')),
        'permohonan' => strtoupper($this->input->get('permohonan')),
        'start_date' => $this->input->get('start_date'),
        'end_date'   => $this->input->get('end_date'),
    ];

    /* 2. Ambil Data (Limit 5000) */
    $ids = $this->Tangkapan_model->getIds($filters, 5000, 0);
    $rows = $ids ? $this->Tangkapan_model->getByIds($ids, $filters['karantina']) : [];

    /* 3. Setup Header Excel */
    $headers = [
        'No', 'No. P3 (Tangkapan)', 'Tanggal P3', 'UPT', 'Satpel', 
        'Lokasi', 'Keterangan Lokasi', 'Asal', 'Tujuan', 
        'Pengirim', 'Penerima', 'Komoditas', 'Volume', 'Satuan', 
        'Alasan Tahan', 'Petugas Pelaksana', 'Rekomendasi'
    ];

    /* 4. Mapping Data dengan Idem Terbatas */
    $exportData = [];
    $no = 1;
    $lastNoP3 = null;

    foreach ($rows as $r) {
        // Cek Idem hanya berdasarkan nomor P3
        $isIdem = ($r['no_p3'] === $lastNoP3);
        
        // Bersihkan data komoditas (dari <br> ke newline Excel)
        $komoditasArr = explode('<br>', $r['komoditas']);
        $volumeArr    = explode('<br>', $r['volume']);
        $satuanArr    = explode('<br>', $r['satuan']);

        $exportData[] = [
            $isIdem ? '' : $no++,             // Nomor urut: Idem (Kosong)
            $isIdem ? 'Idem' : $r['no_p3'],   // No. P3: Idem
            $r['tgl_p3'],                     // Tanggal: Tetap muncul
            $r['upt_singkat'],                // UPT: Tetap muncul
            $r['satpel'],
            $r['lokasi_label'],
            $r['ket_lokasi'],
            $r['asal'],
            $r['tujuan'],
            $r['pengirim'],
            $r['penerima'],
            str_replace('<br>', "\n", $r['komoditas']),
            str_replace('<br>', "\n", $r['volume']),
            str_replace('<br>', "\n", $r['satuan']),
            $r['alasan_tahan'],
            $r['petugas_pelaksana'],
            $r['rekomendasi']
        ];

        $lastNoP3 = $r['no_p3'];
    }

    $title = "LAPORAN TANGKAPAN - " . ($filters['karantina'] ?: 'ALL');
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Laporan_Tangkapan", $headers, $exportData, $reportInfo);
}
    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
