<?php
defined('BASEPATH') OR exit('No direct script access allowed');
ini_set('memory_limit', '512M');
/**
 * @property CI_Input           $input
 * @property PenggunaJasa_model $PenggunaJasa_model
 * @property Csv_handler        $csv_handler
 */
class PenggunaJasa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PenggunaJasa_model');
        $this->load->library('csv_handler');
    }

    private function buildFilter(): array
    {
        return [
            'upt'        => $this->input->get('upt', TRUE),
            'permohonan' => $this->input->get('permohonan', TRUE),
        ];
    }

    public function index()
    {
        $filter = $this->buildFilter();
        $data   = $this->PenggunaJasa_model->getAll($filter);

        return $this->json(['success' => true, 'data' => $data]);
    }

    public function detail()
    {
        $input = json_decode(file_get_contents("php://input"), true);
        $id    = $input['id'] ?? null;

        if (!$id) {
            return $this->json(['success' => false, 'message' => 'ID parameter is required'], 400);
        }

        $profil = $this->PenggunaJasa_model->get_profil_lengkap($id);

        if (!$profil) {
            return $this->json(['success' => false, 'message' => 'Pengguna jasa tidak ditemukan'], 404);
        }

        $history = $this->PenggunaJasa_model->get_history_ptk($profil['uid']);

        return $this->json([
            'success' => true,
            'data'    => [
                'profil'  => $profil,
                'history' => $history ?: [],
            ]
        ]);
    }

    public function export_csv()
    {
        $filter = $this->buildFilter();
        $rows   = $this->PenggunaJasa_model->getFullData($filter);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No', 'Nama Pemohon', 'Jenis Perusahaan', 'Nama Perusahaan',
            'Identitas', 'Nomor Identitas', 'NITKU', 'UPT Registrasi',
            'Lingkup Aktivitas', 'Rerata Frekuensi', 'Daftar Komoditas',
            'Tempat Karantina', 'Status Kepemilikan', 'Email',
            'Nomor Registrasi', 'Tanggal Registrasi', 'Status Blokir'
        ];

        $exportData = [];
        $no = 1;
        foreach ($rows as $r) {
            $lingkupArr  = json_decode($r['lingkup_aktifitas'], true) ?: [];
            $lingkupTxt  = implode("; ", array_column($lingkupArr, 'activity'));

            $komoditasArr = json_decode($r['daftar_komoditas'], true) ?: [];
            $komoditasTxt = implode("; ", array_filter($komoditasArr, function ($v) {
                return !empty($v);
            }));

            $exportData[] = [
                $no++,
                $r['pemohon'],
                $r['jenis_perusahaan'],
                $r['nama_perusahaan'],
                $r['jenis_identitas'],
                $r['nomor_identitas'],
                $r['nitku'],
                $r['upt'],
                $lingkupTxt ?: '-',
                $r['rerata_frekuensi'],
                $komoditasTxt ?: '-',
                ($r['tempat_karantina'] == 1 ? 'Internal' : 'Luar'),
                $r['status_kepemilikan'],
                $r['email'],
                $r['nomor_registrasi'],
                $r['tgl_registrasi'],
                ($r['blockir'] == 1 ? 'Terblokir' : 'Aktif'),
            ];
        }

        $this->logActivity("EXPORT CSV: Pengguna Jasa");

        if (ob_get_length()) ob_end_clean();
        return $this->csv_handler->download("Laporan_PenggunaJasa_" . date('Ymd_His'), $headers, $exportData);
    }
}
