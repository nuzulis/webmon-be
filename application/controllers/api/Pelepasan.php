<?php
defined('BASEPATH') OR exit('No direct script access allowed');

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
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth) return $this->json(401);

        $filter = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper($this->input->get('karantina', true)),
            'lingkup'    => $this->input->get('lingkup', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'search'     => $this->input->get('search', true),
            'sort_by'    => $this->input->get('sort_by', true),
            'sort_order' => $this->input->get('sort_order', true),
        ];

        if (!in_array($filter['karantina'], ['H','I','T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $ids = $this->Pelepasan_model->getIds($filter, $perPage, $offset);
        $data = $this->Pelepasan_model->getByIds($ids);
        $total = $this->Pelepasan_model->countAll($filter);

        return $this->json([
            'success' => true,
            'data'    => $data,
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
        $filter = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper($this->input->get('karantina', true)),
            'lingkup'    => $this->input->get('lingkup', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'search'     => $this->input->get('search', true),
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
            
            'Satuan', 'Harga Barang (Rp)', 'Kontainer', 'Dokumen Pendukung'
        ];

        $exportData = [];
        $no = 0;
        $lastId = null;

        foreach ($rows as $r) {
            $isIdem = ($r['id'] === $lastId);
            if (!$isIdem) { $no++; }

            $exportData[] = [
                $isIdem ? '' : $no,
                $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'),
                $isIdem ? 'Idem' : $r['no_aju'],
                $isIdem ? 'Idem' : $r['tgl_aju'],
                $isIdem ? 'Idem' : $r['no_dok_permohonan'],
                $isIdem ? 'Idem' : $r['tgl_dok_permohonan'],
                $isIdem ? 'Idem' : $r['upt'],
                $isIdem ? 'Idem' : $r['satpel'],
                $isIdem ? 'Idem' : $r['nama_tempat_pemeriksaan'],
                $isIdem ? 'Idem' : $r['alamat_tempat_pemeriksaan'],
                $isIdem ? 'Idem' : $r['tgl_pemeriksaan'],
                $isIdem ? 'Idem' : $r['nama_pemohon'],
                $isIdem ? 'Idem' : $r['alamat_pemohon'],
                $isIdem ? 'Idem' : $r['nomor_identitas_pemohon'],
                $isIdem ? 'Idem' : $r['nama_pengirim'],
                $isIdem ? 'Idem' : $r['alamat_pengirim'],
                $isIdem ? 'Idem' : $r['nomor_identitas_pengirim'],
                $isIdem ? 'Idem' : $r['nama_penerima'],
                $isIdem ? 'Idem' : $r['alamat_penerima'],
                $isIdem ? 'Idem' : $r['nomor_identitas_penerima'],
                $isIdem ? 'Idem' : $r['asal'],
                $isIdem ? 'Idem' : $r['kota_asal'],
                $isIdem ? 'Idem' : $r['pelabuhanasal'],
                $isIdem ? 'Idem' : $r['tujuan'],
                $isIdem ? 'Idem' : $r['kota_tujuan'],
                $isIdem ? 'Idem' : $r['pelabuhantuju'],
                $isIdem ? 'Idem' : $r['moda'],
                $isIdem ? 'Idem' : $r['nama_alat_angkut_terakhir'],
                $isIdem ? 'Idem' : $r['no_voyage_terakhir'],
                $isIdem ? 'Idem' : $r['kemas'],
                $isIdem ? 'Idem' : $r['total_kemas'],
                $isIdem ? 'Idem' : $r['tanda_khusus'],
                $isIdem ? 'Idem' : $r['nkt'],
                $isIdem ? 'Idem' : $r['seri'],
                $isIdem ? 'Idem' : $r['tanggal_lepas'],
                $r['klasifikasi'] ?? '-',
                $r['komoditas'] ?? '-',
                $r['nama_umum_tercetak'] ?? '-',
                $r['hs'] ?? '-',
                $r['vol_p1'], $r['vol_p2'], $r['vol_p3'], $r['vol_p4'], 
                $r['vol_p5'], $r['vol_p6'], $r['vol_p7'], $r['vol_p8'],
                $r['volume_lain'],
                $r['net_p1'], $r['net_p2'], $r['net_p3'], $r['net_p4'], 
                $r['net_p5'], $r['net_p6'], $r['net_p7'], $r['net_p8'],

                $r['satuan'],
                $r['harga_rp'],
                $isIdem ? 'Idem' : ($r['kontainer_string'] ?? '-'),
                $isIdem ? 'Idem' : ($r['dokumen_pendukung_string'] ?? '-')
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