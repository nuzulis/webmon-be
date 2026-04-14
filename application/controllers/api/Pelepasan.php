<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '1024M');
/**
 * @property CI_Input       $input
 * @property Pelepasan_model $Pelepasan_model
 * @property Csv_handler    $csv_handler
 */
class Pelepasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pelepasan_model');
        $this->load->library('csv_handler');
    }

    public function index()
    {
        $filter = [
            'upt'        => $this->input->post('upt', true),
            'karantina'  => strtoupper($this->input->post('karantina', true)),
            'lingkup'    => $this->input->post('lingkup', true),
            'start_date' => $this->input->post('start_date', true),
            'end_date'   => $this->input->post('end_date', true),
        ];

        if (!in_array($filter['karantina'], ['H','I','T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $data = $this->Pelepasan_model->getAll($filter);

        return $this->json([
            'success' => true,
            'data'    => $data,
            'total'   => count($data),
        ]);
    }

    public function export_excel()
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $filter = [
            'upt'        => $this->input->post('upt', true),
            'karantina'  => strtoupper($this->input->post('karantina', true)),
            'lingkup'    => $this->input->post('lingkup', true),
            'start_date' => $this->input->post('start_date', true),
            'end_date'   => $this->input->post('end_date', true),
        ];

        if (!in_array($filter['karantina'], ['H', 'I', 'T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        if (empty($filter['start_date']) || empty($filter['end_date'])) {
            return $this->json(['success' => false, 'message' => 'Parameter start_date dan end_date wajib diisi'], 400);
        }

        try {
            $rows = $this->Pelepasan_model->getFullData($filter);
            log_message('info', '[Pelepasan::export_excel] rows fetched: ' . count($rows));
        } catch (\Throwable $e) {
            log_message('error', '[Pelepasan::export_excel] DB error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Gagal mengambil data'], 500);
        }

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.', 'Pengajuan via', 'No. Aju', 'Tgl Aju', 'No. K.1.1', 'Tgl K.1.1', 'UPT', 'Satpel',
            'Tempat Periksa', 'Alamat Tempat Periksa', 'Tgl Periksa', 'Pemohon', 'Alamat Pemohon', 'Identitas Pemohon',
            'Pengirim', 'Alamat Pengirim', 'Identitas Pengirim', 'Penerima', 'Alamat Penerima', 'Identitas Penerima',
            'Asal', 'Daerah Asal', 'Pelabuhan Asal', 'Tujuan', 'Daerah Tujuan', 'Pelabuhan Tujuan',
            'Moda Alat Angkut', 'Nama Alat Angkut', 'Nomor Voyage', 'Jenis Kemasan', 'Jumlah Kemasan', 'Tanda Kemasan',
            'Nomor Dokumen', 'Nomor Seri', 'Tgl Dokumen', 'Klasifikasi', 'Komoditas', 'Nama Tercetak', 'Kode HS',
            'Vol P1', 'Vol P2', 'Vol P3', 'Vol P4', 'Vol P5', 'Vol P6', 'Vol P7', 'Vol P8', 'Vol Lain',
            'Netto P1', 'Netto P2', 'Netto P3', 'Netto P4', 'Netto P5', 'Netto P6', 'Netto P7', 'Netto P8',
            'Satuan Netto', 'Satuan Bruto', 'Satuan Lain', 'Harga Barang (Rp)', 'Kontainer', 'Dokumen Pendukung'
        ];

        $clean  = fn($v) => trim(str_replace(["\r\n", "\r", "\n"], ' ', (string) ($v ?? '')));
        $fmt    = fn($v) => number_format((float) ($v ?? 0), 2, ',', '.');
        $asText = fn($v) => '="' . str_replace('"', '""', trim((string) ($v ?? ''))) . '"';

        $no     = 0;
        $lastId = null;
        $rowGen = (function () use ($rows, $clean, $fmt, $asText, &$no, &$lastId) {
            foreach ($rows as $r) {
                if ($r['id'] !== $lastId) {
                    $no++;
                    $lastId = $r['id'];
                }
                yield [
                    $no,
                    $r['tssm_id'] ? 'SSM' : 'PTK',
                    $clean($r['no_aju']),
                    $clean($r['tgl_aju']),
                    $clean($r['no_dok_permohonan']),
                    $clean($r['tgl_dok_permohonan']),
                    $clean($r['upt']),
                    $clean($r['satpel']),
                    $clean($r['nama_tempat_pemeriksaan']),
                    $clean($r['alamat_tempat_pemeriksaan']),
                    $clean($r['tgl_pemeriksaan']),
                    $clean($r['nama_pemohon']),
                    $clean($r['alamat_pemohon']),
                    $asText($r['nomor_identitas_pemohon']),
                    $clean($r['nama_pengirim']),
                    $clean($r['alamat_pengirim']),
                    $asText($r['nomor_identitas_pengirim']),
                    $clean($r['nama_penerima']),
                    $clean($r['alamat_penerima']),
                    $asText($r['nomor_identitas_penerima']),
                    $clean($r['asal']),
                    $clean($r['kota_asal']),
                    $clean($r['pelabuhanasal']),
                    $clean($r['tujuan']),
                    $clean($r['kota_tujuan']),
                    $clean($r['pelabuhantuju']),
                    $clean($r['moda']),
                    $clean($r['nama_alat_angkut_terakhir']),
                    $clean($r['no_voyage_terakhir']),
                    $clean($r['kemas']),
                    $fmt($r['total_kemas']),
                    $clean($r['tanda_khusus']),
                    $clean($r['nkt']),
                    $clean($r['seri']),
                    $clean($r['tanggal_lepas']),
                    $clean($r['klasifikasi'] ?? '-'),
                    $clean($r['komoditas'] ?? '-'),
                    $clean($r['nama_umum_tercetak'] ?? '-'),
                    $asText($r['hs'] ?? '-'),
                    $fmt($r['vol_p1']),
                    $fmt($r['vol_p2']),
                    $fmt($r['vol_p3']),
                    $fmt($r['vol_p4']),
                    $fmt($r['vol_p5']),
                    $fmt($r['vol_p6']),
                    $fmt($r['vol_p7']),
                    $fmt($r['vol_p8']),
                    $fmt($r['volume_lain']),
                    $fmt($r['net_p1']),
                    $fmt($r['net_p2']),
                    $fmt($r['net_p3']),
                    $fmt($r['net_p4']),
                    $fmt($r['net_p5']),
                    $fmt($r['net_p6']),
                    $fmt($r['net_p7']),
                    $fmt($r['net_p8']),
                    $clean($r['satuan_netto'] ?? '-'),
                    $clean($r['satuan_bruto'] ?? '-'),
                    $clean($r['satuan_lain'] ?? '-'),
                    $fmt($r['harga_rp']),
                    $clean($r['kontainer_string'] ?? '-'),
                    $clean($r['dokumen_pendukung_string'] ?? '-'),
                ];
            }
        })();

        $title      = "LAPORAN PELEPASAN (" . $filter['karantina'] . ")";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);

        $this->logActivity("EXPORT EXCEL PELEPASAN $filter[karantina]");

        try {
            if (ob_get_level() > 0) ob_end_clean();
            log_message('info', '[Pelepasan::export_excel] starting CSV stream');
            $this->csv_handler->download("Laporan_Pelepasan_" . date('Ymd_His'), $headers, $rowGen, $reportInfo);
        } catch (\Throwable $e) {
            log_message('error', '[Pelepasan::export_excel] CSV write error: ' . $e->getMessage());
            if (!headers_sent()) {
                if (ob_get_level() > 0) ob_end_clean();
                return $this->json(['success' => false, 'message' => 'Gagal membuat file CSV'], 500);
            }
            exit;
        }
    }
}