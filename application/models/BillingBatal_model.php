<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';
/*
 * Model untuk menangani data pembatalan billing dari API Simponi
 * * @property CI_DB_query_builder $db
 */
class BillingBatal_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/billing';

    public function fetch($f)
{
    $karantinaMap = [
        'kh' => 'H',
        'ki' => 'I',
        'kt' => 'T',
    ];

    $uptInput = $f['upt'] ?? 'all';
    $uptField = '';

    if ($uptInput !== 'all' && !empty($uptInput)) {
        $uptField = (strlen($uptInput) > 2) ? substr($uptInput, 0, 2) : $uptInput;
    }

    $payload = [
        'dFrom' => $f['start_date'] ?? '',
        'dTo'   => $f['end_date'] ?? '',
        'kar'   => $karantinaMap[strtolower($f['karantina'] ?? '')] ?? '',
        'upt'   => $uptField,
    ];


    $response = $this->curlPostJson($this->endpoint, json_encode($payload));

    if (!$response || empty($response['status']) || empty($response['data'])) {
        return [];
    }

    return $this->normalize($response['data']);
}

    private function normalize($rows)
{
    if (!is_array($rows)) return [];

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id'            => $r['id'] ?? '',
            'nama_upt'      => $r['nama_upt'] ?? '',
            'karantina'     => $this->mapKarantina($r['jenis_karantina'] ?? ''),
            'total_bill'    => (float) ($r['total_bill'] ?? 0), // Native menggunakan total_bill
            'kode_bill'     => $r['kode_bill'] ?? '',
            'status_bill'   => $r['status_bill'] ?? '',
            'ntpn'          => $r['ntpn'] ?? '',
            'ntb'           => $r['ntb'] ?? '',
            'created_at'    => $r['created_at'] ?? '',
            'alasan_hapus'  => $r['alasan_hapus'] ?? '-',
            'deleted_at'    => $r['deleted_at'] ?? '',
        ];
    }

    usort($out, function($a, $b) {
        return strcmp($b['deleted_at'], $a['deleted_at']);
    });

    return $out;
}

    private function mapKarantina($v)
    {
        return match (strtoupper($v)) {
            'H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan', default => '-'
        };
    }


    public function getIds($filter = [], $limit = null, $offset = null): array
    {
        return [];
    }

     public function getByIds($ids)
    {
        return [];
    }

    public function countAll($filter = []): int
    {
        return 0;
    }

    private function curlPostJson($url, $jsonPayload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $jsonPayload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 30
        ]);

        $res = curl_exec($ch);
        if (empty($res)) {
    log_message('error', 'CURL Error: ' . curl_error($ch));
}
log_message('error', 'Raw Response Simponi: ' . $res);
        if (is_resource($ch) || (is_object($ch) && $ch instanceof \CurlHandle)) {
            curl_close($ch);
        }

        return json_decode($res, true);
    }
}