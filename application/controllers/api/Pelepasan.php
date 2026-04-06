<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input       $input
 * @property Pelepasan_model $Pelepasan_model
 * @property Excel_handler  $excel_handler
 */
class Pelepasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pelepasan_model');
        $this->load->library('excel_handler');
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

        $filter = [
        'upt'        => $this->input->post('upt', true),
        'karantina'  => strtoupper($this->input->post('karantina', true)),
        'lingkup'    => $this->input->post('lingkup', true),
        'start_date' => $this->input->post('start_date', true),
        'end_date'   => $this->input->post('end_date', true),
        ];

        $rows = $this->Pelepasan_model->getFullData($filter);

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

        $exportData = [];
        $no = 0;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            if (!$isIdem) { $no++; }

            $exportData[] = [
                $isIdem ? '' : $no,
                $r['tssm_id'] ? 'SSM' : 'PTK',
                $r['no_aju'],
                $r['tgl_aju'],
                $r['no_dok_permohonan'],
                $r['tgl_dok_permohonan'],
                $r['upt'],
                $r['satpel'],
                $r['nama_tempat_pemeriksaan'],
                $r['alamat_tempat_pemeriksaan'],
                $r['tgl_pemeriksaan'],
                $r['nama_pemohon'],
                $r['alamat_pemohon'],
                $r['nomor_identitas_pemohon'],
                $r['nama_pengirim'],
                $r['alamat_pengirim'],
                $r['nomor_identitas_pengirim'],
                $r['nama_penerima'],
                $r['alamat_penerima'],
                $r['nomor_identitas_penerima'],
                $r['asal'],
                $r['kota_asal'],
                $r['pelabuhanasal'],
                $r['tujuan'],
                $r['kota_tujuan'],
                $r['pelabuhantuju'],
                $r['moda'],
                $r['nama_alat_angkut_terakhir'],
                $r['no_voyage_terakhir'],
                $r['kemas'],
                (float) ($r['total_kemas'] ?? 0),
                $r['tanda_khusus'],
                $r['nkt'],
                $r['seri'],
                $r['tanggal_lepas'],
                $r['klasifikasi'] ?? '-',
                $r['komoditas'] ?? '-',
                $r['nama_umum_tercetak'] ?? '-',
                $r['hs'] ?? '-',
                (float) ($r['vol_p1'] ?? 0), 
                (float) ($r['vol_p2'] ?? 0), 
                (float) ($r['vol_p3'] ?? 0), 
                (float) ($r['vol_p4'] ?? 0), 
                (float) ($r['vol_p5'] ?? 0), 
                (float) ($r['vol_p6'] ?? 0), 
                (float) ($r['vol_p7'] ?? 0), 
                (float) ($r['vol_p8'] ?? 0),
                (float) ($r['volume_lain'] ?? 0),
                (float) ($r['net_p1'] ?? 0), 
                (float) ($r['net_p2'] ?? 0), 
                (float) ($r['net_p3'] ?? 0), 
                (float) ($r['net_p4'] ?? 0), 
                (float) ($r['net_p5'] ?? 0), 
                (float) ($r['net_p6'] ?? 0), 
                (float) ($r['net_p7'] ?? 0), 
                (float) ($r['net_p8'] ?? 0),
                $r['satuan_netto'] ?? '-',
                $r['satuan_bruto'] ?? '-',
                $r['satuan_lain'] ?? '-',
                (float) ($r['harga_rp'] ?? 0),
                $r['kontainer_string'] ?? '-',
                $r['dokumen_pendukung_string'] ?? '-'
            ];
            $lastId = $r['id'];
        }

        $title = "LAPORAN PELEPASAN (" . $filter['karantina'] . ")";
        $reportInfo = $this->buildReportHeader($title, $filter, $rows);
        
        $this->logActivity("EXPORT EXCEL PELEPASAN $filter[karantina]");

        if (ob_get_length()) ob_end_clean();
        return $this->excel_handler->download("Laporan_Pelepasan_" . date('Ymd_His'), $headers, $exportData, $reportInfo);
    }
}