<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property Perlakuan_model $Perlakuan_model
 * @property Excel_handler   $excel_handler
 */
class Perlakuan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Perlakuan_model');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->Perlakuan_model->getAll($filters);

        return $this->json([
            'success' => true,
            'data'    => $rows,
        ], 200);
    }

    public function export_excel()
    {
        $filters = [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper($this->input->get('karantina', TRUE)),
            'lingkup'    => $this->input->get('lingkup', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];

        $rows = $this->Perlakuan_model->getForExcel($filters);

        if (empty($rows)) {
            return $this->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $headers = [
            'No.', 'No. P4', 'Tgl P4', 'Satpel', 
            'Tempat Perlakuan', 'Pengirim', 'Penerima', 
            'Komoditas', 'Volume', 'Satuan',
            'Alasan', 'Metode', 'Tipe Perlakuan', 'Mulai', 'Selesai', 
            'Rekomendasi', 'Operator'
        ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);

            $exportData[] = [
                $isIdem ? '' : $no++,
                ($r['no_p4'] ?? ''),
                ($r['tgl_p4'] ?? ''),
                (($r['upt'] ?? '') . ' - ' . ($r['nama_satpel'] ?? '')),
                ($r['lokasi_perlakuan'] ?? ''),
                ($r['nama_pengirim'] ?? ''),
                ($r['nama_penerima'] ?? ''),
                $r['komoditas'] ?? '',
                $r['volume'] ?? '',
                $r['satuan'] ?? '',
                ($r['alasan_perlakuan'] ?? ''),
                ($r['metode'] ?? ''),
                ($r['tipe'] ?? ''),
                ($r['mulai'] ?? ''),
                ($r['selesai'] ?? ''),
                ($r['rekom'] ?? ''),
                ($r['nama_operator'] ?? '')
            ];

            $lastId = $r['id'];
        }

        $title = "LAPORAN TINDAKAN PERLAKUAN (" . ($filters['karantina'] ?: 'ALL') . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);

        $this->logActivity("EXPORT EXCEL: Perlakuan {$filters['karantina']}");

        return $this->excel_handler->download("Laporan_Perlakuan", $headers, $exportData, $reportInfo);
    }
}