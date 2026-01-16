<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Penahanan_model   $Penahanan_model
 * @property Excel_handler     $excel_handler
 */
class Penahanan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penahanan_model');
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
        $ids = $this->Penahanan_model
            ->getIds($filters, $perPage, $offset);

        /* =============================
         * STEP 2 — DATA
         * ============================= */
        $rows = [];
        if ($ids) {
            $rows = $this->Penahanan_model
                ->getByIds($ids, $filters['karantina']);
        }

        /* =============================
         * TOTAL
         * ============================= */
        $total = $this->Penahanan_model->countAll($filters);

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

        $filters = [
            'upt'        => $this->input->get('upt'),
            'karantina'  => strtoupper($this->input->get('karantina')),
            'permohonan' => strtoupper($this->input->get('permohonan')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
        ];

        // 2. Ambil Data
        $ids = $this->Penahanan_model->getIds($filters, 5000, 0);
        $rows = $this->Penahanan_model->getByIds($ids, $filters['karantina'], true);

        // 3. Persiapan Header
        $headers = [
            'No', 'No.K.1.1', 'Tgl K.1.1', 'Nomor Penahanan', 'Tgl Penahanan', 
            'Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan', 
            'Alasan', 'Petugas', 'Komoditas', 'Nama Tercetak', 
            'Kode HS', 'Vol P0', 'Vol P5', 'Satuan'
        ];

        $exportData = [];
        $lastId = null;
        $no = 1;

        foreach ($rows as $r) {
            // Jika ID sama dengan sebelumnya, berarti ini komoditas dari dokumen yang sama
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,                  // No urut hanya bertambah jika dokumen baru
                $isIdem ? 'Idem' : $r['no_dok_permohonan'], // Kolom Idem
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['no_p5'],
                $isIdem ? '' : $r['tgl_p5'],
                $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['asal'] . ' - ' . $r['kota_asal'],
                $isIdem ? '' : $r['tujuan'] . ' - ' . $r['kota_tujuan'],
                $isIdem ? '' : $r['alasan_string'],
                $isIdem ? '' : $r['petugas'],
                // Baris di bawah ini SELALU tampil (Data Komoditas)
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                number_format((float)$r['volume'], 3, ',', '.'),
                number_format((float)$r['p5_vol'], 3, ',', '.'),
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

         $title = "LAPORAN PENAHANAN " . $filters['karantina'];
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

    $this->load->library('excel_handler');
    return $this->excel_handler->download("Laporan_Penahanan", $headers, $exportData, $reportInfo);
    }
    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
