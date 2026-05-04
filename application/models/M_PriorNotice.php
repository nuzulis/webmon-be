<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_Priornotice extends CI_Model
{
    private string $apiUrl = 'https://api3.karantinaindonesia.go.id/rest-prior/docPrior/getAll';
    private string $authHeader = 'Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm';

    public function get_by_docnbr($docnbr)
    {
        $url = $this->apiUrl . '?docnbr=' . urlencode($docnbr);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->authHeader
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch);

        if ($err) {
            log_message('error', "PRIOR NOTICE DETAIL CURL ERROR: $err");
            return null;
        }

        $json = json_decode($response, true);
        if (isset($json['data']) && $json['data'] !== null) {
            return $json['data'];
        }

        return null;
    }
}