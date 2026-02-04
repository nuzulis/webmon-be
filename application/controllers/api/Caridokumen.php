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
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
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
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401);
        }

        $key = 'WEBMON_SUPER_SECRET_KEY_GANTI_INI';
        $decoded = jwt_decode($m[1], $key);

        if (!$decoded) {
            return $this->json(401);
        }

        $user_upt_id = $decoded['upt'] ?? null;
        $user_role   = $decoded['detil'] ?? [];
        $filter    = $this->input->post('filter', TRUE);
        $pencarian = $this->input->post('pencarian', TRUE);

        if (!$filter || !$pencarian) {
            return $this->json(400);
        }
        
        $pencarian = $this->caridokumen->replaceDok($pencarian);
        $dataPtk = $this->caridokumen->getPtk($filter, $pencarian, $user_upt_id);

        if (!$dataPtk) {
            return $this->json(404);
        }

        $mapKarantina = [
    'hewan' => 'kh',
    'tumbuhan' => 'kt',
    'ikan' => 'ki'
];

$jenis = strtolower($dataPtk['jenis_karantina']);
$kode_karantina = isset($mapKarantina[$jenis]) ? $mapKarantina[$jenis] : substr($jenis, 0, 2);

$history = $this->caridokumen->getHistory(
    $dataPtk['id'],
    $dataPtk['jenis_karantina']
);
        $respon_ssm = '';
        if (in_array($dataPtk['jenis_permohonan'], ['IM', 'EX'], true)) {
           $respon_ssm = $this->caridokumen->buildResponSsmJson(
                $history,
                $dataPtk['tssm_id']
            );

        }

        return $this->json([
            'success' => true,
            'message' => 'Berhasil load data',
            'data' => [
                'dataPtk'     => $dataPtk,
                'riwayat_dok' => $this->caridokumen->setValueRiwayatJson($history),
                'komoditas'   => $this->caridokumen->getKomoditas($dataPtk['id'], $dataPtk['jenis_karantina']),
                'kontainer'   => $this->caridokumen->getKontainer($dataPtk['id']),
                'dokumen'     => $this->caridokumen->getDokumen($dataPtk['id']),
                'singmat'     => $this->caridokumen->getSingmat($dataPtk['id']),
                'kuitansi'    => $this->caridokumen->getKuitansiHtml($dataPtk['id']),
                'respon_ssm'  => $respon_ssm
            ]
        ], 200);
    }

}
