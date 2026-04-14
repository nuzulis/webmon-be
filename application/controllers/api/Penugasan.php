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
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $filter = $this->buildFilter();

        if (empty($filter['petugas']) && !in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        if (empty($filter['start_date']) || empty($filter['end_date'])) {
            return $this->json(['success' => false, 'message' => 'Parameter start_date dan end_date wajib diisi'], 400);
        }

        try {
            $rows = $this->Penugasan_model->getFullData($filter);
            log_message('info', '[Penugasan::export_excel] rows fetched: ' . count($rows));
        } catch (\Throwable $e) {
            log_message('error', '[Penugasan::export_excel] DB error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Gagal mengambil data'], 500);
        }

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

        $clean  = fn($v) => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($v ?? '')));
        $fmt    = fn($v) => number_format((float) ($v ?? 0), 2, ',', '.');
        $asText = fn($v) => '="' . str_replace('"', '""', trim((string) ($v ?? ''))) . '"';

        $no         = 0;
        $lastSurtug = null;
        $rowGen = (function () use ($rows, $clean, $fmt, $asText, &$no, &$lastSurtug) {
            foreach ($rows as $r) {
                if ($r['nomor_surtug'] !== $lastSurtug) {
                    $no++;
                    $lastSurtug = $r['nomor_surtug'];
                }
                yield [
                    $no,
                    $clean($r['nomor_surtug']),
                    $clean($r['tgl_surtug']),
                    $clean($r['no_dok_permohonan']),
                    $clean($r['tgl_dok_permohonan']),
                    $clean($r['upt']),
                    $clean($r['satpel']),
                    $clean($r['nama_petugas']),
                    $asText($r['nip_petugas']),
                    $clean($r['jenis_tugas']),
                    $clean($r['negara_asal']),
                    $clean($r['daerah_asal']),
                    $clean($r['negara_tujuan']),
                    $clean($r['daerah_tujuan']),
                    $clean($r['nama_komoditas']),
                    $clean($r['nama_umum_tercetak']),
                    $asText($r['kode_hs']),
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
        })();

        $title      = "LAPORAN PENUGASAN PETUGAS KARANTINA";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL: Penugasan");

        try {
            if (ob_get_level() > 0) ob_end_clean();
            log_message('info', '[Penugasan::export_excel] starting CSV stream');
            $this->csv_handler->download(
                "Laporan_Penugasan_" . date('Ymd'),
                $headers,
                $rowGen,
                $reportInfo
            );
        } catch (\Throwable $e) {
            log_message('error', '[Penugasan::export_excel] CSV write error: ' . $e->getMessage());
            if (!headers_sent()) {
                if (ob_get_level() > 0) ob_end_clean();
                return $this->json(['success' => false, 'message' => 'Gagal membuat file CSV'], 500);
            }
            exit;
        }
    }
}
