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
            'permohonan' => strtoupper(trim($this->input->get('lingkup', TRUE) ?: $this->input->get('permohonan', TRUE))),
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
        'No.', 'No. Aju', 'No. Dokumen', 'Tgl Dokumen', 'No. P1B', 'Tgl P1B',
        'UPT', 'Satpel', 'Pengirim', 'Penerima', 'Asal (Negara - Kota)', 'Tujuan (Negara - Kota)',
        'Komoditas', 'HS Code', 'Volume', 'Satuan'
    ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            $asalFull = trim(($r['asal'] ?? '') . ' - ' . ($r['kota_asal'] ?? ''), ' -');
        $tujuanFull = trim(($r['tujuan'] ?? '') . ' - ' . ($r['kota_tujuan'] ?? ''), ' -');

        $exportData[] = [
            $isIdem ? '' : $no++,
            $isIdem ? 'Idem' : ($r['no_aju'] ?? '-'),
            $isIdem ? 'Idem' : ($r['no_dok_permohonan'] ?? '-'),
            $isIdem ? 'Idem' : ($r['tgl_dok_permohonan'] ?? '-'),
            $isIdem ? 'Idem' : ($r['no_p1b'] ?? '-'),
            $isIdem ? 'Idem' : ($r['tgl_p1b'] ?? '-'),
            $isIdem ? 'Idem' : ($r['upt'] ?? '-'),
            $isIdem ? 'Idem' : ($r['nama_satpel'] ?? '-'),
            $isIdem ? 'Idem' : ($r['nama_pengirim'] ?? '-'),
            $isIdem ? 'Idem' : ($r['nama_penerima'] ?? '-'),
            $isIdem ? 'Idem' : ($asalFull ?: '-'),
            $isIdem ? 'Idem' : ($tujuanFull ?: '-'),
            str_replace('<br>', "\n", $r['nama_umum_tercetak'] ?? '-'),
            str_replace('<br>', "\n", $r['hs'] ?? '-'),
            str_replace('<br>', "\n", $r['volume'] ?? '-'),
            str_replace('<br>', "\n", $r['satuan'] ?? '-')
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