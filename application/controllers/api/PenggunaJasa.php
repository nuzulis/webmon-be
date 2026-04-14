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
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $filter = $this->buildFilter();

        try {
            $rows = $this->PenggunaJasa_model->getFullData($filter);
            log_message('info', '[PenggunaJasa::export_csv] rows fetched: ' . count($rows));
        } catch (\Throwable $e) {
            log_message('error', '[PenggunaJasa::export_csv] DB error: ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Gagal mengambil data'], 500);
        }

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

        $asText = fn($v) => '="' . str_replace('"', '""', trim((string) ($v ?? ''))) . '"';

        $no = 1;
        $rowGen = (function () use ($rows, $asText, &$no) {
            foreach ($rows as $r) {
                $lingkupArr  = json_decode($r['lingkup_aktifitas'], true) ?: [];
                $lingkupTxt  = implode("; ", array_column($lingkupArr, 'activity'));

                $komoditasArr = json_decode($r['daftar_komoditas'], true) ?: [];
                $komoditasTxt = implode("; ", array_filter($komoditasArr, fn($v) => !empty($v)));

                yield [
                    $no++,
                    $r['pemohon'],
                    $r['jenis_perusahaan'],
                    $r['nama_perusahaan'],
                    $r['jenis_identitas'],
                    $asText($r['nomor_identitas']),
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
        })();

        $this->logActivity("EXPORT CSV: Pengguna Jasa");

        try {
            if (ob_get_level() > 0) ob_end_clean();
            log_message('info', '[PenggunaJasa::export_csv] starting CSV stream');
            $this->csv_handler->download("Laporan_PenggunaJasa_" . date('Ymd_His'), $headers, $rowGen);
        } catch (\Throwable $e) {
            log_message('error', '[PenggunaJasa::export_csv] CSV write error: ' . $e->getMessage());
            if (!headers_sent()) {
                if (ob_get_level() > 0) ob_end_clean();
                return $this->json(['success' => false, 'message' => 'Gagal membuat file CSV'], 500);
            }
            exit;
        }
    }
}
