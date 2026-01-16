<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input   $input
 * @property CI_Output  $output
 * @property CI_Config  $config
 * @property CI_DB_query_builder $db
 * @property Permohonan_model $Permohonan_model
 * @property Excel_handler    $excel_handler
 */
class Permohonan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Permohonan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
        $this->load->helper('sla');
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
         * 2️⃣ FILTER INPUT (SIMPLE & KONSISTEN)
         * ===================================================== */
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        // validasi karantina JIKA ADA
        if (!empty($filters['karantina'])) {
            if (!in_array($filters['karantina'], ['H', 'I', 'T'], true)) {
                return $this->json(400, [
                    'success' => false,
                    'message' => 'Jenis karantina tidak valid (H | I | T)'
                ]);
            }
        }

        /* =====================================================
         * 3️⃣ PAGINATION
         * ===================================================== */
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->Permohonan_model->getIds($filters, $perPage, $offset);
        $data  = $this->Permohonan_model->getByIds($ids, $filters['karantina']);
        $total = $this->Permohonan_model->countAll($filters);


        return $this->json(200, [
            'success' => true,
            'data'    => $data,
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
        /* 1. Ambil Filter & Data */
        $filters = $this->_get_filters();
        
        // Ambil ID dokumen (limit 5000 untuk export)
        $ids = $this->Permohonan_model->getIds($filters, 5000, 0);
        $rows = $ids ? $this->Permohonan_model->getByIdsExport($ids) : [];

        /* 2. Setup Header Excel */
        $headers = [
            'No', 'Sumber', 'No. Aju', 'Tgl Aju', 'No. K.1.1', 'Tgl K.1.1',
            'UPT', 'Satpel', 'Tempat Periksa', 'Pemohon', 'Pengirim', 'Penerima',
            'Asal', 'Tujuan', 'Alat Angkut', 'Kemasan', 'Jml',
            'Komoditas', 'Nama Tercetak', 'HS Code', 
            'P1', 'P2', 'P3', 'P4', 'P5', 'Satuan', 'Nilai (Rp)', 'SLA', 'Risiko'
        ];

        /* 3. Mapping Data dengan Logika IDEM & SLA */
        $exportData = [];
        $no = 1;
        $lastAju = null;
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $r) {
            $isIdem = ($r['no_aju'] === $lastAju);
            
            // Hitung SLA menggunakan helper
            $sla = hitung_sla($r['tgl_periksa'], $now);
            $slaLabel = $sla ? $sla['label'] : '-';

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['tssm_id'] ? 'SSM' : 'PTK'),
                $isIdem ? 'Idem' : $r['no_aju'],
                $isIdem ? '' : $r['tgl_aju'],
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['upt'],
                $isIdem ? '' : $r['satpel'],
                $isIdem ? '' : $r['nama_tempat_pemeriksaan'],
                $isIdem ? '' : $r['nama_pemohon'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : ($r['asal'] ?: $r['kota_asal']),
                $isIdem ? '' : ($r['tujuan'] ?: $r['kota_tujuan']),
                $isIdem ? '' : $r['nama_alat_angkut_terakhir'],
                $isIdem ? '' : $r['kemas'],
                $isIdem ? '' : $r['total_kemas'],
                // Data Komoditas (Selalu Muncul per baris)
                $r['komoditas'],
                $r['nama_umum_tercetak'],
                $r['hs'],
                $r['p1'], $r['p2'], $r['p3'], $r['p4'], $r['p5'],
                $r['satuan'],
                $r['harga_rp'],
                $isIdem ? '' : $slaLabel,
                $r['risiko']
            ];

            $lastAju = $r['no_aju'];
        }

        /* 4. Download File */
        $title = "LAPORAN PERMOHONAN KARANTINA (K.1.1) - " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Permohonan", $headers, $exportData, $reportInfo);
    }

    /**
     * Helper internal untuk konsistensi filter
     */
    private function _get_filters()
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];
    }

    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
