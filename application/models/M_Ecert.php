<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class M_Ecert extends CI_Model
{
    private string $endpoint =
        'https://api3.karantinaindonesia.go.id/ecert/printCertin';

    public function fetch_document(string $idCert, string $kar, string $from): ?array
    {
        $payload = http_build_query([
            'id'   => $idCert,
            'kar'  => $kar,
            'from' => $from
        ]);

        $ch = curl_init($this->endpoint);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . $this->getAuthHeader()
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT        => 15
        ]);

        $body = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

        if ($body === false) {
            log_message('error', 'ECERT ERROR: ' . curl_error($ch));
            curl_close($ch);
            return null;
        }

        curl_close($ch);

        return [
            'content_type' => $contentType ?: 'application/pdf',
            'body'         => $body
        ];
    }

    private function getAuthHeader(): string
    {
        $auth = $this->config->item('auth_header', 'prior');

    return (string) $auth;
    }
}
