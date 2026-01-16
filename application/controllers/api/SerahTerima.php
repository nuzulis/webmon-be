<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property SerahTerima_model $SerahTerima_model
 * @property Excel_handler    $excel_handler
 */

    class SerahTerima extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('SerahTerima_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        // JWT
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400, [
                'success' => false,
                'message' => 'Jenis karantina tidak valid'
            ]);
        }

        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int)$this->input->get('per_page');
        $perPage = $perPage > 0 ? min($perPage, 25) : 20;
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->SerahTerima_model->getIds($filters, $perPage, $offset);
        $rows  = $this->SerahTerima_model->getByIds($ids, $filters['karantina']);
        $total = $this->SerahTerima_model->countAll($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ]
        ]);
    }

    public function export_excel()
{
    /* 1. Ambil Filter */
    $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
    ];

    /* 2. Ambil Data (Limit 5000 untuk export) */
    $ids = $this->SerahTerima_model->getIds($filters, 5000, 0);
    $rows = $ids ? $this->SerahTerima_model->getByIds($ids, $filters['karantina']) : [];

    /* 3. Setup Header Excel */
    $headers = [
        'No', 'Sumber', 'No. Berita Acara', 'Tanggal BA', 'Jenis', 
        'Instansi Asal', 'UPT Tujuan', 'Media Pembawa', 'Komoditas', 
        'No. Aju Tujuan', 'Tgl Aju Tujuan', 'Petugas Penyerah', 
        'Petugas Penerima', 'Keterangan'
    ];

    /* 4. Mapping Data dengan Logika IDEM */
    $exportData = [];
    $no = 1;
    $lastBA = null;

    foreach ($rows as $r) {
        // Cek apakah nomor BA sama dengan baris sebelumnya
        $isIdem = ($r['nomor_ba'] === $lastBA);
        
        // Bersihkan keterangan dari karakter yang merusak format
        $ketClean = str_replace(["\r", "\n", "\t"], " ", $r['keterangan']);
        // Komoditas menggunakan <br> di query, ubah jadi newline untuk Excel
        $komoditasClean = str_replace("<br>", "\n", $r['komoditas']);

        $exportData[] = [
            $isIdem ? '' : $no++,
            $isIdem ? 'Idem' : $r['sumber'],             
            $isIdem ? 'Idem' : $r['nomor_ba'],           
            $isIdem ? '' : $r['tgl_ba'],                 
            $isIdem ? '' : $r['jns_kar'],                          
            $isIdem ? '' : $r['instansi_asal'],
            $isIdem ? '' : $r['upt_tujuan'],
            $isIdem ? '' : $r['media_pembawa'],
            $komoditasClean,
            $isIdem ? 'Idem' : $r['no_aju_tujuan'], 
            $isIdem ? '' : $r['tgl_aju_tujuan'],         
            $isIdem ? '' : $r['petugas_penyerah'],       
            $isIdem ? '' : $r['petugas_penerima'],       
            $ketClean                                    
        ];

        $lastBA = $r['nomor_ba'];
    }

    /* 5. Download File */
    $title = "LAPORAN SERAH TERIMA MEDIA PEMBAWA - " . ($filters['karantina'] ?: 'ALL');
    $reportInfo = $this->buildReportHeader($title, $filters);

    return $this->excel_handler->download("Laporan_Serah_Terima", $headers, $exportData, $reportInfo);
}

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}

