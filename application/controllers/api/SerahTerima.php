<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input              $input
 * @property SerahTerima_model     $SerahTerima_model
 * @property Excel_handler         $excel_handler
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

        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        if (!empty($filters['karantina']) &&
            !in_array($filters['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(400);
        }

        $data = $this->SerahTerima_model->getAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->SerahTerima_model->getAll($filters);

        $headers = [
            'No', 'Sumber', 'No. Berita Acara', 'Tanggal BA', 'Jenis',
            'Instansi Asal', 'UPT Tujuan', 'Media Pembawa', 'Komoditas',
            'No. Aju Tujuan', 'Tgl Aju Tujuan', 'Petugas Penyerah',
            'Petugas Penerima', 'Keterangan'
        ];

        $exportData = [];
        $no     = 1;
        $lastBA = null;

        foreach ($rows as $r) {
            $isIdem         = ($r['nomor_ba'] === $lastBA);
            $ketClean       = str_replace(["\r", "\n", "\t"], " ", $r['keterangan'] ?? '');
            $komoditasClean = str_replace('<br>', "\n", $r['komoditas'] ?? '');

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

        $title      = "LAPORAN SERAH TERIMA MEDIA PEMBAWA - " . ($filters['karantina'] ?: 'ALL');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_Serah_Terima", $headers, $exportData, $reportInfo);
    }
}
