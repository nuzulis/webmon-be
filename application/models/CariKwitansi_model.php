<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class CariKuitansi_model extends CI_Model
{
    private string $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/cariBy';

    public function cari(string $filter, string $pencarian): array
    {
        $berdasarkan = ($filter === 'K') ? 'K' : 'B';

        $payload = [
            'berdasarkan' => $berdasarkan,
            'nomor'       => $pencarian,
        ];

        $ch = curl_init($this->endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm',
            ],
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new RuntimeException(curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('HTTP Error ' . $httpCode);
        }

        $json = json_decode($response, true);

        if (
            !$json ||
            !isset($json['data']['list_kuitansi'])
        ) {
            return [];
        }

        return $json['data'];
    }
}
