<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
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
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }
        $sortBy    = $this->input->get('sort_by', true) ?: 'tgl_ba';
        $sortOrder = strtoupper($this->input->get('sort_order', true)) === 'ASC' ? 'ASC' : 'DESC';

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', true),
            'sort_by'    => $sortBy,
            'sort_order' => $sortOrder,
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(400);
        }

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = max((int) $this->input->get('per_page'), 10);
        $offset  = ($page - 1) * $perPage;

        $ids   = $this->SerahTerima_model->getIds($filters, $perPage, $offset);
        $rows  = $this->SerahTerima_model->getByIds($ids, $filters['karantina'], $sortBy, $sortOrder);
        $total = $this->SerahTerima_model->countAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'page'       => $page,
                'per_page'   => $perPage,
                'total'      => $total,
                'total_page' => ceil($total / $perPage),
            ]
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
            'search'     => $this->input->get('search', true),
        ];
        
        $ids = $this->SerahTerima_model->getIds($filters, 5000, 0);
        $rows = $ids ? $this->SerahTerima_model->getByIds($ids, $filters['karantina']) : [];
        
        $headers = [
            'No', 'Sumber', 'No. Berita Acara', 'Tanggal BA', 'Jenis', 
            'Instansi Asal', 'UPT Tujuan', 'Media Pembawa', 'Komoditas', 
            'No. Aju Tujuan', 'Tgl Aju Tujuan', 'Petugas Penyerah', 
            'Petugas Penerima', 'Keterangan'
        ];

        $exportData = [];
        $no = 1;
        $lastBA = null;

        foreach ($rows as $r) {
            $isIdem = ($r['nomor_ba'] === $lastBA);
            $ketClean = str_replace(["\r", "\n", "\t"], " ", $r['keterangan'] ?? '');
            $komoditasClean = str_replace("<br>", "\n", $r['komoditas'] ?? '');

            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['sumber'] ?? '-',
                $r['nomor_ba'] ?? '-',
                $r['tgl_ba'] ?? '-',
                $r['jns_kar'] ?? '-',
                $r['instansi_asal'] ?? '-',
                $r['upt_tujuan'] ?? '-',
                $r['media_pembawa'] ?? '-',
                $komoditasClean,
                $r['no_aju_tujuan'] ?? '-',
                $r['tgl_aju_tujuan'] ?? '-',
                $r['petugas_penyerah'] ?? '-',
                $r['petugas_penerima'] ?? '-',
                $ketClean
            ];

            $lastBA = $r['nomor_ba'];
        }

        $title = "LAPORAN SERAH TERIMA MEDIA PEMBAWA - " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Serah_Terima", $headers, $exportData, $reportInfo);
    }
}