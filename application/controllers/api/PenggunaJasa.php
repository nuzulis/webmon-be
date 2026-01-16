<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property CI_Input                $input
 * @property CI_Output               $output
 * @property CI_Config               $config
 * @property PenggunaJasa_model      $PenggunaJasa_model
 * @property Excel_handler              $excel_handler
 */ 
class PenggunaJasa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PenggunaJasa_model');
        $this->load->helper('jwt');
        $this->load->library('excel_handler');
    }

    public function index()
    {
        /* ================= JWT GUARD ================= */
        $auth = $this->input->get_request_header('Authorization', true);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'permohonan' => strtoupper(trim($this->input->get('permohonan', true))),
        ];

        /* ================= DATA ================= */
        $rows = $this->PenggunaJasa_model->getList($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $rows,
            'meta'    => [
                'total' => count($rows)
            ]
        ]);
    }
    public function export_excel()
    {
        /* ================= FILTER ================= */
        $filters = [
            'upt'        => $this->input->get('upt', true),
            'permohonan' => $this->input->get('permohonan', true), // Ini mapping ke lingkup_aktifitas
        ];

        $rows = $this->PenggunaJasa_model->getList($filters, true);

        // 2. Persiapan Header Excel
        $headers = [
            'No', 
            'Nama Pemohon', 
            'Jenis Perusahaan', 
            'Nama Perusahaan', 
            'Identitas (NPWP/KTP)', 
            'Nomor Identitas', 
            'NITKU',
            'UPT Registrasi', 
            'Lingkup Aktivitas', 
            'Rerata Frekuensi', 
            'Daftar Komoditas', 
            'Tempat Karantina', 
            'Status Kepemilikan',
            'Email', 
            'Nomor Registrasi', 
            'Tanggal Registrasi', 
            'Status Blokir'
        ];

        // 3. Mapping Data dengan Nomor Urut
        $exportData = [];
        $no = 1;

        foreach ($rows as $r) {
            $exportData[] = [
                $no++,
                $r['pemohon'],
                $r['jenis_perusahaan'],
                $r['nama_perusahaan'],
                $r['jenis_identitas'],
                $r['nomor_identitas'],
                $r['nitku'],
                $r['upt'],
                $r['lingkup_aktifitas'],
                $r['rerata_frekuensi'],
                $r['daftar_komoditas'],
                $r['tempat_karantina'],
                $r['status_kepemilikan'],
                $r['email'],
                $r['nomor_registrasi'],
                $r['tgl_registrasi'],
                ($r['blockir'] === 1 ? 'Terblokir' : 'Aktif') // Konversi status blokir
            ];
        }

        $title = "DATA REGISTRASI PENGGUNA JASA";
        $this->load->library('excel_handler');
        $reportInfo = $this->buildReportHeader($title, $filters);

        return $this->excel_handler->download("Laporan_PenggunaJasa", $headers, $exportData, $reportInfo);
    }

    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}