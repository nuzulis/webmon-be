<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EcertDetail extends MY_Controller {

    public function view() {
        $idCert   = trim($this->input->post('id_cert'));
        $dataFrom = trim($this->input->post('data_from'));

        if (!$idCert) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'ID Cert tidak ditemukan']));
        }
        $baseUrl = "https://cert.karantinaindonesia.go.id/ecert/xml/";

        if (strtolower($dataFrom) === 'h2h') {
            $finalUrl = $baseUrl . "ecert/" . $idCert;
        } else {
            $finalUrl = $baseUrl . "ephyto/" . $idCert;
        }

        return $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => true,
                'data_from' => $dataFrom,
                'direct_url' => $finalUrl
            ]));
    }
}