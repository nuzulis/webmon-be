<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Pelepasan_model    $Pelepasan_model
 * @property Excel_handler      $excel_handler
 */
class Pelepasan extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Pelepasan_model');
        $this->load->helper(['jwt']);
        $this->load->library('excel_handler');
    }

    public function index()
    {
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth) return $this->json(401);
        $filters = [
            'upt'       => $this->input->get('upt', true),
            'karantina' => strtoupper($this->input->get('karantina', true) ?? 'H'),
            'lingkup'   => $this->input->get('lingkup', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'search'     => $this->input->get('search', true),
        ];

        if (!in_array($filters['karantina'], ['H','I','T'], true)) {
            return $this->json(['success' => false, 'message' => 'Parameter karantina tidak valid'], 400);
        }

        $page    = max((int) $this->input->get('page'), 1);
        $perPage = (int) $this->input->get('per_page') ?: 10;
        $offset  = ($page - 1) * $perPage;
        $data  = $this->Pelepasan_model->getList($filters, $perPage, $offset);
        $total = $this->Pelepasan_model->countAll($filters);

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
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'karantina'  => strtoupper($this->input->get('karantina', true)),
            'lingkup'    => $this->input->get('lingkup', true),
            'start_date' => $this->input->get('start_date', true),
            'end_date'   => $this->input->get('end_date', true),
            'search'     => $this->input->get('search', true),
        ];
        $rows = $this->Pelepasan_model->getExportByFilter($filters);

        $headers = [
            'No.', 'Pengajuan via', 'No. Aju', 'Tgl Aju', 'No. K.1.1', 'Tgl K.1.1', 'UPT', 'Satpel',
            'Tempat Periksa', 'Alamat Tempat Periksa', 'Tgl Periksa', 'Pemohon', 'Alamat Pemohon', 'Identitas Pemohon',
            'Pengirim', 'Alamat Pengirim', 'Identitas Pengirim', 'Penerima', 'Alamat Penerima', 'Identitas Penerima',
            'Asal', 'Daerah Asal', 'Pelabuhan Asal', 'Tujuan', 'Daerah Tujuan', 'Pelabuhan Tujuan',
            'Moda Alat Angkut', 'Nama Alat Angkut', 'Nomor Voyage', 'Jenis Kemasan', 'Jumlah Kemasan', 'Tanda Kemasan',
            'Nomor Dokumen', 'Nomor Seri', 'Tgl Dokumen', 'Klasifikasi', 'Komoditas', 'Nama Tercetak', 'Kode HS',
            'volumeP1', 'volumeP2', 'volumeP3', 'volumeP4', 'volumeP5', 'volumeP6', 'volumeP7', 'volumeP8',
            'satuan', 'Harga Barang (Rp)', 'Kontainer', 'Dokumen Pendukung'
            ];

        $exportData = [];
        $no = 1;
        $lastId = null;

        foreach ($rows as $r) {
        $isIdem = ($r['id'] === $lastId);
        $exportData[] = [
        $isIdem ? '' : $no++,
        $isIdem ? 'Idem' : (isset($r['tssm_id']) ? 'SSM' : 'PTK'),
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
            $r['total_kemas'],
            $r['tanda_khusus'],
            $r['nkt'],
            $r['seri'],
            $r['tanggal_lepas'],
            $r['klasifikasi'],
            $r['komoditas'],
            $r['nama_umum_tercetak'],
            $r['hs'],
            $r['p1'], $r['p2'], $r['p3'], $r['p4'], 
            $r['p5'], $r['p6'], $r['p7'], $r['p8'],
            $r['satuan'],
            $r['harga_rp'],
            $r['kontainer_string'],
            $r['dokumen_pendukung_string']
        ];
        $lastId = $r['id'];
        }

        $title = "LAPORAN PELEPASAN (" . $filters['karantina'] . ")";
        $reportInfo = $this->buildReportHeader($title, $filters, $rows);
        return $this->excel_handler->download("Laporan_Pelepasan", $headers, $exportData, $reportInfo);
    }
}