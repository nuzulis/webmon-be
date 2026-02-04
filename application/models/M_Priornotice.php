<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_Priornotice extends CI_Model
{
    private string $endpoint =
        'https://api3.karantinaindonesia.go.id/rest-prior/docPrior/getAll';

    public function get_by_docnbr(string $docnbr): ?array
    {
        $url = $this->endpoint . '?docnbr=' . urlencode($docnbr);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->getAuthHeader()
            ]
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            log_message('error', 'PRIOR API ERROR: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        $json = json_decode($response, true);

        return $json['data'] ?? null;
    }

    private function getAuthHeader(): string
    {
       $this->config->load('prior', TRUE);
    
    $auth = $this->config->item('auth_header', 'prior');

    return (string) $auth;
    }
}
