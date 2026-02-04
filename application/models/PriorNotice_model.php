<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class PriorNotice_model extends CI_Model
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/rest-prior/puskodal';
    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';

    public function fetch($f): array
    {
        $kar = match (strtolower($f['karantina'] ?? '')) {
            'kh'    => 'H',
            'ki'    => 'I',
            'kt'    => 'T',
            default => null
        };

        if (!$kar) {
            return ['success' => false, 'message' => 'Jenis karantina (kh/ki/kt) diperlukan'];
        }

        $payload = [
            'dFrom' => $f['start_date'] ?? date('Y-m-d'),
            'dTo'   => $f['end_date'] ?? date('Y-m-d'),
            'kar'   => $kar,
            'par'   => 'all'
        ];

        if (!empty($f['upt']) && $f['upt'] !== 'all') {
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
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 45
        ]);

        $response = curl_exec($ch);
        $errNo    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        curl_close($ch);

        if ($errNo) {
            return [
                'success' => false,
                'message' => "Koneksi API Gagal: $errMsg"
            ];
        }

        $json = json_decode($response, true);

        if (!isset($json['status']) || $json['status'] != 1) {
            return [
                'success' => false,
                'message' => $json['message'] ?? 'Respon API Pusat menyatakan error atau data kosong.'
            ];
        }

        return [
            'success' => true,
            'data'    => $json['data'] ?? []
        ];
    }
}