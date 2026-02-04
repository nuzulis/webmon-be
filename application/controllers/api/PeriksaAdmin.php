<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Permohonan / Pemeriksaan Administrasi
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
        'upt'        => $this->input->get('upt'),
        'karantina'  => $this->input->get('karantina'),
        'lingkup'    => $this->input->get('lingkup', true), 
        'start_date' => $this->input->get('start_date'),
        'end_date'   => $this->input->get('end_date'),
        'search'     => $this->input->get('search', true),
    ];

        $this->applyScope($filter);
        
        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) ($this->input->get('per_page') ?? 10);
        $offset  = ($page - 1) * $perPage;
        $rows  = $this->PeriksaAdmin_model->getList($filter, $perPage, $offset);
        $total = $this->PeriksaAdmin_model->countAll($filter);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => (int) ceil($total / max(1, $perPage))
            ]
        ]);
    }

    public function export_excel()
    {
         $filters = [
        'upt'        => $this->input->get('upt', TRUE),
        'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE))),
        'lingkup'    => strtoupper(trim($this->input->get('lingkup', TRUE))), 
        'start_date' => $this->input->get('start_date', TRUE),
        'end_date'   => $this->input->get('end_date', TRUE),
        'search'     => $this->input->get('search', true),
    ];

        $this->applyScope($filters);

        $rows = $this->PeriksaAdmin_model->getExportByFilter($filters);


        $headers = [
            'No', 'No. Permohonan', 'Tgl Permohonan', 'No. P1/P1A', 'Tgl P1/P1A',
            'UPT / Satpel', 'Pengirim', 'Penerima', 'Asal', 'Tujuan',
            'Komoditas', 'HS Code', 'Volume', 'Satuan'
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
                $isIdem ? '' : ($r['no_p1a'] ?? ''),
                $isIdem ? '' : ($r['tgl_p1a'] ?? ''),
                $isIdem ? '' : (($r['upt'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '')),
                $isIdem ? '' : ($r['nama_pengirim'] ?? ''),
                $isIdem ? '' : ($r['nama_penerima'] ?? ''),
                $isIdem ? '' : (($r['asal'] ?? '') . ' - ' . ($r['kota_asal'] ?? '')),
                $isIdem ? '' : (($r['tujuan'] ?? '') . ' - ' . ($r['kota_tujuan'] ?? '')),
                $r['tercetak'] ?? '-',
                $r['hs'] ?? '-',
                is_numeric($r['volume']) ? number_format($r['volume'], 3, ",", ".") : ($r['volume'] ?? '0'),
                $r['satuan'] ?? '-'
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PEMERIKSAAN ADMINISTRASI (" . strtoupper($filters['karantina'] ?? 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download(
            "Laporan_PeriksaAdmin_" . date('Ymd'), 
            $headers, 
            $exportData, 
            $reportInfo
        );
    }
}