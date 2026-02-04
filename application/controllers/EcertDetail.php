<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 *
 * @property M_Ecert $ecert
 */
class EcertDetail extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('M_Ecert', 'ecert');
    }

    public function view()
    {
        $idCert   = trim($this->input->post('id_cert'));
        $from     = trim($this->input->post('data_from'));
        $kar      = trim($this->input->post('kar'));

        if (!$idCert || !$from || !$kar) {
            return $this->json_error('Parameter tidak lengkap');
        }

        $result = $this->ecert->fetch_document($idCert, $kar, $from);

        if (!$result) {
            return $this->json_error('Gagal mengambil dokumen e-Cert');
        }
        $this->output
            ->set_content_type($result['content_type'])
            ->set_header('Content-Disposition: inline')
            ->set_output($result['body']);
    }

    private function json_error(string $msg, int $code = 400)
    {
        return $this->output
            ->set_status_header($code)
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => false,
                'message' => $msg
            ]));
    }
}
