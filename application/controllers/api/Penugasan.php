<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input        $input
 * @property Penugasan_model $Penugasan_model
 * @property Csv_handler     $csv_handler
 */
class Penugasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Penugasan_model');
        $this->load->library('csv_handler');
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

        $clean = fn($v) => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($v ?? '')));
        $fmt   = fn($v)  => number_format((float) ($v ?? 0), 2, ',', '.');

        $exportData = [];
        $no         = 0;
        $lastSurtug = null;

        foreach ($rows as $r) {
            if ($r['nomor_surtug'] !== $lastSurtug) {
                $no++;
                $lastSurtug = $r['nomor_surtug'];
            }

            $exportData[] = [
                $no,
                $clean($r['nomor_surtug']),
                $clean($r['tgl_surtug']),
                $clean($r['no_dok_permohonan']),
                $clean($r['tgl_dok_permohonan']),
                $clean($r['upt']),
                $clean($r['satpel']),
                $clean($r['nama_petugas']),
                $clean($r['nip_petugas']),
                $clean($r['jenis_tugas']),
                $clean($r['negara_asal']),
                $clean($r['daerah_asal']),
                $clean($r['negara_tujuan']),
                $clean($r['daerah_tujuan']),
                $clean($r['nama_komoditas']),
                $clean($r['nama_umum_tercetak']),
                $clean($r['kode_hs']),
                $fmt($r['volumeP1']),
                $fmt($r['volumeP2']),
                $fmt($r['volumeP3']),
                $fmt($r['volumeP4']),
                $fmt($r['volumeP5']),
                $fmt($r['volumeP6']),
                $fmt($r['volumeP7']),
                $fmt($r['volumeP8']),
                $clean($r['nama_satuan']),
            ];
        }

        $title      = "LAPORAN PENUGASAN PETUGAS KARANTINA";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Penugasan");

        if (ob_get_length()) ob_end_clean();

        return $this->csv_handler->download(
            "Laporan_Penugasan_" . date('Ymd'),
            $headers,
            $exportData,
            $reportInfo
        );
    }
}
