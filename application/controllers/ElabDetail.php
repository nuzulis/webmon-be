<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * @property ElabDetail_model $ElabDetail_model
 */
class ElabDetail extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('ElabDetail_model');
    }

    public function detail($penerimaanId = null)
    {
        if (empty($penerimaanId)) {
            return $this->jsonRes(400, [
                'status'  => false,
                'message' => 'Parameter penerimaanId wajib diisi'
            ]);
        }

        $data = $this->ElabDetail_model->getFullDetail($penerimaanId);

        if (empty($data['header'])) {
            return $this->jsonRes(404, [
                'status'  => false,
                'message' => 'Data penerimaan tidak ditemukan'
            ]);
        }

        return $this->jsonRes(200, [
            'status' => true,
            'data'   => $data
        ]);
    }
}
