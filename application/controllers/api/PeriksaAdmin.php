<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permohonan / Pemeriksaan Administrasi
 * /**
 * @property PeriksaAdmin_model $PeriksaAdmin_model
 * @property Excel_handler      $excel_handler
 */
class PeriksaAdmin extends MY_Controller
{
   
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaAdmin_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }
    public function index()
    {
        $filter = [
            'upt_id'     => $this->input->get('upt'),
            'karantina'  => $this->input->get('karantina'),
            'permohonan' => $this->input->get('permohonan'),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
        ];

        $this->applyScope($filter);
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page');
        $perPage = ($perPage > 0 && $perPage <= 25) ? $perPage : 20;
        $offset  = ($page - 1) * $perPage;

        /* =============================
         * 5. STEP QUERY (AMAN)
         * ============================= */
        $ids   = $this->PeriksaAdmin_model->getIds($filter, $perPage, $offset);
        $rows  = $this->PeriksaAdmin_model->getByIds($ids);
        $total = $this->PeriksaAdmin_model->countAll($filter);

        /* =============================
         * 6. RESPONSE
         * ============================= */
        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data'    => $rows,
                'meta'    => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'total_page' => (int) ceil($total / $perPage)
                ]
            ], JSON_UNESCAPED_UNICODE));
    }
    public function export_excel()
    {
        $filters = [
            'upt_id'     => $this->input->get('upt'),
            'karantina'  => $this->input->get('karantina'),
            'permohonan' => $this->input->get('permohonan'),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
        ];

        // 1. Ambil ID (Gunakan limit besar untuk export)
        $ids = $this->PeriksaAdmin_model->getIds($filters, 5000, 0);
        $rows = $this->PeriksaAdmin_model->getByIds($ids, true);

        // 2. Header
        $headers = [
            'No', 'No. Permohonan', 'Tgl Permohonan', 'No. P1/P1A', 'Tgl P1/P1A',
            'UPT / Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
            'Komoditas', 'HS Code', 'Volume', 'Satuan'
        ];

        // 3. Mapping Data (Logika Idem)
        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? '' : $r['tgl_dok_permohonan'],
                $isIdem ? '' : $r['no_p1a'],
                $isIdem ? '' : $r['tgl_p1a'],
                $isIdem ? '' : $r['upt'] . ' - ' . $r['nama_satpel'],
                $isIdem ? '' : $r['nama_pengirim'],
                $isIdem ? '' : $r['nama_penerima'],
                $isIdem ? '' : $r['asal'] . ' - ' . $r['kota_asal'],
                $isIdem ? '' : $r['tujuan'] . ' - ' . $r['kota_tujuan'],
                $r['tercetak'],
                $r['hs'],
                number_format($r['volume'], 3, ",", "."),
                $r['satuan']
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMERIKSAAN ADMINISTRASI (" . strtoupper($filters['karantina'] ?? 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_PeriksaAdmin", $headers, $exportData, $reportInfo);
    }
}
