<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'models/BaseModelStrict.php';

class KwitansiBelumBayar_model extends BaseModelStrict
{
    protected $endpoint = 'https://simponi.karantinaindonesia.go.id/epnbp/kuitansi/unpaid';
    private $_cache_data = null;

    public function getIds($f, $limit, $offset) { return []; }
    public function getByIds($ids) { return []; }

    public function countAll($f)
    {
        $allData = $this->getAllDataFromSimponi($f);
        return count($allData);
    }

    public function fetch($f)
    {
        $allData = $this->getAllDataFromSimponi($f);
        
        $limit = isset($f['per_page']) ? (int)$f['per_page'] : 10;
        $page = isset($f['page']) ? (int)$f['page'] : 1;
        $offset = ($page - 1) * $limit;

        return array_slice($allData, $offset, $limit);
    }

    private function getAllDataFromSimponi($f)
{
    if ($this->_cache_data !== null) return $this->_cache_data;

    $karInput = strtolower($f['karantina'] ?? '');
    $karantinaField = match($karInput) {
        'kh' => 'H',
        'kt' => 'T',
        'ki' => 'I',
        default => ''
    };

    $uptField = ($f['upt'] !== 'all' && !empty($f['upt'])) ? $f['upt'] : '';

    $permInput = strtolower($f['permohonan'] ?? '');
    $permohonanField = in_array($permInput, ['ex', 'im', 'dk', 'dm']) ? $permInput : '';

    $payload = [
        'dFrom'       => $f['start_date'] ?? '',
        'dTo'         => $f['end_date'] ?? '',
        'kar'         => $karantinaField,
        'upt'         => $uptField,
        'permohonan'  => $permohonanField,
        'berdasarkan' => $f['berdasarkan'] ?? '', 
    ];

    log_message('error', 'SIMPONI UNPAID PAYLOAD: ' . http_build_query($payload));

    $response = $this->curlPost($this->endpoint, http_build_query($payload));

    if (!$response || empty($response['status']) || !isset($response['data'])) {
        $this->_cache_data = [];
        return [];
    }

    $this->_cache_data = $this->normalize($response['data']);
    return $this->_cache_data;
}
    
private function normalize($rows)
    {
        if (!is_array($rows)) return [];
        usort($rows, function ($a, $b) {
            return strcmp($a['ptk_id'] ?? '', $b['ptk_id'] ?? '');
        });

        $processed_ids = [];
        $out = [];

        foreach ($rows as $item) {
            $ptk_id = $item['ptk_id'] ?? '';
            if ($ptk_id && in_array($ptk_id, $processed_ids)) {
                continue;
            }
            $processed_ids[] = $ptk_id;

            $out[] = [
                'id'               => $item['id'] ?? '',
                'nama_upt'         => $item['nama_upt'] ?? '',
                'nama_satpel'      => $item['nama_satpel'] ?? '',
                'nama_pospel'      => $item['nama_pospel'] ?? '',
                'nomor'            => $item['nomor'] ?? '',
                'no_aju'           => $item['ptk_id'] ?? '',
                'tanggal'          => $item['tanggal'] ?? '',
                'nama_wajib_bayar' => $item['nama_wajib_bayar'] ?? '',
                'total_pnbp'       => (float) ($item['total_pnbp'] ?? 0),
                'kode_bill'        => $item['kode_bill'] ?? '',
                'expired_date'     => $item['tgl_exp_billing'] ?? ($item['date_bill_exp'] ?? '-'), 
                'jenis_karantina'  => $this->mapKarantina($item['jenis_karantina'] ?? ''),
                'jenis_permohonan' => $this->mapPermohonan($item['jenis_permohonan'] ?? ''),
            ];
        }
        return $out;
    }

    private function mapKarantina($v) {
        return match (strtoupper($v)) { 'H' => 'Hewan', 'I' => 'Ikan', 'T' => 'Tumbuhan', default => '-' };
    }

    private function mapPermohonan($v) {
        return match (strtoupper($v)) { 'EX' => 'Ekspor', 'IM' => 'Impor', 'DK' => 'Domestik Keluar', 'DM' => 'Domestik Masuk', default => '-' };
    }

    private function curlPost($url, $payload)
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic bXJpZHdhbjpaPnV5JCx+NjR7KF42WDQm'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 60 
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return json_decode($res, true);
    }
}