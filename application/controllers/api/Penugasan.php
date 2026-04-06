<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property Penugasan_model $Penugasan_model
 * @property Excel_handler   $excel_handler
 */
class Penugasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penugasan_model');
        $this->load->library('excel_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'karantina'  => strtoupper(trim($this->input->get('karantina', TRUE) ?? '')),
            'petugas'    => $this->input->get('petugas', TRUE),
            'start_date' => $this->input->get('start_date', TRUE),
            'end_date'   => $this->input->get('end_date', TRUE),
        ];
    }

    public function index()
    {
        $filter = $this->buildFilter();
        if (empty($filter['petugas']) && !in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $data = $this->Penugasan_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function export_excel()
    {
        $filter = $this->buildFilter();
        $rows   = $this->Penugasan_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.', 'Nomor Surtug', 'Tgl Surtug',
            'No Permohonan', 'Tgl Permohonan',
            'UPT', 'Satpel',
            'Nama Petugas', 'NIP Petugas',
            'Jenis Tugas',
            'Negara Asal', 'Daerah Asal',
            'Negara Tujuan', 'Daerah Tujuan',
            'Komoditas', 'Nama Tercetak', 'HS Code',
            'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4',
            'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8',
            'Satuan'
        ];

        $exportData = [];
        $no         = 1;
        $lastSurtug = null;

        foreach ($rows as $r) {
            $isIdem = ($r['nomor_surtug'] === $lastSurtug);

            $exportData[] = [
                $isIdem ? '' : $no++,
                $r['nomor_surtug'],
                $r['tgl_surtug'],
                $r['no_dok_permohonan'],
                $r['tgl_dok_permohonan'],
                $r['upt'],
                $r['satpel'],
                $r['nama_petugas'],
                $r['nip_petugas'],
                $r['jenis_tugas'],
                $r['negara_asal'],
                $r['daerah_asal'],
                $r['negara_tujuan'],
                $r['daerah_tujuan'],
                $r['nama_komoditas'],
                $r['nama_umum_tercetak'],
                $r['kode_hs'],
                (float) ($r['volumeP1'] ?? 0),
                (float) ($r['volumeP2'] ?? 0),
                (float) ($r['volumeP3'] ?? 0),
                (float) ($r['volumeP4'] ?? 0),
                (float) ($r['volumeP5'] ?? 0),
                (float) ($r['volumeP6'] ?? 0),
                (float) ($r['volumeP7'] ?? 0),
                (float) ($r['volumeP8'] ?? 0),
                $r['nama_satuan'],
            ];

            $lastSurtug = $r['nomor_surtug'];
        }

        $title      = "LAPORAN PENUGASAN PETUGAS KARANTINA";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Penugasan");

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Laporan_Penugasan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}
