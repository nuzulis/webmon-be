<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property M_Priornotice $M_Priornotice
 */
class PriornoticeDetail extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('M_Priornotice');
    }

    public function view()
    {
        $docnbr = trim($this->input->post('docnbr', TRUE));

        if (empty($docnbr)) {
            return $this->json([
                'success' => false,
                'message' => 'Nomor Dokumen (docnbr) wajib diisi'
            ], 400);
        }

        try {
            $data = $this->M_Priornotice->get_by_docnbr($docnbr);

            if (empty($data)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Data Prior Notice tidak ditemukan.'
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data'    => $data
            ], 200);

        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }
}