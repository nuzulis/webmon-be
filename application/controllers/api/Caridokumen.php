<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input          $input
 * @property CI_Output         $output
 * @property CI_Config         $config
 * @property Caridokumen_model $caridokumen
 */

class Caridokumen extends MY_Controller
    {
        public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        
        // 2. Izinkan method yang diperlukan
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
        
        // 3. Izinkan Header Authorization (untuk JWT) dan Content-Type
        header("Access-Control-Allow-Headers: Content-Type, Content-Length, Accept-Encoding, Authorization");
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            header("HTTP/1.1 200 OK");
            exit;
        }

        $this->load->model('Caridokumen_model', 'caridokumen');
        $this->load->helper(['jwt']);
    }

    public function search()
    {
        // --- 1. VALIDASI JWT ---
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Token tidak ditemukan']);
        }

        $key = 'WEBMON_SUPER_SECRET_KEY_GANTI_INI';
        $decoded = jwt_decode($m[1], $key);

        if (!$decoded) {
            return $this->json(401, ['success' => false, 'message' => 'Token tidak valid atau kedaluwarsa']);
        }

        $user_upt_id = $decoded['upt'] ?? null; // Menggunakan 'upt' sesuai isi token
        $user_role   = $decoded['detil'] ?? [];

        // --- 2. VALIDASI INPUT ---
        $filter    = $this->input->post('filter', TRUE);
        $pencarian = $this->input->post('pencarian', TRUE);

        if (!$filter || !$pencarian) {
            return $this->json(400, ['success' => false, 'message' => 'Filter dan kata kunci harus diisi']);
        }

        // --- 3. LOGIC (DENGAN FILTER UPT) ---
        $pencarian = $this->caridokumen->replaceDok($pencarian);
        $dataPtk = $this->caridokumen->getPtk($filter, $pencarian, $user_upt_id);

        if (!$dataPtk) {
            return $this->json(404, [
                'success' => false,
                'message' => 'Data PTK tidak ditemukan'
            ]);
        }

        $mapKarantina = [
    'hewan' => 'kh',
    'tumbuhan' => 'kt',
    'ikan' => 'ki'
];

$jenis = strtolower($dataPtk['jenis_karantina']);
// Jika tidak ditemukan di map, default ke kode aslinya
$kode_karantina = isset($mapKarantina[$jenis]) ? $mapKarantina[$jenis] : substr($jenis, 0, 2);

$history = $this->caridokumen->getHistory($dataPtk['id'], $kode_karantina);
        $respon_ssm = '';
        if (in_array($dataPtk['jenis_permohonan'], ['IM', 'EX'], true)) {
            $respon_ssm = $this->caridokumen->buildResponSsm(
                $history,
                $dataPtk['tssm_id']
            );
        }

        // ================= RESPONSE =================
        return $this->json(200, [
            'success' => true,
            'message' => 'Berhasil load data',
            'data' => [
                'dataPtk'     => $dataPtk,
                'tbl_ptk'     => $this->caridokumen->setValueTblPtk($dataPtk),
                'riwayat_dok' => $this->caridokumen->setValueRiwayat($history),
                'komoditas'   => $this->caridokumen->getKomoditas($dataPtk['id'], $dataPtk['jenis_karantina']),
                'kontainer'   => $this->caridokumen->getKontainer($dataPtk['id']),
                'dokumen'     => $this->caridokumen->getDokumen($dataPtk['id']),
                'singmat'     => $this->caridokumen->getSingmat($dataPtk['id']),
                'kuitansi'    => $this->caridokumen->getKuitansiHtml($dataPtk['id']),
                'respon_ssm'  => $respon_ssm
            ]
        ]);
    }

    private function json($status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
