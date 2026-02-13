<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class BillingBatal_model extends BaseModelStrict
{
    private $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/batal/billing';
    
   private $cachedData = null;

    public function __construct() { parent::__construct(); }
    public function getIds($f, $limit, $offset)
    {
        $data = $this->fetchDataInternal($f);
        if (!empty($f['search'])) {
            $s = strtolower($f['search']);
            $data = array_filter($data, function($row) use ($s) {
                return (
                    str_contains(strtolower($row['kode_bill']), $s) || 
                    str_contains(strtolower($row['nama_upt']), $s) || 
                    str_contains(strtolower($row['ntpn']), $s) ||
                    str_contains(strtolower($row['alasan_hapus']), $s)
                );
            });
        }
        $sortBy = $f['sort_by'] ?? 'deleted_at';
        $sortOrder = $f['sort_order'] ?? 'DESC';
        if (!empty($data) && isset($data[0][$sortBy])) {
            usort($data, function($a, $b) use ($sortBy, $sortOrder) {
                $valA = $a[$sortBy];
                $valB = $b[$sortBy];
                
                if ($valA == $valB) return 0;
                $result = ($valA < $valB) ? -1 : 1;
                return (strtoupper($sortOrder) === 'DESC') ? -$result : $result;
            });
        }
        return array_slice($data, $offset, $limit);
    }
    public function getByIds($ids)
    {
        return $ids;
    }

    public function countAll($f)
    {
        $data = $this->fetchDataInternal($f);
        if (!empty($f['search'])) {
            $s = strtolower($f['search']);
            $data = array_filter($data, function($row) use ($s) {
                return (
                    str_contains(strtolower($row['kode_bill']), $s) || 
                    str_contains(strtolower($row['nama_upt']), $s) ||
                    str_contains(strtolower($row['ntpn']), $s) ||
                    str_contains(strtolower($row['alasan_hapus']), $s)
                );
            });
        }
        
        return count($data);
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
        return $this->getIds($f, 100000, 0); 
    }
}