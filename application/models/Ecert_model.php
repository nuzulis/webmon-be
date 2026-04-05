<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'core/BaseModelStrict.php';

class Ecert_model extends BaseModelStrict
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/ecert/certin';
    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';

    public function __construct()
    {
        parent::__construct();
    }

    public function getAll(array $filter): array
    {
        return $this->fetchFromApi($filter);
    }

    public function getFullData(array $filter): array
    {
        return $this->fetchFromApi($filter);
    }

    private function fetchFromApi($filter)
    {
        if (empty($filter['karantina']) || empty($filter['start_date']) || empty($filter['end_date'])) {
            return [];
        }

       $negara = (!empty($filter['negara']) && $filter['negara'] !== 'Semua' && $filter['negara'] !== 'undefined') 
              ? strtoupper($filter['negara']) 
              : '';

        $payload = [
            'kar'    => $filter['karantina'],
            'dstart' => $filter['start_date'],
            'dend'   => $filter['end_date'],
            'negara' => $negara
        ];

        if (!empty($filter['upt']) && strtolower($filter['upt']) !== 'all' && strlen($filter['upt']) <= 2) {
            $payload['upt'] = $filter['upt'];
        }

        $ch = curl_init($this->apiUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->authHeader
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 120
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            log_message('error', 'E-Cert API Error: ' . curl_error($ch));
            curl_close($ch);
            return [];
        }

        curl_close($ch);
        $response = preg_replace('/^\[\s*,/', '[', trim($response));

        $data = json_decode($response, true);

        if (!is_array($data)) {
            log_message('error', 'E-Cert JSON Decode Error');
            return [];
        }

        return $data;
    }

}