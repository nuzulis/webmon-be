<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class BillingBatal_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/billing';
    
   private $cachedData = null;

    public function __construct() { parent::__construct(); }
    public function getAll(array $f): array
    {
        return $this->fetchDataInternal($f);
    }

    public function getIds(array $f, int $limit, int $offset): array
    {
        return array_slice($this->fetchDataInternal($f), $offset, $limit);
    }

    public function getByIds($ids)
    {
        return $ids;
    }
    private function fetchDataInternal($f) {
        if ($this->cachedData !== null) return $this->cachedData;
        $karantinaMap = ['kh' => 'H', 'ki' => 'I', 'kt' => 'T'];
        $uptInput = $f['upt_id'] ?? ($f['upt'] ?? 'all');
        $uptField = '';

        if ($uptInput !== 'all' && !empty($uptInput)) {
            $uptField = (strlen($uptInput) > 2) ? substr($uptInput, 0, 2) : $uptInput;
        }

        $payload = [
            'dFrom' => $f['start_date'] ?? date('Y-m-d'),
            'dTo'   => $f['end_date'] ?? date('Y-m-d'),
            'kar'   => $karantinaMap[strtolower($f['karantina'] ?? '')] ?? '',
            'upt'   => $uptField,
        ];

        $response = $this->curlPostJson($this->endpoint, json_encode($payload));
        
        if (!$response || empty($response['status']) || empty($response['data'])) {
            $this->cachedData = [];
        } else {
            $this->cachedData = $this->normalize($response['data']);
        }

        return $this->cachedData;
    }
    private function normalize($rows) {
        if (!is_array($rows)) return [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'            => $r['id'] ?? uniqid(),
                'nama_upt'      => $r['nama_upt'] ?? '',
                'karantina'     => $this->mapKarantina($r['jenis_karantina'] ?? ''),
                'total_bill'    => (float) ($r['total_bill'] ?? 0),
                'kode_bill'     => $r['kode_bill'] ?? '',
                'status_bill'   => $r['status_bill'] ?? '',
                'ntpn'          => $r['ntpn'] ?? '',
                'ntb'           => $r['ntb'] ?? '',
                'created_at'    => $r['created_at'] ?? '',
                'alasan_hapus'  => $r['alasan_hapus'] ?? '-',
                'deleted_at'    => $r['deleted_at'] ?? '',
            ];
        }
        return $out;
    }

    private function mapKarantina($v) {
        return match (strtoupper($v)) { 'H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan', default => '-' };
    }

    private function curlPostJson($url, $jsonPayload) {
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
        if (is_resource($ch) || (is_object($ch) && $ch instanceof \CurlHandle)) curl_close($ch);
        return json_decode($res, true);
    }
    
    public function getFullData($f) {
        return $this->fetchDataInternal($f);
    }
}