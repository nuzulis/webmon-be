<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input $input
 * @property CI_Output $output
 * @property CI_Config $config
 * @property KwitansiBatal_model $KwitansiBatal_model
 */
class KwitansiBatal extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('KwitansiBatal_model');
        $this->load->helper('jwt');
    }

    public function index()
    {
        /* ================= JWT ================= */
        $auth = $this->input->get_request_header('Authorization', TRUE);
        if (!$auth || !preg_match('/Bearer\s+(\S+)/', $auth, $m)) {
            return $this->json(401, 'Unauthorized');
        }

        try {
            
        } catch (Exception $e) {
            return $this->json(401, 'Token tidak valid');
        }

        /* ================= FILTER ================= */
        $filters = [
            'karantina'   => strtolower($this->input->get('karantina')),
            'permohonan'  => strtolower($this->input->get('permohonan')),
            'upt'         => $this->input->get('upt'),
            'start_date'  => $this->input->get('start_date'),
            'end_date'    => $this->input->get('end_date'),
            'berdasarkan' => $this->input->get('berdasarkan'),
        ];

        $data = $this->KwitansiBatal_model->fetch($filters);

        return $this->json(200, [
            'success' => true,
            'data'    => $data,
            'meta'    => [
                'total' => count($data),
            ]
        ]);
    }

    private function json(int $status, $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode(
                is_array($data)
                    ? $data
                    : ['success' => false, 'message' => $data],
                JSON_UNESCAPED_UNICODE
            ));
    }
}
