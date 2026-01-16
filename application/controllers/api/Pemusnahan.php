<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Pemusnahan_model  $Pemusnahan_model
 * @property Excel_handler      $excel_handler
 */
class Pemusnahan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pemusnahan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* =====================================================
         * 1️⃣ JWT GUARD
         * ===================================================== */
        $auth = $this->input->get_request_header('Authorization', TRUE);

        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, [
                'success' => false,
                'message' => 'Unauthorized'
            ]);
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, [
                'success' => false,
                'message' => 'Token tidak valid'
            ]);
        }

        /* =====================================================
         * 2️⃣ FILTER INPUT
         * ===================================================== */
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        // validasi karantina jika diisi
        if (!empty($filters['karantina']) && !in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400, [
                'success' => false,
                'message' => 'Jenis karantina tidak valid (H | I | T)'
            ]);
        }

        /* =====================================================
         * 3️⃣ PAGINATION
         * ===================================================== */
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* =====================================================
         * 4️⃣ STEP 1 — ID SAJA
         * ===================================================== */
        $ids = $this->Pemusnahan_model
            ->getIds($filters, $perPage, $offset);

        $rows = [];
        if ($ids) {
            // Hapus parameter kedua
            $rows = $this->Pemusnahan_model->getByIds($ids); 
        }
        /* =====================================================
         * 6️⃣ TOTAL
         * ===================================================== */
        $total = $this->Pemusnahan_model->countAll($filters);

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
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $ids = $this->Pemusnahan_model->getIds($filters, 5000, 0);
        $rows = $this->Pemusnahan_model->getByIds($ids, true); // true = mode export

        $headers = [
            'Pengajuan via', 'Nomor Dokumen', 'Tgl Dokumen', 'Satpel', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Alasan Pemusnahan', 'Tempat Pemusnahan', 'Metode Pemusnahan',
            'Petugas', 'Komoditas', 'Nama Tercetak', 'Kode HS', 'Volume P0', 'Volume P7', 'Satuan'
        ];


        $exportData = [];
        $lastId = null;
        $no = 1; // Inisialisasi nomor urut

        foreach ($rows as $r) {
            // Cek apakah ID PTK sama dengan baris sebelumnya
            $isIdem = ($r['id'] === $lastId);
            
            $exportData[] = [
                $isIdem ? '' : $no++, 
                $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'),
                $isIdem ? '' : $r['nomor'],
                $isIdem ? '' : $r['tgl_p7'],
                $isIdem ? '' : $r['nama_upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['negara_asal'] . ' - ' . $r['kota_kab_asal'],
                $isIdem ? '' : $r['negara_tujuan'] . ' - ' . $r['kota_kab_tujuan'],
                $isIdem ? '' : $r['alasan_string'],
                $isIdem ? '' : $r['tempat'],
                $isIdem ? '' : $r['metode'],
                $isIdem ? '' : $r['petugas'],
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                $r['volume'],
                $r['p7'],
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

         $title = "LAPORAN PEMUSNAHAN " . $filters['karantina'];
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

    $this->load->library('excel_handler');
    return $this->excel_handler->download("Laporan_Pemusnahan", $headers, $exportData, $reportInfo);
    }
    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
