<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input            $input
 * @property CI_Output           $output
 * @property PeriksaFisik_model  $PeriksaFisik_model
 * @property Excel_handler       $excel_handler
 */
class PeriksaFisik extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaFisik_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', TRUE),
            'sort_by'    => $this->input->get('sort_by', TRUE),
            'sort_order' => $this->input->get('sort_order', TRUE),
        ];

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) ($this->input->get('per_page') ?? 10);
        $offset  = ($page - 1) * $perPage;
        $ids   = $this->PeriksaFisik_model->getIds($filters, $perPage, $offset);
        $data  = $this->PeriksaFisik_model->getByIds($ids);
        $total = $this->PeriksaFisik_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / $perPage),
            ]
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', TRUE))),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', true),
        ];

        $rows = $this->PeriksaFisik_model->getFullData($filters);
        if (empty($rows)) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Data tidak ditemukan'
                    ], 404);
                }

        $headers = [
            'No', 'No. Permohonan', 'Tgl Permohonan', 'No. P1B (Fisik)', 'Tgl P1B',
            'UPT / Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
            'Komoditas', 'Volume', 'Satuan'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? ''),
                $isIdem ? '' : ($r['tgl_dok_permohonan'] ?? ''),
                $isIdem ? '' : ($r['no_p1b'] ?? ''),
                $isIdem ? '' : ($r['tgl_p1b'] ?? ''),
                $isIdem ? '' : ($r['upt'] . ' - ' . ($r['nama_satpel'] ?? '')),
                $isIdem ? '' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? '' : ($r['nama_penerima'] ?? ''),
                $isIdem ? '' : ($r['asal'] ?? ''),
                $isIdem ? '' : ($r['tujuan'] ?? ''),
                $r['nama_umum_tercetak'] ?? '-',
                is_numeric($r['volume']) ? number_format($r['volume'], 3, ",", ".") : ($r['volume'] ?? '0'),
                $r['satuan'] ?? '-'
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMERIKSAAN FISIK & KESEHATAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download(
            "Laporan_PeriksaFisik_" . date('Ymd'), 
            $headers, 
            $exportData, 
            $reportInfo
        );
    }
}