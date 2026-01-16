<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input  $input
 * @property CI_Output $output
 * @property CI_Config $config
 * @property Ecert_model $Ecert_model
 */
class Ecert extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('Ecert_model');
        $this->load->helper('jwt');
    }

    public function index()
    {
        /* ================= JWT ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, ['success' => false, 'message' => 'Unauthorized']);
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, ['success' => false, 'message' => 'Token tidak valid']);
        }

        /* ================= FILTER ================= */
        $filters = [
            'karantina'  => trim($this->input->get('karantina')),
            'start_date' => $this->input->get('start_date'),
            'end_date'   => $this->input->get('end_date'),
            'negara'     => $this->input->get('negara'),
            'upt'        => $this->input->get('upt'),
        ];

        $result = $this->Ecert_model->fetch($filters);

        if (!$result['success']) {
            return $this->json(400, $result);
        }

        return $this->json(200, [
            'success' => true,
            'data'    => $this->format($result['data'])
        ]);
    }

    /**
     * Normalisasi output agar FE bersih
     */
    private function format(array $rows): array
    {
        return array_map(function ($r) {
            return [
                'no_cert'      => $r['no_cert'] ?? '',
                'tgl_cert'     => $r['tgl_cert'] ?? '',
                'komoditas'    => $r['komo_eng'] ?? '',
                'negara_asal'  => $r['neg_asal'] ?? '',
                'tujuan'       => $r['tujuan'] ?? '',
                'port_tujuan'  => $r['port_tujuan'] ?? '',
                'upt'          => $r['upt'] ?? '',
                'id_cert'      => $r['id_cert'] ?? '',
                'data_from'    => $r['data_from'] ?? '',
            ];
        }, $rows);
    }

    private function json(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
