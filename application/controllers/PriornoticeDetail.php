<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Detail Prior Notice (lookup eksternal REST PRIOR)
 *
 * @property M_Priornotice $prior
 */
class PriornoticeDetail extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('M_Priornotice', 'prior');
    }

    /**
     * Ambil detail Prior Notice berdasarkan docnbr
     * HANYA untuk halaman detail Prior Notice
     */
    public function view()
    {
        $docnbr = trim($this->input->post('docnbr'));

        if (!$docnbr) {
            return $this->json_error('docnbr wajib diisi');
        }

        $data = $this->prior->get_by_docnbr($docnbr);

        if (!$data) {
            return $this->json_error('Data Prior Notice tidak ditemukan');
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data'    => $data
            ]));
    }

    /* ===============================
     * HELPER RESPONSE
     * =============================== */
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
