<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Ecert_model extends CI_Model
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/ecert/certin';

    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';

    /**
     * Ambil data e-Cert dari API eksternal
     */
    public function fetch(array $f): array
    {
        if (empty($f['karantina']) || empty($f['start_date']) || empty($f['end_date'])) {
            return [
                'success' => false,
                'message' => 'Parameter wajib belum lengkap'
            ];
        }

        $payload = [
            'kar'    => $f['karantina'],
            'dstart' => $f['start_date'],
            'dend'   => $f['end_date'],
            'negara' => $f['negara'] ?? ''
        ];

        if (!empty($f['upt']) && strlen($f['upt']) <= 2) {
            $payload['upt'] = $f['upt'];
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
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            return [
                'success' => false,
                'message' => curl_error($ch)
            ];
        }

        curl_close($ch);

        // ⚠️ API kadang kirim JSON invalid (leading comma)
        $response = preg_replace('/^\[\s*,/', '[', trim($response));

        $data = json_decode($response, true);

        if (!is_array($data)) {
            return [
                'success' => false,
                'message' => 'Gagal decode JSON e-Cert'
            ];
        }

        return [
            'success' => true,
            'data'    => $data
        ];
    }
}
