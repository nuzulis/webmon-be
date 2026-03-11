<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class EcertDetail extends MY_Controller {

    public function view() {
        $idCert   = trim($this->input->post('id_cert'));
        $dataFrom = trim($this->input->post('data_from'));

        if (!$idCert || !$dataFrom) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']));
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
                'view_url' => $finalUrl,
                'method' => 'GET_BROWSER'
            ]));
    }

    public function detail() {
        $idCert   = trim($this->input->post('id_cert'));
        $dataFrom = trim($this->input->post('data_from'));

        if (!$idCert || !$dataFrom) {
            return $this->output
                ->set_status_header(400)
                ->set_content_type('application/json')
                ->set_output(json_encode(['success' => false, 'message' => 'Parameter tidak lengkap']));
        }

        $apiUrl = "https://api3.karantinaindonesia.go.id/ecert/certin/detail";
        $postData = "id={$idCert}&from={$dataFrom}";

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->output
            ->set_status_header($httpCode)
            ->set_content_type('application/json')
            ->set_output($response);
    }
}