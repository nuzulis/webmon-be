<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PeriksaLapangan extends MY_Controller 
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('PeriksaLapangan_model');
    }

    public function detail()
    {
        $raw = json_decode($this->input->raw_input_stream, true);
        $p_id = isset($raw['id']) ? $raw['id'] : $this->input->post('id');
        if (!$p_id) $p_id = $this->input->get('id');

        if (!$p_id) {
            return $this->jsonRes(400, [
                'success' => false,
                'message' => 'ID permohonan tidak ditemukan'
            ]);
        }
        $data = $this->PeriksaLapangan_model->getDetailFinal($p_id);

        if (!$data) {
            return $this->jsonRes(200, [
                'success' => false,
                'message' => 'Belum ada data pemeriksaan teknis untuk permohonan ini.',
                'data' => null
            ]);
        }
        return $this->jsonRes(200, [
            'success' => true,
            'data' => $data
        ]);
    }
}