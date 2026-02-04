<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Pnbp extends CI_Controller { 

   public function __construct() {
    parent::__construct();
}

   public function get_detail_kw()
    {
        $this->output->set_content_type('application/json');
        $input = json_decode($this->input->raw_input_stream, true);
        $id = isset($input['id']) ? trim($input['id']) : '';

        if (empty($id)) {
            return $this->output->set_output(json_encode([
                'success' => false, 
                'message' => 'Parameter ID/Nomor tidak ditemukan'
            ]));
        }

        $url = "https://simponi.karantinaindonesia.go.id/epnbp/kuitansi?id=" . urlencode($id);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm',
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($curl);
        $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($http_code === 200) {
            $data = json_decode($response, true);
            return $this->output->set_output(json_encode([
                'success' => true, 
                'data' => $data['data'] ?? $data 
            ]));
        } else {
            return $this->output->set_output(json_encode([
                'success' => false, 
                'message' => "Simponi Error ($http_code)",
                'debug' => $error ?: "Data tidak ditemukan untuk ID: $id"
            ]));
        }
    }
}