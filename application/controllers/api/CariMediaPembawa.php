<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input                  $input
 * @property CI_Output                 $output
 * @property Carimediapembawa_model    $carimediapembawa
 * @property Excel_handler             $excel_handler
 */
class CariMediaPembawa extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization");

        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit;
        }

        $this->load->model('CariMediaPembawa_model', 'carimediapembawa');
        $this->load->helper(['jwt']);
        $this->load->config('jwt');
        $this->load->library('excel_handler');
    }

    public function search()
    {
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $key     = $this->config->item('jwt_key');
        $decoded = jwt_decode($m[1], $key);

        if (!$decoded) {
            return $this->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $user_upt_id = $decoded['upt'] ?? null;
        $keyword   = trim($this->input->post('keyword', TRUE));
        $karantina = strtoupper(trim($this->input->post('karantina', TRUE)));

        if (!$keyword) {
            return $this->json(['success' => false, 'message' => 'Keyword pencarian wajib diisi'], 400);
        }

        $allowedKarantina = ['H', 'I', 'T'];
        if (!in_array($karantina, $allowedKarantina, true)) {
            return $this->json(['success' => false, 'message' => 'Jenis karantina wajib diisi'], 400);
        }
        $rows = $this->carimediapembawa->searchMediaPembawa(
            $keyword,
            $karantina,
            $user_upt_id
        );

        if (!$rows) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        return $this->json([
            'success' => true,
            'message' => 'Berhasil load data',
            'total'   => count($rows),
            'data'    => $rows
        ], 200);
    }

    public function export_excel()
    {
        $keyword   = trim($this->input->get('keyword', TRUE));
        $karantina = strtoupper(trim($this->input->get('karantina', TRUE)));

        if (!$keyword) {
            return $this->json(['success' => false, 'message' => 'wajib diisi'], 400);
        }

        $allowedKarantina = ['H', 'I', 'T'];
        if (!in_array($karantina, $allowedKarantina, true)) {
            return $this->json(['success' => false, 'message' => 'Jenis karantina tidak valid (H/I/T)'], 400);
        }

        $user_upt_id = (string) ($this->user['upt'] ?? '');

        $rows = $this->carimediapembawa->searchMediaPembawa($keyword, $karantina, $user_upt_id);

        if (empty($rows)) {
            return $this->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $headers = [
            'No.',  'UPT',  'Satpel', 'No Dok Permohonan', 'Klasifikasi', 'Komoditas',
            'Nama Umum Tercetak', 'Kode HS', 'Vol. Bruto', 'Vol. Netto',
            'Vol. Lain', 'Satuan', 'Tgl Permohonan', 'Pemohon',
            'Penerima', 'Pengirim', 'Asal', 'Tujuan',
            'Kota Asal', 'Kota Tujuan', 'Jenis Karantina', 'Jenis Permohonan',
            'Jml Kontainer', 'No. Kontainer', 'Segel', 'No. P8', 'No. Seri P8',  'Nama TTD P8', 'Tgl P8'
        ];

        $exportData = [];
        $no = 0;

        foreach ($rows as $r) {
            $no++;
            $exportData[] = [
                $no,
                $r['nama_upt'] ?? '-',
                $r['satpel'] ?? '-',
                $r['no_dok_permohonan'] ?? '-',
                $r['klasifikasi'] ?? '-',
                $r['komoditas'] ?? '-',
                $r['nama_umum_tercetak'] ?? '-',
                $r['kode_hs'] ?? '-',
                (float) ($r['volume_bruto'] ?? 0),
                (float) ($r['volume_netto'] ?? 0),
                (float) ($r['volume_lain'] ?? 0),
                $r['satuan'] ?? '-',
                $r['tgl_dok_permohonan'] ?? '-',
                $r['nama_pemohon'] ?? '-',
                $r['nama_penerima'] ?? '-',
                $r['nama_pengirim'] ?? '-',
                $r['asal'] ?? '-',
                $r['tujuan'] ?? '-',
                $r['kota_asal'] ?? '-',
                $r['kota_tujuan'] ?? '-',
                $r['jenis_karantina'] ?? '-',
                $r['jenis_permohonan'] ?? '-',
                (int) ($r['jumlah_kontainer'] ?? 0),
                $r['nomor_kontainer'] ?? '-',
                $r['segel'] ?? '-',
                $r['nomor_p8'] ?? '-',
                $r['nomor_seri_p8'] ?? '-',
                $r['nama_ttd_p8'] ?? '-',
                $r['tgl_p8'] ?? '-',
            ];
        }

        $this->logActivity("EXPORT EXCEL CARI MEDIA PEMBAWA: " . $karantina);

        if (ob_get_length()) ob_end_clean();

        return $this->excel_handler->download(
            "Cari_Media_Pembawa_" . date('Ymd_His'),
            $headers,
            $exportData
        );
    }
}
