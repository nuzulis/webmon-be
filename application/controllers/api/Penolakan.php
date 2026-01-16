<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Penolakan_model   $Penolakan_model
 * @property Excel_handler     $excel_handler
 */
class Penolakan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penolakan_model');
        $this->load->helper(['jwt']);
    }

    public function index()
    {
        /* ================= JWT ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
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
        $page    = max((int)$this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* ================= STEP 1 ================= */
        $ids = $this->Penolakan_model
            ->getIds($filters, $perPage, $offset);

        /* ================= STEP 2 ================= */
        $rows = [];
        if ($ids) {
            $rows = $this->Penolakan_model
                ->getByIds($ids, $filters['karantina']);
        }

        /* ================= TOTAL ================= */
        $total = $this->Penolakan_model->countAll($filters);

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

        $ids = $this->Penolakan_model->getIds($filters, 5000, 0);
        $rows = $this->Penolakan_model->getByIds($ids, $filters['karantina']);

        $headers = [
            'No', 'Nomor Dokumen', 'Tgl Dokumen', 'No. K.1.1 (P6)', 'Tgl K.1.1 (P6)',
            'Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan', 
            'Alasan', 'Petugas', 'Alasan Penolakan','Komoditas', 'Nama Tercetak', 'HS Code', 
            'Vol P0', 'Vol P6', 'Satuan'
        ];

        $exportData = [];
        $lastId = null;
        $no = 1;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['nomor_penolakan'],
                $isIdem ? '' : $r['tgl_penolakan'],
                $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['asal'] . ' - ' . $r['kota_asal'],
                $isIdem ? '' : $r['tujuan'] . ' - ' . $r['kota_tujuan'],
                $isIdem ? '' : str_replace('<br>', "\n", $r['alasan_string']),
                $isIdem ? '' : $r['petugas'],
                $isIdem ? '' : $r['alasan_string'],
                $r['komoditas'],
                $r['tercetak'],
                $r['hs'],
                number_format($r['volume'], 3, ",", "."),
                number_format($r['p6_vol'], 3, ",", "."),
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PENOLAKAN " . $filters['karantina'];
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Penolakan", $headers, $exportData, $reportInfo);
    
    }

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
